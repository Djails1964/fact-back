<?php
/**
 * PaiementControleur.php
 * 
 * Contrôleur pour la gestion des paiements multiples
 * Fonctionnalités : enregistrement, modification, suppression, historique
 * ✅ REFACTORISÉ : Ajout de méthodes helper pour le logging
 */

class PaiementControleur {
    
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
    public static function getPaiementInfoPourLog($conn, $id_paiement) {
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
    public static function getFactureInfoPourLog($conn, $id_facture) {
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
    
    // ========================================
    // MÉTHODES CRUD PRINCIPALES
    // ========================================
    
    /**
     * Enregistre un nouveau paiement pour une facture
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int $factureId ID de la facture
     * @param array $data Données du paiement
     * @return array Résultat de l'opération
     * @throws Exception En cas d'erreur
     */
    public static function enregistrerPaiement($conn, $id_facture, $data) {
        try {
            // Validation des données
            if (!isset($data['date_paiement']) || !isset($data['montant_paye']) || !isset($data['methode_paiement'])) {
                throw new Exception('Données de paiement incomplètes');
            }
            
            $montant_paye = floatval($data['montant_paye']);
            if ($montant_paye <= 0) {
                throw new Exception('Le montant payé doit être positif');
            }
            
            // Vérifier que la facture existe et récupérer ses informations
            $sqlFacture = "SELECT id_facture, montant_total, ristourne, montant_paye_total, etat 
                          FROM facture WHERE id_facture = ?";
            $stmtFacture = $conn->prepare($sqlFacture);
            $stmtFacture->execute([$id_facture]);
            $facture = $stmtFacture->fetch(PDO::FETCH_ASSOC);
            
            if (!$facture) {
                throw new Exception('Facture non trouvée');
            }
            
            // Calculer le montant restant à payer
            $montant_total = floatval($facture['montant_total']) - floatval($facture['ristourne']);
            $montant_deja_paye = floatval($facture['montant_paye_total']);
            $montant_restants = $montant_total - $montant_deja_paye;
            
            // Vérifier que le paiement ne dépasse pas le montant restant
            if ($montant_paye > $montant_restants + 0.01) { // +0.01 pour les erreurs d'arrondi
                throw new Exception("Le montant payé ({$montant_paye} CHF) dépasse le montant restant à payer ({$montant_restants} CHF)");
            }
            
            // Déterminer le numéro de paiement
            $sqlNumeroPaiement = "SELECT COALESCE(MAX(numero_paiement), 0) + 1 as prochain_numero 
                                 FROM paiement WHERE id_facture = ?";
            $stmtNumero = $conn->prepare($sqlNumeroPaiement);
            $stmtNumero->execute([$id_facture]);
            $numero_paiement = $stmtNumero->fetch(PDO::FETCH_ASSOC)['prochain_numero'];
            
            // Insérer le paiement
            $sqlPaiement = "INSERT INTO paiement 
                           (id_facture, date_paiement, montant_paye, methode_paiement, commentaire, numero_paiement) 
                           VALUES (?, ?, ?, ?, ?, ?)";
            $stmtPaiement = $conn->prepare($sqlPaiement);
            $stmtPaiement->execute([
                $id_facture,
                $data['date_paiement'],
                $montant_paye,
                $data['methode_paiement'],
                $data['commentaire'] ?? null,
                $numero_paiement
            ]);
            
            $id_paiement = $conn->lastInsertId();
            
            // Les triggers se chargent automatiquement de la mise à jour de la facture
            
            return [
                'success' => true,
                'message' => 'Paiement enregistré avec succès',
                'id_paiement' => $id_paiement,
                'numero_paiement' => $numero_paiement
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
     * @param int $paiementId ID du paiement
     * @return array Informations du paiement
     * @throws Exception En cas d'erreur
     */
    public static function getPaiement($conn, $id_paiement) {
        try {
            error_log("Récupération du paiement avec ID: " . $id_paiement);
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
                $sql .= " AND c.id = ?";
                $params[] = $options['id_client'];
            }
            
            if (!empty($options['facture_id'])) {
                $sql .= " AND f.id_facture = ?";
                $params[] = $options['facture_id'];
            }
            
            // Tri par défaut
            $sql .= " ORDER BY p.date_paiement DESC, p.id_paiement DESC";
            
            // Pagination
            $page = isset($options['page']) ? max(1, intval($options['page'])) : 1;
            $limit = isset($options['limit']) ? max(1, intval($options['limit'])) : 50;
            $offset = ($page - 1) * $limit;
            
            // Compter le total avant pagination
            $sqlCount = "SELECT COUNT(*) as total FROM paiement p
                        JOIN facture f ON p.id_facture = f.id_facture
                        JOIN client c ON f.id_client = c.id
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
                $sqlCount .= " AND c.id = ?";
                $countParams[] = $options['id_client'];
            }
            if (!empty($options['facture_id'])) {
                $sqlCount .= " AND f.id_facture = ?";
                $countParams[] = $options['facture_id'];
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
     * @param int $factureId ID de la facture
     * @return array Liste des paiements de la facture
     * @throws Exception En cas d'erreur
     */
    public static function getHistoriquePaiements($conn, $id_facture) {
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
    public static function getStatistiquesGlobales($conn, $annee = null) {
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
     * @param int $paiementId ID du paiement à annuler
     * @param string $motif Motif de l'annulation
     * @return array Résultat de l'opération
     * @throws Exception En cas d'erreur
     */
    public static function annulerPaiement($conn, $id_paiement, $motif_annulation = null) {
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
            
            // Mettre à jour la facture (recalculer les totaux)
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
     * @param int $paiementId ID du paiement à supprimer
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
     * @param int $paiementId ID du paiement à modifier
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
            
            // Les triggers se chargent automatiquement de la mise à jour de la facture
            
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
?>