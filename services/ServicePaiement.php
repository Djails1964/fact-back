<?php
/**
 * ServicePaiement.php
 * Service pour la gestion des paiements
 */

require_once realpath(__DIR__ . '/../controllers/PaiementControleur.php');

class ServicePaiement {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Création d'un nouveau paiement
     * @param array $data Les données du paiement
     * @return array Résultat de l'opération
     */
    public function creerPaiement($data) {
        try {
            // Démarrer une transaction
            $this->conn->beginTransaction();
            
            // Validation des données obligatoires
            if (!isset($data['factureId']) || !isset($data['datePaiement']) || 
                !isset($data['montantPaye']) || !isset($data['methodePaiement'])) {
                throw new Exception('Données obligatoires manquantes');
            }
            
            // Appeler le contrôleur pour enregistrer le paiement
            $resultat = PaiementControleur::enregistrerPaiement($this->conn, $data['factureId'], $data);
            
            if (!$resultat['success']) {
                throw new Exception($resultat['message']);
            }
            
            // Valider la transaction
            $this->conn->commit();
            
            return $resultat;
            
        } catch (Exception $e) {
            // Annuler la transaction en cas d'erreur
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            
            return [
                'success' => false,
                'message' => 'Erreur lors de la création du paiement: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Modification d'un paiement existant
     * @param int $id ID du paiement
     * @param array $data Les nouvelles données du paiement
     * @return array Résultat de l'opération
     */
    public function modifierPaiement($id, $data) {
        try {
            // Démarrer une transaction
            $this->conn->beginTransaction();
            
            // Appeler la méthode du contrôleur
            $resultat = PaiementControleur::modifierPaiement($this->conn, $id, $data);
            
            if (!$resultat['success']) {
                throw new Exception($resultat['message']);
            }
            
            // Valider la transaction
            $this->conn->commit();
            
            return $resultat;
            
        } catch (Exception $e) {
            // Annuler la transaction en cas d'erreur
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            
            return [
                'success' => false,
                'message' => 'Erreur lors de la modification du paiement: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Suppression d'un paiement
     * @param int $id ID du paiement
     * @return array Résultat de l'opération
     */
    public function supprimerPaiement($id) {
        try {
            // Démarrer une transaction
            $this->conn->beginTransaction();
            
            $resultat = PaiementControleur::supprimerPaiement($this->conn, $id);
            
            if (!$resultat['success']) {
                throw new Exception($resultat['message']);
            }
            
            // Valider la transaction
            $this->conn->commit();
            
            return $resultat;
            
        } catch (Exception $e) {
            // Annuler la transaction en cas d'erreur
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            
            return [
                'success' => false,
                'message' => 'Erreur lors de la suppression du paiement: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Récupération d'un paiement spécifique
     * @param int $id ID du paiement
     * @return array Informations du paiement
     */
    public function getPaiement($id) {
        try {
            return PaiementControleur::getPaiement($this->conn, $id);
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération du paiement: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Récupération des paiements d'une facture spécifique
     * @param int $factureId ID de la facture
     * @return array Liste des paiements
     */
    public function getPaiementsParFacture($factureId) {
        try {
            return PaiementControleur::getHistoriquePaiements($this->conn, $factureId);
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération des paiements: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Récupération de la liste complète des paiements avec filtrage
     * @param array $options Options de filtrage et pagination
     * @return array Liste des paiements
     */
    public function listerPaiements($options = []) {
        try {
            return PaiementControleur::listerPaiements($this->conn, $options);
        } catch (Exception $e) {
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
    public function getStatistiquesGlobales($annee = null) {
        try {
            return PaiementControleur::getStatistiquesGlobales($this->conn, $annee);
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques: ' . $e->getMessage()
            ];
        }
    }
}
?>