<?php
// controllers/ServiceControleur.php

require_once __DIR__ . '/base/DatabaseHelpers.php';
require_once __DIR__ . '/base/UsageChecker.php';

class ServiceControleur {
    use DatabaseHelpers, UsageChecker;
    
    private $conn;
    
    public function __construct(PDO $conn) {
        $this->conn = $conn;
    }
    
    public function getAll(bool $actif = true): array {
        $sql = "SELECT 
                    id as id_service, 
                    code as code_service, 
                    nom as nom_service, 
                    description as description_service, 
                    actif, 
                    isDefault 
                FROM services";
        $params = [];
        
        if ($actif) {
            $sql .= " WHERE actif = 1";
        }
        $sql .= " ORDER BY nom";
        
        return $this->fetchAll($sql, $params);
    }
    
    public function getById(int $id): ?array {
        $sql = "SELECT 
                    id as id_service, 
                    code as code_service, 
                    nom as nom_service, 
                    description as description_service, 
                    actif 
                FROM services WHERE id = ?";
        return $this->fetchOne($sql, [$id]);
    }
    
    public function create(array $data): int {
        if (isset($data['isDefault']) && $data['isDefault']) {
            $this->resetDefaultServices();
        }
        
        $sql = "INSERT INTO services (code, nom, description, actif, isDefault) VALUES (?, ?, ?, ?, ?)";
        $this->executeQuery($sql, [
            // ✅ CORRECTION: Support des deux formats de noms de champs
            $data['code_service'] ?? $data['code'] ?? null,
            $data['nom_service'] ?? $data['nom'] ?? null,
            $data['description_service'] ?? $data['description'] ?? null,
            isset($data['actif']) ? ($data['actif'] ? 1 : 0) : 1,
            isset($data['isDefault']) && $data['isDefault'] ? 1 : 0
        ]);
        
        return $this->getLastInsertId();
    }
    
    public function update(int $id, array $data): bool {
        $setFields = [];
        $params = [];
        
        // ✅ CORRECTION: Support des deux formats de noms de champs
        if (isset($data['code_service']) || isset($data['code'])) {
            $setFields[] = "code = ?";
            $params[] = $data['code_service'] ?? $data['code'];
        }
        
        if (isset($data['nom_service']) || isset($data['nom'])) {
            $setFields[] = "nom = ?";
            $params[] = $data['nom_service'] ?? $data['nom'];
        }
        
        if (isset($data['description_service']) || isset($data['description'])) {
            $setFields[] = "description = ?";
            $params[] = $data['description_service'] ?? $data['description'];
        }
        
        if (isset($data['actif'])) {
            $setFields[] = "actif = ?";
            $params[] = $data['actif'] ? 1 : 0;
        }
        
        if (isset($data['isDefault'])) {
            $isDefault = $data['isDefault'] ? 1 : 0;
            
            if ($isDefault) {
                $this->resetDefaultServices($id);
            }
            
            $setFields[] = "isDefault = ?";
            $params[] = $isDefault;
        }
        
        if (empty($setFields)) {
            return false;
        }
        
        $sql = "UPDATE services SET " . implode(", ", $setFields) . " WHERE id = ?";
        $params[] = $id;
        
        $stmt = $this->executeQuery($sql, $params);
        return $stmt->rowCount() > 0;
    }
    
    public function delete(int $id): array {
        // Vérifier l'usage
        $usageCheck = $this->checkUsage($id);
        
        if ($usageCheck['isUsed']) {
            // Désactiver au lieu de supprimer
            $this->executeQuery("UPDATE services SET actif = 0 WHERE id = ?", [$id]);
            
            return [
                'success' => true,
                'message' => 'Le service a été désactivé car il est utilisé dans des tarifs ou des liaisons',
                'action' => 'desactive'
            ];
        } else {
            // Supprimer vraiment
            $stmt = $this->executeQuery("DELETE FROM services WHERE id = ?", [$id]);
            
            return [
                'success' => true,
                'message' => 'Le service a été supprimé avec succès',
                'action' => 'supprime'
            ];
        }
    }
    
    public function checkUsage(int $id): array {
        return $this->checkUsageInTables($id, 'id_service', [
            'services_unites', 'tarifs', 'tarifs_speciaux'
        ]);
    }
    
    private function resetDefaultServices(?int $excludeId = null): void {
        $sql = "UPDATE services SET isDefault = 0 WHERE isDefault = 1";
        $params = [];
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $this->executeQuery($sql, $params);
    }
}
?>