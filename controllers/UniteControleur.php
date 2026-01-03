<?php
// controllers/UniteControleur.php - VERSION CORRIGÉE

require_once __DIR__ . '/base/DatabaseHelpers.php';
require_once __DIR__ . '/base/UsageChecker.php';

class UniteControleur {
    use DatabaseHelpers, UsageChecker;
    
    private $conn;
    
    public function __construct(PDO $conn) {
        $this->conn = $conn;
    }
    
    public function getAll(): array {
        // ✅ CORRECTION: Ajout des alias cohérents avec le pattern _unite
        $sql = "SELECT 
                    id as id_unite, 
                    code as code_unite, 
                    nom as nom_unite, 
                    description as description_unite 
                FROM unites ORDER BY nom";
        return $this->fetchAll($sql);
    }
    
    public function getById(int $id_unite): ?array {
        // ✅ CORRECTION: Ajout des alias cohérents avec le pattern _unite
        $sql = "SELECT 
                    id as id_unite, 
                    code as code_unite, 
                    nom as nom_unite, 
                    description as description_unite 
                FROM unites WHERE id = ?";
        return $this->fetchOne($sql, [$id_unite]);
    }
    
    public function getByService(int $id_service): array {
        // ✅ CORRECTION: Ajout des alias cohérents avec le pattern _unite
        $sql = "SELECT 
                    u.id as id_unite, 
                    u.code as code_unite, 
                    u.nom as nom_unite, 
                    u.description as description_unite 
                FROM unites u 
                JOIN services_unites su ON u.id = su.unite_id 
                WHERE su.service_id = ? 
                ORDER BY u.nom";
        
        return $this->fetchAll($sql, [$id_service]);
    }
    
    public function getServicesUnites(): array {
        $sql = "SELECT service_id as id_service, unite_id as id_unite FROM services_unites";
        return $this->fetchAll($sql);
    }

    /**
     * Récupère toutes les relations services-unités avec les détails des unités
     * Inclut le flag isDefault pour savoir quelle unité est par défaut pour chaque service
     * @return array
     */
    public function getServicesUnitesDetaillees(): array {
        $sql = "SELECT 
                    su.service_id as id_service,
                    su.unite_id as id_unite,
                    su.isDefault as is_default,
                    su.actif as actif,
                    u.code as code_unite,
                    u.nom as nom_unite,
                    u.description as description_unite
                FROM services_unites su
                JOIN unites u ON su.unite_id = u.id
                ORDER BY su.service_id, u.nom";
        
        return $this->fetchAll($sql);
    }
    
    public function getUniteDefaultForService(int $id_service): ?int {
        $sql = "SELECT unite_id as id_unite FROM services_unites 
                WHERE service_id = ? AND isDefault = 1 
                LIMIT 1";
        
        $result = $this->fetchOne($sql, [$id_service]);
        return $result ? (int)$result['id_unite'] : null;
    }
    
    public function create(array $data): array {
        
        $sql = "INSERT INTO unites (code, nom, description) 
                VALUES (?, ?, ?)";
        
        $this->executeQuery($sql, [
            $data['code_unite'],
            $data['nom_unite'],
            $data['description_unite'] ?? null
        ]);
        
        $id_unite = $this->getLastInsertId();
        
        // Construction de l'objet à partir des données déjà disponibles
        $objet = [
            'id_unite' => $id_unite,
            'code_unite' => $data['code_unite'],
            'nom_unite' => $data['nom_unite'],
            'description_unite' => $data['description_unite'] ?? null
        ];
        
        return [
            'id_unite' => $id_unite,
            'unite' => $objet
        ];
    }
    
    public function update(int $id_unite, array $data): bool {
        $setFields = [];
        $params = [];
        
        // ✅ CORRECTION: Support des deux formats de noms de champs
        if (isset($data['code_unite']) || isset($data['code'])) {
            $setFields[] = "code = ?";
            $params[] = $data['code_unite'] ?? $data['code'];
        }
        
        if (isset($data['nom_unite']) || isset($data['nom'])) {
            $setFields[] = "nom = ?";
            $params[] = $data['nom_unite'] ?? $data['nom'];
        }
        
        if (isset($data['description_unite']) || isset($data['description'])) {
            $setFields[] = "description = ?";
            $params[] = $data['description_unite'] ?? $data['description'];
        }
        
        if (empty($setFields)) {
            return false;
        }
        
        $sql = "UPDATE unites SET " . implode(", ", $setFields) . " WHERE id = ?";
        $params[] = $id_unite;
        
        $stmt = $this->executeQuery($sql, $params);
        
        // Gestion du champ isDefault au niveau de la relation service-unité
        if (isset($data['isDefault']) && isset($data['id_service'])) {
            $id_service = $data['id_service'];
            $this->updateServiceUniteDefault($id_service, $id_unite, $data['isDefault']);
        }
        
        return $stmt->rowCount() > 0;
    }
    
    public function delete(int $id_unite): array {
        // Vérifier l'usage
        $usageCheck = $this->checkUsage($id_unite);

        if ($usageCheck['isUsed']) {
            throw new Exception('Impossible de supprimer cette unité car elle est utilisée dans des tarifs ou des liaisons');
        }

        $stmt = $this->executeQuery("DELETE FROM unites WHERE id = ?", [$id_unite]);
        
        return [
            'success' => true,
            'message' => 'L\'unité a été supprimée avec succès'
        ];
    }

    public function linkToService(int $id_unite, int $id_service, bool $isDefault = false): bool {
        // Vérifier si la liaison existe déjà
        $existingLink = $this->fetchOne(
            "SELECT id FROM services_unites WHERE service_id = ? AND unite_id = ?",
            [$id_service, $id_unite]
        );
        
        if (!$existingLink) {
           // Créer une nouvelle liaison
            $sql = "INSERT INTO services_unites (service_id, unite_id, isDefault) VALUES (?, ?, ?)";
            $this->executeQuery($sql, [$id_service, $id_unite, $isDefault ? 1 : 0]);
        }
        
        // Si c'est par défaut, désactiver les autres
        if ($isDefault) {
            $this->updateServiceUniteDefault($id_service, $id_unite, true);
        }
        
        return true;
    }

    public function unlinkFromService(int $id_unite, int $id_service): array {
        // Vérifier d'abord si cette liaison est utilisée dans des factures
        $checkFacture = $this->checkServiceUniteUsageInFacture($id_service, $id_unite);

        if ($checkFacture['isUsed']) {
            return [
                'success' => false,
                'message' => $checkFacture['message'],
                'action' => 'impossible'
            ];
        } else {
            // Supprimer la liaison
            $this->executeQuery(
                "DELETE FROM services_unites WHERE service_id = ? AND unite_id = ?",
                [$id_service, $id_unite]
            );
            
            return [
                'success' => true,
                'message' => 'La liaison a été supprimée avec succès',
                'action' => 'supprime'
            ];
        }
    }

    public function updateServiceUniteDefault(int $id_service, int $id_unite, bool $isDefault = true): array {
        if ($isDefault) {
            // Désactiver toutes les autres unités par défaut pour ce service
            $this->executeQuery(
                "UPDATE services_unites SET isDefault = 0 WHERE service_id = ?",
                [$id_service]
            );
            
            // Définir la nouvelle unité par défaut
            $this->executeQuery(
                "UPDATE services_unites SET isDefault = 1 WHERE service_id = ? AND unite_id = ?",
                [$id_service, $id_unite]
            );
        } else {
            // Désactiver cette unité comme défaut
            $this->executeQuery(
                "UPDATE services_unites SET isDefault = 0 WHERE service_id = ? AND unite_id = ?",
                [$id_service, $id_unite]
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

    public function checkServiceUniteUsageInFacture(int $id_service, int $id_unite): array {
        $sql = "SELECT COUNT(*) as total FROM lignesfacture 
                WHERE service_id = ? AND unite_id = ?";

        $result = $this->fetchOne($sql, [$id_service, $id_unite]);
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