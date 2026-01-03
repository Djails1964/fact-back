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
    

    public function createTypeTarif(array $data): array {
        
        $sql = "INSERT INTO types_tarifs (code, nom, description) VALUES (?, ?, ?)";
        
        $this->executeQuery($sql, [
            $data['code_type_tarif'],
            $data['nom_type_tarif'],
            $data['description_type_tarif'] ?? null
        ]);
        
        $id_type_tarif = $this->getLastInsertId();
        
        // Construction de l'objet à partir des données déjà disponibles
        $objet = [
            'id_type_tarif' => $id_type_tarif,
            'code_type_tarif' => $data['code_type_tarif'],
            'nom_type_tarif' => $data['nom_type_tarif'],
            'description_type_tarif' => $data['description_type_tarif'] ?? null
        ];
        
        return [
            'id_type_tarif' => $id_type_tarif,
            'type_tarif' => $objet
        ];
    }
    
    public function updateTypeTarif(int $id, array $data): bool {
        $setFields = [];
        $params = [];
        
        if (isset($data['code_type_tarif'])) {
            $setFields[] = "code = ?";
            $params[] = $data['code_type_tarif'];
        }
        
        if (isset($data['nom_type_tarif'])) {
            $setFields[] = "nom = ?";
            $params[] = $data['nom_type_tarif'];
        }
        
        if (isset($data['description_type_tarif'])) {
            $setFields[] = "description = ?";
            $params[] = $data['description_type_tarif'];
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
    
    public function getTarifsStandards(?int $id_service = null, ?int $id_unite = null, ?int $id_type_tarif = null, ?string $date = null): array {
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
        
        if ($id_service !== null) {
            $conditions[] = "t.service_id = ?";
            $params[] = $id_service;
        }
        
        if ($id_unite !== null) {
            $conditions[] = "t.unite_id = ?";
            $params[] = $id_unite;
        }
        
        if ($id_type_tarif !== null) {
            $conditions[] = "t.type_tarif_id = ?";
            $params[] = $id_type_tarif;
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

        error_log("Executing SQL for getTarifsStandards: $sql with params: " . implode(", ", $params));
        
        return $this->fetchAll($sql, $params);
    }
    
    public function getAllTarifsStandards(?int $id_service = null, ?int $id_unite = null, ?int $id_type_tarif = null): array {
        return $this->getTarifsStandards($id_service, $id_unite, $id_type_tarif, null);
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
            $data['id_service'],
            $data['id_unite'],
            $data['id_type_tarif'],
            $data['date_debut_tarif_standard'] ?? date('Y-m-d'),
            $data['date_fin_tarif_standard'] ?? null
        );
        
        if ($existingTarif) {
            // Mettre à jour le tarif existant
            $this->executeQuery(
                "UPDATE tarifs SET prix = ?, date_fin = ? WHERE id = ?",
                [$data['prix_tarif_standard'], $data['date_fin_tarif_standard'] ?? null, $existingTarif['id_tarif_standard']]
            );
            return (int)$existingTarif['id'];
        }
        
        // Créer un nouveau tarif
        $sql = "INSERT INTO tarifs (service_id, unite_id, type_tarif_id, prix, date_debut, date_fin) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $this->executeQuery($sql, [
            $data['id_service'],
            $data['id_unite'],
            $data['id_type_tarif'],
            $data['prix_tarif_standard'],
            $data['date_debut_tarif_standard'] ?? date('Y-m-d'),
            $data['date_fin_tarif_standard'] ?? null
        ]);
        
        return $this->getLastInsertId();
    }
    
    public function updateTarifStandard(int $id, array $data): bool {
        $setFields = [];
        $params = [];
        
        if (isset($data['prix_tarif_standard'])) {
            $setFields[] = "prix = ?";
            $params[] = $data['prix_tarif_standard'];
        }
        
        if (isset($data['date_debut_tarif_standard'])) {
            $setFields[] = "date_debut = ?";
            $params[] = $data['date_debut_tarif_standard'];
        }
        
        if (array_key_exists('date_fin_tarif_standard', $data)) {
            $setFields[] = "date_fin = ?";
            $params[] = (isset($data['date_fin_tarif_standard']) && $data['date_fin_tarif_standard'] !== '') ? $data['date_fin_tarif_standard'] : null;
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
        $tarif = $this->fetchOne("SELECT service_id as id_service, unite_id as id_unite FROM tarifs WHERE id = ?", [$id]);
        
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
        
        $result = $this->fetchOne($sql, [$tarif['id_service'], $tarif['id_unite']]);
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
    
    private function findExistingTarifStandard(int $id_service, int $id_unite, int $id_type_tarif, string $date_debut_tarif_standard, ?string $date_fin_tarif_standard): ?array {
        $sql = "SELECT id FROM tarifs 
                WHERE service_id = ? AND unite_id = ? AND type_tarif_id = ? 
                AND date_debut <= ? AND (date_fin IS NULL OR date_fin >= ?)";
        
        return $this->fetchOne($sql, [$id_service, $id_unite, $id_type_tarif, $date_debut_tarif_standard, $date_fin_tarif_standard ?? $date_debut_tarif_standard]);
    }

    /**
     * Supprime tous les tarifs standards pour un service et une unité donnés
     * 
     * @param int $id_service ID du service
     * @param int $id_unite ID de l'unité
     * @return int Nombre de tarifs supprimés
     */
    public function deleteTarifsStandardsByServiceAndUnite(int $id_service, int $id_unite): int {
        $stmt = $this->executeQuery(
            "DELETE FROM tarifs WHERE service_id = ? AND unite_id = ?", 
            [$id_service, $id_unite]
        );
        return $stmt->rowCount();
    }
    
    
    /**
     * ===============================
     * TARIFS SPÉCIAUX
     * ===============================
     */
    
    public function getTarifsSpeciaux(?int $client_id = null, ?int $id_service = null, ?int $id_unite = null, ?string $date = null): array {
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
        
        if ($client_id !== null) {
            $conditions[] = "ts.client_id = ?";
            $params[] = $client_id;
        }
        
        if ($id_service !== null) {
            $conditions[] = "ts.service_id = ?";
            $params[] = $id_service;
        }
        
        if ($id_unite !== null) {
            $conditions[] = "ts.unite_id = ?";
            $params[] = $id_unite;
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
    
    public function getAllTarifsSpeciaux(?int $client_id = null, ?int $id_service = null, ?int $id_unite = null): array {
        return $this->getTarifsSpeciaux($client_id, $id_service, $id_unite, null);
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
            $data['id_service'],
            $data['id_unite'],
            $data['date_debut_tarif_special'] ?? date('Y-m-d'),
            $data['date_fin_tarif_special'] ?? null
        );
        
        if ($existingTarif) {
            // Mettre à jour le tarif existant
            $this->executeQuery(
                "UPDATE tarifs_speciaux SET prix = ?, date_fin = ?, note = ? WHERE id = ?",
                [$data['prix_tarif_special'], $data['date_fin_tarif_special'] ?? null, $data['note'], $existingTarif['id_tarif_special']]
            );
            return (int)$existingTarif['id_tarif_special'];
        }
        
        // Créer un nouveau tarif spécial
        $sql = "INSERT INTO tarifs_speciaux (client_id, service_id, unite_id, prix, date_debut, date_fin, note) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $this->executeQuery($sql, [
            $data['client_id'],
            $data['id_service'],
            $data['id_unite'],
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
        
        if (isset($data['prix_tarif_special'])) {
            $setFields[] = "prix = ?";
            $params[] = $data['prix_tarif_special'];
        }
        
        if (isset($data['date_debut_tarif_special'])) {
            $setFields[] = "date_debut = ?";
            $params[] = $data['date_debut_tarif_special'];
        }
        
        if (array_key_exists('date_fin_tarif_special', $data)) {
            $setFields[] = "date_fin = ?";
            $params[] = (isset($data['date_fin_tarif_special']) && $data['date_fin_tarif_special'] !== '') ? $data['date_fin_tarif_special'] : null;
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
            "SELECT client_id, service_id as id_service, unite_id as id_unite FROM tarifs_speciaux WHERE id = ?", 
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
                WHERE f.client_id = ? AND lf.service_id = ? AND lf.unite_id = ?";
        
        $result = $this->fetchOne($sql, [
            $tarifSpecial['client_id'], 
            $tarifSpecial['id_service'], 
            $tarifSpecial['id_unite']
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
    
    private function findExistingTarifSpecial(int $client_id, int $id_service, int $id_unite, string $date_debut_tarif_special, ?string $date_fin_tarif_special): ?array {
        $sql = "SELECT id FROM tarifs_speciaux 
                WHERE client_id = ? AND service_id = ? AND unite_id = ? 
                AND date_debut <= ? AND (date_fin IS NULL OR date_fin >= ?)";
        
        return $this->fetchOne($sql, [$client_id, $id_service, $id_unite, $date_debut_tarif_special, $date_fin_tarif_special ?? $date_debut_tarif_special]);
    }

    /**
     * Supprime tous les tarifs spéciaux pour un service et une unité donnés
     * 
     * @param int $id_service ID du service
     * @param int $id_unite ID de l'unité
     * @return int Nombre de tarifs supprimés
     */
    public function deleteTarifsSpeciauxByServiceAndUnite(int $id_service, int $id_unite): int {
        $stmt = $this->executeQuery(
            "DELETE FROM tarifs_speciaux WHERE service_id = ? AND unite_id = ?", 
            [$id_service, $id_unite]
        );
        return $stmt->rowCount();
    }
    
    
    /**
     * ===============================
     * CALCULS DE TARIFS POUR CLIENTS
     * ===============================
     */
    
    public function getTarifPourClient(int $client_id, int $id_service, int $id_unite, string $date): array {
        error_log("Calcul du tarif pour client ID: $client_id, service ID: $id_service, unité ID: $id_unite, date: $date");
        // 1. Chercher un tarif spécial
        $tarifSpecial = $this->findTarifSpecialForClient($client_id, $id_service, $id_unite, $date);
        if ($tarifSpecial) {
            return [
                'success' => true,
                'tarif' => $tarifSpecial,
                'type' => 'special'
            ];
        }
        
        // 2. Déterminer le type de client et chercher un tarif standard
        $estTherapeute = $this->isClientTherapeute($client_id);
        $code_type_tarif = $estTherapeute ? 'therapeute' : 'normal';
        error_log("Client ID: $client_id isTherapeute: " . ($estTherapeute ? 'yes' : 'no') . ", searching for type: $code_type_tarif");

        $tarifStandard = $this->findTarifStandardForClient($id_service, $id_unite, $code_type_tarif, $date);

        if ($tarifStandard) {
            return [
                'success' => true,
                'tarif' => $tarifStandard,
                'type' => $code_type_tarif
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Aucun tarif trouvé pour le service, l\'unité et la date spécifiés'
        ];
    }
    
    public function isClientTherapeute(int $client_id): bool {
        $sql = "SELECT estTherapeute FROM client WHERE id = ?";
        $result = $this->fetchOne($sql, [$client_id]);
        return (bool)($result['estTherapeute'] ?? false);
    }

    public function clientPossedeTarifSpecial(int $client_id, ?string $date = null): bool {
        $date = $date ?: date('Y-m-d');
        
        $sql = "SELECT COUNT(*) as count FROM tarifs_speciaux 
                WHERE client_id = ? 
                AND date_debut <= ? 
                AND (date_fin IS NULL OR date_fin >= ?)";
        
        $result = $this->fetchOne($sql, [$client_id, $date, $date]);
        return (int)$result['count'] > 0;
    }

    public function getUnitesApplicablesPourClient(int $client_id, string $date): array {
        error_log("Fetching applicable units for client ID: $client_id on date: $date");
        // Déterminer si le client est thérapeute
        $estTherapeute = $this->isClientTherapeute($client_id);
        error_log("Client ID: $client_id isTherapeute: " . ($estTherapeute ? 'yes' : 'no'));
        
        // ✅ CORRECTION PRINCIPALE: Requête avec logique de priorité correcte
        $sql = "
            SELECT DISTINCT 
                u.id as id_unite, 
                u.code as code_unite, 
                u.nom as nom_unite, 
                u.description as description_unite, 
                s.id as id_service, 
                s.code as code_service, 
                s.nom as nom_service,
                CASE 
                    WHEN ts.prix IS NOT NULL THEN 'special'
                    WHEN ? = 1 AND tt.prix IS NOT NULL THEN 'therapeute'
                    WHEN tn.prix IS NOT NULL THEN 'standard'
                    ELSE NULL
                END as type_tarif_applicable,
                CASE 
                    WHEN ts.prix IS NOT NULL THEN ts.prix
                    WHEN ? = 1 AND tt.prix IS NOT NULL THEN tt.prix
                    WHEN tn.prix IS NOT NULL THEN tn.prix
                    ELSE NULL
                END as prix_applicable
            FROM unites u
            JOIN services_unites su ON u.id = su.unite_id
            JOIN services s ON su.service_id = s.id AND s.actif = 1
            
            -- ✅ TARIFS SPÉCIAUX (priorité 1)
            LEFT JOIN (
                SELECT ts.unite_id, ts.service_id, ts.prix
                FROM tarifs_speciaux ts
                WHERE ts.client_id = ?
                AND ts.date_debut <= ?
                AND (ts.date_fin IS NULL OR ts.date_fin >= ?)
            ) ts ON u.id = ts.unite_id AND s.id = ts.service_id
            
            -- ✅ TARIFS THÉRAPEUTE (priorité 2)
            LEFT JOIN (
                SELECT t.unite_id, t.service_id, t.prix
                FROM tarifs t
                JOIN types_tarifs typ ON t.type_tarif_id = typ.id
                WHERE typ.code = 'therapeute'
                AND t.date_debut <= ?
                AND (t.date_fin IS NULL OR t.date_fin >= ?)
            ) tt ON u.id = tt.unite_id AND s.id = tt.service_id
            
            -- ✅ TARIFS STANDARD (priorité 3)
            LEFT JOIN (
                SELECT t.unite_id, t.service_id, t.prix
                FROM tarifs t
                JOIN types_tarifs typ ON t.type_tarif_id = typ.id
                WHERE typ.code = 'normal'
                AND t.date_debut <= ?
                AND (t.date_fin IS NULL OR t.date_fin >= ?)
            ) tn ON u.id = tn.unite_id AND s.id = tn.service_id
            
            -- ✅ CONDITION: Au moins un tarif doit exister selon la priorité
            WHERE (
                ts.prix IS NOT NULL  -- Tarif spécial existe
                OR (? = 1 AND tt.prix IS NOT NULL)  -- Client thérapeute ET tarif thérapeute existe
                OR tn.prix IS NOT NULL  -- Tarif standard existe
            )
            ORDER BY s.nom, u.nom
        ";
        
        $params = [
            $estTherapeute ? 1 : 0,  // Pour CASE 1
            $estTherapeute ? 1 : 0,  // Pour CASE 2
            $client_id,               // Pour tarifs spéciaux
            $date, $date,           // Pour tarifs spéciaux
            $date, $date,           // Pour tarifs thérapeute
            $date, $date,           // Pour tarifs standard
            $estTherapeute ? 1 : 0   // Pour WHERE final
        ];
        
        error_log("Executing corrected SQL for client units with priority logic");
        error_log("Params: " . print_r($params, true));
        
        $results = $this->fetchAll($sql, $params);
        
        // ✅ LOG pour debug : afficher la logique appliquée
        foreach ($results as $result) {
            error_log(sprintf(
                "Service: %s, Unité: %s => Tarif: %s (%.2f CHF)",
                $result['nom_service'],
                $result['nom_unite'],
                $result['type_tarif_applicable'],
                $result['prix_applicable'] ?? 0
            ));
        }
        
        return $results;
    }
    
    private function findTarifSpecialForClient(int $client_id, int $id_service, int $id_unite, string $date): ?array {
        $sql = "SELECT prix, 'special' as type 
                FROM tarifs_speciaux 
                WHERE client_id = ? AND service_id = ? AND unite_id = ? 
                AND date_debut <= ? AND (date_fin IS NULL OR date_fin >= ?)
                ORDER BY date_debut DESC LIMIT 1";
        
        return $this->fetchOne($sql, [$client_id, $id_service, $id_unite, $date, $date]);
    }
    
    private function findTarifStandardForClient(int $id_service, int $id_unite, string $codeTypeTarif, string $date): ?array {
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
        
        return $this->fetchOne($sql, [$codeTypeTarif, $id_service, $id_unite, $codeTypeTarif, $date, $date]);
    }
}
?>