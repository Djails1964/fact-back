<?php
/**
 * MotifLocationControleur.php
 * Couche SQL pour la table `motif_location`.
 * Méthodes statiques — appelé depuis ServiceMotifLocation.
 */
class MotifLocationControleur
{
    // ── LECTURE ───────────────────────────────────────────────────────────────

    /**
     * Retourne tous les motifs d'un type de contrat.
     */
    public static function listerParTypeContrat(PDO $conn, int $idTypeContrat, bool $actifSeulement = true): array
    {
        try {
            $sql = "SELECT id, id_type_contrat, libelle, est_defaut, actif, ordre
                    FROM motif_location
                    WHERE id_type_contrat = ?";
            if ($actifSeulement) $sql .= " AND actif = 1";
            $sql .= " ORDER BY ordre ASC, libelle ASC";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$idTypeContrat]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("MotifLocationControleur::listerParTypeContrat - " . $e->getMessage());
            throw new Exception('Erreur récupération motifs');
        }
    }

    /**
     * Retourne tous les motifs groupés par type de contrat.
     * Retourne : [ idTypeContrat => [ { id, libelle, est_defaut, ordre }, ... ] ]
     */
    public static function listerTous(PDO $conn, bool $actifSeulement = true): array
    {
        try {
            $sql = "SELECT id, id_type_contrat, libelle, est_defaut, actif, ordre
                    FROM motif_location";
            if ($actifSeulement) $sql .= " WHERE actif = 1";
            $sql .= " ORDER BY id_type_contrat ASC, ordre ASC, libelle ASC";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $grouped = [];
            foreach ($rows as $row) {
                $grouped[$row['id_type_contrat']][] = $row;
            }
            return $grouped;
        } catch (PDOException $e) {
            error_log("MotifLocationControleur::listerTous - " . $e->getMessage());
            throw new Exception('Erreur récupération motifs');
        }
    }

    public static function getById(PDO $conn, int $id): ?array
    {
        try {
            $stmt = $conn->prepare("SELECT * FROM motif_location WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (PDOException $e) {
            error_log("MotifLocationControleur::getById - " . $e->getMessage());
            throw new Exception('Erreur récupération motif');
        }
    }

    // ── ÉCRITURE ──────────────────────────────────────────────────────────────

    public static function creer(PDO $conn, array $data): array
    {
        try {
            // Si est_defaut=1, réinitialiser les autres pour ce type
            if (!empty($data['est_defaut'])) {
                self::_resetDefaut($conn, (int)$data['id_type_contrat']);
            }
            $stmt = $conn->prepare("
                INSERT INTO motif_location (id_type_contrat, libelle, est_defaut, actif, ordre)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                (int)$data['id_type_contrat'],
                trim($data['libelle']),
                empty($data['est_defaut']) ? 0 : 1,
                isset($data['actif']) ? (int)(bool)$data['actif'] : 1,
                (int)($data['ordre'] ?? 0),
            ]);
            return ['success' => true, 'id' => (int)$conn->lastInsertId()];
        } catch (PDOException $e) {
            error_log("MotifLocationControleur::creer - " . $e->getMessage());
            throw new Exception('Erreur création motif : ' . $e->getMessage());
        }
    }

    public static function modifier(PDO $conn, int $id, array $data): array
    {
        try {
            $motif = self::getById($conn, $id);
            if (!$motif) throw new Exception("Motif introuvable (id={$id})");

            // Si est_defaut=1, réinitialiser les autres pour ce type
            if (!empty($data['est_defaut'])) {
                self::_resetDefaut($conn, (int)$motif['id_type_contrat'], $id);
            }
            $stmt = $conn->prepare("
                UPDATE motif_location SET
                    libelle    = ?,
                    est_defaut = ?,
                    actif      = ?,
                    ordre      = ?
                WHERE id = ?
            ");
            $stmt->execute([
                trim($data['libelle'] ?? $motif['libelle']),
                empty($data['est_defaut']) ? 0 : 1,
                isset($data['actif']) ? (int)(bool)$data['actif'] : $motif['actif'],
                (int)($data['ordre'] ?? $motif['ordre']),
                $id,
            ]);
            return ['success' => true, 'modifie' => $stmt->rowCount() > 0];
        } catch (PDOException $e) {
            error_log("MotifLocationControleur::modifier - " . $e->getMessage());
            throw new Exception('Erreur modification motif : ' . $e->getMessage());
        }
    }

    public static function supprimer(PDO $conn, int $id): array
    {
        try {
            $stmt = $conn->prepare("DELETE FROM motif_location WHERE id = ?");
            $stmt->execute([$id]);
            return ['success' => true, 'supprime' => $stmt->rowCount() > 0];
        } catch (PDOException $e) {
            error_log("MotifLocationControleur::supprimer - " . $e->getMessage());
            throw new Exception('Impossible de supprimer ce motif : ' . $e->getMessage());
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Réinitialise est_defaut=0 pour tous les motifs d'un type (sauf $exceptId) */
    private static function _resetDefaut(PDO $conn, int $idTypeContrat, int $exceptId = 0): void
    {
        $sql = "UPDATE motif_location SET est_defaut = 0 WHERE id_type_contrat = ?";
        if ($exceptId > 0) $sql .= " AND id != {$exceptId}";
        $conn->prepare($sql)->execute([$idTypeContrat]);
    }

    /** Valide les données d'entrée */
    public static function valider(array $data, bool $isCreate = true): void
    {
        if ($isCreate && empty($data['id_type_contrat'])) {
            throw new Exception('id_type_contrat requis');
        }
        if (empty($data['libelle']) || !trim($data['libelle'])) {
            throw new Exception('Le libellé du motif est obligatoire');
        }
        if (strlen(trim($data['libelle'])) > 150) {
            throw new Exception('Le libellé ne doit pas dépasser 150 caractères');
        }
    }
}
?>