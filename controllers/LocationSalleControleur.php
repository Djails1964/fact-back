<?php
/**
 * LocationSalleControleur.php
 *
 * Contrôleur pour la gestion des locations de salle.
 * ✅ ARCHITECTURE : couche SQL uniquement — méthodes statiques
 * ✅ Appelé exclusivement depuis ServiceLocationSalle (jamais directement depuis l'API)
 *
 * STRUCTURE DB (maître / détail) :
 *   location_salle_contrat  — un enregistrement par (id_client, annee)
 *   location_salle_detail   — un enregistrement par (id_contrat, mois, salle, id_unite)
 */

class LocationSalleControleur
{
    // ──────────────────────────────────────────────────────────────────────────
    // CONTRATS (table maître)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Liste tous les contrats d'une année donnée.
     * Un contrat = un client présent dans le tableau de l'année.
     *
     * @param PDO  $conn
     * @param int  $annee
     * @return array  [['id_contrat', 'id_client', 'nom_client', 'annee'], ...]
     */
    public static function listerContrats(PDO $conn, int $annee): array
    {
        try {
            $sql = "
                SELECT
                    lsc.id                              AS id_contrat,
                    lsc.id_client,
                    CONCAT(c.prenom, ' ', c.nom)        AS nom_client,
                    lsc.annee,
                    lsc.created_at,
                    lsc.updated_at
                FROM location_salle_contrat lsc
                JOIN client c ON c.id = lsc.id_client
                WHERE lsc.annee = ?
                ORDER BY c.nom ASC, c.prenom ASC
            ";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$annee]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("LocationSalleControleur::listerContrats - " . $e->getMessage());
            throw new Exception('Erreur lors de la récupération des contrats de location');
        }
    }

    /**
     * Crée un contrat (ajoute un client au tableau d'une année).
     * Idempotent : si le contrat existe déjà, retourne son id sans erreur.
     *
     * @param PDO      $conn
     * @param int      $id_client
     * @param int      $annee
     * @param int|null $userId
     * @return array  ['success' => true, 'id' => int, 'created' => bool]
     */
    public static function creerContrat(PDO $conn, int $id_client, int $annee, ?int $userId = null): array
    {
        try {
            $stmt = $conn->prepare("
                INSERT INTO location_salle_contrat
                    (id_client, annee, created_by, updated_by)
                VALUES
                    (?, ?, ?, ?)
            ");
            $stmt->execute([$id_client, $annee, $userId, $userId]);

            return [
                'success' => true,
                'id'      => (int) $conn->lastInsertId(),
                'created' => true,
                'message' => 'Contrat ajouté',
            ];

        } catch (PDOException $e) {
            error_log("LocationSalleControleur::creerContrat - " . $e->getMessage());
            throw new Exception("Erreur lors de l'ajout du contrat");
        }
    }

    /**
     * Supprime un contrat et tous ses détails (CASCADE en DB).
     *
     * @param PDO $conn
     * @param int $idContrat
     * @return array
     */
    public static function supprimerContrat(PDO $conn, int $idContrat): array
    {
        try {
            $stmt = $conn->prepare("DELETE FROM location_salle_contrat WHERE id = ?");
            $stmt->execute([$idContrat]);

            if ($stmt->rowCount() === 0) {
                throw new Exception('Contrat introuvable (id=' . $idContrat . ')');
            }

            return [
                'success' => true,
                'message' => 'Client retiré du tableau (contrat et détails supprimés)',
            ];

        } catch (PDOException $e) {
            error_log("LocationSalleControleur::supprimerContrat - " . $e->getMessage());
            throw new Exception('Erreur lors de la suppression du contrat');
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // DÉTAILS (table détail)
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Liste tous les détails de location d'une année.
     *
     * @param PDO   $conn
     * @param int   $annee
     * @param array $filtres  ['id_client' => int]  (optionnel)
     * @return array
     */
    public static function listerDetails(PDO $conn, int $annee, array $filtres = []): array
    {
        try {
            $sql = "
                SELECT
                    lsd.id,
                    lsd.id_contrat,
                    lsc.id_client,
                    CONCAT(c.prenom, ' ', c.nom)    AS nom_client,
                    lsc.annee,
                    lsd.mois,
                    lsd.salle,
                    lsd.id_unite,
                    lsd.id_service,
                    lsd.motif,
                    lsd.description,
                    lsd.dates,
                    lsd.quantite,
                    lsd.duree,
                    lsd.nb_seances,
                    u.nom                          AS nom_unite,
                    u.code                         AS code_unite,
                    u.abreviation                  AS abreviation_unite,
                    u.permet_multiplicateur,
                    lsd.created_at,
                    lsd.updated_at,
                    lsd.created_by,
                    lsd.updated_by
                FROM location_salle_detail lsd
                JOIN location_salle_contrat lsc ON lsc.id = lsd.id_contrat
                JOIN client c ON c.id = lsc.id_client
                LEFT JOIN unites u ON u.id = lsd.id_unite
                WHERE lsc.annee = ?
            ";
            $params = [$annee];

            if (!empty($filtres['id_client'])) {
                $sql .= " AND lsc.id_client = ?";
                $params[] = (int) $filtres['id_client'];
            }

            $sql .= " ORDER BY c.nom ASC, c.prenom ASC, lsd.mois ASC";

            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("LocationSalleControleur::listerDetails - " . $e->getMessage());
            throw new Exception('Erreur lors de la récupération des détails de location');
        }
    }

    /**
     * Récupère un détail par son identifiant.
     *
     * @param PDO $conn
     * @param int $id
     * @return array
     */
    public static function getDetailParId(PDO $conn, int $id): array
    {
        try {
            $sql = "
                SELECT
                    lsd.id,
                    lsd.id_contrat,
                    lsc.id_client,
                    CONCAT(c.prenom, ' ', c.nom)    AS nom_client,
                    lsc.annee,
                    lsd.mois,
                    lsd.salle,
                    lsd.id_unite,
                    lsd.id_service,
                    lsd.motif,
                    lsd.description,
                    lsd.dates,
                    lsd.quantite,
                    lsd.duree,
                    lsd.nb_seances,
                    u.nom                          AS nom_unite,
                    u.code                         AS code_unite,
                    u.abreviation                  AS abreviation_unite,
                    u.permet_multiplicateur,
                    lsd.created_at,
                    lsd.updated_at,
                    lsd.created_by,
                    lsd.updated_by
                FROM location_salle_detail lsd
                JOIN location_salle_contrat lsc ON lsc.id = lsd.id_contrat
                JOIN client c ON c.id = lsc.id_client
                LEFT JOIN unites u ON u.id = lsd.id_unite
                WHERE lsd.id = ?
            ";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                throw new Exception("Détail de location introuvable (id=$id)");
            }
            return $row;

        } catch (PDOException $e) {
            error_log("LocationSalleControleur::getDetailParId - " . $e->getMessage());
            throw new Exception('Erreur lors de la récupération du détail de location');
        }
    }

    /**
     * Insère un nouveau détail de location.
     * Le contrat maître doit déjà exister.
     *
     * @param PDO   $conn
     * @param array $data  ['id_contrat', 'mois', 'salle', 'id_unite', 'id_service', 'motif', 'quantite', 'note?', 'created_by?']
     * @return array
     */
    public static function ajouterDetail(PDO $conn, array $data): array
    {
        try {
            self::validerDetail($data);

            $stmt = $conn->prepare("
                INSERT INTO location_salle_detail
                    (id_contrat, mois, salle, id_unite, id_service, motif, description, dates, quantite, duree, nb_seances, created_by, updated_by)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                (int)   $data['id_contrat'],
                (int)   $data['mois'],
                        $data['salle'],
                        isset($data['id_unite'])   ? (int)$data['id_unite']   : null,
                        isset($data['id_service']) ? (int)$data['id_service'] : null,
                        $data['motif']       ?? null,
                        $data['description'] ?? null,
                        isset($data['dates']) && is_array($data['dates'])
                            ? json_encode($data['dates'])
                            : ($data['dates'] ?? null),
                (float) $data['quantite'],
                        $data['duree']      ?? null,
                        isset($data['nb_seances']) ? (int)$data['nb_seances'] : null,
                        $data['created_by'] ?? null,
                        $data['updated_by'] ?? null,
            ]);

            return [
                'success' => true,
                'id'      => (int) $conn->lastInsertId(),
                'message' => 'Location de salle créée avec succès',
            ];

        } catch (PDOException $e) {
            error_log("LocationSalleControleur::ajouterDetail - " . $e->getMessage());
            if ($e->getCode() === '23000') {
                throw new Exception(
                    'Une location existe déjà pour ce mois, cette salle et ce type de location'
                );
            }
            throw new Exception("Erreur lors de la création de la location : " . $e->getMessage());
        }
    }

    /**
     * Modifie un détail de location existant.
     *
     * @param PDO   $conn
     * @param int   $id
     * @param array $data
     * @return array
     */
    public static function modifierDetail(PDO $conn, int $id, array $data): array
    {
        try {
            error_log("LocationSalleControleur::modifierDetail - Tentative de modification du détail id=$id avec data: " . json_encode($data));
            self::validerDetail($data);

            $stmt = $conn->prepare("
                UPDATE location_salle_detail SET
                    mois          = ?,
                    salle         = ?,
                    id_unite      = ?,
                    id_service    = ?,
                    motif         = ?,
                    description   = ?,
                    dates         = ?,
                    quantite      = ?,
                    duree         = ?,
                    nb_seances    = ?,
                    updated_by    = ?
                WHERE id = ?
            ");
            $stmt->execute([
                (int)   $data['mois'],
                        $data['salle'],
                        isset($data['id_unite'])   ? (int)$data['id_unite']   : null,
                        isset($data['id_service']) ? (int)$data['id_service'] : null,
                        $data['motif']       ?? null,
                        $data['description'] ?? null,
                        isset($data['dates']) && is_array($data['dates'])
                            ? json_encode($data['dates'])
                            : ($data['dates'] ?? null),
                (float) $data['quantite'],
                        $data['duree']      ?? null,
                        isset($data['nb_seances']) ? (int)$data['nb_seances'] : null,
                        $data['updated_by'] ?? null,
                $id,
            ]);

            // rowCount() === 0 peut signifier "aucune valeur modifiée" (navigation sans changement)
            // On vérifie plutôt que l'enregistrement existe
            if ($stmt->rowCount() === 0) {
                $check = $conn->prepare('SELECT id FROM location_salle_detail WHERE id = ?');
                $check->execute([$id]);
                if (!$check->fetch()) {
                    throw new Exception('Détail introuvable');
                }
            }

            return [
                'success' => true,
                'id'      => $id,
                'message' => 'Location de salle modifiée avec succès',
            ];

        } catch (PDOException $e) {
            error_log("LocationSalleControleur::modifierDetail - " . $e->getMessage());
            if ($e->getCode() === '23000') {
                throw new Exception(
                    'Une location existe déjà pour ce mois, cette salle et ce type de location'
                );
            }
            throw new Exception("Erreur lors de la modification de la location : " . $e->getMessage());
        }
    }

    /**
     * Supprime un détail de location.
     *
     * @param PDO $conn
     * @param int $id
     * @return array
     */
    public static function supprimerDetail(PDO $conn, int $id): array
    {
        try {
            $stmt = $conn->prepare("DELETE FROM location_salle_detail WHERE id = ?");
            $stmt->execute([$id]);

            if ($stmt->rowCount() === 0) {
                throw new Exception('Détail introuvable (id=' . $id . ')');
            }

            return [
                'success' => true,
                'message' => 'Location de salle supprimée avec succès',
            ];

        } catch (PDOException $e) {
            error_log("LocationSalleControleur::supprimerDetail - " . $e->getMessage());
            throw new Exception("Erreur lors de la suppression de la location : " . $e->getMessage());
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // SALLES DISPONIBLES
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Récupère la liste des salles disponibles depuis la table parametres.
     *
     * @param PDO $conn
     * @return array  [['id' => int, 'nom' => string], ...]
     */
    /**
     * Récupère la liste des salles disponibles depuis la table parametres.
     * Structure attendue :
     *   groupe=LocationSalle / sous_groupe=Salles / categorie=<NomSalle>
     *     nom_parametre=label       → valeur = nom affiché de la salle
     *     nom_parametre=nom_service → valeur = nom du service tarifaire associé
     *
     * @param PDO $conn
     * @return array  [['nom' => string, 'nom_service' => string|null], ...]
     */
    public static function getSallesDisponibles(PDO $conn): array
    {
        try {
            $stmt = $conn->prepare("
                SELECT categorie, nom_parametre, valeur_parametre
                FROM parametres
                WHERE groupe_parametre      = 'LocationSalle'
                  AND sous_groupe_parametre = 'Salles'
                ORDER BY categorie, nom_parametre ASC
            ");
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // ── Nouvelle structure : catégorie = nom de la salle, nom_parametre = label|nom_service
            $nouvelles = array_filter($rows, fn($r) => in_array($r['nom_parametre'], ['label', 'nom_service', 'type_client_requis'], true));

            if (!empty($nouvelles)) {
                $salles = [];
                foreach ($rows as $row) {
                    $cat = $row['categorie'];
                    if (!isset($salles[$cat])) {
                        $salles[$cat] = ['nom' => $cat, 'nom_service' => null, 'type_client_requis' => null];
                    }
                    if ($row['nom_parametre'] === 'label') {
                        $salles[$cat]['nom'] = $row['valeur_parametre'];
                    }
                    if ($row['nom_parametre'] === 'nom_service') {
                        $salles[$cat]['nom_service'] = $row['valeur_parametre'];
                    }
                    if ($row['nom_parametre'] === 'type_client_requis') {
                        $salles[$cat]['type_client_requis'] = $row['valeur_parametre'];
                    }
                }
                $result = array_values($salles);
                return !empty($result) ? $result : self::_fallbackSalles();
            }

            // ── Ancienne structure : nom_parametre = 'Salle 1', valeur_parametre = 'Cabinet'
            $anciennes = array_filter($rows, fn($r) => str_starts_with($r['nom_parametre'], 'Salle '));
            if (!empty($anciennes)) {
                return array_values(array_map(
                    fn($r) => ['nom' => $r['valeur_parametre'], 'nom_service' => null],
                    $anciennes
                ));
            }

            return self::_fallbackSalles();

        } catch (PDOException $e) {
            error_log("LocationSalleControleur::getSallesDisponibles - " . $e->getMessage());
            return self::_fallbackSalles();
        }
    }

    private static function _fallbackSalles(): array
    {
        return [
            ['nom' => 'Cabinet', 'nom_service' => null, 'type_client_requis' => 'therapeute'],
            ['nom' => 'Salle',   'nom_service' => null, 'type_client_requis' => null],
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // VALIDATION INTERNE
    // ──────────────────────────────────────────────────────────────────────────

    private static function validerDetail(array $data): void
    {
        if (empty($data['id_contrat'])) {
            throw new Exception('Le contrat de référence est obligatoire');
        }
        if (empty($data['mois']) || (int)$data['mois'] < 1 || (int)$data['mois'] > 12) {
            throw new Exception('Mois invalide (doit être entre 1 et 12)');
        }
        if (empty($data['salle'])) {
            throw new Exception('La salle est obligatoire');
        }
        if (empty($data['id_unite'])) {
            throw new Exception("L'unité tarifaire (id_unite) est obligatoire");
        }
        if (!isset($data['quantite']) || (float)$data['quantite'] <= 0) {
            throw new Exception('La quantité doit être supérieure à 0');
        }
    }
}