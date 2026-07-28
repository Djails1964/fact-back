<?php

/**
 * PaiementControleur.php
 * 
 * Contrôleur pour la gestion des paiements multiples
 * Fonctionnalités : enregistrement, modification, suppression, historique
 * ✅ REFACTORISÉ : Ajout de méthodes helper pour le logging
 */

class PaiementControleur
{
    
    // ========================================
    // MÉTHODES HELPER POUR LE LOGGING
    // ========================================

    /**
     * ✅ NOUVELLE : Récupère les informations d'un paiement pour le logging
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int $id_paiement ID du paiement
     * @return array|null Informations du paiement ou null si non trouvé
     */
    public static function getPaiementInfoPourLog($conn, $id_paiement)
    {
        try {
            $sql = "SELECT p.*, 
                           f.numero_facture, f.montant_total, f.ristourne,
                           CONCAT(c.prenom, ' ', c.nom) as nom_client
                    FROM paiement p
                    LEFT JOIN facture f ON p.id_facture = f.id_facture
                    JOIN client c ON p.id_client = c.id
                    WHERE p.id_paiement = ?";

            $stmt = $conn->prepare($sql);
            $stmt->execute([$id_paiement]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur SQL getPaiementInfoPourLog: " . $e->getMessage());
            return null;
        }
    }

    /**
     * ✅ NOUVELLE : Récupère les informations d'une facture pour le logging
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int $id_facture ID de la facture
     * @return array|null Informations de la facture ou null si non trouvée
     */
    public static function getFactureInfoPourLog($conn, $id_facture)
    {
        try {
            $sql = "SELECT f.numero_facture, f.montant_total, f.ristourne,
                           CONCAT(c.prenom, ' ', c.nom) as nom_client,
                           c.id as id_client
                    FROM facture f 
                    JOIN client c ON f.id_client = c.id 
                    WHERE f.id_facture = ?";

            $stmt = $conn->prepare($sql);
            $stmt->execute([$id_facture]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erreur SQL getFactureInfoPourLog: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Génère le prochain numéro de paiement pour un client
     * Simple incrémentation par client (1, 2, 3, 4...)
     * 
     * @param PDO $conn Connexion à la base de données
     * @param int $id_client ID du client
     * @return int Prochain numéro de paiement
     */
    private static function genererNumeroPaiement($conn, $id_client) {
        try {
            // Trouver le prochain numéro pour ce client
            $sql = "SELECT COALESCE(MAX(numero_paiement), 0) + 1 as prochain_numero
                    FROM paiement
                    WHERE id_client = ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([$id_client]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return (int)$result['prochain_numero'];
            
        } catch (PDOException $e) {
            error_log("Erreur génération numéro paiement: " . $e->getMessage());
            // Fallback : retourner 1
            return 1;
        }
    }
    
    // ========================================
    // MÉTHODES CRUD PRINCIPALES
    // ========================================

    /**
     * Enregistre un nouveau paiement pour une facture
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int $id_facture ID de la facture
     * @param array $data Données du paiement
     * @return array Résultat de l'opération
     * @throws Exception En cas d'erreur
     */
    public static function enregistrerPaiement($conn, $id_facture, $data) {
        try {
            // Validation des données de base
            if (!isset($data['date_paiement']) || !isset($data['montant_paye']) || !isset($data['methode_paiement'])) {
                throw new Exception('Données de paiement incomplètes');
            }

            // CLIENT-FIRST : id_client obligatoire
            $id_client = isset($data['id_client']) ? intval($data['id_client']) : null;
            if (!$id_client) {
                throw new Exception('Le client est obligatoire');
            }

            // Vérifier que le client existe
            $stmtClient = $conn->prepare("SELECT id as id_client FROM client WHERE id = ?");
            $stmtClient->execute([$id_client]);
            if (!$stmtClient->fetch()) {
                throw new Exception('Client non trouvé');
            }

            $montant_paye = floatval($data['montant_paye']);
            if ($montant_paye <= 0) {
                throw new Exception('Le montant payé doit être positif');
            }

            // id_facture est optionnel — réconcilier paramètre + body
            $id_facture = isset($data['id_facture']) && $data['id_facture'] !== ''
                ? intval($data['id_facture'])
                : ($id_facture ? intval($id_facture) : null);

            // ✅ NOUVEAU : Générer le numéro basé sur le CLIENT (pas la facture)
            $numero_paiement = self::genererNumeroPaiement($conn, $id_client);

            if ($id_facture) {
                // Vérifier que la facture existe
                $sqlFacture = "SELECT id_facture, montant_total, ristourne, montant_paye_total, id_client as facture_id_client 
                              FROM facture WHERE id_facture = ?";
                $stmtFacture = $conn->prepare($sqlFacture);
                $stmtFacture->execute([$id_facture]);
                $facture = $stmtFacture->fetch(PDO::FETCH_ASSOC);

                if (!$facture) {
                    throw new Exception('Facture non trouvée');
                }

                // Vérifier cohérence client ↔ facture
                if ((int)$facture['facture_id_client'] !== $id_client) {
                    throw new Exception('La facture n\'appartient pas au client sélectionné');
                }

                // Vérifier que le paiement ne dépasse pas le montant restant
                // ✅ montant_total est déjà net (montant_brut - ristourne, cf.
                // FactureControleur::modifierAttributsLimitesConfirmation et
                // le calcul équivalent pour les factures standard) — ne pas
                // resoustraire la ristourne ici, sous peine de la déduire deux
                // fois (bug : rejetait un paiement soldant exactement la
                // facture, à hauteur du montant de la ristourne).
                $montant_total   = floatval($facture['montant_total']);
                $montant_deja_paye = floatval($facture['montant_paye_total']);
                $montant_restant = $montant_total - $montant_deja_paye;

                if ($montant_paye > $montant_restant + 0.01) {
                    throw new Exception("Le montant payé ({$montant_paye} CHF) dépasse le montant restant à payer ({$montant_restant} CHF)");
                }

            }

            // ✅ Insérer le paiement (les champs loyer ont été retirés de la
            // table paiement — migration 042, plus de loyer intermédiaire)
            $sqlPaiement = "INSERT INTO paiement 
                           (id_client, id_facture,
                            date_paiement, montant_paye, methode_paiement, commentaire, numero_paiement, statut) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, 'confirme')";
            $stmtPaiement = $conn->prepare($sqlPaiement);
            $stmtPaiement->execute([
                $id_client,
                $id_facture,        // NULL si paiement libre
                $data['date_paiement'],
                $montant_paye,
                $data['methode_paiement'],
                $data['commentaire'] ?? null,
                $numero_paiement,
            ]);

            $id_paiement = $conn->lastInsertId();

            // Le trigger gère automatiquement les totaux/état de la facture
            // (montant_paye_total, montant_restant, etat). La cascade mensuelle
            // (confirmations de paiement) est recalculée explicitement ici : c'est
            // un algorithme à deux niveaux (mois × paiements) trop complexe pour
            // un trigger — ne fait rien si la facture n'a pas de détail mensuel
            // (facture standard à l'utilisation).
            if ($id_facture) {
                FactureControleur::recalculerCascadeMensuelle($conn, $id_facture);
            }

            return [
                'success'          => true,
                'message'          => 'Paiement enregistré avec succès',
                'id_paiement'      => $id_paiement,
                'numero_paiement'  => $numero_paiement,
            ];

        } catch (PDOException $e) {
            error_log("Erreur SQL lors de l'enregistrement du paiement: " . $e->getMessage());
            throw new Exception('Erreur lors de l\'enregistrement du paiement: ' . $e->getMessage());
        }
    }


    public static function getPaiement($conn, $id_paiement)
    {
        try {
            error_log("Récupération du paiement avec ID: " . $id_paiement);
            $sql = "SELECT p.*, 
                           f.numero_facture, f.montant_total, f.ristourne,
                           CONCAT(c.prenom, ' ', c.nom) as nom_client
                    FROM paiement p
                    LEFT JOIN facture f ON p.id_facture = f.id_facture
                    JOIN client c ON p.id_client = c.id
                    WHERE p.id_paiement = ?";

            $stmt = $conn->prepare($sql);
            $stmt->execute([$id_paiement]);
            $paiement = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$paiement) {
                throw new Exception('Paiement non trouvé');
            }

            return [
                'success' => true,
                'paiement' => $paiement
            ];
        } catch (PDOException $e) {
            error_log("Erreur SQL lors de la récupération du paiement: " . $e->getMessage());
            throw new Exception('Erreur lors de la récupération du paiement');
        }
    }

    /**
     * Récupère la liste des paiements avec filtrage et pagination
     * 
     * @param PDO $conn La connexion à la base de données
     * @param array $options Options de filtrage et pagination
     * @return array Liste des paiements avec pagination
     * @throws Exception En cas d'erreur
     */
    public static function listerPaiements($conn, $options = [])
    {
        try {
            // Construction de la requête de base
            // ✅ Colonnes/jointures loyer retirées (supprimées, migration 042)
            $sql = "SELECT p.id_paiement, p.date_paiement, p.montant_paye, p.methode_paiement, 
                        p.commentaire, p.numero_paiement, p.date_creation, p.statut,
                        p.date_annulation, p.motif_annulation,
                        p.id_client,
                        f.id_facture, f.numero_facture, f.montant_total, f.ristourne,
                        CONCAT(c.prenom, ' ', c.nom) as nom_client
                    FROM paiement p
                    LEFT JOIN facture f  ON p.id_facture    = f.id_facture
                    JOIN  client      c  ON p.id_client     = c.id
                    WHERE 1=1"; // Inclure tous les paiements (confirmés et annulés)

            $params = [];

            // Filtrage par statut (optionnel)
            if (!empty($options['statut'])) {
                $sql .= " AND p.statut = ?";
                $params[] = $options['statut'];

                // ✅ DEBUG: Log pour vérifier le filtrage
                if (function_exists('is_dev_mode') && is_dev_mode()) {
                    error_log("PaiementControleur - Filtrage par statut: " . $options['statut']);
                }
            }

            // Autres filtres existants...
            if (!empty($options['annee'])) {
                $sql .= " AND YEAR(p.date_paiement) = ?";
                $params[] = $options['annee'];
            }

            if (!empty($options['mois'])) {
                $sql .= " AND MONTH(p.date_paiement) = ?";
                $params[] = $options['mois'];
            }

            if (!empty($options['methode'])) {
                $sql .= " AND p.methode_paiement = ?";
                $params[] = $options['methode'];
            }

            if (!empty($options['id_client'])) {
                $sql .= " AND p.id_client = ?";
                $params[] = $options['id_client'];
            }

            // Paiements libres : non rattachés à une facture, avec solde > 0
            if (!empty($options['libre'])) {
                $sql .= " AND p.id_facture IS NULL AND p.montant_paye > 0";
            }

            if (!empty($options['id_facture'])) {
                $sql .= " AND f.id_facture = ?";
                $params[] = $options['id_facture'];
            }

            // Tri par défaut
            $sql .= " ORDER BY p.date_paiement DESC, p.id_paiement DESC";

            // Pagination
            $page = isset($options['page']) ? max(1, intval($options['page'])) : 1;
            $limit = isset($options['limit']) ? max(1, intval($options['limit'])) : 50;
            $offset = ($page - 1) * $limit;

            // Compter le total avant pagination
            $sqlCount = "SELECT COUNT(*) as total FROM paiement p
                        LEFT JOIN facture f ON p.id_facture = f.id_facture
                        JOIN  client      c ON p.id_client  = c.id
                        WHERE 1=1";

            // Reconstruire les conditions pour le count
            $countParams = [];
            if (!empty($options['statut'])) {
                $sqlCount .= " AND p.statut = ?";
                $countParams[] = $options['statut'];
            }
            if (!empty($options['annee'])) {
                $sqlCount .= " AND YEAR(p.date_paiement) = ?";
                $countParams[] = $options['annee'];
            }
            if (!empty($options['mois'])) {
                $sqlCount .= " AND MONTH(p.date_paiement) = ?";
                $countParams[] = $options['mois'];
            }
            if (!empty($options['methode'])) {
                $sqlCount .= " AND p.methode_paiement = ?";
                $countParams[] = $options['methode'];
            }
            if (!empty($options['id_client'])) {
                $sqlCount .= " AND p.id_client = ?";
                $countParams[] = $options['id_client'];
            }
            if (!empty($options['libre'])) {
                $sqlCount .= " AND p.id_facture IS NULL AND p.montant_paye > 0";
            }
            if (!empty($options['id_facture'])) {
                $sqlCount .= " AND f.id_facture = ?";
                $countParams[] = $options['id_facture'];
            }

            $stmtCount = $conn->prepare($sqlCount);
            $stmtCount->execute($countParams);
            $total = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];

            // Ajouter la limite
            $sql .= " LIMIT " . $limit . " OFFSET " . $offset;
            $stmt = $conn->prepare($sql);

            $stmt->execute($params);
            $paiements = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'paiements' => $paiements,
                'pagination' => [
                    'total' => intval($total),
                    'page' => $page,
                    'limit' => $limit,
                    'totalPages' => ceil($total / $limit)
                ]
            ];
        } catch (PDOException $e) {
            error_log("Erreur SQL lors de la liste des paiements: " . $e->getMessage());
            throw new Exception('Erreur lors de la récupération des paiements');
        }
    }

    /**
     * Récupère l'historique des paiements d'une facture
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int $id_facture ID de la facture
     * @return array Liste des paiements de la facture
     * @throws Exception En cas d'erreur
     */
    public static function getHistoriquePaiements($conn, $id_facture)
    {
        try {
            $sql = "SELECT p.id_paiement, p.date_paiement, p.montant_paye, p.methode_paiement, 
                           p.commentaire, p.numero_paiement, p.date_creation, p.statut,
                           p.date_annulation, p.motif_annulation,
                           f.numero_facture, f.montant_total, f.ristourne
                    FROM paiement p
                    JOIN facture f ON p.id_facture = f.id_facture
                    WHERE p.id_facture = ?
                    ORDER BY p.date_paiement DESC, p.numero_paiement DESC";

            $stmt = $conn->prepare($sql);
            $stmt->execute([$id_facture]);
            $paiements = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'paiements' => $paiements
            ];
        } catch (PDOException $e) {
            error_log("Erreur SQL lors de la récupération de l'historique: " . $e->getMessage());
            throw new Exception('Erreur lors de la récupération de l\'historique des paiements');
        }
    }

    /**
     * Récupère les statistiques globales des paiements
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int|null $annee Année pour filtrer
     * @return array Statistiques globales
     * @throws Exception En cas d'erreur
     */
    public static function getStatistiquesGlobales($conn, $annee = null)
    {
        try {
            $params = [];
            $whereAnnee = "";

            if ($annee) {
                $whereAnnee = " AND YEAR(p.date_paiement) = ?";
                $params[] = $annee;
            }

            // Statistiques générales
            $sqlStats = "SELECT 
                            COUNT(*) as nombre_total,
                            SUM(CASE WHEN p.statut = 'confirme' THEN 1 ELSE 0 END) as nombre_confirmes,
                            SUM(CASE WHEN p.statut = 'annule' THEN 1 ELSE 0 END) as nombre_annules,
                            SUM(CASE WHEN p.statut = 'confirme' THEN p.montant_paye ELSE 0 END) as montant_total,
                            AVG(CASE WHEN p.statut = 'confirme' THEN p.montant_paye ELSE NULL END) as montant_moyen
                         FROM paiement p
                         WHERE 1=1 {$whereAnnee}";

            $stmtStats = $conn->prepare($sqlStats);
            $stmtStats->execute($params);
            $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

            // Évolution mensuelle
            $sqlEvolution = "SELECT 
                                YEAR(p.date_paiement) as annee,
                                MONTH(p.date_paiement) as mois,
                                COUNT(*) as nombre,
                                SUM(CASE WHEN p.statut = 'confirme' THEN p.montant_paye ELSE 0 END) as montant
                             FROM paiement p
                             WHERE p.statut = 'confirme' {$whereAnnee}
                             GROUP BY YEAR(p.date_paiement), MONTH(p.date_paiement)
                             ORDER BY annee DESC, mois DESC";

            $stmtEvolution = $conn->prepare($sqlEvolution);
            $stmtEvolution->execute($params);
            $evolution = $stmtEvolution->fetchAll(PDO::FETCH_ASSOC);

            // Par méthode de paiement
            $sqlMethodes = "SELECT 
                               p.methode_paiement,
                               COUNT(*) as nombre,
                               SUM(p.montant_paye) as montant
                            FROM paiement p
                            WHERE p.statut = 'confirme' {$whereAnnee}
                            GROUP BY p.methode_paiement
                            ORDER BY montant DESC";

            $stmtMethodes = $conn->prepare($sqlMethodes);
            $stmtMethodes->execute($params);
            $parMethode = $stmtMethodes->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'statistiques' => [
                    'general' => $stats,
                    'evolution_mensuelle' => $evolution,
                    'par_methode' => $parMethode
                ]
            ];
        } catch (PDOException $e) {
            error_log("Erreur SQL statistiques globales: " . $e->getMessage());
            throw new Exception('Erreur lors de la récupération des statistiques');
        }
    }

    /**
     * Annule un paiement (soft delete)
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int $id_paiement ID du paiement à annuler
     * @param string $motif Motif de l'annulation
     * @return array Résultat de l'opération
     * @throws Exception En cas d'erreur
     */
    public static function annulerPaiement($conn, $id_paiement, $motif_annulation = null)
    {
        try {
            // Vérifier que le paiement existe et n'est pas déjà annulé
            $sqlCheck = "SELECT id_paiement, id_facture, montant_paye, statut, numero_paiement 
                        FROM paiement WHERE id_paiement = ?";
            $stmtCheck = $conn->prepare($sqlCheck);
            $stmtCheck->execute([$id_paiement]);
            $paiement = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if (!$paiement) {
                throw new Exception('Paiement non trouvé');
            }

            if ($paiement['statut'] === 'annule') {
                throw new Exception('Ce paiement est déjà annulé');
            }

            // Mettre à jour le statut
            $sqlUpdate = "UPDATE paiement SET 
                            statut = 'annule',
                            date_annulation = NOW(),
                            motif_annulation = ?
                          WHERE id_paiement = ?";
            $stmtUpdate = $conn->prepare($sqlUpdate);
            $stmtUpdate->execute([$motif_annulation, $id_paiement]);

            // Mettre à jour la facture SEULEMENT si une facture est liée
            if ($paiement['id_facture']) {
                $sqlUpdateFacture = "UPDATE facture f SET 
                                        montant_paye_total = (
                                            SELECT COALESCE(SUM(montant_paye), 0) 
                                            FROM paiement 
                                            WHERE id_facture = f.id_facture AND statut = 'confirme'
                                        ),
                                        nb_paiements = (
                                            SELECT COUNT(*) 
                                            FROM paiement 
                                            WHERE id_facture = f.id_facture AND statut = 'confirme'
                                        )
                                     WHERE id_facture = ?";
                $stmtUpdateFacture = $conn->prepare($sqlUpdateFacture);
                $stmtUpdateFacture->execute([$paiement['id_facture']]);

                // ✅ Recalculer la cascade mensuelle (confirmations de paiement)
                // — ne fait rien si la facture n'a pas de détail mensuel.
                FactureControleur::recalculerCascadeMensuelle($conn, $paiement['id_facture']);
            }

            return [
                'success' => true,
                'message' => 'Paiement annulé avec succès',
                'id_paiement' => $id_paiement,
                'id_facture' => $paiement['id_facture'],
                'numero_paiement' => $paiement['numero_paiement']
            ];
        } catch (PDOException $e) {
            error_log("Erreur SQL lors de l'annulation du paiement: " . $e->getMessage());
            throw new Exception('Erreur lors de l\'annulation du paiement: ' . $e->getMessage());
        }
    }

    /**
     * Supprime un paiement (hard delete)
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int $id_paiement ID du paiement à supprimer
     * @return array Résultat de l'opération
     * @throws Exception En cas d'erreur
     */
    public static function supprimerPaiement($conn, $id_paiement)
    {
        try {
            // Vérifier que le paiement existe
            $sqlCheck = "SELECT id_paiement, id_facture, montant_paye FROM paiement WHERE id_paiement = ?";
            $stmtCheck = $conn->prepare($sqlCheck);
            $stmtCheck->execute([$id_paiement]);
            $paiement = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if (!$paiement) {
                throw new Exception('Paiement non trouvé');
            }

            // Supprimer le paiement (les triggers se chargent de la mise à jour de la facture)
            $sqlDelete = "DELETE FROM paiement WHERE id_paiement = ?";
            $stmtDelete = $conn->prepare($sqlDelete);
            $stmtDelete->execute([$id_paiement]);

            // ✅ Recalculer la cascade mensuelle (confirmations de paiement)
            // — ne fait rien si la facture n'a pas de détail mensuel. Le
            // trigger AFTER DELETE a déjà recalculé les totaux de la facture.
            if ($paiement['id_facture']) {
                FactureControleur::recalculerCascadeMensuelle($conn, $paiement['id_facture']);
            }

            return [
                'success' => true,
                'message' => 'Paiement supprimé avec succès',
                'id_facture' => $paiement['id_facture']
            ];
        } catch (PDOException $e) {
            error_log("Erreur SQL lors de la suppression du paiement: " . $e->getMessage());
            throw new Exception('Erreur lors de la suppression du paiement: ' . $e->getMessage());
        }
    }

    /**
     * Récupère les statistiques de paiement d'une facture
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int $id_facture ID de la facture
     * @return array Statistiques de paiement
     * @throws Exception En cas d'erreur
     */
    public static function getStatistiquesPaiement($conn, $id_facture)
    {
        try {
            $sql = "SELECT 
                        f.montant_total,
                        f.ristourne,
                        f.montant_paye_total,
                        f.montant_restant,
                        f.nb_paiements,
                        f.date_dernier_paiement,
                        -- ✅ montant_total est déjà net (montant_brut - ristourne) —
                        -- ne pas resoustraire ristourne (même bug que ligne ~163).
                        f.montant_total as montant_net,
                        CASE 
                            WHEN f.montant_restant <= 0 THEN 100
                            ELSE ROUND((f.montant_paye_total / f.montant_total) * 100, 2)
                        END as pourcentage_paye
                    FROM facture f
                    WHERE f.id_facture = ?";

            $stmt = $conn->prepare($sql);
            $stmt->execute([$id_facture]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$stats) {
                throw new Exception('Facture non trouvée');
            }

            return [
                'success' => true,
                'statistiques' => $stats
            ];
        } catch (PDOException $e) {
            error_log("Erreur SQL lors de la récupération des statistiques: " . $e->getMessage());
            throw new Exception('Erreur lors de la récupération des statistiques de paiement');
        }
    }

    /**
     * Modifie un paiement existant
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int $id_paiement ID du paiement à modifier
     * @param array $data Nouvelles données du paiement
     * @return array Résultat de l'opération
     * @throws Exception En cas d'erreur
     */
    public static function modifierPaiement($conn, $id_paiement, $data)
    {
        try {
            // Vérifier que le paiement existe
            $sqlCheck = "SELECT id_paiement, id_facture, montant_paye as ancien_montant FROM paiement WHERE id_paiement = ?";
            $stmtCheck = $conn->prepare($sqlCheck);
            $stmtCheck->execute([$id_paiement]);
            $paiement = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if (!$paiement) {
                throw new Exception('Paiement non trouvé');
            }

            // Validation des nouvelles données
            if (isset($data['montant_paye'])) {
                $nouveauMontant = floatval($data['montant_paye']);
                if ($nouveauMontant <= 0) {
                    throw new Exception('Le montant payé doit être positif');
                }

                // Vérifier que le nouveau montant ne dépasse pas le total possible
                $id_facture = $paiement['id_facture'];
                $ancienMontant = floatval($paiement['ancien_montant']);

                $sqlFacture = "SELECT montant_total, ristourne, montant_paye_total FROM facture WHERE id_facture = ?";
                $stmtFacture = $conn->prepare($sqlFacture);
                $stmtFacture->execute([$id_facture]);
                $facture = $stmtFacture->fetch(PDO::FETCH_ASSOC);

                // ✅ montant_total est déjà net (voir enregistrerPaiement ci-dessus)
                $montantTotal = floatval($facture['montant_total']);
                $montantAutresPaiements = floatval($facture['montant_paye_total']) - $ancienMontant;
                $montantMaximal = $montantTotal - $montantAutresPaiements;

                if ($nouveauMontant > $montantMaximal + 0.01) {
                    throw new Exception("Le nouveau montant ({$nouveauMontant} CHF) dépasse le montant maximal possible ({$montantMaximal} CHF)");
                }
            }

            // Construire la requête de mise à jour
            $updateFields = [];
            $params = [];

            if (isset($data['date_paiement'])) {
                $updateFields[] = "date_paiement = ?";
                $params[] = $data['date_paiement'];
            }

            if (isset($data['montant_paye'])) {
                $updateFields[] = "montant_paye = ?";
                $params[] = $data['montant_paye'];
            }

            if (isset($data['methode_paiement'])) {
                $updateFields[] = "methode_paiement = ?";
                $params[] = $data['methode_paiement'];
            }

            if (isset($data['commentaire'])) {
                $updateFields[] = "commentaire = ?";
                $params[] = $data['commentaire'];
            }

            if (empty($updateFields)) {
                throw new Exception('Aucune donnée à mettre à jour');
            }

            $params[] = $id_paiement;

            $sql = "UPDATE paiement SET " . implode(", ", $updateFields) . " WHERE id_paiement = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);

            // Le trigger se charge automatiquement de la mise à jour des
            // totaux/état de la facture. La cascade mensuelle est recalculée
            // explicitement (montant_paye ou date_paiement ont pu changer,
            // ce qui affecte l'allocation FIFO) — ne fait rien si la facture
            // n'a pas de détail mensuel.
            if ($paiement['id_facture']) {
                FactureControleur::recalculerCascadeMensuelle($conn, $paiement['id_facture']);
            }

            return [
                'success' => true,
                'message' => 'Paiement modifié avec succès',
                'id_paiement' => $id_paiement
            ];
        } catch (PDOException $e) {
            error_log("Erreur SQL lors de la modification du paiement: " . $e->getMessage());
            throw new Exception('Erreur lors de la modification du paiement: ' . $e->getMessage());
        }
    }
}