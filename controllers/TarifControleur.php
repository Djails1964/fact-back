<?php
// controllers/TarifControleur.php - VERSION REFACTORISÉE AVEC ALIAS REFORMULÉS

require_once __DIR__ . '/base/DatabaseHelpers.php';
require_once __DIR__ . '/base/UsageChecker.php';

class TarifControleur {
    use DatabaseHelpers, UsageChecker;
    
    private $conn;
    
    public function __construct(PDO $conn) {
        $this->conn = $conn;
    }
    
    /**
     * ===============================
     * TYPES DE TARIFS
     * ===============================
     */
    
    public function getAllTypesTarifs(): array {
        $sql = "SELECT id as id_type_tarif, 
                       code as code_type_tarif, 
                       nom as nom_type_tarif, 
                       description as description_type_tarif 
                FROM types_tarifs ORDER BY id";
        return $this->fetchAll($sql);
    }
    
    public function getTypeTarifById(int $id): ?array {
        $sql = "SELECT id as id_type_tarif, 
                       code as code_type_tarif, 
                       nom as nom_type_tarif, 
                       description as description_type_tarif 
                FROM types_tarifs WHERE id = ?";
        return $this->fetchOne($sql, [$id]);
    }
    
    public function createTypeTarif(array $data): int {
        $sql = "INSERT INTO types_tarifs (code, nom, description) VALUES (?, ?, ?)";
        $this->executeQuery($sql, [
            $data['code'],
            $data['nom'],
            $data['description'] ?? null
        ]);
        
        return $this->getLastInsertId();
    }
    
    public function updateTypeTarif(int $id, array $data): bool {
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
        
        $sql = "UPDATE types_tarifs SET " . implode(", ", $setFields) . " WHERE id = ?";
        $params[] = $id;
        
        $stmt = $this->executeQuery($sql, $params);
        return $stmt->rowCount() > 0;
    }
    
    public function deleteTypeTarif(int $id): array {
        // Vérifier l'usage
        $usageCheck = $this->checkTypeTarifUsage($id);
        
        if ($usageCheck['isUsed']) {
            throw new Exception('Impossible de supprimer ce type de tarif car il est utilisé dans des tarifs');
        }
        
        $this->executeQuery("DELETE FROM types_tarifs WHERE id = ?", [$id]);
        
        return [
            'success' => true,
            'message' => 'Le type de tarif a été supprimé avec succès'
        ];
    }
    
    public function checkTypeTarifUsage(int $id): array {
        return $this->checkUsageInTables($id, 'type_tarif_id', ['tarifs']);
    }
    
    /**
     * ===============================
     * TARIFS STANDARDS
     * ===============================
     */
    
    public function getTarifsStandards(?int $serviceId = null, ?int $uniteId = null, ?int $typeTarifId = null, ?string $date = null): array {
        $conditions = [];
        $params = [];
        
        $sql = "SELECT t.id as id_tarif_standard, 
                       t.service_id as id_service, 
                       t.unite_id as id_unite, 
                       t.type_tarif_id as id_type_tarif, 
                       t.prix as prix_tarif_standard, 
                       t.date_debut as date_debut_tarif_standard, 
                       t.date_fin as date_fin_tarif_standard,
                       s.code as code_service, 
                       s.nom as nom_service,
                       u.code as code_unite, 
                       u.nom as nom_unite,
                       tt.code as code_type_tarif, 
                       tt.nom as nom_type_tarif
                FROM tarifs t
                JOIN services s ON t.service_id = s.id
                JOIN unites u ON t.unite_id = u.id
                JOIN types_tarifs tt ON t.type_tarif_id = tt.id
                WHERE 1=1";
        
        if ($serviceId !== null) {
            $conditions[] = "t.service_id = ?";
            $params[] = $serviceId;
        }
        
        if ($uniteId !== null) {
            $conditions[] = "t.unite_id = ?";
            $params[] = $uniteId;
        }
        
        if ($typeTarifId !== null) {
            $conditions[] = "t.type_tarif_id = ?";
            $params[] = $typeTarifId;
        }
        
        if ($date !== null) {
            $conditions[] = "(t.date_debut <= ? AND (t.date_fin IS NULL OR t.date_fin >= ?))";
            $params[] = $date;
            $params[] = $date;
        }
        
        if (!empty($conditions)) {
            $sql .= " AND " . implode(" AND ", $conditions);
        }
        
        $sql .= " ORDER BY s.nom, u.nom, tt.nom, t.date_debut DESC";
        
        return $this->fetchAll($sql, $params);
    }
    
    public function getAllTarifsStandards(?int $serviceId = null, ?int $uniteId = null, ?int $typeTarifId = null): array {
        return $this->getTarifsStandards($serviceId, $uniteId, $typeTarifId, null);
    }
    
    public function getTarifStandardById(int $id): ?array {
        $sql = "SELECT t.id as id_tarif_standard, 
                       t.service_id as id_service, 
                       t.unite_id as id_unite, 
                       t.type_tarif_id as id_type_tarif, 
                       t.prix as prix_tarif_standard, 
                       t.date_debut as date_debut_tarif_standard, 
                       t.date_fin as date_fin_tarif_standard,
                       s.code as code_service, 
                       s.nom as nom_service,
                       u.code as code_unite, 
                       u.nom as nom_unite,
                       tt.code as code_type_tarif, 
                       tt.nom as nom_type_tarif
                FROM tarifs t
                JOIN services s ON t.service_id = s.id
                JOIN unites u ON t.unite_id = u.id
                JOIN types_tarifs tt ON t.type_tarif_id = tt.id
                WHERE t.id = ?";
        
        return $this->fetchOne($sql, [$id]);
    }
    
    public function createTarifStandard(array $data): int {
        // Vérifier si un tarif existe déjà pour cette combinaison et cette période
        $existingTarif = $this->findExistingTarifStandard(
            $data['service_id'],
            $data['unite_id'],
            $data['type_tarif_id'],
            $data['date_debut'] ?? date('Y-m-d'),
            $data['date_fin'] ?? null
        );
        
        if ($existingTarif) {
            // Mettre à jour le tarif existant
            $this->executeQuery(
                "UPDATE tarifs SET prix = ?, date_fin = ? WHERE id = ?",
                [$data['prix'], $data['date_fin'] ?? null, $existingTarif['id']]
            );
            return (int)$existingTarif['id'];
        }
        
        // Créer un nouveau tarif
        $sql = "INSERT INTO tarifs (service_id, unite_id, type_tarif_id, prix, date_debut, date_fin) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $this->executeQuery($sql, [
            $data['service_id'],
            $data['unite_id'],
            $data['type_tarif_id'],
            $data['prix'],
            $data['date_debut'] ?? date('Y-m-d'),
            $data['date_fin'] ?? null
        ]);
        
        return $this->getLastInsertId();
    }
    
    public function updateTarifStandard(int $id, array $data): bool {
        $setFields = [];
        $params = [];
        
        if (isset($data['prix'])) {
            $setFields[] = "prix = ?";
            $params[] = $data['prix'];
        }
        
        if (isset($data['date_debut'])) {
            $setFields[] = "date_debut = ?";
            $params[] = $data['date_debut'];
        }
        
        if (array_key_exists('date_fin', $data)) {
            $setFields[] = "date_fin = ?";
            $params[] = (isset($data['date_fin']) && $data['date_fin'] !== '') ? $data['date_fin'] : null;
        }
        
        if (empty($setFields)) {
            return false;
        }
        
        $sql = "UPDATE tarifs SET " . implode(", ", $setFields) . " WHERE id = ?";
        $params[] = $id;
        
        $stmt = $this->executeQuery($sql, $params);
        return $stmt->rowCount() > 0;
    }
    
    public function deleteTarifStandard(int $id): bool {
        $stmt = $this->executeQuery("DELETE FROM tarifs WHERE id = ?", [$id]);
        return $stmt->rowCount() > 0;
    }
    
    public function checkTarifStandardUsage(int $id): array {
        // Récupérer les détails du tarif
        $tarif = $this->fetchOne("SELECT service_id, unite_id FROM tarifs WHERE id = ?", [$id]);
        
        if (!$tarif) {
            return [
                'success' => false,
                'message' => 'Tarif non trouvé'
            ];
        }
        
        // Vérifier si utilisé dans des factures
        $sql = "SELECT COUNT(*) as total 
                FROM lignesfacture 
                WHERE service_id = ? AND unite_id = ?";
        
        $result = $this->fetchOne($sql, [$tarif['service_id'], $tarif['unite_id']]);
        $count = (int)$result['total'];
        $isUsed = $count > 0;
        
        return [
            'success' => true,
            'isUsed' => $isUsed,
            'count' => $count,
            'message' => $isUsed 
                ? "Ce tarif est utilisé dans $count ligne(s) de facture et ne peut pas être supprimé." 
                : "Ce tarif peut être supprimé en toute sécurité."
        ];
    }
    
    private function findExistingTarifStandard(int $serviceId, int $uniteId, int $typeTarifId, string $dateDebut, ?string $dateFin): ?array {
        $sql = "SELECT id FROM tarifs 
                WHERE service_id = ? AND unite_id = ? AND type_tarif_id = ? 
                AND date_debut <= ? AND (date_fin IS NULL OR date_fin >= ?)";
        
        return $this->fetchOne($sql, [$serviceId, $uniteId, $typeTarifId, $dateDebut, $dateFin ?? $dateDebut]);
    }
    
    /**
     * ===============================
     * TARIFS SPÉCIAUX
     * ===============================
     */
    
    public function getTarifsSpeciaux(?int $clientId = null, ?int $serviceId = null, ?int $uniteId = null, ?string $date = null): array {
        $conditions = [];
        $params = [];
        
        $sql = "SELECT ts.id as id_tarif_special, 
                       ts.client_id, 
                       ts.service_id as id_service, 
                       ts.unite_id as id_unite, 
                       ts.prix as prix_tarif_special, 
                       ts.date_debut as date_debut_tarif_special, 
                       ts.date_fin as date_fin_tarif_special, 
                       ts.note,
                       c.nom as client_nom, 
                       c.prenom as client_prenom,
                       s.code as code_service, 
                       s.nom as nom_service,
                       u.code as code_unite, 
                       u.nom as nom_unite
                FROM tarifs_speciaux ts
                JOIN client c ON ts.client_id = c.id
                JOIN services s ON ts.service_id = s.id
                JOIN unites u ON ts.unite_id = u.id
                WHERE 1=1";
        
        if ($clientId !== null) {
            $conditions[] = "ts.client_id = ?";
            $params[] = $clientId;
        }
        
        if ($serviceId !== null) {
            $conditions[] = "ts.service_id = ?";
            $params[] = $serviceId;
        }
        
        if ($uniteId !== null) {
            $conditions[] = "ts.unite_id = ?";
            $params[] = $uniteId;
        }
        
        if ($date !== null) {
            $conditions[] = "(ts.date_debut <= ? AND (ts.date_fin IS NULL OR ts.date_fin >= ?))";
            $params[] = $date;
            $params[] = $date;
        }
        
        if (!empty($conditions)) {
            $sql .= " AND " . implode(" AND ", $conditions);
        }
        
        $sql .= " ORDER BY c.nom, s.nom, u.nom, ts.date_debut DESC";
        
        return $this->fetchAll($sql, $params);
    }
    
    public function getAllTarifsSpeciaux(?int $clientId = null, ?int $serviceId = null, ?int $uniteId = null): array {
        return $this->getTarifsSpeciaux($clientId, $serviceId, $uniteId, null);
    }
    
    public function getTarifSpecialById(int $id): ?array {
        $sql = "SELECT ts.id as id_tarif_special, 
                       ts.client_id, 
                       ts.service_id as id_service, 
                       ts.unite_id as id_unite, 
                       ts.prix as prix_tarif_special, 
                       ts.date_debut as date_debut_tarif_special, 
                       ts.date_fin as date_fin_tarif_special, 
                       ts.note,
                       c.nom as client_nom, 
                       c.prenom as client_prenom,
                       s.code as code_service, 
                       s.nom as nom_service,
                       u.code as code_unite, 
                       u.nom as nom_unite
                FROM tarifs_speciaux ts
                JOIN client c ON ts.client_id = c.id
                JOIN services s ON ts.service_id = s.id
                JOIN unites u ON ts.unite_id = u.id
                WHERE ts.id = ?";
        
        return $this->fetchOne($sql, [$id]);
    }
    
    public function createTarifSpecial(array $data): int {
        // Vérifier que la note est présente
        if (!isset($data['note']) || trim($data['note']) === '') {
            throw new Exception('La note est obligatoire pour un tarif spécial');
        }
        
        // Vérifier si un tarif spécial existe déjà 
        $existingTarif = $this->findExistingTarifSpecial(
            $data['client_id'],
            $data['service_id'],
            $data['unite_id'],
            $data['date_debut'] ?? date('Y-m-d'),
            $data['date_fin'] ?? null
        );
        
        if ($existingTarif) {
            // Mettre à jour le tarif existant
            $this->executeQuery(
                "UPDATE tarifs_speciaux SET prix = ?, date_fin = ?, note = ? WHERE id = ?",
                [$data['prix'], $data['date_fin'] ?? null, $data['note'], $existingTarif['id']]
            );
            return (int)$existingTarif['id'];
        }
        
        // Créer un nouveau tarif spécial
        $sql = "INSERT INTO tarifs_speciaux (client_id, service_id, unite_id, prix, date_debut, date_fin, note) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $this->executeQuery($sql, [
            $data['client_id'],
            $data['service_id'],
            $data['unite_id'],
            $data['prix'],
            $data['date_debut'] ?? date('Y-m-d'),
            $data['date_fin'] ?? null,
            $data['note']
        ]);
        
        return $this->getLastInsertId();
    }
    
    public function updateTarifSpecial(int $id, array $data): bool {
        $setFields = [];
        $params = [];
        
        if (isset($data['prix'])) {
            $setFields[] = "prix = ?";
            $params[] = $data['prix'];
        }
        
        if (isset($data['date_debut'])) {
            $setFields[] = "date_debut = ?";
            $params[] = $data['date_debut'];
        }
        
        if (array_key_exists('date_fin', $data)) {
            $setFields[] = "date_fin = ?";
            $params[] = (isset($data['date_fin']) && $data['date_fin'] !== '') ? $data['date_fin'] : null;
        }
        
        if (isset($data['note'])) {
            if (trim($data['note']) === '') {
                throw new Exception('La note est obligatoire pour un tarif spécial');
            }
            $setFields[] = "note = ?";
            $params[] = $data['note'];
        }
        
        if (empty($setFields)) {
            return false;
        }
        
        $sql = "UPDATE tarifs_speciaux SET " . implode(", ", $setFields) . " WHERE id = ?";
        $params[] = $id;
        
        $stmt = $this->executeQuery($sql, $params);
        return $stmt->rowCount() > 0;
    }
    
    public function deleteTarifSpecial(int $id): bool {
        $stmt = $this->executeQuery("DELETE FROM tarifs_speciaux WHERE id = ?", [$id]);
        return $stmt->rowCount() > 0;
    }
    
    public function checkTarifSpecialUsage(int $id): array {
        // Récupérer les détails du tarif spécial
        $tarifSpecial = $this->fetchOne(
            "SELECT client_id, service_id, unite_id FROM tarifs_speciaux WHERE id = ?", 
            [$id]
        );
        
        if (!$tarifSpecial) {
            return [
                'success' => false,
                'message' => 'Tarif spécial non trouvé'
            ];
        }
        
        // Vérifier si utilisé dans des factures
        $sql = "SELECT COUNT(*) as total 
                FROM lignesfacture lf 
                JOIN facture f ON lf.id_facture = f.id_facture 
                WHERE f.id_client = ? AND lf.service_id = ? AND lf.unite_id = ?";
        
        $result = $this->fetchOne($sql, [
            $tarifSpecial['client_id'], 
            $tarifSpecial['service_id'], 
            $tarifSpecial['unite_id']
        ]);
        
        $count = (int)$result['total'];
        $isUsed = $count > 0;
        
        return [
            'success' => true,
            'isUsed' => $isUsed,
            'count' => $count,
            'message' => $isUsed 
                ? "Ce tarif spécial est utilisé dans $count ligne(s) de facture et ne peut pas être supprimé." 
                : "Ce tarif spécial peut être supprimé en toute sécurité."
        ];
    }
    
    private function findExistingTarifSpecial(int $clientId, int $serviceId, int $uniteId, string $dateDebut, ?string $dateFin): ?array {
        $sql = "SELECT id FROM tarifs_speciaux 
                WHERE client_id = ? AND service_id = ? AND unite_id = ? 
                AND date_debut <= ? AND (date_fin IS NULL OR date_fin >= ?)";
        
        return $this->fetchOne($sql, [$clientId, $serviceId, $uniteId, $dateDebut, $dateFin ?? $dateDebut]);
    }
    
    /**
     * ===============================
     * CALCULS DE TARIFS POUR CLIENTS
     * ===============================
     */
    
    public function getTarifPourClient(int $clientId, int $serviceId, int $uniteId, string $date): array {
        // 1. Chercher un tarif spécial
        $tarifSpecial = $this->findTarifSpecialForClient($clientId, $serviceId, $uniteId, $date);
        if ($tarifSpecial) {
            return [
                'success' => true,
                'tarif' => $tarifSpecial,
                'type' => 'special'
            ];
        }
        
        // 2. Déterminer le type de client et chercher un tarif standard
        $estTherapeute = $this->isClientTherapeute($clientId);
        $typeTarifCode = $estTherapeute ? 'therapeute' : 'normal';
        
        $tarifStandard = $this->findTarifStandardForClient($serviceId, $uniteId, $typeTarifCode, $date);
        
        if ($tarifStandard) {
            return [
                'success' => true,
                'tarif' => $tarifStandard,
                'type' => $typeTarifCode
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Aucun tarif trouvé pour le service, l\'unité et la date spécifiés'
        ];
    }
    
    public function isClientTherapeute(int $clientId): bool {
        $sql = "SELECT estTherapeute FROM client WHERE id = ?";
        $result = $this->fetchOne($sql, [$clientId]);
        return (bool)($result['estTherapeute'] ?? false);
    }
    
    public function clientPossedeTarifSpecial(int $clientId, ?string $date = null): bool {
        $date = $date ?: date('Y-m-d');
        
        $sql = "SELECT COUNT(*) as count FROM tarifs_speciaux 
                WHERE client_id = ? 
                AND date_debut <= ? 
                AND (date_fin IS NULL OR date_fin >= ?)";
        
        $result = $this->fetchOne($sql, [$clientId, $date, $date]);
        return (int)$result['count'] > 0;
    }
    
    public function getUnitesApplicablesPourClient(int $clientId, string $date): array {
        // Déterminer le type de tarif approprié
        $estTherapeute = $this->isClientTherapeute($clientId);
        $typeTarifCode = $estTherapeute ? 'therapeute' : 'normal';
        
        // Récupérer l'ID du type de tarif
        $typeTarif = $this->fetchOne("SELECT id FROM types_tarifs WHERE code = ?", [$typeTarifCode]);
        $typeTarifId = $typeTarif ? (int)$typeTarif['id'] : 1; // Fallback sur ID 1 (normal)
        
        $sql = "
            SELECT DISTINCT u.id as id_unite, 
                   u.code as code_unite, 
                   u.nom as nom_unite, 
                   u.description as description_unite, 
                   s.id as id_service, 
                   s.code as code_service, 
                   s.nom as nom_service,
                   CASE 
                       WHEN ts.unite_id IS NOT NULL THEN 'special'
                       ELSE 'standard'
                   END as type_tarif
            FROM unites u
            JOIN services_unites su ON u.id = su.unite_id AND su.actif = 1
            JOIN services s ON su.service_id = s.id AND s.actif = 1
            LEFT JOIN (
                SELECT t.unite_id, t.service_id 
                FROM tarifs t
                WHERE t.type_tarif_id = ?
                AND t.date_debut <= ?
                AND (t.date_fin IS NULL OR t.date_fin >= ?)
            ) t ON u.id = t.unite_id AND t.service_id = s.id
            LEFT JOIN (
                SELECT ts.unite_id, ts.service_id
                FROM tarifs_speciaux ts
                WHERE ts.client_id = ?
                AND ts.date_debut <= ?
                AND (ts.date_fin IS NULL OR ts.date_fin >= ?)
            ) ts ON u.id = ts.unite_id AND ts.service_id = s.id
            WHERE t.unite_id IS NOT NULL OR ts.unite_id IS NOT NULL
            ORDER BY s.nom, u.nom
        ";
        
        return $this->fetchAll($sql, [
            $typeTarifId, $date, $date,
            $clientId, $date, $date
        ]);
    }
    
    private function findTarifSpecialForClient(int $clientId, int $serviceId, int $uniteId, string $date): ?array {
        $sql = "SELECT prix, 'special' as type 
                FROM tarifs_speciaux 
                WHERE client_id = ? AND service_id = ? AND unite_id = ? 
                AND date_debut <= ? AND (date_fin IS NULL OR date_fin >= ?)
                ORDER BY date_debut DESC LIMIT 1";
        
        return $this->fetchOne($sql, [$clientId, $serviceId, $uniteId, $date, $date]);
    }
    
    private function findTarifStandardForClient(int $serviceId, int $uniteId, string $typeTarifCode, string $date): ?array {
        $sql = "SELECT t.prix, ? as type, 
                       s.code as code_service, 
                       s.nom as nom_service,
                       u.code as code_unite, 
                       u.nom as nom_unite,
                       tt.code as code_type_tarif, 
                       tt.nom as nom_type_tarif
                FROM tarifs t
                JOIN services s ON t.service_id = s.id
                JOIN unites u ON t.unite_id = u.id
                JOIN types_tarifs tt ON t.type_tarif_id = tt.id
                WHERE t.service_id = ? AND t.unite_id = ? AND tt.code = ?
                AND t.date_debut <= ? AND (t.date_fin IS NULL OR t.date_fin >= ?)
                ORDER BY t.date_debut DESC LIMIT 1";
        
        return $this->fetchOne($sql, [$typeTarifCode, $serviceId, $uniteId, $typeTarifCode, $date, $date]);
    }
}
?>