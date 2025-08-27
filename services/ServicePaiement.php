<?php
/**
 * ServicePaiement.php - Version complète avec toutes les méthodes du contrôleur
 * Service pour la gestion des paiements avec logging complet
 */

require_once realpath(__DIR__ . '/../controllers/PaiementControleur.php');
require_once realpath(__DIR__ . '/ActivityLogger.php');
require_once realpath(__DIR__ . '/../constants/ActivityLogsConstants.php');

class ServicePaiement {
    private $conn;
    private $logger;
    
    public function __construct($conn) {
        $this->conn = $conn;
        $this->logger = new ActivityLogger($conn);
    }
    
    /**
     * Récupère les informations utilisateur depuis la session
     * @return array Informations utilisateur
     */
    private function getCurrentUser() {
        return [
            'id' => $_SESSION['user_id'] ?? null,
            'name' => $_SESSION['user_name'] ?? 'Système'
        ];
    }
    
    /**
     * Création d'un nouveau paiement
     * @param array $data Les données du paiement
     * @return array Résultat de l'opération
     */
    public function creerPaiement($data) {
        $user = $this->getCurrentUser();
        
        try {
            // Démarrer une transaction
            $this->conn->beginTransaction();
            
            // Validation des données obligatoires
            if (!isset($data['factureId']) || !isset($data['datePaiement']) || 
                !isset($data['montantPaye']) || !isset($data['methodePaiement'])) {
                throw new Exception('Données obligatoires manquantes');
            }
            
            // Récupérer les infos de la facture pour le log
            $sqlFacture = "SELECT f.numero_facture, f.montant_total, 
                                  CONCAT(c.prenom, ' ', c.nom) as nom_client
                           FROM facture f 
                           JOIN client c ON f.id_client = c.id 
                           WHERE f.id_facture = ?";
            $stmtFacture = $this->conn->prepare($sqlFacture);
            $stmtFacture->execute([$data['factureId']]);
            $factureInfo = $stmtFacture->fetch(PDO::FETCH_ASSOC);
            
            // Appeler le contrôleur pour enregistrer le paiement
            $resultat = PaiementControleur::enregistrerPaiement($this->conn, $data['factureId'], $data);
            
            if (!$resultat['success']) {
                throw new Exception($resultat['message']);
            }
            
            // Logging avec constantes
            $this->logger->log([
                'user_id' => $user['id'],
                'user_name' => $user['name'],
                'action_type' => ActivityLogsConstants::ACTION_PAIEMENT_CREATE,
                'entity_type' => ActivityLogsConstants::ENTITY_PAIEMENT,
                'entity_id' => $resultat['paiementId'],
                'description' => "Nouveau paiement #{$resultat['numeroPaiement']} pour la facture {$factureInfo['numero_facture']} ({$factureInfo['nom_client']})",
                'details' => [
                    'paiement_id' => $resultat['paiementId'],
                    'numero_paiement' => $resultat['numeroPaiement'],
                    'facture_id' => $data['factureId'],
                    'facture_numero' => $factureInfo['numero_facture'],
                    'client_nom' => $factureInfo['nom_client'],
                    'montant_paye' => floatval($data['montantPaye']),
                    'methode_paiement' => $data['methodePaiement'],
                    'date_paiement' => $data['datePaiement'],
                    'commentaire' => $data['commentaire'] ?? null
                ],
                'severity' => ActivityLogsConstants::SEVERITY_INFO
            ]);
            
            // Valider la transaction
            $this->conn->commit();
            
            return $resultat;
            
        } catch (Exception $e) {
            // Annuler la transaction en cas d'erreur
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            
            // Logging d'erreur avec constantes
            $this->logger->log([
                'user_id' => $user['id'],
                'user_name' => $user['name'],
                'action_type' => ActivityLogsConstants::ACTION_SYSTEM_ERROR,
                'entity_type' => ActivityLogsConstants::ENTITY_PAIEMENT,
                'entity_id' => $data['factureId'] ?? null,
                'description' => "Échec de création d'un paiement pour la facture ID {$data['factureId']}",
                'details' => [
                    'error_message' => $e->getMessage(),
                    'facture_id' => $data['factureId'] ?? null,
                    'montant_paye' => $data['montantPaye'] ?? null,
                    'methode_paiement' => $data['methodePaiement'] ?? null
                ],
                'severity' => ActivityLogsConstants::SEVERITY_ERROR
            ]);
            
            return [
                'success' => false,
                'message' => 'Erreur lors de la création du paiement: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Récupération d'un paiement spécifique
     * @param int $id ID du paiement
     * @return array Informations du paiement
     */
    public function getPaiement($id_paiement) {
        $user = $this->getCurrentUser();
        
        try {
            error_log('ServicePaiement - getPaiement - id:'. $id_paiement);
            $resultat = PaiementControleur::getPaiement($this->conn, $id_paiement);
            error_log('ServicePaiement - getPaiement - id:'. json_encode($resultat));
            return $resultat;
            
        } catch (Exception $e) {
            // Logging d'erreur uniquement avec constantes
            $this->logger->log([
                'user_id' => $user['id'],
                'user_name' => $user['name'],
                'action_type' => ActivityLogsConstants::ACTION_SYSTEM_ERROR,
                'entity_type' => ActivityLogsConstants::ENTITY_PAIEMENT,
                'entity_id' => $id_paiement,
                'description' => "Erreur lors de la consultation du paiement ID {$id_paiement}",
                'details' => [
                    'error_message' => $e->getMessage(),
                    'paiement_id' => $id_paiement
                ],
                'severity' => ActivityLogsConstants::SEVERITY_WARNING
            ]);
            
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération du paiement: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * ✅ NOUVELLE : Récupération de la liste complète des paiements avec filtrage
     * @param array $options Options de filtrage et pagination
     * @return array Liste des paiements avec pagination
     */
    public function listerPaiements($options = []) {
        $user = $this->getCurrentUser();
        
        try {
            $resultat = PaiementControleur::listerPaiements($this->conn, $options);
            return $resultat;
            
        } catch (Exception $e) {
            // Logging d'erreur uniquement avec constantes
            $this->logger->log([
                'user_id' => $user['id'],
                'user_name' => $user['name'],
                'action_type' => ActivityLogsConstants::ACTION_SYSTEM_ERROR,
                'entity_type' => ActivityLogsConstants::ENTITY_PAIEMENT,
                'description' => "Erreur lors de la consultation de la liste des paiements",
                'details' => [
                    'error_message' => $e->getMessage(),
                    'options' => $options
                ],
                'severity' => ActivityLogsConstants::SEVERITY_WARNING
            ]);
            
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération des paiements: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * ✅ NOUVELLE : Récupération des statistiques globales des paiements
     * @param int|null $annee Année pour filtrer les statistiques
     * @return array Statistiques complètes
     */
    public function getStatistiquesGlobales($annee = null) {
        $user = $this->getCurrentUser();
        
        try {
            $resultat = PaiementControleur::getStatistiquesGlobales($this->conn, $annee);
            return $resultat;
            
        } catch (Exception $e) {
            // Logging d'erreur uniquement avec constantes
            $this->logger->log([
                'user_id' => $user['id'],
                'user_name' => $user['name'],
                'action_type' => ActivityLogsConstants::ACTION_SYSTEM_ERROR,
                'entity_type' => ActivityLogsConstants::ENTITY_PAIEMENT,
                'description' => "Erreur lors de la consultation des statistiques des paiements",
                'details' => [
                    'error_message' => $e->getMessage(),
                    'annee_demandee' => $annee
                ],
                'severity' => ActivityLogsConstants::SEVERITY_WARNING
            ]);
            
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * ✅ NOUVELLE : Récupération de l'historique des paiements d'une facture
     * @param int $factureId ID de la facture
     * @return array Liste des paiements
     */
    public function getHistoriquePaiements($factureId) {
        $user = $this->getCurrentUser();
        error_log('getHistoriquePaiements - factureId: '. $factureId);
        
        try {
            $resultat = PaiementControleur::getHistoriquePaiements($this->conn, $factureId);
            return $resultat;
            
        } catch (Exception $e) {
            // Logging d'erreur uniquement avec constantes
            $this->logger->log([
                'user_id' => $user['id'],
                'user_name' => $user['name'],
                'action_type' => ActivityLogsConstants::ACTION_SYSTEM_ERROR,
                'entity_type' => ActivityLogsConstants::ENTITY_FACTURE,
                'entity_id' => $factureId,
                'description' => "Erreur lors de la consultation de l'historique des paiements de la facture ID {$factureId}",
                'details' => [
                    'error_message' => $e->getMessage(),
                    'facture_id' => $factureId
                ],
                'severity' => ActivityLogsConstants::SEVERITY_WARNING
            ]);
            
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération des paiements: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * ✅ EXISTANTE : Modification d'un paiement existant
     * @param int $id ID du paiement
     * @param array $data Les nouvelles données du paiement
     * @return array Résultat de l'opération
     */
    public function modifierPaiement($id, $data) {
        $user = $this->getCurrentUser();
        
        try {
            // Démarrer une transaction
            $this->conn->beginTransaction();
            
            // Récupérer les données actuelles du paiement pour comparaison
            $sqlPaiementActuel = "SELECT p.*, f.numero_facture, 
                                         CONCAT(c.prenom, ' ', c.nom) as nom_client
                                  FROM paiement p
                                  JOIN facture f ON p.id_facture = f.id_facture
                                  JOIN client c ON f.id_client = c.id
                                  WHERE p.id_paiement = ?";
            $stmtActuel = $this->conn->prepare($sqlPaiementActuel);
            $stmtActuel->execute([$id]);
            $paiementActuel = $stmtActuel->fetch(PDO::FETCH_ASSOC);
            
            if (!$paiementActuel) {
                throw new Exception('Paiement non trouvé');
            }
            
            // Identifier les changements
            $changes = [];
            if (isset($data['datePaiement']) && $data['datePaiement'] !== $paiementActuel['date_paiement']) {
                $changes['date_paiement'] = [
                    'ancien' => $paiementActuel['date_paiement'],
                    'nouveau' => $data['datePaiement']
                ];
            }
            if (isset($data['montantPaye']) && floatval($data['montantPaye']) !== floatval($paiementActuel['montant_paye'])) {
                $changes['montant_paye'] = [
                    'ancien' => floatval($paiementActuel['montant_paye']),
                    'nouveau' => floatval($data['montantPaye'])
                ];
            }
            if (isset($data['methodePaiement']) && $data['methodePaiement'] !== $paiementActuel['methode_paiement']) {
                $changes['methode_paiement'] = [
                    'ancien' => $paiementActuel['methode_paiement'],
                    'nouveau' => $data['methodePaiement']
                ];
            }
            if (isset($data['commentaire']) && $data['commentaire'] !== $paiementActuel['commentaire']) {
                $changes['commentaire'] = [
                    'ancien' => $paiementActuel['commentaire'],
                    'nouveau' => $data['commentaire']
                ];
            }
            
            // Appeler la méthode du contrôleur
            $resultat = PaiementControleur::modifierPaiement($this->conn, $id, $data);
            
            if (!$resultat['success']) {
                throw new Exception($resultat['message']);
            }
            
            // Logging avec constantes
            $this->logger->log([
                'user_id' => $user['id'],
                'user_name' => $user['name'],
                'action_type' => ActivityLogsConstants::ACTION_PAIEMENT_UPDATE,
                'entity_type' => ActivityLogsConstants::ENTITY_PAIEMENT,
                'entity_id' => $id,
                'description' => "Modification du paiement #{$paiementActuel['numero_paiement']} de la facture {$paiementActuel['numero_facture']} ({$paiementActuel['nom_client']})",
                'details' => [
                    'paiement_id' => $id,
                    'numero_paiement' => $paiementActuel['numero_paiement'],
                    'facture_numero' => $paiementActuel['numero_facture'],
                    'client_nom' => $paiementActuel['nom_client'],
                    'changes' => $changes,
                    'nb_changes' => count($changes)
                ],
                'severity' => ActivityLogsConstants::SEVERITY_INFO
            ]);
            
            // Valider la transaction
            $this->conn->commit();
            
            return $resultat;
            
        } catch (Exception $e) {
            // Annuler la transaction en cas d'erreur
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            
            // Logging d'erreur avec constantes
            $this->logger->log([
                'user_id' => $user['id'],
                'user_name' => $user['name'],
                'action_type' => ActivityLogsConstants::ACTION_SYSTEM_ERROR,
                'entity_type' => ActivityLogsConstants::ENTITY_PAIEMENT,
                'entity_id' => $id,
                'description' => "Échec de modification du paiement ID {$id}",
                'details' => [
                    'error_message' => $e->getMessage(),
                    'paiement_id' => $id,
                    'attempted_changes' => $data
                ],
                'severity' => ActivityLogsConstants::SEVERITY_ERROR
            ]);
            
            return [
                'success' => false,
                'message' => 'Erreur lors de la modification du paiement: ' . $e->getMessage()
            ];
        }
    }

    /**
     * ✅ EXISTANTE : Annulation d'un paiement (remplace la suppression)
     * @param int $id ID du paiement
     * @param string $motifAnnulation Motif de l'annulation
     * @return array Résultat de l'opération
     */
    public function annulerPaiement($id, $motifAnnulation = null) {
        $user = $this->getCurrentUser();
        
        try {
            // Démarrer une transaction
            $this->conn->beginTransaction();
            
            // Récupérer les informations du paiement avant annulation
            $sqlPaiement = "SELECT p.*, f.numero_facture, 
                                CONCAT(c.prenom, ' ', c.nom) as nom_client
                            FROM paiement p
                            JOIN facture f ON p.id_facture = f.id_facture
                            JOIN client c ON f.id_client = c.id
                            WHERE p.id_paiement = ?";
            $stmtPaiement = $this->conn->prepare($sqlPaiement);
            $stmtPaiement->execute([$id]);
            $paiementInfo = $stmtPaiement->fetch(PDO::FETCH_ASSOC);
            
            if (!$paiementInfo) {
                throw new Exception('Paiement non trouvé');
            }
            
            if ($paiementInfo['statut'] === 'annule') {
                throw new Exception('Ce paiement est déjà annulé');
            }
            
            $resultat = PaiementControleur::annulerPaiement($this->conn, $id, $motifAnnulation);
            
            if (!$resultat['success']) {
                throw new Exception($resultat['message']);
            }
            
            // Logging avec constantes
            $this->logger->log([
                'user_id' => $user['id'],
                'user_name' => $user['name'],
                'action_type' => ActivityLogsConstants::ACTION_PAIEMENT_CANCEL,
                'entity_type' => ActivityLogsConstants::ENTITY_PAIEMENT,
                'entity_id' => $id,
                'description' => "Annulation du paiement #{$paiementInfo['numero_paiement']} de la facture {$paiementInfo['numero_facture']} ({$paiementInfo['nom_client']})",
                'details' => [
                    'paiement_id' => $id,
                    'numero_paiement' => $paiementInfo['numero_paiement'],
                    'facture_id' => $paiementInfo['id_facture'],
                    'facture_numero' => $paiementInfo['numero_facture'],
                    'client_nom' => $paiementInfo['nom_client'],
                    'montant_annule' => floatval($paiementInfo['montant_paye']),
                    'methode_paiement' => $paiementInfo['methode_paiement'],
                    'date_paiement' => $paiementInfo['date_paiement'],
                    'motif_annulation' => $motifAnnulation
                ],
                'severity' => ActivityLogsConstants::SEVERITY_WARNING
            ]);
            
            // Valider la transaction
            $this->conn->commit();
            
            return $resultat;
            
        } catch (Exception $e) {
            // Annuler la transaction en cas d'erreur
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            
            // Logging d'erreur avec constantes
            $this->logger->log([
                'user_id' => $user['id'],
                'user_name' => $user['name'],
                'action_type' => ActivityLogsConstants::ACTION_SYSTEM_ERROR,
                'entity_type' => ActivityLogsConstants::ENTITY_PAIEMENT,
                'entity_id' => $id,
                'description' => "Échec d'annulation du paiement ID {$id}",
                'details' => [
                    'error_message' => $e->getMessage(),
                    'paiement_id' => $id,
                    'motif_annulation' => $motifAnnulation
                ],
                'severity' => ActivityLogsConstants::SEVERITY_ERROR
            ]);
            
            return [
                'success' => false,
                'message' => 'Erreur lors de l\'annulation du paiement: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * ✅ EXISTANTE : Suppression d'un paiement
     * @param int $id ID du paiement
     * @return array Résultat de l'opération
     */
    public function supprimerPaiement($id) {
        $user = $this->getCurrentUser();
        
        try {
            // Démarrer une transaction
            $this->conn->beginTransaction();
            
            // Récupérer les informations du paiement avant suppression
            $sqlPaiement = "SELECT p.*, f.numero_facture, 
                                   CONCAT(c.prenom, ' ', c.nom) as nom_client
                            FROM paiement p
                            JOIN facture f ON p.id_facture = f.id_facture
                            JOIN client c ON f.id_client = c.id
                            WHERE p.id_paiement = ?";
            $stmtPaiement = $this->conn->prepare($sqlPaiement);
            $stmtPaiement->execute([$id]);
            $paiementInfo = $stmtPaiement->fetch(PDO::FETCH_ASSOC);
            
            if (!$paiementInfo) {
                throw new Exception('Paiement non trouvé');
            }
            
            $resultat = PaiementControleur::supprimerPaiement($this->conn, $id);
            
            if (!$resultat['success']) {
                throw new Exception($resultat['message']);
            }
            
            // Logging avec constantes
            $this->logger->log([
                'user_id' => $user['id'],
                'user_name' => $user['name'],
                'action_type' => ActivityLogsConstants::ACTION_PAIEMENT_DELETE,
                'entity_type' => ActivityLogsConstants::ENTITY_PAIEMENT,
                'entity_id' => $id,
                'description' => "Suppression du paiement #{$paiementInfo['numero_paiement']} de la facture {$paiementInfo['numero_facture']} ({$paiementInfo['nom_client']})",
                'details' => [
                    'paiement_id' => $id,
                    'numero_paiement' => $paiementInfo['numero_paiement'],
                    'facture_id' => $paiementInfo['id_facture'],
                    'facture_numero' => $paiementInfo['numero_facture'],
                    'client_nom' => $paiementInfo['nom_client'],
                    'montant_supprime' => floatval($paiementInfo['montant_paye']),
                    'methode_paiement' => $paiementInfo['methode_paiement'],
                    'date_paiement' => $paiementInfo['date_paiement']
                ],
                'severity' => ActivityLogsConstants::SEVERITY_CRITICAL
            ]);
            
            // Valider la transaction
            $this->conn->commit();
            
            return $resultat;
            
        } catch (Exception $e) {
            // Annuler la transaction en cas d'erreur
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            
            // Logging d'erreur avec constantes
            $this->logger->log([
                'user_id' => $user['id'],
                'user_name' => $user['name'],
                'action_type' => ActivityLogsConstants::ACTION_SYSTEM_ERROR,
                'entity_type' => ActivityLogsConstants::ENTITY_PAIEMENT,
                'entity_id' => $id,
                'description' => "Échec de suppression du paiement ID {$id}",
                'details' => [
                    'error_message' => $e->getMessage(),
                    'paiement_id' => $id
                ],
                'severity' => ActivityLogsConstants::SEVERITY_ERROR
            ]);
            
            return [
                'success' => false,
                'message' => 'Erreur lors de la suppression du paiement: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * ✅ NOUVELLE : Récupération des statistiques de paiement d'une facture
     * @param int $factureId ID de la facture
     * @return array Statistiques de paiement
     */
    public function getStatistiquesPaiement($factureId) {
        $user = $this->getCurrentUser();
        
        try {
            $resultat = PaiementControleur::getStatistiquesPaiement($this->conn, $factureId);
            return $resultat;
            
        } catch (Exception $e) {
            // Logging d'erreur uniquement avec constantes
            $this->logger->log([
                'user_id' => $user['id'],
                'user_name' => $user['name'],
                'action_type' => ActivityLogsConstants::ACTION_SYSTEM_ERROR,
                'entity_type' => ActivityLogsConstants::ENTITY_FACTURE,
                'entity_id' => $factureId,
                'description' => "Erreur lors de la consultation des statistiques de paiement de la facture ID {$factureId}",
                'details' => [
                    'error_message' => $e->getMessage(),
                    'facture_id' => $factureId
                ],
                'severity' => ActivityLogsConstants::SEVERITY_WARNING
            ]);
            
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * ✅ NOUVELLE : Alias pour compatibilité avec PaiementService.js
     * @param int $factureId ID de la facture
     * @return array Liste des paiements
     */
    public function getPaiementsParFacture($factureId) {
        return $this->getHistoriquePaiements($factureId);
    }
}
?>