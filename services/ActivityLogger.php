<?php
/**
 * ActivityLogger.php - Classe utilitaire pour logger les activités
 * Emplacement: fact-back/utils/ActivityLogger.php
 */

require_once realpath(__DIR__ . '/../controllers/ActivityLogsControleur.php');
require_once realpath(__DIR__ . '/../constants/ActivityLogsConstants.php');

class ActivityLogger {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Log une activité avec validation automatique
     * 
     * @param array $data Données du log
     * @return bool Succès de l'opération
     */
    public function log($data) {
        try {
            // Validation et enrichissement automatique des données
            $enrichedData = $this->enrichLogData($data);
            
            // S'assurer que severity est défini
            if (!isset($enrichedData['severity']) || empty($enrichedData['severity'])) {
                $enrichedData['severity'] = ActivityLogsConstants::SEVERITY_INFO;
            }
            
            // Utiliser le contrôleur pour insérer
            return ActivityLogsControleur::insertLog($this->conn, $enrichedData);
        } catch (Exception $e) {
            error_log("❌ ActivityLogger::log - Erreur: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Méthodes raccourcies pour les actions courantes
     */
    
    // Authentification
    public function logLogin($userId, $userName) {
        return $this->log([
            'user_id' => $userId,
            'user_name' => $userName,
            'action_type' => ActivityLogsConstants::ACTION_AUTH_LOGIN,
            'description' => "Connexion de l'utilisateur {$userName}",
            'severity' => ActivityLogsConstants::SEVERITY_INFO
        ]);
    }
    
    public function logLogout($userId, $userName) {
        return $this->log([
            'user_id' => $userId,
            'user_name' => $userName,
            'action_type' => ActivityLogsConstants::ACTION_AUTH_LOGOUT,
            'description' => "Déconnexion de l'utilisateur {$userName}",
            'severity' => ActivityLogsConstants::SEVERITY_INFO
        ]);
    }
    
    public function logAuthFailed($attemptedUser = null) {
        return $this->log([
            'action_type' => ActivityLogsConstants::ACTION_AUTH_FAILED,
            'description' => "Tentative de connexion échouée" . ($attemptedUser ? " pour {$attemptedUser}" : ""),
            'details' => ['attempted_user' => $attemptedUser],
            'severity' => ActivityLogsConstants::SEVERITY_ERROR
        ]);
    }
    
    // Gestion des entités
    public function logEntityCreate($entityType, $entityId, $userId, $userName, $details = null) {
        $actionType = $this->getEntityActionType($entityType, 'create');
        $entityLabel = $this->getEntityLabel($entityType);
        
        return $this->log([
            'user_id' => $userId,
            'user_name' => $userName,
            'action_type' => $actionType,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'description' => "Création d'un(e) {$entityLabel} (ID: {$entityId})",
            'details' => $details,
            'severity' => ActivityLogsConstants::SEVERITY_INFO
        ]);
    }
    
    public function logEntityUpdate($entityType, $entityId, $userId, $userName, $details = null) {
        $actionType = $this->getEntityActionType($entityType, 'update');
        $entityLabel = $this->getEntityLabel($entityType);
        
        return $this->log([
            'user_id' => $userId,
            'user_name' => $userName,
            'action_type' => $actionType,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'description' => "Modification d'un(e) {$entityLabel} (ID: {$entityId})",
            'details' => $details,
            'severity' => ActivityLogsConstants::SEVERITY_INFO
        ]);
    }
    
    public function logEntityDelete($entityType, $entityId, $userId, $userName, $details = null) {
        $actionType = $this->getEntityActionType($entityType, 'delete');
        $entityLabel = $this->getEntityLabel($entityType);
        
        return $this->log([
            'user_id' => $userId,
            'user_name' => $userName,
            'action_type' => $actionType,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'description' => "Suppression d'un(e) {$entityLabel} (ID: {$entityId})",
            'details' => $details,
            'severity' => ActivityLogsConstants::SEVERITY_WARNING
        ]);
    }
    
    // Actions spécifiques aux factures
    public function logFactureSend($factureId, $userId, $userName, $clientEmail) {
        return $this->log([
            'user_id' => $userId,
            'user_name' => $userName,
            'action_type' => ActivityLogsConstants::ACTION_FACTURE_SEND,
            'entity_type' => ActivityLogsConstants::ENTITY_FACTURE,
            'entity_id' => $factureId,
            'description' => "Envoi de la facture #{$factureId} à {$clientEmail}",
            'details' => ['client_email' => $clientEmail],
            'severity' => ActivityLogsConstants::SEVERITY_INFO
        ]);
    }
    
    public function logFactureValidate($factureId, $userId, $userName) {
        return $this->log([
            'user_id' => $userId,
            'user_name' => $userName,
            'action_type' => ActivityLogsConstants::ACTION_FACTURE_VALIDATE,
            'entity_type' => ActivityLogsConstants::ENTITY_FACTURE,
            'entity_id' => $factureId,
            'description' => "Validation de la facture #{$factureId}",
            'severity' => ActivityLogsConstants::SEVERITY_INFO
        ]);
    }
    
    // Actions spécifiques aux paiements
    public function logPaiementValidate($paiementId, $userId, $userName, $montant) {
        return $this->log([
            'user_id' => $userId,
            'user_name' => $userName,
            'action_type' => ActivityLogsConstants::ACTION_PAIEMENT_VALIDATE,
            'entity_type' => ActivityLogsConstants::ENTITY_PAIEMENT,
            'entity_id' => $paiementId,
            'description' => "Validation du paiement #{$paiementId} ({$montant}€)",
            'details' => ['montant' => $montant],
            'severity' => ActivityLogsConstants::SEVERITY_INFO
        ]);
    }
    
    public function logPaiementRefund($paiementId, $userId, $userName, $montant, $raison = null) {
        return $this->log([
            'user_id' => $userId,
            'user_name' => $userName,
            'action_type' => ActivityLogsConstants::ACTION_PAIEMENT_REFUND,
            'entity_type' => ActivityLogsConstants::ENTITY_PAIEMENT,
            'entity_id' => $paiementId,
            'description' => "Remboursement du paiement #{$paiementId} ({$montant}€)",
            'details' => ['montant' => $montant, 'raison' => $raison],
            'severity' => ActivityLogsConstants::SEVERITY_WARNING
        ]);
    }
    
    // Erreurs système
    public function logSystemError($error, $details = null) {
        return $this->log([
            'action_type' => ActivityLogsConstants::ACTION_SYSTEM_ERROR,
            'entity_type' => ActivityLogsConstants::ENTITY_SYSTEM,
            'description' => "Erreur système: {$error}",
            'details' => $details,
            'severity' => ActivityLogsConstants::SEVERITY_ERROR
        ]);
    }
    
    public function logAccessDenied($userId = null, $userName = null, $resource = null) {
        return $this->log([
            'user_id' => $userId,
            'user_name' => $userName,
            'action_type' => ActivityLogsConstants::ACTION_ACCESS_DENIED,
            'description' => "Accès refusé" . ($resource ? " à {$resource}" : ""),
            'details' => ['resource' => $resource],
            'severity' => ActivityLogsConstants::SEVERITY_ERROR
        ]);
    }
    
    /**
     * Log en mode batch (plusieurs logs en une fois)
     * 
     * @param array $logsData Tableau de données de logs
     * @return array Résultats des insertions
     */
    public function logBatch($logsData) {
        $results = [];
        
        foreach ($logsData as $logData) {
            $results[] = $this->log($logData);
        }
        
        return $results;
    }
    
    /**
     * Enrichit automatiquement les données du log
     * 
     * @param array $data Données brutes
     * @return array Données enrichies
     */
    private function enrichLogData($data) {
        // Ajouter la sévérité recommandée si non spécifiée
        if (empty($data['severity']) && !empty($data['action_type'])) {
            $data['severity'] = ActivityLogsConstants::getRecommendedSeverity($data['action_type']);
        }
        
        // Ajouter des informations de contexte si disponibles
        if (empty($data['ip_address'])) {
            $data['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? null;
        }
        
        if (empty($data['user_agent'])) {
            $data['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? null;
        }
        
        // Ajouter l'utilisateur de session si non spécifié
        if (empty($data['user_id']) && isset($_SESSION['user_id'])) {
            $data['user_id'] = $_SESSION['user_id'];
        }
        
        if (empty($data['user_name']) && isset($_SESSION['user_name'])) {
            $data['user_name'] = $_SESSION['user_name'];
        }
        
        return $data;
    }
    
    /**
     * Génère le type d'action pour une entité
     * 
     * @param string $entityType Type d'entité
     * @param string $operation Opération (create, update, delete)
     * @return string Type d'action
     */
    private function getEntityActionType($entityType, $operation) {
        $mapping = [
            'user' => [
                'create' => ActivityLogsConstants::ACTION_USER_CREATE,
                'update' => ActivityLogsConstants::ACTION_USER_UPDATE,
                'delete' => ActivityLogsConstants::ACTION_USER_DELETE
            ],
            'client' => [
                'create' => ActivityLogsConstants::ACTION_CLIENT_CREATE,
                'update' => ActivityLogsConstants::ACTION_CLIENT_UPDATE,
                'delete' => ActivityLogsConstants::ACTION_CLIENT_DELETE
            ],
            'facture' => [
                'create' => ActivityLogsConstants::ACTION_FACTURE_CREATE,
                'update' => ActivityLogsConstants::ACTION_FACTURE_UPDATE,
                'delete' => ActivityLogsConstants::ACTION_FACTURE_DELETE
            ],
            'paiement' => [
                'create' => ActivityLogsConstants::ACTION_PAIEMENT_CREATE,
                'update' => ActivityLogsConstants::ACTION_PAIEMENT_UPDATE,
                'delete' => ActivityLogsConstants::ACTION_PAIEMENT_DELETE
            ]
        ];
        
        return $mapping[$entityType][$operation] ?? $entityType . '_' . $operation;
    }
    
    /**
     * Récupère le label français d'une entité
     * 
     * @param string $entityType Type d'entité
     * @return string Label français
     */
    private function getEntityLabel($entityType) {
        $labels = [
            'user' => 'utilisateur',
            'client' => 'client',
            'facture' => 'facture',
            'paiement' => 'paiement',
            'system' => 'système'
        ];
        
        return $labels[$entityType] ?? $entityType;
    }
    
    /**
     * Méthodes statiques pour un usage rapide sans instanciation
     */
    
    /**
     * Log rapide d'une activité (méthode statique)
     * 
     * @param PDO $conn Connexion base de données
     * @param array $data Données du log
     * @return bool Succès de l'opération
     */
    public static function quickLog($conn, $data) {
        $logger = new self($conn);
        return $logger->log($data);
    }
    
    /**
     * Log rapide d'une erreur système (méthode statique)
     * 
     * @param PDO $conn Connexion base de données
     * @param string $error Message d'erreur
     * @param array $details Détails supplémentaires
     * @return bool Succès de l'opération
     */
    public static function quickSystemError($conn, $error, $details = null) {
        $logger = new self($conn);
        return $logger->logSystemError($error, $details);
    }
    
    /**
     * Log rapide d'un accès refusé (méthode statique)
     * 
     * @param PDO $conn Connexion base de données
     * @param string $resource Ressource demandée
     * @param int $userId ID utilisateur (optionnel)
     * @param string $userName Nom utilisateur (optionnel)
     * @return bool Succès de l'opération
     */
    public static function quickAccessDenied($conn, $resource, $userId = null, $userName = null) {
        $logger = new self($conn);
        return $logger->logAccessDenied($userId, $userName, $resource);
    }
}

/**
 * Fonction globale pour logger rapidement (si nécessaire)
 * 
 * @param PDO $conn Connexion base de données
 * @param array $data Données du log
 * @return bool Succès de l'opération
 */
function log_activity($conn, $data) {
    return ActivityLogger::quickLog($conn, $data);
}

/**
 * Fonction globale pour logger une erreur système rapidement
 * 
 * @param PDO $conn Connexion base de données
 * @param string $error Message d'erreur
 * @param array $details Détails supplémentaires
 * @return bool Succès de l'opération
 */
function log_system_error($conn, $error, $details = null) {
    return ActivityLogger::quickSystemError($conn, $error, $details);
}
?>