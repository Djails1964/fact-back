<?php
// controllers/UniteControleur.php

require_once __DIR__ . '/base/DatabaseHelpers.php';
require_once __DIR__ . '/base/UsageChecker.php';

class UniteControleur {
    use DatabaseHelpers, UsageChecker;
    
    private $conn;
    
    public function __construct(PDO $conn) {
        $this->conn = $conn;
    }
    
    public function getAll(): array {
        $sql = "SELECT id as id_unite, code, nom, description FROM unites ORDER BY nom";
        return $this->fetchAll($sql);
    }
    
    public function getById(int $id): ?array {
        $sql = "SELECT id as id_unite, code, nom, description FROM unites WHERE id = ?";
        return $this->fetchOne($sql, [$id]);
    }
    
    public function getByService(int $serviceId): array {
        $sql = "SELECT u.id as id_unite, u.code, u.nom, u.description 
                FROM unites u 
                JOIN services_unites su ON u.id = su.unite_id 
                WHERE su.service_id = ? AND su.actif = 1 
                ORDER BY u.nom";
        
        return $this->fetchAll($sql, [$serviceId]);
    }
    
    public function getServicesUnites(): array {
        $sql = "SELECT service_id as id_service, unite_id as id_unite FROM services_unites WHERE actif = 1";
        return $this->fetchAll($sql);
    }
    
    public function getUniteDefaultForService(int $serviceId): ?int {
        $sql = "SELECT unite_id FROM services_unites 
                WHERE service_id = ? AND isDefault = 1 
                LIMIT 1";
        
        $result = $this->fetchOne($sql, [$serviceId]);
        return $result ? (int)$result['unite_id'] : null;
    }
    
    public function create(array $data): int {
        $sql = "INSERT INTO unites (code, nom, description) VALUES (?, ?, ?)";
        $this->executeQuery($sql, [
            $data['code'],
            $data['nom'],
            $data['description'] ?? null
        ]);
        
        $uniteId = $this->getLastInsertId();
        
        // Si un service est spécifié, créer la liaison
        if (isset($data['service_id'])) {
            $this->linkToService($uniteId, $data['service_id'], $data['isDefault'] ?? false);
        }
        
        return $uniteId;
    }
    
    public function update(int $id, array $data): bool {
        $setFields = [];
        $params = [];
        
        if (isset($data['code'])) {
            $setFields[] = "code = ?";
            $params[] = $data['code'];
        }
        
        if (isset($data['nom'])) {
            $setFields[] = "nom = ?";
            $params[] = $data['nom'];
        }
        
        if (isset($data['description'])) {
            $setFields[] = "description = ?";
            $params[] = $data['description'];
        }
        
        if (empty($setFields)) {
            return false;
        }
        
        $sql = "UPDATE unites SET " . implode(", ", $setFields) . " WHERE id = ?";
        $params[] = $id;
        
        $stmt = $this->executeQuery($sql, $params);
        
        // Gestion du champ isDefault au niveau de la relation service-unité
        if (isset($data['isDefault']) && isset($data['service_id'])) {
            $this->updateServiceUniteDefault($data['service_id'], $id, $data['isDefault']);
        }
        
        return $stmt->rowCount() > 0;
    }
    
    public function delete(int $id): array {
        // Vérifier l'usage
        $usageCheck = $this->checkUsage($id);
        
        if ($usageCheck['isUsed']) {
            throw new Exception('Impossible de supprimer cette unité car elle est utilisée dans des tarifs ou des liaisons');
        }
        
        $stmt = $this->executeQuery("DELETE FROM unites WHERE id = ?", [$id]);
        
        return [
            'success' => true,
            'message' => 'L\'unité a été supprimée avec succès'
        ];
    }
    
    public function linkToService(int $uniteId, int $serviceId, bool $isDefault = false): bool {
        // Vérifier si la liaison existe déjà
        $existingLink = $this->fetchOne(
            "SELECT id, actif FROM services_unites WHERE service_id = ? AND unite_id = ?",
            [$serviceId, $uniteId]
        );
        
        if ($existingLink) {
            // Si la liaison existe mais est inactive, la réactiver
            if (!$existingLink['actif']) {
                $this->executeQuery("UPDATE services_unites SET actif = 1 WHERE id = ?", [$existingLink['id']]);
            }
        } else {
            // Créer une nouvelle liaison
            $sql = "INSERT INTO services_unites (service_id, unite_id, actif, isDefault) VALUES (?, ?, 1, ?)";
            $this->executeQuery($sql, [$serviceId, $uniteId, $isDefault ? 1 : 0]);
        }
        
        // Si c'est par défaut, désactiver les autres
        if ($isDefault) {
            $this->updateServiceUniteDefault($serviceId, $uniteId, true);
        }
        
        return true;
    }
    
    public function unlinkFromService(int $uniteId, int $serviceId): array {
        // Vérifier d'abord si cette liaison est utilisée dans des factures
        $checkFacture = $this->checkServiceUniteUsageInFacture($serviceId, $uniteId);
        
        if ($checkFacture['isUsed']) {
            return [
                'success' => false,
                'message' => $checkFacture['message'],
                'action' => 'impossible'
            ];
        }
        
        // Vérifier si utilisée dans des tarifs
        $checkTarifs = $this->fetchOne(
            "SELECT 1 FROM tarifs WHERE service_id = ? AND unite_id = ? 
             UNION 
             SELECT 1 FROM tarifs_speciaux WHERE service_id = ? AND unite_id = ? 
             LIMIT 1",
            [$serviceId, $uniteId, $serviceId, $uniteId]
        );
        
        if ($checkTarifs) {
            // Désactiver la liaison
            $this->executeQuery(
                "UPDATE services_unites SET actif = 0 WHERE service_id = ? AND unite_id = ?",
                [$serviceId, $uniteId]
            );
            
            return [
                'success' => true,
                'message' => 'La liaison a été désactivée car elle est utilisée dans des tarifs',
                'action' => 'desactive'
            ];
        } else {
            // Supprimer la liaison
            $this->executeQuery(
                "DELETE FROM services_unites WHERE service_id = ? AND unite_id = ?",
                [$serviceId, $uniteId]
            );
            
            return [
                'success' => true,
                'message' => 'La liaison a été supprimée avec succès',
                'action' => 'supprime'
            ];
        }
    }
    
    public function updateServiceUniteDefault(int $serviceId, int $uniteId, bool $isDefault = true): array {
        if ($isDefault) {
            // Désactiver toutes les autres unités par défaut pour ce service
            $this->executeQuery(
                "UPDATE services_unites SET isDefault = 0 WHERE service_id = ?",
                [$serviceId]
            );
            
            // Définir la nouvelle unité par défaut
            $this->executeQuery(
                "UPDATE services_unites SET isDefault = 1 WHERE service_id = ? AND unite_id = ?",
                [$serviceId, $uniteId]
            );
        } else {
            // Désactiver cette unité comme défaut
            $this->executeQuery(
                "UPDATE services_unites SET isDefault = 0 WHERE service_id = ? AND unite_id = ?",
                [$serviceId, $uniteId]
            );
        }
        
        return [
            'success' => true,
            'message' => 'Unité par défaut mise à jour avec succès'
        ];
    }
    
    public function checkUsage(int $id): array {
        return $this->checkUsageInTables($id, 'unite_id', [
            'services_unites', 'tarifs', 'tarifs_speciaux'
        ]);
    }
    
    public function checkServiceUniteUsageInFacture(int $serviceId, int $uniteId): array {
        $sql = "SELECT COUNT(*) as total FROM lignesfacture 
                WHERE service_id = ? AND unite_id = ?";
        
        $result = $this->fetchOne($sql, [$serviceId, $uniteId]);
        $count = (int)$result['total'];
        $isUsed = $count > 0;
        
        return [
            'success' => true,
            'isUsed' => $isUsed,
            'count' => $count,
            'message' => $isUsed 
                ? "Cette liaison est utilisée dans $count ligne(s) de facture et ne peut pas être supprimée." 
                : "Cette liaison peut être supprimée en toute sécurité."
        ];
    }
}
?>