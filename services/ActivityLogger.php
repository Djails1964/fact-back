<?php
/**
 * ActivityLogger.php - Utilitaire de logging (simplifié)
 * Emplacement: fact-back/services/ActivityLogger.php
 * 
 * RÔLE: Utilitaire simple pour enregistrer des logs depuis n'importe où dans l'application
 *       La logique métier complexe est dans ServiceActivityLogs
 */

require_once realpath(__DIR__ . '/../controllers/ActivityLogsControleur.php');

class ActivityLogger {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Méthode principale pour enregistrer un log
     * 
     * @param array $data Données du log
     * @return bool Succès de l'enregistrement
     */
    public function log($data) {
        try {
            return ActivityLogsControleur::insertLog($this->conn, $data);
        } catch (Exception $e) {
            error_log("❌ ActivityLogger::log - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Méthodes raccourcies pour les logs courants
     */
    
    public function logUserLogin($userId, $userName) {
        return $this->log([
            'user_id' => $userId,
            'user_name' => $userName,
            'action_type' => 'auth_login',
            'description' => "Connexion réussie de l'utilisateur {$userName}"
        ]);
    }
    
    public function logUserLogout($userId, $userName) {
        return $this->log([
            'user_id' => $userId,
            'user_name' => $userName,
            'action_type' => 'auth_logout',
            'description' => "Déconnexion de l'utilisateur {$userName}"
        ]);
    }
    
    public function logAuthFailed($username, $reason = 'invalid_credentials') {
        return $this->log([
            'user_name' => $username,
            'action_type' => 'auth_failed',
            'description' => "Tentative de connexion échouée pour {$username}",
            'details' => ['reason' => $reason],
            'severity' => 'warning'
        ]);
    }
    
    public function logUserCreate($adminId, $adminName, $newUserId, $newUserData) {
        return $this->log([
            'user_id' => $adminId,
            'user_name' => $adminName,
            'action_type' => 'user_create',
            'entity_type' => 'user',
            'entity_id' => $newUserId,
            'description' => "Création d'un nouvel utilisateur: {$newUserData['username']}",
            'details' => [
                'username' => $newUserData['username'],
                'role' => $newUserData['role'] ?? 'standard',
                'created_by' => $adminName
            ]
        ]);
    }
    
    public function logUserUpdate($adminId, $adminName, $userId, $changes) {
        return $this->log([
            'user_id' => $adminId,
            'user_name' => $adminName,
            'action_type' => 'user_update',
            'entity_type' => 'user',
            'entity_id' => $userId,
            'description' => "Modification de l'utilisateur ID {$userId}",
            'details' => $changes
        ]);
    }
    
    public function logUserDelete($adminId, $adminName, $userId, $username) {
        return $this->log([
            'user_id' => $adminId,
            'user_name' => $adminName,
            'action_type' => 'user_delete',
            'entity_type' => 'user',
            'entity_id' => $userId,
            'description' => "Suppression de l'utilisateur {$username}",
            'details' => ['deleted_username' => $username],
            'severity' => 'warning'
        ]);
    }
    
    public function logFactureCreate($userId, $userName, $factureId, $factureData) {
        return $this->log([
            'user_id' => $userId,
            'user_name' => $userName,
            'action_type' => 'facture_create',
            'entity_type' => 'facture',
            'entity_id' => $factureId,
            'description' => "Création de la facture {$factureId}",
            'details' => [
                'facture_id' => $factureId,
                'client_id' => $factureData['client_id'] ?? null,
                'montant' => $factureData['montant'] ?? null
            ]
        ]);
    }
    
    public function logFactureUpdate($userId, $userName, $factureId, $changes) {
        return $this->log([
            'user_id' => $userId,
            'user_name' => $userName,
            'action_type' => 'facture_update',
            'entity_type' => 'facture',
            'entity_id' => $factureId,
            'description' => "Modification de la facture {$factureId}",
            'details' => $changes
        ]);
    }
    
    public function logFactureDelete($userId, $userName, $factureId) {
        return $this->log([
            'user_id' => $userId,
            'user_name' => $userName,
            'action_type' => 'facture_delete',
            'entity_type' => 'facture',
            'entity_id' => $factureId,
            'description' => "Suppression de la facture {$factureId}",
            'severity' => 'warning'
        ]);
    }
    
    public function logFactureSend($userId, $userName, $factureId, $recipient) {
        return $this->log([
            'user_id' => $userId,
            'user_name' => $userName,
            'action_type' => 'facture_send',
            'entity_type' => 'facture',
            'entity_id' => $factureId,
            'description' => "Envoi de la facture {$factureId} à {$recipient}",
            'details' => ['recipient' => $recipient]
        ]);
    }
    
    public function logClientCreate($userId, $userName, $clientId, $clientData) {
        return $this->log([
            'user_id' => $userId,
            'user_name' => $userName,
            'action_type' => 'client_create',
            'entity_type' => 'client',
            'entity_id' => $clientId,
            'description' => "Création du client: {$clientData['nom']}",
            'details' => [
                'nom' => $clientData['nom'],
                'email' => $clientData['email'] ?? null
            ]
        ]);
    }
    
    public function logSystemError($error, $context = []) {
        return $this->log([
            'action_type' => 'system_error',
            'entity_type' => 'system',
            'description' => "Erreur système: {$error}",
            'details' => $context,
            'severity' => 'error'
        ]);
    }
    
    public function logSystemBackup($backupInfo = []) {
        return $this->log([
            'action_type' => 'system_backup',
            'entity_type' => 'system',
            'description' => "Sauvegarde du système effectuée",
            'details' => $backupInfo,
            'severity' => 'info'
        ]);
    }
    
    public function logAccessDenied($userId = null, $userName = null, $resource = '') {
        return $this->log([
            'user_id' => $userId,
            'user_name' => $userName,
            'action_type' => 'access_denied',
            'description' => "Accès refusé" . ($resource ? " à {$resource}" : ''),
            'details' => ['resource' => $resource],
            'severity' => 'warning'
        ]);
    }
    
    /**
     * Méthode utilitaire pour logger depuis n'importe où avec context automatique
     * 
     * @param string $actionType Type d'action
     * @param string $description Description
     * @param array $additionalData Données supplémentaires
     */
    public function logQuick($actionType, $description, $additionalData = []) {
        // Récupérer le contexte utilisateur depuis la session si disponible
        $userId = $_SESSION['user_id'] ?? null;
        $userName = $_SESSION['user_name'] ?? null;
        
        $logData = [
            'user_id' => $userId,
            'user_name' => $userName,
            'action_type' => $actionType,
            'description' => $description
        ];
        
        // Merger les données supplémentaires
        $logData = array_merge($logData, $additionalData);
        
        return $this->log($logData);
    }
}
?>