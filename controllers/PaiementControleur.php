<?php
/**
 * PaiementControleur.php
 * 
 * Contrôleur pour la gestion des paiements multiples
 * Fonctionnalités : enregistrement, modification, suppression, historique
 */

class PaiementControleur {
    
    /**
     * Enregistre un nouveau paiement pour une facture
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int $factureId ID de la facture
     * @param array $data Données du paiement
     * @return array Résultat de l'opération
     * @throws Exception En cas d'erreur
     */
    public static function enregistrerPaiement($conn, $factureId, $data) {
        try {
            // Validation des données
            if (!isset($data['datePaiement']) || !isset($data['montantPaye']) || !isset($data['methodePaiement'])) {
                throw new Exception('Données de paiement incomplètes');
            }
            
            $montantPaye = floatval($data['montantPaye']);
            if ($montantPaye <= 0) {
                throw new Exception('Le montant payé doit être positif');
            }
            
            // Vérifier que la facture existe et récupérer ses informations
            $sqlFacture = "SELECT id_facture, montant_total, ristourne, montant_paye_total, etat 
                          FROM facture WHERE id_facture = ?";
            $stmtFacture = $conn->prepare($sqlFacture);
            $stmtFacture->execute([$factureId]);
            $facture = $stmtFacture->fetch(PDO::FETCH_ASSOC);
            
            if (!$facture) {
                throw new Exception('Facture non trouvée');
            }
            
            // Calculer le montant restant à payer
            $montantTotal = floatval($facture['montant_total']) - floatval($facture['ristourne']);
            $montantDejaPaye = floatval($facture['montant_paye_total']);
            $montantRestant = $montantTotal - $montantDejaPaye;
            
            // Vérifier que le paiement ne dépasse pas le montant restant
            if ($montantPaye > $montantRestant + 0.01) { // +0.01 pour les erreurs d'arrondi
                throw new Exception("Le montant payé ({$montantPaye} CHF) dépasse le montant restant à payer ({$montantRestant} CHF)");
            }
            
            // Déterminer le numéro de paiement
            $sqlNumeroPaiement = "SELECT COALESCE(MAX(numero_paiement), 0) + 1 as prochain_numero 
                                 FROM paiement WHERE id_facture = ?";
            $stmtNumero = $conn->prepare($sqlNumeroPaiement);
            $stmtNumero->execute([$factureId]);
            $numeroPaiement = $stmtNumero->fetch(PDO::FETCH_ASSOC)['prochain_numero'];
            
            // Insérer le paiement
            $sqlPaiement = "INSERT INTO paiement 
                           (id_facture, date_paiement, montant_paye, methode_paiement, commentaire, numero_paiement) 
                           VALUES (?, ?, ?, ?, ?, ?)";
            $stmtPaiement = $conn->prepare($sqlPaiement);
            $stmtPaiement->execute([
                $factureId,
                $data['datePaiement'],
                $montantPaye,
                $data['methodePaiement'],
                $data['commentaire'] ?? null,
                $numeroPaiement
            ]);
            
            $id_paiement = $conn->lastInsertId();
            
            // Les triggers se chargent automatiquement de la mise à jour de la facture
            
            return [
                'success' => true,
                'message' => 'Paiement enregistré avec succès',
                'paiementId' => $id_paiement,
                'numeroPaiement' => $numeroPaiement
            ];
            
        } catch (PDOException $e) {
            error_log("Erreur SQL lors de l'enregistrement du paiement: " . $e->getMessage());
            throw new Exception('Erreur lors de l\'enregistrement du paiement: ' . $e->getMessage());
        }
    }
    
    /**
     * Récupère un paiement spécifique par son ID
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int $id_paiement ID du paiement
     * @return array Informations du paiement
     * @throws Exception En cas d'erreur
     */
    public static function getPaiement($conn, $id_paiement) {
        try {
            $sql = "SELECT p.*, 
                           f.numero_facture, f.montant_total, f.ristourne,
                           CONCAT(c.prenom, ' ', c.nom) as nom_client,
                           c.id as id_client
                    FROM paiement p
                    JOIN facture f ON p.id_facture = f.id_facture
                    JOIN client c ON f.id_client = c.id
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
    public static function listerPaiements($conn, $options = []) {
        try {
            // Construction de la requête de base
            $sql = "SELECT p.id_paiement, p.date_paiement, p.montant_paye, p.methode_paiement, 
                        p.commentaire, p.numero_paiement, p.date_creation, p.statut,
                        p.date_annulation, p.motif_annulation,
                        f.id_facture, f.numero_facture, f.montant_total, f.ristourne,
                        CONCAT(c.prenom, ' ', c.nom) as nom_client,
                        c.id as id_client
                    FROM paiement p
                    JOIN facture f ON p.id_facture = f.id_facture
                    JOIN client c ON f.id_client = c.id
                    WHERE 1=1"; // Inclure tous les paiements (confirmés et annulés)
            
            $params = [];
            
            // Filtrage par statut (optionnel)
            if (!empty($options['statut'])) {
                $sql .= " AND p.statut = ?";
                $params[] = $options['statut'];

                // ✅ DEBUG: Log pour vérifier le filtrage
                if (is_dev_mode()) {
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
            
            if (!empty($options['client_id'])) {
                $sql .= " AND c.id = ?";
                $params[] = $options['client_id'];
            }
            
            if (!empty($options['facture_id'])) {
                $sql .= " AND f.id_facture = ?";
                $params[] = $options['facture_id'];
            }
            
            // Tri par défaut : plus récents en premier
            $sql .= " ORDER BY p.date_paiement DESC, p.date_creation DESC";
            
            // Pagination
            $page = intval($options['page'] ?? 1);
            $limit = intval($options['limit'] ?? 50);
            $offset = ($page - 1) * $limit;
            
            // Compter le total
            $countSql = "SELECT COUNT(*) as total FROM (" . 
                    str_replace("SELECT p.id_paiement, p.date_paiement, p.montant_paye, p.methode_paiement, p.commentaire, p.numero_paiement, p.date_creation, p.statut, p.date_annulation, p.motif_annulation, f.id_facture, f.numero_facture, f.montant_total, f.ristourne, CONCAT(c.prenom, ' ', c.nom) as nom_client, c.id as id_client", "SELECT 1", $sql) . 
                    ") as count_query";
            $countStmt = $conn->prepare($countSql);
            $countStmt->execute($params);
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Ajouter la limitation
            $sql .= " LIMIT " . $limit . " OFFSET " . $offset;
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $paiements = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculer les métadonnées de pagination
            $totalPages = ceil($total / $limit);
            
            return [
                'success' => true,
                'paiements' => $paiements,
                'pagination' => [
                    'page_actuelle' => $page,
                    'total_pages' => $totalPages,
                    'total_elements' => $total,
                    'elements_par_page' => $limit,
                    'a_page_precedente' => $page > 1,
                    'a_page_suivante' => $page < $totalPages
                ]
            ];
            
        } catch (PDOException $e) {
            error_log("Erreur SQL lors de la récupération des paiements: " . $e->getMessage());
            throw new Exception('Erreur lors de la récupération des paiements');
        }
    }
    
    /**
     * Récupère les statistiques globales des paiements
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int|null $annee Année pour filtrer les statistiques
     * @return array Statistiques complètes
     * @throws Exception En cas d'erreur
     */
    public static function getStatistiquesGlobales($conn, $annee = null) {
        try {
            $whereClause = "WHERE p.statut = 'confirme'";
            $params = [];
            
            if ($annee) {
                $whereClause .= " AND YEAR(p.date_paiement) = ?";
                $params[] = $annee;
            }
            
            // Statistiques générales
            $sqlStats = "SELECT 
                            COUNT(*) as total_paiements,
                            SUM(p.montant_paye) as montant_total_paye,
                            AVG(p.montant_paye) as montant_moyen,
                            MIN(p.montant_paye) as montant_min,
                            MAX(p.montant_paye) as montant_max,
                            COUNT(DISTINCT p.id_facture) as nombre_factures_payees
                         FROM paiement p
                         $whereClause";
            
            $stmt = $conn->prepare($sqlStats);
            $stmt->execute($params);
            $statsGenerales = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Répartition par méthode de paiement
            $sqlMethodes = "SELECT 
                               p.methode_paiement,
                               COUNT(*) as nombre_paiements,
                               SUM(p.montant_paye) as montant_total
                            FROM paiement p
                            $whereClause
                            GROUP BY p.methode_paiement
                            ORDER BY montant_total DESC";
            
            $stmt = $conn->prepare($sqlMethodes);
            $stmt->execute($params);
            $repartitionMethodes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Évolution mensuelle
            $sqlMensuel = "SELECT 
                              YEAR(p.date_paiement) as annee,
                              MONTH(p.date_paiement) as mois,
                              COUNT(*) as nombre_paiements,
                              SUM(p.montant_paye) as montant_total
                           FROM paiement p
                           $whereClause
                           GROUP BY YEAR(p.date_paiement), MONTH(p.date_paiement)
                           ORDER BY annee, mois";
            
            $stmt = $conn->prepare($sqlMensuel);
            $stmt->execute($params);
            $evolutionMensuelle = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'statistiques' => [
                    'generales' => $statsGenerales,
                    'repartition_methodes' => $repartitionMethodes,
                    'evolution_mensuelle' => $evolutionMensuelle,
                    'annee_filtree' => $annee
                ]
            ];
            
        } catch (PDOException $e) {
            error_log("Erreur SQL lors de la récupération des statistiques: " . $e->getMessage());
            throw new Exception('Erreur lors de la récupération des statistiques');
        }
    }
    
    /**
     * Récupère l'historique des paiements d'une facture
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int $factureId ID de la facture
     * @return array Liste des paiements
     * @throws Exception En cas d'erreur
     */
    // public static function getHistoriquePaiements($conn, $factureId) {
    //     try {
    //         $sql = "SELECT p.*, f.numero_facture, f.montant_total, f.ristourne
    //                 FROM paiement p
    //                 JOIN facture f ON p.id_facture = f.id_facture
    //                 WHERE p.id_facture = ?
    //                 ORDER BY p.numero_paiement ASC, p.date_creation ASC";
            
    //         $stmt = $conn->prepare($sql);
    //         $stmt->execute([$factureId]);
    //         $paiements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    //         error_log('PaiementControleur - getHistoriquePaiements - paiements:'. json_encode($paiements));
            
    //         return [
    //             'success' => true,
    //             'paiements' => $paiements
    //         ];
            
    //     } catch (PDOException $e) {
    //         error_log("Erreur SQL lors de la récupération de l'historique: " . $e->getMessage());
    //         throw new Exception('Erreur lors de la récupération de l\'historique des paiements');
    //     }
    // }

    public static function getHistoriquePaiements($conn, $factureId) {
        try {
            // ✅ AJOUT: Debug de l'ID facture reçu
            error_log("🔍 PaiementControleur - getHistoriquePaiements - ID facture reçu: " . var_export($factureId, true));
            error_log("🔍 PaiementControleur - getHistoriquePaiements - Type ID facture: " . gettype($factureId));
            
            $sql = "SELECT p.*, f.numero_facture, f.montant_total, f.ristourne
                    FROM paiement p
                    JOIN facture f ON p.id_facture = f.id_facture
                    WHERE p.id_facture = ?
                    ORDER BY p.numero_paiement ASC, p.date_creation ASC";
            
            // ✅ AJOUT: Debug de la requête SQL
            error_log("🔍 PaiementControleur - SQL à exécuter: " . $sql);
            error_log("🔍 PaiementControleur - Paramètre: " . var_export($factureId, true));
            
            $stmt = $conn->prepare($sql);
            
            // ✅ AJOUT: Vérification de la préparation
            if (!$stmt) {
                error_log("❌ Erreur lors de la préparation de la requête SQL");
                throw new Exception('Erreur lors de la préparation de la requête SQL');
            }
            
            // ✅ AJOUT: Debug avant exécution
            error_log("🔍 PaiementControleur - Exécution de la requête avec paramètre: " . var_export($factureId, true));
            
            $executeResult = $stmt->execute([$factureId]);
            
            // ✅ AJOUT: Vérification de l'exécution
            if (!$executeResult) {
                error_log("❌ Erreur lors de l'exécution de la requête SQL");
                error_log("❌ Erreur PDO: " . json_encode($stmt->errorInfo()));
                throw new Exception('Erreur lors de l\'exécution de la requête SQL');
            }
            
            // ✅ AJOUT: Debug du nombre de lignes trouvées
            $rowCount = $stmt->rowCount();
            error_log("🔍 PaiementControleur - Nombre de lignes trouvées: " . $rowCount);
            
            $paiements = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // ✅ AJOUT: Debug détaillé des résultats
            error_log("🔍 PaiementControleur - Nombre de paiements récupérés: " . count($paiements));
            
            if (count($paiements) > 0) {
                error_log("🔍 PaiementControleur - Premier paiement (structure): " . json_encode($paiements[0], JSON_PRETTY_PRINT));
                error_log("🔍 PaiementControleur - Clés disponibles: " . json_encode(array_keys($paiements[0])));
                
                // ✅ AJOUT: Vérifier les ID facture dans les résultats
                $idsFacture = array_unique(array_column($paiements, 'id_facture'));
                error_log("🔍 PaiementControleur - IDs facture trouvés dans les résultats: " . json_encode($idsFacture));
            } else {
                error_log("📭 PaiementControleur - Aucun paiement trouvé pour la facture ID: " . $factureId);
                
                // ✅ AJOUT: Vérification si la facture existe
                $checkSql = "SELECT COUNT(*) as count FROM facture WHERE id_facture = ?";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->execute([$factureId]);
                $factureExists = $checkStmt->fetch(PDO::FETCH_ASSOC);
                error_log("🔍 PaiementControleur - La facture existe-t-elle? " . json_encode($factureExists));
                
                // ✅ AJOUT: Vérification s'il y a des paiements pour cette facture
                $paiementsSql = "SELECT COUNT(*) as count FROM paiement WHERE id_facture = ?";
                $paiementsStmt = $conn->prepare($paiementsSql);
                $paiementsStmt->execute([$factureId]);
                $paiementsCount = $paiementsStmt->fetch(PDO::FETCH_ASSOC);
                error_log("🔍 PaiementControleur - Nombre de paiements dans la table: " . json_encode($paiementsCount));
            }
            
            // ✅ AJOUT: Debug final avec tous les paiements (si pas trop nombreux)
            if (count($paiements) <= 10) {
                error_log("🔍 PaiementControleur - Tous les paiements: " . json_encode($paiements, JSON_PRETTY_PRINT));
            } else {
                error_log("🔍 PaiementControleur - Trop de paiements pour un log complet (" . count($paiements) . "), affichage des 3 premiers:");
                error_log("🔍 PaiementControleur - 3 premiers paiements: " . json_encode(array_slice($paiements, 0, 3), JSON_PRETTY_PRINT));
            }
            
            return [
                'success' => true,
                'paiements' => $paiements
            ];
            
        } catch (PDOException $e) {
            error_log("❌ Erreur SQL lors de la récupération de l'historique: " . $e->getMessage());
            error_log("❌ Code erreur SQL: " . $e->getCode());
            error_log("❌ Trace SQL: " . $e->getTraceAsString());
            throw new Exception('Erreur lors de la récupération de l\'historique des paiements: ' . $e->getMessage());
        } catch (Exception $e) {
            error_log("❌ Erreur générale lors de la récupération de l'historique: " . $e->getMessage());
            error_log("❌ Trace: " . $e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Annule un paiement (au lieu de le supprimer)
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int $id_paiement ID du paiement à annuler
     * @param string $motifAnnulation Motif de l'annulation
     * @return array Résultat de l'opération
     * @throws Exception En cas d'erreur
     */
    public static function annulerPaiement($conn, $id_paiement, $motifAnnulation = null) {
        try {
            // Vérifier que le paiement existe et n'est pas déjà annulé
            $sqlCheck = "SELECT id_paiement, id_facture, montant_paye, statut, numero_paiement 
                        FROM paiement 
                        WHERE id_paiement = ?";
            $stmtCheck = $conn->prepare($sqlCheck);
            $stmtCheck->execute([$id_paiement]);
            $paiement = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            
            if (!$paiement) {
                throw new Exception('Paiement non trouvé');
            }
            
            if ($paiement['statut'] === 'annule') {
                throw new Exception('Ce paiement est déjà annulé');
            }
            
            // Annuler le paiement (mettre à jour le statut et la date d'annulation)
            $sql = "UPDATE paiement 
                    SET statut = 'annule', 
                        date_annulation = NOW(),
                        motif_annulation = ?,
                        date_modification = NOW()
                    WHERE id_paiement = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$motifAnnulation, $id_paiement]);
            
            // Les triggers se chargent automatiquement de la mise à jour de la facture
            
            return [
                'success' => true,
                'message' => 'Paiement annulé avec succès',
                'paiementId' => $id_paiement,
                'factureId' => $paiement['id_facture'],
                'numeroPaiement' => $paiement['numero_paiement']
            ];
            
        } catch (PDOException $e) {
            error_log("Erreur SQL lors de l'annulation du paiement: " . $e->getMessage());
            throw new Exception('Erreur lors de l\'annulation du paiement: ' . $e->getMessage());
        }
    }
    
    /**
     * Supprime un paiement (annulation)
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int $id_paiement ID du paiement à supprimer
     * @return array Résultat de l'opération
     * @throws Exception En cas d'erreur
     */
    public static function supprimerPaiement($conn, $id_paiement) {
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
            
            return [
                'success' => true,
                'message' => 'Paiement supprimé avec succès',
                'factureId' => $paiement['id_facture']
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
     * @param int $factureId ID de la facture
     * @return array Statistiques de paiement
     * @throws Exception En cas d'erreur
     */
    public static function getStatistiquesPaiement($conn, $factureId) {
        try {
            $sql = "SELECT 
                        f.montant_total,
                        f.ristourne,
                        f.montant_paye_total,
                        f.montant_restant,
                        f.nb_paiements,
                        f.date_dernier_paiement,
                        (f.montant_total - f.ristourne) as montant_net,
                        CASE 
                            WHEN f.montant_restant <= 0 THEN 100
                            ELSE ROUND((f.montant_paye_total / (f.montant_total - f.ristourne)) * 100, 2)
                        END as pourcentage_paye
                    FROM facture f
                    WHERE f.id_facture = ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([$factureId]);
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
    public static function modifierPaiement($conn, $id_paiement, $data) {
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
            if (isset($data['montantPaye'])) {
                $nouveauMontant = floatval($data['montantPaye']);
                if ($nouveauMontant <= 0) {
                    throw new Exception('Le montant payé doit être positif');
                }
                
                // Vérifier que le nouveau montant ne dépasse pas le total possible
                $factureId = $paiement['id_facture'];
                $ancienMontant = floatval($paiement['ancien_montant']);
                
                $sqlFacture = "SELECT montant_total, ristourne, montant_paye_total FROM facture WHERE id_facture = ?";
                $stmtFacture = $conn->prepare($sqlFacture);
                $stmtFacture->execute([$factureId]);
                $facture = $stmtFacture->fetch(PDO::FETCH_ASSOC);
                
                $montantTotal = floatval($facture['montant_total']) - floatval($facture['ristourne']);
                $montantAutresPaiements = floatval($facture['montant_paye_total']) - $ancienMontant;
                $montantMaximal = $montantTotal - $montantAutresPaiements;
                
                if ($nouveauMontant > $montantMaximal + 0.01) {
                    throw new Exception("Le nouveau montant ({$nouveauMontant} CHF) dépasse le montant maximal possible ({$montantMaximal} CHF)");
                }
            }
            
            // Construire la requête de mise à jour
            $updateFields = [];
            $params = [];
            
            if (isset($data['datePaiement'])) {
                $updateFields[] = "date_paiement = ?";
                $params[] = $data['datePaiement'];
            }
            
            if (isset($data['montantPaye'])) {
                $updateFields[] = "montant_paye = ?";
                $params[] = $data['montantPaye'];
            }
            
            if (isset($data['methodePaiement'])) {
                $updateFields[] = "methode_paiement = ?";
                $params[] = $data['methodePaiement'];
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
            
            // Les triggers se chargent automatiquement de la mise à jour de la facture
            
            return [
                'success' => true,
                'message' => 'Paiement modifié avec succès',
                'paiementId' => $id_paiement
            ];
            
        } catch (PDOException $e) {
            error_log("Erreur SQL lors de la modification du paiement: " . $e->getMessage());
            throw new Exception('Erreur lors de la modification du paiement: ' . $e->getMessage());
        }
    }
}
?>