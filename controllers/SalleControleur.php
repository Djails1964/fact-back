<?php
/**
 * SalleControleur.php
 * Couche SQL pour la table `salle`.
 * Méthodes statiques — appelé exclusivement depuis ServiceSalle.
 */
class SalleControleur
{
    // ── LECTURE ───────────────────────────────────────────────────────────────

    /**
     * Retourne toutes les salles (actives par défaut).
     */
    public static function lister(PDO $conn, bool $actifSeulement = true): array
    {
        try {
            $sql = "
                SELECT id, nom, id_service, actif, created_at, updated_at
                FROM salle
            ";
            if ($actifSeulement) {
                $sql .= " WHERE actif = 1";
            }
            $sql .= " ORDER BY nom ASC";

            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $salles = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Enrichir avec le nom du service (requêtes séparées pour éviter
            // tout problème si la table services a un schéma inattendu)
            foreach ($salles as &$salle) {
                $salle['nom_service'] = self::_getNomService($conn, $salle['id_service'] ?? null);
            }
            unset($salle);

            return $salles;

        } catch (PDOException $e) {
            error_log("SalleControleur::lister - " . $e->getMessage());
            throw new Exception('Erreur lors de la récupération des salles: ' . $e->getMessage());
        }
    }

    /**
     * Retourne une salle par son id.
     */
    public static function getById(PDO $conn, int $id): ?array
    {
        try {
            $stmt = $conn->prepare("
                SELECT id, nom, id_service, actif, created_at, updated_at
                FROM salle
                WHERE id = ?
            ");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) return null;

            $row['nom_service'] = self::_getNomService($conn, $row['id_service'] ?? null);
            return $row;

        } catch (PDOException $e) {
            error_log("SalleControleur::getById - " . $e->getMessage());
            throw new Exception('Erreur lors de la récupération de la salle: ' . $e->getMessage());
        }
    }

    /**
     * Retourne le booléen est_forfait depuis le type de contrat lié à un id_service.
     * Recherche via location_salle_contrat → type_contrat_location.
     * Utilisé par la génération de document depuis un loyer.
     */
    public static function getTypeDocumentByService(PDO $conn, int $idService): string
    {
        try {
            $stmt = $conn->prepare("
                SELECT tcl.est_forfait
                FROM location_salle_contrat lsc
                JOIN salle s ON s.id = lsc.id_salle AND s.id_service = ?
                JOIN type_contrat_location tcl ON tcl.id = lsc.id_type_contrat
                WHERE tcl.actif = 1
                LIMIT 1
            ");
            $stmt->execute([$idService]);
            $estForfait = $stmt->fetchColumn();
            if ($estForfait === false) return 'facture';
            return $estForfait ? 'confirmation' : 'facture';

        } catch (PDOException $e) {
            error_log("SalleControleur::getTypeDocumentByService - " . $e->getMessage());
            return 'facture';
        }
    }

    // ── ÉCRITURE ──────────────────────────────────────────────────────────────

    /**
     * Crée une nouvelle salle.
     * @param array $data ['nom', 'id_service'?]
     */
    public static function creer(PDO $conn, array $data): array
    {
        try {
            self::_valider($data, false);

            $stmt = $conn->prepare("
                INSERT INTO salle (nom, id_service)
                VALUES (?, ?)
            ");
            $stmt->execute([
                trim($data['nom']),
                isset($data['id_service']) && $data['id_service'] !== '' && $data['id_service'] !== null
                    ? (int)$data['id_service'] : null,
            ]);

            return ['success' => true, 'id' => (int)$conn->lastInsertId()];

        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                throw new Exception("Une salle nommée \"{$data['nom']}\" existe déjà.");
            }
            error_log("SalleControleur::creer - " . $e->getMessage());
            throw new Exception('Erreur lors de la création de la salle: ' . $e->getMessage());
        }
    }

    /**
     * Met à jour une salle existante.
     */
    public static function modifier(PDO $conn, int $id, array $data): array
    {
        try {
            self::_valider($data, true);

            $stmt = $conn->prepare("
                UPDATE salle SET
                    nom        = ?,
                    id_service = ?,
                    actif      = ?
                WHERE id = ?
            ");
            $stmt->execute([
                trim($data['nom']),
                isset($data['id_service']) && $data['id_service'] !== '' && $data['id_service'] !== null
                    ? (int)$data['id_service'] : null,
                isset($data['actif']) ? (int)(bool)$data['actif'] : 1,
                $id,
            ]);

            return ['success' => true, 'modifie' => $stmt->rowCount() > 0];

        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                throw new Exception("Une salle nommée \"{$data['nom']}\" existe déjà.");
            }
            error_log("SalleControleur::modifier - " . $e->getMessage());
            throw new Exception('Erreur lors de la modification de la salle: ' . $e->getMessage());
        }
    }

    /**
     * Supprime une salle si aucune location ne la référence.
     */
    public static function supprimer(PDO $conn, int $id): array
    {
        try {
            $stmt = $conn->prepare("
                SELECT COUNT(*) FROM location_salle_detail WHERE id_salle = ?
            ");
            $stmt->execute([$id]);
            $nbLocations = (int)$stmt->fetchColumn();

            if ($nbLocations > 0) {
                throw new Exception(
                    "Impossible de supprimer cette salle : {$nbLocations} location(s) y font référence."
                );
            }

            $stmt = $conn->prepare("DELETE FROM salle WHERE id = ?");
            $stmt->execute([$id]);

            return ['success' => true, 'supprime' => $stmt->rowCount() > 0];

        } catch (PDOException $e) {
            error_log("SalleControleur::supprimer - " . $e->getMessage());
            throw new Exception('Erreur lors de la suppression de la salle: ' . $e->getMessage());
        }
    }

    /**
     * Retourne le nom d'une salle par son id.
     * Utilisé par les services pour les logs d'activité.
     */
    public static function getNomSalle(PDO $conn, int $id): ?string
    {
        try {
            $stmt = $conn->prepare("SELECT nom FROM salle WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            $val = $stmt->fetchColumn();
            return $val ?: null;
        } catch (PDOException $e) {
            error_log("SalleControleur::getNomSalle - " . $e->getMessage());
            return null;
        }
    }

    // ── Helpers privés ────────────────────────────────────────────────────────

    /**
     * Récupère le nom_service depuis la table services.
     * Isolé dans un helper pour que l'absence de cette colonne ne bloque pas le reste.
     */
    private static function _getNomService(PDO $conn, ?int $idService): ?string
    {
        if (!$idService) return null;
        try {
            $stmt = $conn->prepare("SELECT nom AS nom_service FROM services WHERE id = ? LIMIT 1");
            $stmt->execute([$idService]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row['nom_service'] ?? null;
        } catch (PDOException $e) {
            error_log("SalleControleur::_getNomService - " . $e->getMessage());
            return null;
        }
    }

    private static function _valider(array $data, bool $update): void
    {
        if (!$update && empty($data['nom'])) {
            throw new Exception('Le nom de la salle est obligatoire.');
        }
        if (isset($data['nom']) && strlen(trim($data['nom'])) === 0) {
            throw new Exception('Le nom de la salle ne peut pas être vide.');
        }
    }
}
?>