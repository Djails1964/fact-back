<?php
/**
 * LoyerControleur.php - VERSION TABLE SÉPARÉE
 * 
 * Contrôleur pour la gestion des loyers
 * ✅ ARCHITECTURE: Table loyer dédiée (séparée de facture)
 * ✅ NUMÉROTATION: LOY-{id_client}-{seq} gérée en PHP
 */

require_once __DIR__ . '/../utils/helpers.php';

class LoyerControleur {

    /**
     * Liste tous les loyers
     * @param PDO $conn Connexion DB
     * @param array $filtres Filtres optionnels
     * @return array Liste des loyers
     */
    public static function listerLoyers($conn, $filtres = []) {
        try {
            $sql = "SELECT 
                id_loyer,
                numero_loyer,
                numero_sequence,
                id_client,
                prenom_client,
                nom_client,
                nom_complet_client,
                email_client,
                telephone_client,
                rue_client,
                numero_client,
                code_postal_client,
                localite_client,
                date_creation_loyer,
                periode_debut,
                periode_fin,
                duree_mois,
                motif,
                afficher_dates_paiement,
                description,
                montant_total           AS loyer_montant_total,
                montant_mensuel_moyen,
                statut                  AS loyer_statut,
                etat_paiement,
                montant_paye,
                montant_restant,
                pourcentage_paye,
                mois_payes,
                total_mois,
                montant_paye_total,
                solde_restant,
                date_creation,
                date_modification,
                createur_id,
                modificateur_id 
            FROM v_loyers_complets WHERE 1=1";
            $params = [];
            
            // Filtre par client
            if (!empty($filtres['id_client'])) {
                $sql .= " AND id_client = ?";
                $params[] = $filtres['id_client'];
            }
            
            // Filtre par année
            if (!empty($filtres['loyer_annee'])) {
                $sql .= " AND YEAR(periode_debut) = ?";
                $params[] = $filtres['loyer_annee'];
            }
            
            // Filtre par statut
            if (!empty($filtres['loyer_statut'])) {
                $sql .= " AND statut = ?";
                $params[] = $filtres['loyer_statut'];
            }
            
            $sql .= " ORDER BY periode_debut DESC, numero_sequence DESC";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur listing loyers: " . $e->getMessage());
            throw new Exception('Erreur lors de la récupération des loyers');
        }
    }

    /**
     * Récupère un loyer par son ID avec ses détails
     * @param PDO $conn Connexion DB
     * @param int $id_loyer ID du loyer
     * @return array Données du loyer
     */
    public static function getLoyerParId($conn, $id_loyer) {
        try {
            // Récupérer le loyer via la vue
            $sql = "SELECT 
                id_loyer,
                numero_loyer,
                numero_sequence,
                id_client,
                prenom_client,
                nom_client,
                nom_complet_client,
                email_client,
                telephone_client,
                rue_client,
                numero_client,
                code_postal_client,
                localite_client,
                date_creation_loyer,
                periode_debut,
                periode_fin,
                duree_mois,
                motif,
                afficher_dates_paiement,
                description,
                montant_total           AS loyer_montant_total,
                montant_mensuel_moyen,
                statut                  AS loyer_statut,
                etat_paiement,
                montant_paye,
                montant_restant,
                pourcentage_paye,
                mois_payes,
                total_mois,
                montant_paye_total,
                solde_restant,
                date_creation,
                date_modification,
                createur_id,
                modificateur_id 
            FROM v_loyers_complets WHERE id_loyer = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$id_loyer]);
            $loyer = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$loyer) {
                throw new Exception('Loyer non trouvé');
            }
            
            // Récupérer les détails mensuels
            // ✅ montant = montant DÛ ORIGINAL (ne jamais modifier ce champ lors d'un paiement)
            $sqlDetails = "SELECT
                                id              AS id_loyer_detail,
                                id_loyer,
                                mois            AS loyer_mois,
                                numero_mois     AS loyer_numero_mois,
                                annee           AS loyer_annee,
                                montant         AS loyer_detail_montant,
                                est_paye,
                                date_paiement,
                                date_creation,
                                date_modification
                            FROM loyer_detail
                            WHERE id_loyer = ?
                            ORDER BY annee ASC, numero_mois ASC";
            $stmtDetails = $conn->prepare($sqlDetails);
            $stmtDetails->execute([$id_loyer]);
            $details = $stmtDetails->fetchAll(PDO::FETCH_ASSOC);

            // ✅ Pour chaque détail, charger les paiements effectués
            // Permet au frontend de calculer le solde et d'afficher les versements
            $sqlPaiements = "SELECT
                                id_paiement,
                                date_paiement,
                                montant_paye,
                                methode_paiement,
                                commentaire,
                                numero_paiement,
                                statut
                             FROM paiement
                             WHERE id_loyer_detail = ?
                               AND statut != 'annule'
                             ORDER BY date_paiement ASC, id_paiement ASC";
            $stmtPaiements = $conn->prepare($sqlPaiements);

            foreach ($details as &$detail) {
                $stmtPaiements->execute([$detail['id_loyer_detail']]);
                $detail['paiements'] = $stmtPaiements->fetchAll(PDO::FETCH_ASSOC);
            }
            unset($detail); // casser la référence

            $loyer['montants_mensuels'] = $details;

            return $loyer;
        } catch (PDOException $e) {
            error_log("Erreur récupération loyer: " . $e->getMessage());
            throw new Exception('Erreur lors de la récupération du loyer');
        }
    }

    /**
     * Crée un nouveau loyer
     * ✅ La numérotation est gérée dans cette méthode (pas de fonction SQL)
     * 
     * @param PDO $conn Connexion DB
     * @param array $data Données du loyer
     * @return array Résultat avec id_loyer et numero_loyer
     */
    public static function ajouterLoyer($conn, $data) {
        try {
            error_log("LoyerControleur::ajouterLoyer - Données reçues: " . json_encode($data));
            // Validation
            if (empty($data['id_client']) || empty($data['periode_debut']) || empty($data['duree_mois'])) {
                throw new Exception('Données obligatoires manquantes (id_client, periode_debut, duree_mois)');
            }
            
            // ✅ Générer le numéro de loyer (numérotation par client)
            $numeroInfo = self::genererNumeroLoyer($conn, $data['id_client']);
            
            // Calculer la date de fin si pas fournie
            if (empty($data['periode_fin'])) {
                $dateDebut = new DateTime($data['periode_debut']);
                $dateFin = clone $dateDebut;
                $dateFin->modify('+' . $data['duree_mois'] . ' months');
                $dateFin->modify('-1 day');
                $data['periode_fin'] = $dateFin->format('Y-m-d');
            }
            
            // Insérer le loyer
            $afficher_dates = toTinyInt($data['afficher_dates_paiement'] ?? 0);

            $sql = "INSERT INTO loyer (
                        numero_loyer, numero_sequence, id_client,
                        date_creation_loyer, periode_debut, periode_fin, duree_mois,
                        motif, afficher_dates_paiement, description, montant_total, statut,
                        createur_id
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $success = $stmt->execute([
                $numeroInfo['numero'],
                $numeroInfo['sequence'],
                $data['id_client'],
                $data['date_creation_loyer'] ?? date('Y-m-d'),
                $data['periode_debut'],
                $data['periode_fin'],
                $data['duree_mois'],
                $data['motif'] ?? null,
                $afficher_dates,
                $data['description'] ?? null,
                $data['loyer_montant_total'],
                $data['loyer_statut'] ?? 'actif',
                $data['createur_id'] ?? null
            ]);
            
            if (!$success) {
                throw new Exception("Erreur lors de l'ajout du loyer");
            }
            
            $id_loyer = $conn->lastInsertId();
            
            // Ajouter les détails mensuels
            if (!empty($data['montants_mensuels']) && is_array($data['montants_mensuels'])) {
                self::ajouterMontantsMensuels($conn, $id_loyer, $data['montants_mensuels']);
            }
            
            return [
                'success' => true,
                'id_loyer' => $id_loyer,
                'numero_loyer' => $numeroInfo['numero'],
                'numero_sequence' => $numeroInfo['sequence'],
                'message' => 'Loyer créé avec succès'
            ];
        } catch (PDOException $e) {
            error_log("Erreur ajout loyer: " . $e->getMessage());
            throw new Exception("Erreur lors de l'ajout du loyer: " . $e->getMessage());
        }
    }

    /**
     * Modifie un loyer existant
     * @param PDO $conn Connexion DB
     * @param int $id_loyer ID du loyer
     * @param array $data Nouvelles données
     * @return array Résultat
     */
    public static function modifierLoyer($conn, $id_loyer, $data) {
        try {
            $afficher_dates_upd = toTinyInt($data['afficher_dates_paiement'] ?? 0);

            $sql = "UPDATE loyer SET
                        periode_debut = ?,
                        periode_fin = ?,
                        duree_mois = ?,
                        motif = ?,
                        afficher_dates_paiement = ?,
                        description = ?,
                        montant_total = ?,
                        statut = ?,
                        date_modification = CURRENT_TIMESTAMP,
                        modificateur_id = ?
                    WHERE id_loyer = ?";
            
            $stmt = $conn->prepare($sql);
            $success = $stmt->execute([
                $data['periode_debut'],
                $data['periode_fin'],
                $data['duree_mois'],
                $data['motif'] ?? null,
                $afficher_dates_upd,
                $data['description'] ?? null,
                $data['loyer_montant_total'],
                $data['loyer_statut'] ?? 'actif',
                $data['modificateur_id'] ?? null,
                $id_loyer
            ]);
            
            if (!$success || $stmt->rowCount() === 0) {
                throw new Exception('Erreur lors de la modification ou loyer non trouvé');
            }
            
            // Mettre à jour les détails mensuels si fournis
            if (isset($data['montants_mensuels']) && is_array($data['montants_mensuels'])) {
                // Supprimer les anciens
                $stmtDelete = $conn->prepare("DELETE FROM loyer_detail WHERE id_loyer = ?");
                $stmtDelete->execute([$id_loyer]);
                
                // Recréer les détails
                self::ajouterMontantsMensuels($conn, $id_loyer, $data['montants_mensuels']);
            }
            
            return [
                'success' => true,
                'message' => 'Loyer modifié avec succès'
            ];
        } catch (PDOException $e) {
            error_log("Erreur modification loyer: " . $e->getMessage());
            throw new Exception('Erreur lors de la modification du loyer');
        }
    }

    /**
     * Supprime un loyer
     * Les détails sont supprimés automatiquement (CASCADE)
     * 
     * @param PDO $conn Connexion DB
     * @param int $id_loyer ID du loyer
     * @return array Résultat
     */
    public static function supprimerLoyer($conn, $id_loyer) {
        try {
            $sql = "DELETE FROM loyer WHERE id_loyer = ?";
            $stmt = $conn->prepare($sql);
            $success = $stmt->execute([$id_loyer]);
            
            if (!$success || $stmt->rowCount() === 0) {
                throw new Exception('Erreur lors de la suppression ou loyer non trouvé');
            }
            
            return [
                'success' => true,
                'message' => 'Loyer supprimé avec succès'
            ];
        } catch (PDOException $e) {
            error_log("Erreur suppression loyer: " . $e->getMessage());
            throw new Exception('Erreur lors de la suppression du loyer');
        }
    }

    /**
     * ✅ GÉNÈRE LE PROCHAIN NUMÉRO DE LOYER POUR UN CLIENT
     * Format: LOY-{id_client}-{sequence}
     * Exemple: LOY-12-001, LOY-12-002, LOY-12-003
     * 
     * IMPORTANT: Cette méthode DOIT être appelée dans une transaction
     * pour garantir l'unicité des numéros en cas de concurrence
     * 
     * @param PDO $conn Connexion DB
     * @param int $id_client ID du client
     * @return array ['numero' => 'LOY-12-003', 'sequence' => 3]
     */
    public static function genererNumeroLoyer($conn, $id_client) {
        try {
            // Récupérer la dernière séquence pour ce client
            // FOR UPDATE = verrouillage en écriture (protection concurrence)
            $sql = "SELECT COALESCE(MAX(numero_sequence), 0) AS derniere_sequence 
                    FROM loyer 
                    WHERE id_client = ? 
                    FOR UPDATE";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([$id_client]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Calculer la prochaine séquence
            $prochaine_sequence = $result['derniere_sequence'] + 1;
            
            // Générer le numéro au format LOY-{client}-{seq}
            $numero = sprintf('LOY-%d-%03d', $id_client, $prochaine_sequence);
            
            return [
                'numero' => $numero,
                'sequence' => $prochaine_sequence
            ];
        } catch (PDOException $e) {
            error_log("Erreur génération numéro loyer: " . $e->getMessage());
            throw new Exception('Erreur lors de la génération du numéro de loyer');
        }
    }

    /**
     * Insère les montants mensuels dans loyer_detail
     * @param PDO $conn Connexion DB
     * @param int $id_loyer ID du loyer
     * @param array $montantsMensuels Tableau des montants
     */
    private static function ajouterMontantsMensuels($conn, $id_loyer, $montantsMensuels) {
        try {
            $sql = "INSERT INTO loyer_detail (
                        id_loyer, mois, numero_mois, annee, montant, est_paye, date_paiement
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            
            foreach ($montantsMensuels as $detail) {
                $stmt->execute([
                    $id_loyer,
                    $detail['loyer_mois'],
                    $detail['loyer_numero_mois'],
                    $detail['loyer_annee'] ?? date('Y'),
                    $detail['loyer_detail_montant'],
                    toTinyInt($detail['est_paye'] ?? false),
                    $detail['date_paiement'] ?? null
                ]);
            }
        } catch (PDOException $e) {
            error_log("Erreur ajout montants mensuels: " . $e->getMessage());
            throw new Exception("Erreur lors de l'ajout des montants mensuels");
        }
    }

    /**
     * Vérifie si un numéro de loyer existe déjà
     * @param PDO $conn Connexion DB
     * @param string $numero_loyer Numéro à vérifier
     * @param int|null $id_loyer_exclure ID à exclure (pour modification)
     * @return bool true si existe
     */
    public static function numeroLoyerExiste($conn, $numero_loyer, $id_loyer_exclure = null) {
        try {
            $sql = "SELECT COUNT(*) AS count FROM loyer WHERE numero_loyer = ?";
            $params = [$numero_loyer];
            
            if ($id_loyer_exclure !== null) {
                $sql .= " AND id_loyer != ?";
                $params[] = $id_loyer_exclure;
            }
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result['count'] > 0;
        } catch (PDOException $e) {
            error_log("Erreur vérification numéro: " . $e->getMessage());
            return false;
        }
    }

    // =========================================================================
    // GÉNÉRATION PDF CONFIRMATION DE PAIEMENT
    // =========================================================================

    /**
     * Génère le PDF de confirmation de paiement pour un loyer.
     * Utilise FPDILoyerConfirmationGenerator (étend AbstractPDFGenerator / PDFBase).
     *
     * @param PDO   $conn
     * @param int   $id_loyer
     * @param array $params  Clés optionnelles : banque, signature
     * @return array { success, pdf_url, message }
     */
    public static function genererConfirmationPDF($conn, $id_loyer, $params = []) {
        try {
            error_log("LoyerControleur::genererConfirmationPDF - ID loyer: $id_loyer, params: " . json_encode($params));
            // ── 1. Charger le loyer + client ──────────────────────────────────
            $sql = "SELECT
                        l.id_loyer, l.numero_loyer, l.motif, l.description,
                        l.periode_debut, l.periode_fin, l.duree_mois,
                        l.montant_total as loyer_montant_total, l.etat_paiement, l.date_creation_loyer,
                        c.id AS id_client, c.titre, c.prenom, c.nom,
                        c.rue, c.numero, c.code_postal, c.localite
                    FROM loyer l
                    JOIN client c ON c.id = l.id_client
                    WHERE l.id_loyer = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$id_loyer]);
            $loyer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$loyer) {
                throw new Exception("Loyer #$id_loyer introuvable");
            }

            // ── 2. Charger les détails mensuels avec montant réellement payé ──
            $sqlDetails = "SELECT
                               ld.id AS id_loyer_detail,
                               ld.mois as loyer_mois,
                               ld.numero_mois as loyer_numero_mois,
                               ld.annee as loyer_annee,
                               ld.montant as loyer_detail_montant,
                               ld.est_paye,
                               ld.date_paiement,
                               COALESCE(
                                   (SELECT SUM(p.montant_paye)
                                    FROM paiement p
                                    WHERE p.id_loyer_detail = ld.id
                                      AND p.statut = 'confirme'),
                                   0
                               ) AS montant_paye
                           FROM loyer_detail ld
                           WHERE ld.id_loyer = ?
                           ORDER BY ld.numero_mois ASC";
            $stmtD = $conn->prepare($sqlDetails);
            $stmtD->execute([$id_loyer]);
            $loyer['details'] = $stmtD->fetchAll(PDO::FETCH_ASSOC);

            // ── 3. Date du document = aujourd'hui ─────────────────────────────
            $loyer['date_document'] = date('Y-m-d');

            // ── 4. Paramètres bancaires et signature ──────────────────────────
            $banque = $params['banque'] ?? [
                'Banque'       => 'BCV 1001 Lausanne',
                'IBAN'         => 'CH 88 0076 7000 E536 2645 5',
                'Beneficiaire' => 'Johanna Cherbuin, Chemin du Châtelard 9, 1562 Corcelles-près-Payerne',
            ];
            $signature = $params['signature'] ?? [
                'Ligne 1' => 'Johanna Cherbuin',
                'Ligne 2' => 'Centre « La Grange »',
            ];

            // ── 5. Nom du fichier PDF ─────────────────────────────────────────
            $nomFichier = 'confirmation_' . normalizeForFilename($loyer['numero_loyer']) . '_' . date('Y-m-d') . '.pdf';

            // ── 6. Générer le PDF ─────────────────────────────────────────────
            require_once __DIR__ . '/../FPDILoyerConfirmationGenerator.php';
            $generator = new FPDILoyerConfirmationGenerator();
            $ok = $generator->genererPDF($loyer, $nomFichier, null, $banque, 0, $signature);

            if (!$ok) {
                throw new Exception('Échec de la génération du PDF de confirmation');
            }

            error_log("✅ Confirmation PDF générée : $nomFichier");

            return [
                'success' => true,
                'pdf_url' => 'storage/factures/' . $nomFichier,
                'message' => 'Confirmation de paiement générée avec succès',
            ];

        } catch (Exception $e) {
            error_log("❌ genererConfirmationPDF: " . $e->getMessage());
            throw $e;
        }
    }

    // =========================================================================
    // PAIEMENT D'UN MOIS DE LOYER
    // =========================================================================

    /**
     * ✅ Enregistre le paiement d'un mois de loyer (loyer_detail)
     *
     * Crée une ligne dans `paiement` avec id_loyer + id_loyer_detail,
     * met à jour loyer_detail (est_paye, date_paiement ou montant restant),
     * et recalcule le statut global du loyer.
     *
     * @param PDO   $conn     Connexion DB
     * @param int   $id_loyer ID du loyer parent
     * @param array $data     Corps de la requête :
     *   - id_client           (int)    obligatoire
     *   - id_loyer_detail     (int)    obligatoire
     *   - date_paiement       (string) YYYY-MM-DD, obligatoire
     *   - montant_paye        (float)  > 0, obligatoire
     *   - methode_paiement    (string) défaut 'virement'
     *   - commentaire         (string) optionnel
     *   - est_totalement_paye (bool)   optionnel (calculé automatiquement si absent)
     * @return array Résultat avec success, id_paiement, loyer_statut, est_paye, montant_restant
     */
    public static function payerDetail($conn, $id_loyer, $data) {

        // ── Validation des entrées ────────────────────────────────────────────
        if (empty($id_loyer)) {
            throw new Exception('ID loyer requis');
        }
        if (empty($data['id_client'])) {
            throw new Exception('ID client requis');
        }
        if (empty($data['id_loyer_detail'])) {
            throw new Exception('ID détail loyer requis');
        }
        if (empty($data['date_paiement'])) {
            throw new Exception('Date de paiement requise');
        }
        if (!isset($data['montant_paye']) || floatval($data['montant_paye']) <= 0) {
            throw new Exception('Montant payé invalide (doit être > 0)');
        }

        $datePaiement = DateTime::createFromFormat('Y-m-d', $data['date_paiement']);
        if (!$datePaiement) {
            throw new Exception('Format de date invalide (attendu : YYYY-MM-DD)');
        }

        $id_loyer_detail  = intval($data['id_loyer_detail']);
        $id_client        = intval($data['id_client']);
        $montant_paye     = round(floatval($data['montant_paye']), 2);
        $methode_paiement = $data['methode_paiement'] ?? 'virement';
        $commentaire      = $data['commentaire'] ?? null;

        try {
            $conn->beginTransaction();

            // ── 1. Vérifier que le détail appartient bien à ce loyer ─────────
            $sqlDetail = "SELECT ld.id as id_loyer_detail, ld.montant as loyer_detail_montant, ld.est_paye,
                                 l.id_client AS loyer_client_id
                          FROM loyer_detail ld
                          JOIN loyer l ON l.id_loyer = ld.id_loyer
                          WHERE ld.id = ?
                            AND ld.id_loyer = ?
                          FOR UPDATE";
            $stmtDetail = $conn->prepare($sqlDetail);
            $stmtDetail->execute([$id_loyer_detail, $id_loyer]);
            $detail = $stmtDetail->fetch(PDO::FETCH_ASSOC);

            if (!$detail) {
                throw new Exception("Détail loyer introuvable ou n'appartient pas à ce loyer");
            }

            // Vérifier cohérence client ↔ loyer
            if ((int)$detail['loyer_client_id'] !== $id_client) {
                throw new Exception("Le loyer n'appartient pas au client indiqué");
            }

            $montant_du = floatval($detail['loyer_detail_montant']);

            // ── 2. Calculer le total déjà payé sur ce détail ─────────────────
            $sqlDejaPayé = "SELECT COALESCE(SUM(montant_paye), 0) AS total_paye
                            FROM paiement
                            WHERE id_loyer_detail = ?
                              AND statut != 'annule'";
            $stmtDejaPayé = $conn->prepare($sqlDejaPayé);
            $stmtDejaPayé->execute([$id_loyer_detail]);
            $dejaPayé = floatval($stmtDejaPayé->fetch(PDO::FETCH_ASSOC)['total_paye']);

            $montant_du    = floatval($detail['loyer_detail_montant']); // montant DÛ ORIGINAL — ne jamais modifier
            $totalAprésPaiement = round($dejaPayé + $montant_paye, 2);

            // Vérifier que le paiement ne dépasse pas le solde restant
            $soldeRestant = round($montant_du - $dejaPayé, 2);
            if ($montant_paye > $soldeRestant + 0.005) {
                throw new Exception(
                    "Le montant payé ({$montant_paye} CHF) dépasse le solde restant ({$soldeRestant} CHF)"
                );
            }

            // ── 4. Calculer le numéro de paiement (séquence globale client) ──
            $sqlNumero = "SELECT COALESCE(MAX(numero_paiement), 0) + 1 AS prochain
                          FROM paiement WHERE id_client = ?";
            $stmtNumero = $conn->prepare($sqlNumero);
            $stmtNumero->execute([$id_client]);
            $numero_paiement = intval($stmtNumero->fetch(PDO::FETCH_ASSOC)['prochain']);

            // ── 5. Insérer le paiement ────────────────────────────────────────
            $sqlPaiement = "INSERT INTO paiement
                              (id_client, id_facture, id_loyer, id_loyer_detail,
                               date_paiement, montant_paye, methode_paiement,
                               commentaire, numero_paiement, statut)
                            VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, 'confirme')";
            $stmtPaiement = $conn->prepare($sqlPaiement);
            $stmtPaiement->execute([
                $id_client, $id_loyer, $id_loyer_detail,
                $data['date_paiement'], $montant_paye,
                $methode_paiement, $commentaire, $numero_paiement,
            ]);
            $id_paiement = $conn->lastInsertId();

            // ── 6. Mettre à jour loyer_detail ─────────────────────────────────
            // ⚠️ On ne modifie JAMAIS loyer_detail.montant (= montant dû original)
            // On met uniquement est_paye=1 quand le total des paiements couvre le montant dû
            $nouveauEstPaye = ($totalAprésPaiement >= $montant_du - 0.005) ? 1 : 0;

            if ($nouveauEstPaye) {
                $conn->prepare("UPDATE loyer_detail
                                SET est_paye = 1, date_paiement = ?
                                WHERE id = ?")
                     ->execute([$data['date_paiement'], $id_loyer_detail]);
            } else {
                // Paiement partiel : juste enregistrer la date du dernier versement
                $conn->prepare("UPDATE loyer_detail
                                SET date_paiement = ?
                                WHERE id = ?")
                     ->execute([$data['date_paiement'], $id_loyer_detail]);
            }

            $montant_restant = round($montant_du - $totalAprésPaiement, 2);

            // ── 5. Recalculer le statut global du loyer ───────────────────────
            $nouveauStatut = self::_recalculerStatutLoyer($conn, $id_loyer);

            $conn->commit();

            error_log("✅ payerDetail: loyer={$id_loyer} detail={$id_loyer_detail} "
                . "montant={$montant_paye} estPaye={$nouveauEstPaye} statut={$nouveauStatut}");

            return [
                'success'              => true,
                'message'              => 'Paiement enregistré avec succès',
                'id_paiement'          => $id_paiement,
                'numero_paiement'      => $numero_paiement,
                'loyer_etat_paiement'  => $nouveauStatut,
                'est_paye'             => (bool)$nouveauEstPaye,
                'montant_restant'      => $montant_restant,
            ];

        } catch (PDOException $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            error_log("Erreur SQL payerDetail: " . $e->getMessage());
            throw new Exception("Erreur lors de l'enregistrement du paiement : " . $e->getMessage());
        } catch (Exception $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            throw $e;
        }
    }

    // =========================================================================
    // MÉTHODES PRIVÉES
    // =========================================================================

    /**
     * Recalcule l'état de paiement du loyer en fonction des détails payés
     * Met à jour la colonne `etat_paiement` (et non `statut`) :
     *   non_paye (0 payé) → partiellement_paye (1..n-1) → paye (tous payés)
     *
     * @param PDO $conn     Connexion DB
     * @param int $id_loyer ID du loyer
     * @return string       Nouvel état ('non_paye' | 'partiellement_paye' | 'paye')
     */
    private static function _recalculerStatutLoyer($conn, $id_loyer) {
        $sql  = "SELECT COUNT(*) AS total, SUM(est_paye) AS nb_payes
                 FROM loyer_detail WHERE id_loyer = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$id_loyer]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);

        $total   = intval($r['total']);
        $nbPayes = intval($r['nb_payes']);

        // Valeurs conformes à ENUM('non_paye','partiellement_paye','paye')
        $etatPaiement = match(true) {
            $total === 0 || $nbPayes === 0 => 'non_paye',
            $nbPayes >= $total             => 'paye',
            default                        => 'partiellement_paye'
        };

        $conn->prepare("UPDATE loyer SET etat_paiement = ? WHERE id_loyer = ?")
             ->execute([$etatPaiement, $id_loyer]);

        return $etatPaiement;
    }
}