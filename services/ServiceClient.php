<?php
// ServiceClient.php

require_once realpath(__DIR__ . '/../controllers/ClientControleur.php');

class ServiceClient {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Récupère tous les clients
     * @return array Liste des clients
     */
    public function listerClients() {
        try {
            $clients = ClientControleur::listerClients($this->conn);
            error_log ("ServiceClient - Clients récupérés: " . count($clients));
            error_log ("ServiceClient - Détails clients: " . print_r($clients, true));
            
            return [
                'success' => true,
                'clients' => $clients
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération des clients: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Récupère un client par son ID
     * @param int $id_client ID du client
     * @return array Informations du client
     */
    public function getClientParId($id_client) {
        try {
            $client = ClientControleur::getClientParId($this->conn, $id_client);
            return [
                'success' => true,
                'client' => $client
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération du client: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Vérifie si un client a des factures associées
     * @param int $id_client ID du client
     * @return array Résultat de l'opération avec information booléenne
     */
    public function aUneFacture($id_client) {
        try {
            $aUneFacture = ClientControleur::aUneFacture($this->conn, $id_client);
            
            // Si le client a des factures, compter combien
            $count = 0;
            if ($aUneFacture) {
                $factures = ClientControleur::getFacturesClient($this->conn, $id_client);
                $count = count($factures);
            }
            
            return [
                'success' => true,
                'hasInvoices' => $aUneFacture,     // ← Nom plus cohérent
                'aUneFacture' => $aUneFacture,     // ← Garder pour compatibilité
                'count' => $count
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erreur lors de la vérification des factures: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Ajoute un nouveau client
     * @param object $data Les données du client
     * @return array Résultat de l'opération
     */
    public function ajouterClient($data) {
        try {
            // Démarrer une transaction
            $this->conn->beginTransaction();
            
            $resultat = ClientControleur::ajouterClient($this->conn, $data);
            
            // Valider la transaction
            $this->conn->commit();
            
            return [
                'success' => true,
                'message' => $resultat['message'],
                'id_client' => $resultat['id_client']
            ];
            
        } catch (Exception $e) {
            // Annuler la transaction en cas d'erreur
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            
            return [
                'success' => false,
                'message' => 'Erreur lors de l\'ajout du client: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Modifie un client existant
     * @param int $id_client ID du client
     * @param object $data Les nouvelles données du client
     * @return array Résultat de l'opération
     */
    public function modifierClient($id_client, $data) {
        try {
            // Démarrer une transaction
            $this->conn->beginTransaction();
            
            $resultat = ClientControleur::modifierClient($this->conn, $id_client, $data);
            
            // Valider la transaction
            $this->conn->commit();
            
            return [
                'success' => true,
                'message' => $resultat['message'],
                'id_client' => $resultat['id_client']
            ];
            
        } catch (Exception $e) {
            // Annuler la transaction en cas d'erreur
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            
            return [
                'success' => false,
                'message' => 'Erreur lors de la modification du client: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Supprime un client
     * @param int $id_client ID du client
     * @return array Résultat de l'opération
     */
    public function supprimerClient($id_client) {
        try {
            // Démarrer une transaction
            $this->conn->beginTransaction();
            
            $resultat = ClientControleur::supprimerClient($this->conn, $id_client);
            
            // Valider la transaction
            $this->conn->commit();
            
            return [
                'success' => true,
                'message' => $resultat['message'],
                'id_client' => $resultat['id_client']
            ];
            
        } catch (Exception $e) {
            // Annuler la transaction en cas d'erreur
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            
            return [
                'success' => false,
                'message' => 'Erreur lors de la suppression du client: ' . $e->getMessage()
            ];
        }
    }
    
}
?>