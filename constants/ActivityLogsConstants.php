<?php
/**
 * ActivityLogsConstants.php - Constantes pour les logs d'activité
 * Emplacement: fact-back/constants/ActivityLogsConstants.php
 */

class ActivityLogsConstants {
    
    // ====== TYPES D'ACTIONS ======
    
    // Authentification
    const ACTION_AUTH_LOGIN = 'auth_login';
    const ACTION_AUTH_LOGOUT = 'auth_logout';
    const ACTION_AUTH_FAILED = 'auth_failed';
    const ACTION_AUTH_PASSWORD_RESET = 'auth_password_reset';
    const ACTION_AUTH_PASSWORD_CHANGE = 'auth_password_change';
    const ACTION_AUTH_SESSION_EXPIRED = 'auth_session_expired';
    
    // Gestion des utilisateurs
    const ACTION_USER_CREATE = 'user_create';
    const ACTION_USER_UPDATE = 'user_update';
    const ACTION_USER_DELETE = 'user_delete';
    const ACTION_USER_ROLE_CHANGE = 'user_role_change';
    const ACTION_USER_ACTIVATE = 'user_activate';
    const ACTION_USER_DEACTIVATE = 'user_deactivate';
    const ACTION_USER_PROFILE_UPDATE = 'user_profile_update';
    
    // Gestion des clients
    const ACTION_CLIENT_CREATE = 'client_create';
    const ACTION_CLIENT_UPDATE = 'client_update';
    const ACTION_CLIENT_DELETE = 'client_delete';
    const ACTION_CLIENT_VIEW = 'client_view';
    const ACTION_CLIENT_EXPORT = 'client_export';
    
    // Gestion des factures
    const ACTION_FACTURE_CREATE = 'facture_create';
    const ACTION_FACTURE_UPDATE = 'facture_update';
    const ACTION_FACTURE_DELETE = 'facture_delete';
    const ACTION_FACTURE_SEND = 'facture_send';
    const ACTION_FACTURE_DUPLICATE = 'facture_duplicate';
    const ACTION_FACTURE_PRINT = 'facture_print';
    const ACTION_FACTURE_EXPORT = 'facture_export';
    const ACTION_FACTURE_VALIDATE = 'facture_validate';
    const ACTION_FACTURE_CANCEL = 'facture_cancel';
    
    // Gestion des paiements
    const ACTION_PAIEMENT_CREATE = 'paiement_create';
    const ACTION_PAIEMENT_UPDATE = 'paiement_update';
    const ACTION_PAIEMENT_DELETE = 'paiement_delete';
    const ACTION_PAIEMENT_VALIDATE = 'paiement_validate';
    const ACTION_PAIEMENT_CANCEL = 'paiement_cancel';
    const ACTION_PAIEMENT_REFUND = 'paiement_refund';
    
    // Actions système
    const ACTION_SYSTEM_ERROR = 'system_error';
    const ACTION_SYSTEM_BACKUP = 'system_backup';
    const ACTION_SYSTEM_MAINTENANCE = 'system_maintenance';
    const ACTION_SYSTEM_UPDATE = 'system_update';
    const ACTION_SYSTEM_CLEANUP = 'system_cleanup';
    
    // Sécurité et accès
    const ACTION_ACCESS_DENIED = 'access_denied';
    const ACTION_PERMISSION_DENIED = 'permission_denied';
    const ACTION_SUSPICIOUS_ACTIVITY = 'suspicious_activity';
    
    // Gestion des logs
    const ACTION_LOG_RESOLVED = 'log_resolved';
    const ACTION_LOG_ARCHIVED = 'log_archived';
    const ACTION_LOG_EXPORTED = 'log_exported';
    const ACTION_LOG_CLEANED = 'log_cleaned';
    
    // ====== NIVEAUX DE SÉVÉRITÉ ======
    
    const SEVERITY_INFO = 'info';
    const SEVERITY_WARNING = 'warning';
    const SEVERITY_ERROR = 'error';
    const SEVERITY_CRITICAL = 'critical';
    
    // ====== TYPES D'ENTITÉS ======
    
    const ENTITY_USER = 'user';
    const ENTITY_CLIENT = 'client';
    const ENTITY_FACTURE = 'facture';
    const ENTITY_PAIEMENT = 'paiement';
    const ENTITY_SYSTEM = 'system';
    const ENTITY_LOG = 'activity_log';
    
    // ====== GROUPES D'ACTIONS (pour faciliter les filtres) ======
    
    const GROUP_AUTH = [
        self::ACTION_AUTH_LOGIN,
        self::ACTION_AUTH_LOGOUT,
        self::ACTION_AUTH_FAILED,
        self::ACTION_AUTH_PASSWORD_RESET,
        self::ACTION_AUTH_PASSWORD_CHANGE,
        self::ACTION_AUTH_SESSION_EXPIRED
    ];
    
    const GROUP_USER_MANAGEMENT = [
        self::ACTION_USER_CREATE,
        self::ACTION_USER_UPDATE,
        self::ACTION_USER_DELETE,
        self::ACTION_USER_ROLE_CHANGE,
        self::ACTION_USER_ACTIVATE,
        self::ACTION_USER_DEACTIVATE,
        self::ACTION_USER_PROFILE_UPDATE
    ];
    
    const GROUP_CLIENT_MANAGEMENT = [
        self::ACTION_CLIENT_CREATE,
        self::ACTION_CLIENT_UPDATE,
        self::ACTION_CLIENT_DELETE,
        self::ACTION_CLIENT_VIEW,
        self::ACTION_CLIENT_EXPORT
    ];
    
    const GROUP_FACTURE_MANAGEMENT = [
        self::ACTION_FACTURE_CREATE,
        self::ACTION_FACTURE_UPDATE,
        self::ACTION_FACTURE_DELETE,
        self::ACTION_FACTURE_SEND,
        self::ACTION_FACTURE_DUPLICATE,
        self::ACTION_FACTURE_PRINT,
        self::ACTION_FACTURE_EXPORT,
        self::ACTION_FACTURE_VALIDATE,
        self::ACTION_FACTURE_CANCEL
    ];
    
    const GROUP_PAIEMENT_MANAGEMENT = [
        self::ACTION_PAIEMENT_CREATE,
        self::ACTION_PAIEMENT_UPDATE,
        self::ACTION_PAIEMENT_DELETE,
        self::ACTION_PAIEMENT_VALIDATE,
        self::ACTION_PAIEMENT_CANCEL,
        self::ACTION_PAIEMENT_REFUND
    ];
    
    const GROUP_SYSTEM = [
        self::ACTION_SYSTEM_ERROR,
        self::ACTION_SYSTEM_BACKUP,
        self::ACTION_SYSTEM_MAINTENANCE,
        self::ACTION_SYSTEM_UPDATE,
        self::ACTION_SYSTEM_CLEANUP
    ];
    
    const GROUP_SECURITY = [
        self::ACTION_ACCESS_DENIED,
        self::ACTION_PERMISSION_DENIED,
        self::ACTION_SUSPICIOUS_ACTIVITY
    ];
    
    // ====== MÉTHODES UTILITAIRES ======
    
    /**
     * Récupère tous les types d'actions disponibles
     * 
     * @return array Liste de tous les types d'actions
     */
    public static function getAllActionTypes() {
        return array_merge(
            self::GROUP_AUTH,
            self::GROUP_USER_MANAGEMENT,
            self::GROUP_CLIENT_MANAGEMENT,
            self::GROUP_FACTURE_MANAGEMENT,
            self::GROUP_PAIEMENT_MANAGEMENT,
            self::GROUP_SYSTEM,
            self::GROUP_SECURITY,
            [
                self::ACTION_LOG_RESOLVED,
                self::ACTION_LOG_ARCHIVED,
                self::ACTION_LOG_EXPORTED,
                self::ACTION_LOG_CLEANED
            ]
        );
    }
    
    /**
     * Récupère tous les niveaux de sévérité
     * 
     * @return array Liste des niveaux de sévérité
     */
    public static function getAllSeverityLevels() {
        return [
            self::SEVERITY_INFO,
            self::SEVERITY_WARNING,
            self::SEVERITY_ERROR,
            self::SEVERITY_CRITICAL
        ];
    }
    
    /**
     * Récupère tous les types d'entités
     * 
     * @return array Liste des types d'entités
     */
    public static function getAllEntityTypes() {
        return [
            self::ENTITY_USER,
            self::ENTITY_CLIENT,
            self::ENTITY_FACTURE,
            self::ENTITY_PAIEMENT,
            self::ENTITY_SYSTEM,
            self::ENTITY_LOG
        ];
    }
    
    /**
     * Valide si un type d'action est valide
     * 
     * @param string $actionType Type d'action à valider
     * @return bool True si valide, false sinon
     */
    public static function isValidActionType($actionType) {
        return in_array($actionType, self::getAllActionTypes(), true);
    }
    
    /**
     * Valide si un niveau de sévérité est valide
     * 
     * @param string $severity Niveau de sévérité à valider
     * @return bool True si valide, false sinon
     */
    public static function isValidSeverity($severity) {
        return in_array($severity, self::getAllSeverityLevels(), true);
    }
    
    /**
     * Valide si un type d'entité est valide
     * 
     * @param string $entityType Type d'entité à valider
     * @return bool True si valide, false sinon
     */
    public static function isValidEntityType($entityType) {
        return in_array($entityType, self::getAllEntityTypes(), true);
    }
    
    /**
     * Récupère le groupe d'une action
     * 
     * @param string $actionType Type d'action
     * @return string|null Nom du groupe ou null si non trouvé
     */
    public static function getActionGroup($actionType) {
        if (in_array($actionType, self::GROUP_AUTH)) return 'auth';
        if (in_array($actionType, self::GROUP_USER_MANAGEMENT)) return 'user_management';
        if (in_array($actionType, self::GROUP_CLIENT_MANAGEMENT)) return 'client_management';
        if (in_array($actionType, self::GROUP_FACTURE_MANAGEMENT)) return 'facture_management';
        if (in_array($actionType, self::GROUP_PAIEMENT_MANAGEMENT)) return 'paiement_management';
        if (in_array($actionType, self::GROUP_SYSTEM)) return 'system';
        if (in_array($actionType, self::GROUP_SECURITY)) return 'security';
        
        return 'other';
    }
    
    /**
     * Récupère la sévérité recommandée pour un type d'action
     * 
     * @param string $actionType Type d'action
     * @return string Niveau de sévérité recommandé
     */
    public static function getRecommendedSeverity($actionType) {
        // Actions critiques
        if (in_array($actionType, [
            self::ACTION_SYSTEM_ERROR,
            self::ACTION_SUSPICIOUS_ACTIVITY,
            self::ACTION_USER_DELETE,
            self::ACTION_CLIENT_DELETE,
            self::ACTION_FACTURE_DELETE
        ])) {
            return self::SEVERITY_CRITICAL;
        }
        
        // Actions d'erreur
        if (in_array($actionType, [
            self::ACTION_AUTH_FAILED,
            self::ACTION_ACCESS_DENIED,
            self::ACTION_PERMISSION_DENIED
        ])) {
            return self::SEVERITY_ERROR;
        }
        
        // Actions d'avertissement
        if (in_array($actionType, [
            self::ACTION_AUTH_SESSION_EXPIRED,
            self::ACTION_SYSTEM_MAINTENANCE,
            self::ACTION_PAIEMENT_CANCEL,
            self::ACTION_FACTURE_CANCEL
        ])) {
            return self::SEVERITY_WARNING;
        }
        
        // Par défaut : info
        return self::SEVERITY_INFO;
    }
    
    /**
     * Labels traduits en français pour les types d'actions
     * 
     * @return array Tableau associatif action => label
     */
    public static function getActionLabels() {
        return [
            // Authentification
            self::ACTION_AUTH_LOGIN => 'Connexion',
            self::ACTION_AUTH_LOGOUT => 'Déconnexion',
            self::ACTION_AUTH_FAILED => 'Échec connexion',
            self::ACTION_AUTH_PASSWORD_RESET => 'Reset mot de passe',
            self::ACTION_AUTH_PASSWORD_CHANGE => 'Changement mot de passe',
            self::ACTION_AUTH_SESSION_EXPIRED => 'Session expirée',
            
            // Utilisateurs
            self::ACTION_USER_CREATE => 'Création utilisateur',
            self::ACTION_USER_UPDATE => 'Modification utilisateur',
            self::ACTION_USER_DELETE => 'Suppression utilisateur',
            self::ACTION_USER_ROLE_CHANGE => 'Changement rôle',
            self::ACTION_USER_ACTIVATE => 'Activation utilisateur',
            self::ACTION_USER_DEACTIVATE => 'Désactivation utilisateur',
            self::ACTION_USER_PROFILE_UPDATE => 'Mise à jour profil',
            
            // Clients
            self::ACTION_CLIENT_CREATE => 'Création client',
            self::ACTION_CLIENT_UPDATE => 'Modification client',
            self::ACTION_CLIENT_DELETE => 'Suppression client',
            self::ACTION_CLIENT_VIEW => 'Consultation client',
            self::ACTION_CLIENT_EXPORT => 'Export clients',
            
            // Factures
            self::ACTION_FACTURE_CREATE => 'Création facture',
            self::ACTION_FACTURE_UPDATE => 'Modification facture',
            self::ACTION_FACTURE_DELETE => 'Suppression facture',
            self::ACTION_FACTURE_SEND => 'Envoi facture',
            self::ACTION_FACTURE_DUPLICATE => 'Duplication facture',
            self::ACTION_FACTURE_PRINT => 'Impression facture',
            self::ACTION_FACTURE_EXPORT => 'Export facture',
            self::ACTION_FACTURE_VALIDATE => 'Validation facture',
            self::ACTION_FACTURE_CANCEL => 'Annulation facture',
            
            // Paiements
            self::ACTION_PAIEMENT_CREATE => 'Création paiement',
            self::ACTION_PAIEMENT_UPDATE => 'Modification paiement',
            self::ACTION_PAIEMENT_DELETE => 'Suppression paiement',
            self::ACTION_PAIEMENT_VALIDATE => 'Validation paiement',
            self::ACTION_PAIEMENT_CANCEL => 'Annulation paiement',
            self::ACTION_PAIEMENT_REFUND => 'Remboursement',
            
            // Système
            self::ACTION_SYSTEM_ERROR => 'Erreur système',
            self::ACTION_SYSTEM_BACKUP => 'Sauvegarde',
            self::ACTION_SYSTEM_MAINTENANCE => 'Maintenance',
            self::ACTION_SYSTEM_UPDATE => 'Mise à jour système',
            self::ACTION_SYSTEM_CLEANUP => 'Nettoyage système',
            
            // Sécurité
            self::ACTION_ACCESS_DENIED => 'Accès refusé',
            self::ACTION_PERMISSION_DENIED => 'Permission refusée',
            self::ACTION_SUSPICIOUS_ACTIVITY => 'Activité suspecte',
            
            // Logs
            self::ACTION_LOG_RESOLVED => 'Log résolu',
            self::ACTION_LOG_ARCHIVED => 'Log archivé',
            self::ACTION_LOG_EXPORTED => 'Export logs',
            self::ACTION_LOG_CLEANED => 'Nettoyage logs'
        ];
    }
    
    /**
     * Récupère le label d'une action
     * 
     * @param string $actionType Type d'action
     * @return string Label de l'action ou le type si non trouvé
     */
    public static function getActionLabel($actionType) {
        $labels = self::getActionLabels();
        return $labels[$actionType] ?? $actionType;
    }
    
    /**
     * Labels pour les niveaux de sévérité
     * 
     * @return array Tableau associatif severity => label
     */
    public static function getSeverityLabels() {
        return [
            self::SEVERITY_INFO => 'Information',
            self::SEVERITY_WARNING => 'Avertissement',
            self::SEVERITY_ERROR => 'Erreur',
            self::SEVERITY_CRITICAL => 'Critique'
        ];
    }
    
    /**
     * Récupère le label d'un niveau de sévérité
     * 
     * @param string $severity Niveau de sévérité
     * @return string Label du niveau ou le niveau si non trouvé
     */
    public static function getSeverityLabel($severity) {
        $labels = self::getSeverityLabels();
        return $labels[$severity] ?? ucfirst($severity);
    }
}
?>