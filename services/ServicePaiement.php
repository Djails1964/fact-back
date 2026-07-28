<?php

/**
 * ServicePaiement.php - Version refactorisée
 * Service pour la gestion des paiements avec logging complet
 * ✅ REFACTORISÉ : Utilise PaiementControleur pour toutes les requêtes SQL
 * ✅ Pas de SQL direct dans ce fichier
 */

require_once realpath(__DIR__ . '/../controllers/PaiementControleur.php');
// ✅ Requis par PaiementControleur::enregistrerPaiement/modifierPaiement/
// annulerPaiement/supprimerPaiement, qui appellent
// FactureControleur::recalculerCascadeMensuelle() pour les confirmations
// de paiement (contrats au forfait) — sans ce require, "Class
// FactureControleur not found" dès qu'un paiement est enregistré sur une
// facture liée à une confirmation.
require_once realpath(__DIR__ . '/../controllers/FactureControleur.php');
require_once realpath(__DIR__ . '/ActivityLogger.php');
require_once realpath(__DIR__ . '/../constants/ActivityLogsConstants.php');

class ServicePaiement
{
    private $conn;
    private $logger;

    public function __construct($conn)
    {
        $this->conn = $conn;
        $this->logger = new ActivityLogger($conn);
    }

    /**
     * Récupère les informations utilisateur depuis la session
     * @return array Informations utilisateur
     */
    private function getCurrentUser()
    {
        return [
            'id' => $_SESSION['user_id'] ?? null,
            'name' => $_SESSION['user_name'] ?? 'Système'
        ];
    }

    /**
     * Création d'un nouveau paiement
     * ✅ REFACTORISÉ : Utilise PaiementControleur::getFactureInfoPourLog
     * @param array $data Les données du paiement
     * @return array Résultat de l'opération
     */
    public function creerPaiement($data) {
        error_log("ServicePaiement::creerPaiement - Données reçues: " . json_encode($data));
        $user = $this->getCurrentUser();
        
        try {
            // Démarrer une transaction
            $this->conn->beginTransaction();
            
            // Validation : id_client obligatoire, id_facture optionnel
            if (!isset($data['id_client']) || !isset($data['date_paiement']) || 
                !isset($data['montant_paye']) || !isset($data['methode_paiement'])) {
                throw new Exception('Données obligatoires manquantes (client, date, montant, méthode)');
            }

            $id_facture      = isset($data['id_facture'])      && $data['id_facture']      !== '' ? intval($data['id_facture'])      : null;

            // Récupérer le nom du client pour le log
            $stmtClient = $this->conn->prepare("SELECT CONCAT(prenom, ' ', nom) as nom_client FROM client WHERE id = ?");
            $stmtClient->execute([$data['id_client']]);
            $clientRow = $stmtClient->fetch(PDO::FETCH_ASSOC);
            $nomClient = $clientRow ? $clientRow['nom_client'] : 'Client inconnu';

            // Infos facture pour le log — seulement si facture fournie
            $factureInfo = null;
            if ($id_facture) {
                $factureInfo = PaiementControleur::getFactureInfoPourLog($this->conn, $id_facture);
            }

            
            $resultat = PaiementControleur::enregistrerPaiement($this->conn, $id_facture, $data);
            error_log("ServicePaiement::creerPaiement - Résultat de l'enregistrement: " . json_encode($resultat));
            
            if (!$resultat['success']) {
                throw new Exception($resultat['message']);
            }
            
            // Description adaptée : facture ou libre
            if ($factureInfo) {
                $description = "Nouveau paiement #{$resultat['numero_paiement']} pour la facture {$factureInfo['numero_facture']} ({$nomClient})";
            } else {
                $description = "Nouveau paiement libre #{$resultat['numero_paiement']} pour le client {$nomClient} (sans facture)";
            }

            $this->logger->log([
                'user_id'     => $user['id'],
                'user_name'   => $user['name'],
                'action_type' => ActivityLogsConstants::ACTION_PAIEMENT_CREATE,
                'entity_type' => ActivityLogsConstants::ENTITY_PAIEMENT,
                'entity_id'   => $resultat['id_paiement'],
                'description' => $description,
                'details' => [
                    'id_paiement'      => $resultat['id_paiement'],
                    'numero_paiement'  => $resultat['numero_paiement'],
                    'id_client'        => $data['id_client'],
                    'client_nom'       => $nomClient,
                    'id_facture'       => $id_facture,
                    'numero_facture'   => $factureInfo['numero_facture'] ?? null,
                    'montant_paye'     => floatval($data['montant_paye']),
                    'methode_paiement' => $data['methode_paiement'],
                    'date_paiement'    => $data['date_paiement'],
                    'commentaire'      => $data['commentaire'] ?? null
                ],
                'severity' => ActivityLogsConstants::SEVERITY_INFO
            ]);
            
            $this->conn->commit();
            return $resultat;
            
        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            
            $this->logger->log([
                'user_id'     => $user['id'],
                'user_name'   => $user['name'],
                'action_type' => ActivityLogsConstants::ACTION_SYSTEM_ERROR,
                'entity_type' => ActivityLogsConstants::ENTITY_PAIEMENT,
                'entity_id'   => null,
                'description' => "Échec de création d'un paiement pour le client ID " . ($data['id_client'] ?? '?'),
                'details' => [
                    'error_message'    => $e->getMessage(),
                    'id_client'        => $data['id_client'] ?? null,
                    'id_facture'       => $data['id_facture'] ?? null,
                    'montant_paye'     => $data['montant_paye'] ?? null,
                    'methode_paiement' => $data['methode_paiement'] ?? null
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
     * @param int $id_paiement ID du paiement
     * @return array Informations du paiement
     */
    public function getPaiement($id_paiement)
    {
        $user = $this->getCurrentUser();

        try {
            $resultat = PaiementControleur::getPaiement($this->conn, $id_paiement);
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
                    'id_paiement' => $id_paiement
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
     * Récupération de la liste complète des paiements avec filtrage
     * @param array $options Options de filtrage et pagination
     * @return array Liste des paiements avec pagination
     */
    public function listerPaiements($options = [])
    {
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
     * Récupération des statistiques globales des paiements
     * @param int|null $annee Année pour filtrer les statistiques
     * @return array Statistiques complètes
     */
    public function getStatistiquesGlobales($annee = null)
    {
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
     * Récupération de l'historique des paiements d'une facture
     * @param int $id_facture ID de la facture
     * @return array Liste des paiements
     */
    public function getHistoriquePaiements($id_facture)
    {
        $user = $this->getCurrentUser();

        try {
            $resultat = PaiementControleur::getHistoriquePaiements($this->conn, $id_facture);
            return $resultat;
        } catch (Exception $e) {
            // Logging d'erreur uniquement avec constantes
            $this->logger->log([
                'user_id' => $user['id'],
                'user_name' => $user['name'],
                'action_type' => ActivityLogsConstants::ACTION_SYSTEM_ERROR,
                'entity_type' => ActivityLogsConstants::ENTITY_FACTURE,
                'entity_id' => $id_facture,
                'description' => "Erreur lors de la consultation de l'historique des paiements de la facture ID {$id_facture}",
                'details' => [
                    'error_message' => $e->getMessage(),
                    'id_facture' => $id_facture
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
     * Modification d'un paiement existant
     * ✅ REFACTORISÉ : Utilise PaiementControleur::getPaiementInfoPourLog
     * @param int $id_paiement ID du paiement
     * @param array $data Les nouvelles données du paiement
     * @return array Résultat de l'opération
     */
    public function modifierPaiement($id_paiement, $data)
    {
        $user = $this->getCurrentUser();

        try {
            // Démarrer une transaction
            $this->conn->beginTransaction();

            // ✅ REFACTORISÉ : Récupérer les données actuelles du paiement via le contrôleur
            $paiementActuel = PaiementControleur::getPaiementInfoPourLog($this->conn, $id_paiement);

            if (!$paiementActuel) {
                throw new Exception('Paiement non trouvé');
            }

            // Identifier les changements
            $changes = [];
            if (isset($data['date_paiement']) && $data['date_paiement'] !== $paiementActuel['date_paiement']) {
                $changes['date_paiement'] = [
                    'ancien' => $paiementActuel['date_paiement'],
                    'nouveau' => $data['date_paiement']
                ];
            }
            if (isset($data['montant_paye']) && floatval($data['montant_paye']) !== floatval($paiementActuel['montant_paye'])) {
                $changes['montant_paye'] = [
                    'ancien' => floatval($paiementActuel['montant_paye']),
                    'nouveau' => floatval($data['montant_paye'])
                ];
            }
            if (isset($data['methode_paiement']) && $data['methode_paiement'] !== $paiementActuel['methode_paiement']) {
                $changes['methode_paiement'] = [
                    'ancien' => $paiementActuel['methode_paiement'],
                    'nouveau' => $data['methode_paiement']
                ];
            }
            if (isset($data['commentaire']) && $data['commentaire'] !== $paiementActuel['commentaire']) {
                $changes['commentaire'] = [
                    'ancien' => $paiementActuel['commentaire'],
                    'nouveau' => $data['commentaire']
                ];
            }

            // Appeler la méthode du contrôleur
            $resultat = PaiementControleur::modifierPaiement($this->conn, $id_paiement, $data);

            if (!$resultat['success']) {
                throw new Exception($resultat['message']);
            }

            // Logging avec constantes
            $this->logger->log([
                'user_id' => $user['id'],
                'user_name' => $user['name'],
                'action_type' => ActivityLogsConstants::ACTION_PAIEMENT_UPDATE,
                'entity_type' => ActivityLogsConstants::ENTITY_PAIEMENT,
                'entity_id' => $id_paiement,
                'description' => $paiementActuel['numero_facture']
                    ? "Modification du paiement #{$paiementActuel['numero_paiement']} de la facture {$paiementActuel['numero_facture']} ({$paiementActuel['nom_client']})"
                    : "Modification du paiement libre #{$paiementActuel['numero_paiement']} ({$paiementActuel['nom_client']})",
                'details' => [
                    'id_paiement' => $id_paiement,
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
                'entity_id' => $id_paiement,
                'description' => "Échec de modification du paiement ID {$id_paiement}",
                'details' => [
                    'error_message' => $e->getMessage(),
                    'id_paiement' => $id_paiement,
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
     * Annulation d'un paiement (soft delete)
     * ✅ REFACTORISÉ : Utilise PaiementControleur::getPaiementInfoPourLog
     * @param int $id_paiement ID du paiement
     * @param string $motif_annulation Motif de l'annulation
     * @return array Résultat de l'opération
     */
    public function annulerPaiement($id_paiement, $motif_annulation = null)
    {
        $user = $this->getCurrentUser();

        try {
            // Démarrer une transaction
            $this->conn->beginTransaction();

            // ✅ REFACTORISÉ : Récupérer les informations du paiement via le contrôleur
            $paiementInfo = PaiementControleur::getPaiementInfoPourLog($this->conn, $id_paiement);

            if (!$paiementInfo) {
                throw new Exception('Paiement non trouvé');
            }

            if ($paiementInfo['statut'] === 'annule') {
                throw new Exception('Ce paiement est déjà annulé');
            }

            $resultat = PaiementControleur::annulerPaiement($this->conn, $id_paiement, $motif_annulation);

            if (!$resultat['success']) {
                throw new Exception($resultat['message']);
            }

            // Logging avec constantes
            $this->logger->log([
                'user_id' => $user['id'],
                'user_name' => $user['name'],
                'action_type' => ActivityLogsConstants::ACTION_PAIEMENT_CANCEL,
                'entity_type' => ActivityLogsConstants::ENTITY_PAIEMENT,
                'entity_id' => $id_paiement,
                'description' => $paiementInfo['numero_facture']
                    ? "Annulation du paiement #{$paiementInfo['numero_paiement']} de la facture {$paiementInfo['numero_facture']} ({$paiementInfo['nom_client']})"
                    : "Annulation du paiement libre #{$paiementInfo['numero_paiement']} ({$paiementInfo['nom_client']})",
                'details' => [
                    'id_paiement' => $id_paiement,
                    'numero_paiement' => $paiementInfo['numero_paiement'],
                    'id_facture' => $paiementInfo['id_facture'],
                    'facture_numero' => $paiementInfo['numero_facture'],
                    'client_nom' => $paiementInfo['nom_client'],
                    'montant_annule' => floatval($paiementInfo['montant_paye']),
                    'methode_paiement' => $paiementInfo['methode_paiement'],
                    'date_paiement' => $paiementInfo['date_paiement'],
                    'motif_annulation' => $motif_annulation
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
                'entity_id' => $id_paiement,
                'description' => "Échec d'annulation du paiement ID {$id_paiement}",
                'details' => [
                    'error_message' => $e->getMessage(),
                    'id_paiement' => $id_paiement,
                    'motif_annulation' => $motif_annulation
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
     * Suppression d'un paiement (hard delete)
     * ✅ REFACTORISÉ : Utilise PaiementControleur::getPaiementInfoPourLog
     * @param int $id_paiement ID du paiement
     * @return array Résultat de l'opération
     */
    public function supprimerPaiement($id_paiement)
    {
        $user = $this->getCurrentUser();

        try {
            // Démarrer une transaction
            $this->conn->beginTransaction();

            // ✅ REFACTORISÉ : Récupérer les informations du paiement via le contrôleur
            $paiementInfo = PaiementControleur::getPaiementInfoPourLog($this->conn, $id_paiement);

            if (!$paiementInfo) {
                throw new Exception('Paiement non trouvé');
            }

            $resultat = PaiementControleur::supprimerPaiement($this->conn, $id_paiement);

            if (!$resultat['success']) {
                throw new Exception($resultat['message']);
            }

            // Logging avec constantes
            $this->logger->log([
                'user_id' => $user['id'],
                'user_name' => $user['name'],
                'action_type' => ActivityLogsConstants::ACTION_PAIEMENT_DELETE,
                'entity_type' => ActivityLogsConstants::ENTITY_PAIEMENT,
                'entity_id' => $id_paiement,
                'description' => $paiementInfo['numero_facture']
                    ? "Suppression du paiement #{$paiementInfo['numero_paiement']} de la facture {$paiementInfo['numero_facture']} ({$paiementInfo['nom_client']})"
                    : "Suppression du paiement libre #{$paiementInfo['numero_paiement']} ({$paiementInfo['nom_client']})",
                'details' => [
                    'id_paiement' => $id_paiement,
                    'numero_paiement' => $paiementInfo['numero_paiement'],
                    'id_facture' => $paiementInfo['id_facture'],
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
                'entity_id' => $id_paiement,
                'description' => "Échec de suppression du paiement ID {$id_paiement}",
                'details' => [
                    'error_message' => $e->getMessage(),
                    'id_paiement' => $id_paiement
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
     * Récupération des statistiques de paiement d'une facture
     * @param int $id_facture ID de la facture
     * @return array Statistiques de paiement
     */
    public function getStatistiquesPaiement($id_facture)
    {
        $user = $this->getCurrentUser();

        try {
            $resultat = PaiementControleur::getStatistiquesPaiement($this->conn, $id_facture);
            return $resultat;
        } catch (Exception $e) {
            // Logging d'erreur uniquement avec constantes
            $this->logger->log([
                'user_id' => $user['id'],
                'user_name' => $user['name'],
                'action_type' => ActivityLogsConstants::ACTION_SYSTEM_ERROR,
                'entity_type' => ActivityLogsConstants::ENTITY_FACTURE,
                'entity_id' => $id_facture,
                'description' => "Erreur lors de la consultation des statistiques de paiement de la facture ID {$id_facture}",
                'details' => [
                    'error_message' => $e->getMessage(),
                    'id_facture' => $id_facture
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
     * Alias pour compatibilité avec PaiementService.js
     * @param int $id_facture ID de la facture
     * @return array Liste des paiements
     */
    public function getPaiementsParFacture($id_facture)
    {
        return $this->getHistoriquePaiements($id_facture);
    }
}