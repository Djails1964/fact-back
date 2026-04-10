<?php
// controllers/UniteControleur.php - VERSION AVEC CASCADE DELETE

require_once __DIR__ . '/base/DatabaseHelpers.php';
require_once __DIR__ . '/base/UsageChecker.php';

class UniteControleur {
    use DatabaseHelpers, UsageChecker;
    
    private $conn;
    
    public function __construct(PDO $conn) {
        $this->conn = $conn;
    }
    
    public function getAll(): array {
        $sql = "SELECT 
                    id as id_unite, 
                    code as code_unite, 
                    nom as nom_unite, 
                    description as description_unite,
                    abreviation as abreviation_unite,
                    permet_multiplicateur
                FROM unites ORDER BY nom";
        return $this->fetchAll($sql);
    }
    
    public function getById(int $id_unite): ?array {
        $sql = "SELECT 
                    id as id_unite, 
                    code as code_unite, 
                    nom as nom_unite, 
                    description as description_unite,
                    abreviation as abreviation_unite,
                    permet_multiplicateur
                FROM unites WHERE id = ?";
        return $this->fetchOne($sql, [$id_unite]);
    }
    
    public function getByService(int $id_service): array {
        $sql = "SELECT 
                    u.id as id_unite, 
                    u.code as code_unite, 
                    u.nom as nom_unite, 
                    u.description as description_unite,
                    u.abreviation as abreviation_unite,
                    u.permet_multiplicateur,
                    su.isDefault as is_default_pour_service
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
     * Recupere toutes les relations services-unites avec les details des unites
     * Inclut le flag isDefault pour savoir quelle unite est par defaut pour chaque service
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
                    u.description as description_unite,
                    u.abreviation as abreviation_unite
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
        
        $sql = "INSERT INTO unites (code, nom, description, abreviation) 
                VALUES (?, ?, ?, ?)";
        
        $this->executeQuery($sql, [
            $data['code_unite'],
            $data['nom_unite'],
            $data['description_unite'] ?? null,
            $data['abreviation_unite'] ?? null
        ]);
        
        $id_unite = $this->getLastInsertId();
        
        // Construction de l'objet a partir des donnees deja disponibles
        $objet = [
            'id_unite'         => $id_unite,
            'code_unite'       => $data['code_unite'],
            'nom_unite'        => $data['nom_unite'],
            'description_unite'=> $data['description_unite'] ?? null,
            'abreviation_unite' => $data['abreviation_unite'] ?? null
        ];
        
        return [
            'id_unite' => $id_unite,
            'unite' => $objet
        ];
    }
    
    public function update(int $id_unite, array $data): bool {
        $setFields = [];
        $params = [];
        
        // Support des deux formats de noms de champs
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

        if (array_key_exists('abreviation_unite', $data)) {
            $setFields[] = "abreviation = ?";
            $params[] = ($data['abreviation_unite'] !== '' && $data['abreviation_unite'] !== null)
                ? substr($data['abreviation_unite'], 0, 2)
                : null;
        }
        
        if (empty($setFields)) {
            return false;
        }
        
        $sql = "UPDATE unites SET " . implode(", ", $setFields) . " WHERE id = ?";
        $params[] = $id_unite;
        
        $stmt = $this->executeQuery($sql, $params);
        
        // Gestion du champ isDefault au niveau de la relation service-unite
        if (isset($data['isDefault']) && isset($data['id_service'])) {
            $id_service = $data['id_service'];
            $this->updateServiceUniteDefault($id_service, $id_unite, $data['isDefault']);
        }
        
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Supprime une unite
     * 
     * Avec les FK CASCADE DELETE en base de donnees:
     * - Les tarifs standards associes sont supprimes automatiquement
     * - Les tarifs speciaux associes sont supprimes automatiquement
     * 
     * La suppression est bloquee par les FK RESTRICT si:
     * - L'unite est encore liee a un service (services_unites)
     * - L'unite est utilisee dans des lignes de facture (lignesfacture)
     * 
     * @param int $id_unite ID de l'unite a supprimer
     * @return array Resultat de l'operation
     */
    public function delete(int $id_unite): array {
        // La base de donnees gere les contraintes via les FK:
        // - RESTRICT sur services_unites.unite_id -> bloque si liaison existe
        // - RESTRICT sur lignesfacture.unite_id -> bloque si utilisee dans factures
        // - CASCADE sur tarifs.unite_id -> supprime les tarifs automatiquement
        // - CASCADE sur tarifs_speciaux.unite_id -> supprime les tarifs speciaux automatiquement
        
        try {
            $this->executeQuery("DELETE FROM unites WHERE id = ?", [$id_unite]);
            
            return [
                'success' => true,
                'message' => "L'unite a ete supprimee avec succes (tarifs associes supprimes automatiquement)"
            ];
        } catch (PDOException $e) {
            // Analyser le message d'erreur pour donner un message comprehensible
            $errorMessage = $e->getMessage();
            
            if (strpos($errorMessage, 'fk_services_unites_unite') !== false) {
                throw new Exception(
                    "Impossible de supprimer cette unite car elle est encore liee a un ou plusieurs services. " .
                    "Veuillez d'abord dissocier l'unite de tous les services."
                );
            }
            
            if (strpos($errorMessage, 'fk_lignesfacture_unite') !== false) {
                throw new Exception(
                    "Impossible de supprimer cette unite car elle est utilisee dans des lignes de facture."
                );
            }
            
            // Erreur inconnue
            throw new Exception("Erreur lors de la suppression de l'unite: " . $errorMessage);
        }
    }

    public function linkToService(int $id_unite, int $id_service, bool $isDefault = false): bool {
        // Verifier si la liaison existe deja
        $existingLink = $this->fetchOne(
            "SELECT id FROM services_unites WHERE service_id = ? AND unite_id = ?",
            [$id_service, $id_unite]
        );
        
        if (!$existingLink) {
           // Creer une nouvelle liaison
            $sql = "INSERT INTO services_unites (service_id, unite_id, isDefault) VALUES (?, ?, ?)";
            $this->executeQuery($sql, [$id_service, $id_unite, $isDefault ? 1 : 0]);
        }
        
        // Si c'est par defaut, desactiver les autres
        if ($isDefault) {
            $this->updateServiceUniteDefault($id_service, $id_unite, true);
        }
        
        return true;
    }

    public function unlinkFromService(int $id_unite, int $id_service): array {
        // Verifier d'abord si cette liaison est utilisee dans des factures
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
                'message' => 'La liaison a ete supprimee avec succes',
                'action' => 'supprime'
            ];
        }
    }

    public function updateServiceUniteDefault(int $id_service, int $id_unite, bool $isDefault = true): array {
        if ($isDefault) {
            // Desactiver toutes les autres unites par defaut pour ce service
            $this->executeQuery(
                "UPDATE services_unites SET isDefault = 0 WHERE service_id = ?",
                [$id_service]
            );
            
            // Definir la nouvelle unite par defaut
            $this->executeQuery(
                "UPDATE services_unites SET isDefault = 1 WHERE service_id = ? AND unite_id = ?",
                [$id_service, $id_unite]
            );
        } else {
            // Desactiver cette unite comme defaut
            $this->executeQuery(
                "UPDATE services_unites SET isDefault = 0 WHERE service_id = ? AND unite_id = ?",
                [$id_service, $id_unite]
            );
        }
        
        return [
            'success' => true,
            'message' => 'Unite par defaut mise a jour avec succes'
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
                ? "Cette liaison est utilisee dans $count ligne(s) de facture et ne peut pas etre supprimee." 
                : "Cette liaison peut etre supprimee en toute securite."
        ];
    }
}
?>