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
            
            $paiementId = $conn->lastInsertId();
            
            // Les triggers se chargent automatiquement de la mise à jour de la facture
            
            return [
                'success' => true,
                'message' => 'Paiement enregistré avec succès',
                'paiementId' => $paiementId,
                'numeroPaiement' => $numeroPaiement
            ];
            
        } catch (PDOException $e) {
            error_log("Erreur SQL lors de l'enregistrement du paiement: " . $e->getMessage());
            throw new Exception('Erreur lors de l\'enregistrement du paiement: ' . $e->getMessage());
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
    public static function getHistoriquePaiements($conn, $factureId) {
        try {
            $sql = "SELECT p.*, f.numero_facture, f.montant_total, f.ristourne
                    FROM paiement p
                    JOIN facture f ON p.id_facture = f.id_facture
                    WHERE p.id_facture = ? AND p.statut = 'confirme'
                    ORDER BY p.numero_paiement ASC, p.date_creation ASC";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([$factureId]);
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
     * Supprime un paiement (annulation)
     * 
     * @param PDO $conn La connexion à la base de données
     * @param int $paiementId ID du paiement à supprimer
     * @return array Résultat de l'opération
     * @throws Exception En cas d'erreur
     */
    public static function supprimerPaiement($conn, $paiementId) {
        try {
            // Vérifier que le paiement existe
            $sqlCheck = "SELECT id_paiement, id_facture, montant_paye FROM paiement WHERE id_paiement = ?";
            $stmtCheck = $conn->prepare($sqlCheck);
            $stmtCheck->execute([$paiementId]);
            $paiement = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            
            if (!$paiement) {
                throw new Exception('Paiement non trouvé');
            }
            
            // Supprimer le paiement (les triggers se chargent de la mise à jour de la facture)
            $sqlDelete = "DELETE FROM paiement WHERE id_paiement = ?";
            $stmtDelete = $conn->prepare($sqlDelete);
            $stmtDelete->execute([$paiementId]);
            
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
     * @param int $paiementId ID du paiement à modifier
     * @param array $data Nouvelles données du paiement
     * @return array Résultat de l'opération
     * @throws Exception En cas d'erreur
     */
    public static function modifierPaiement($conn, $paiementId, $data) {
        try {
            // Vérifier que le paiement existe
            $sqlCheck = "SELECT id_paiement, id_facture, montant_paye as ancien_montant FROM paiement WHERE id_paiement = ?";
            $stmtCheck = $conn->prepare($sqlCheck);
            $stmtCheck->execute([$paiementId]);
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
            
            $params[] = $paiementId;
            
            $sql = "UPDATE paiement SET " . implode(", ", $updateFields) . " WHERE id_paiement = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            
            // Les triggers se chargent automatiquement de la mise à jour de la facture
            
            return [
                'success' => true,
                'message' => 'Paiement modifié avec succès',
                'paiementId' => $paiementId
            ];
            
        } catch (PDOException $e) {
            error_log("Erreur SQL lors de la modification du paiement: " . $e->getMessage());
            throw new Exception('Erreur lors de la modification du paiement: ' . $e->getMessage());
        }
    }
}
?>