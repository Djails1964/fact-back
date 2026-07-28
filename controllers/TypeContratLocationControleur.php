<?php
/**
 * TypeContratLocationControleur.php
 * Couche SQL pour la table `type_contrat_location`.
 * Méthodes statiques — appelé exclusivement depuis ServiceLocationSalle.
 *
 * Champs gérés : type_document, type_client_requis, categorie_motifs, actif
 * Champ figé   : nom (non modifiable via l'interface)
 */
class TypeContratLocationControleur
{
    // ── LECTURE ───────────────────────────────────────────────────────────────

    /**
     * Retourne tous les types de contrat (actifs par défaut).
     */
    public static function lister(PDO $conn, bool $actifSeulement = true): array
    {
        try {
            $sql = "
                SELECT id, nom, est_forfait, type_client_requis,
                       categorie_motifs, actif, created_at, updated_at
                FROM type_contrat_location
            ";
            if ($actifSeulement) {
                $sql .= " WHERE actif = 1";
            }
            $sql .= " ORDER BY id ASC";

            $stmt = $conn->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("TypeContratLocationControleur::lister - " . $e->getMessage());
            throw new Exception('Erreur lors de la récupération des types de contrat');
        }
    }

    /**
     * Retourne un type de contrat par son id.
     */
    public static function getById(PDO $conn, int $id): ?array
    {
        try {
            $stmt = $conn->prepare("
                SELECT id, nom, est_forfait, type_client_requis,
                       categorie_motifs, actif, created_at, updated_at
                FROM type_contrat_location
                WHERE id = ?
            ");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;

        } catch (PDOException $e) {
            error_log("TypeContratLocationControleur::getById - " . $e->getMessage());
            throw new Exception('Erreur lors de la récupération du type de contrat');
        }
    }

    // ── ÉCRITURE ──────────────────────────────────────────────────────────────

    /**
     * Met à jour les attributs éditables d'un type de contrat.
     * Le nom est figé et non modifiable.
     *
     * @param array $data ['type_document', 'type_client_requis'?, 'categorie_motifs'?, 'actif'?]
     */
    public static function modifier(PDO $conn, int $id, array $data): array
    {
        try {
            self::_valider($data);

            $stmt = $conn->prepare("
                UPDATE type_contrat_location SET
                    est_forfait        = ?,
                    type_client_requis = ?,
                    categorie_motifs   = ?,
                    actif              = ?
                WHERE id = ?
            ");
            $stmt->execute([
                isset($data['est_forfait']) ? (int)(bool)$data['est_forfait'] : 0,
                isset($data['type_client_requis']) && $data['type_client_requis'] !== ''
                    ? trim($data['type_client_requis']) : null,
                isset($data['categorie_motifs']) && $data['categorie_motifs'] !== ''
                    ? trim($data['categorie_motifs']) : null,
                isset($data['actif']) ? (int)(bool)$data['actif'] : 1,
                $id,
            ]);

            return ['success' => true, 'modifie' => $stmt->rowCount() > 0];

        } catch (PDOException $e) {
            error_log("TypeContratLocationControleur::modifier - " . $e->getMessage());
            throw new Exception('Erreur lors de la modification du type de contrat : ' . $e->getMessage());
        }
    }

    // ── Validation ────────────────────────────────────────────────────────────

    private static function _valider(array $data): void
    {
        if (isset($data['type_client_requis'])
            && $data['type_client_requis'] !== ''
            && !in_array($data['type_client_requis'], ['therapeute'], true)) {
            throw new Exception('type_client_requis invalide.');
        }
    }
}
?>