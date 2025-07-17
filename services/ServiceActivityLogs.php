<?php
/**
 * ServiceActivityLogs.php - Service pour la gestion des logs d'activité
 * Emplacement: fact-back/services/ServiceActivityLogs.php
 */

require_once realpath(__DIR__ . '/../controllers/ActivityLogsControleur.php');

class ServiceActivityLogs {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Récupère les logs d'activité avec validation et logique métier
     * 
     * @param array $filters Filtres à appliquer
     * @param int $limit Nombre de résultats (max 1000)
     * @param int $offset Décalage
     * @return array Résultat avec logs et métadonnées
     */
    public function getLogs($filters = [], $limit = 50, $offset = 0) {
        try {
            // Validation des paramètres
            $limit = max(1, min(1000, (int)$limit)); // Entre 1 et 1000
            $offset = max(0, (int)$offset);
            
            // Validation et nettoyage des filtres
            $cleanFilters = $this->validateAndCleanFilters($filters);
            
            // Récupération des données via le contrôleur
            $logs = ActivityLogsControleur::getLogs($this->conn, $cleanFilters, $limit, $offset);
            $total = ActivityLogsControleur::countLogs($this->conn, $cleanFilters);
            
            // Enrichissement des données (ajout de métadonnées)
            $enrichedLogs = $this->enrichLogs($logs);
            
            return [
                'success' => true,
                'logs' => $enrichedLogs,
                'total' => $total,
                'pagination' => [
                    'limit' => $limit,
                    'offset' => $offset,
                    'page' => floor($offset / $limit) + 1,
                    'totalPages' => ceil($total / $limit),
                    'hasNext' => ($offset + $limit) < $total,
                    'hasPrev' => $offset > 0
                ],
                'filters_applied' => $cleanFilters
            ];
        } catch (Exception $e) {
            error_log("❌ ServiceActivityLogs::getLogs - " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération des logs: ' . $e->getMessage(),
                'logs' => [],
                'total' => 0
            ];
        }
    }
    
    /**
     * Export des logs en CSV avec logique métier
     * 
     * @param array $filters Filtres à appliquer
     * @param int $maxRecords Nombre maximum d'enregistrements à exporter
     * @return array Données CSV ou erreur
     */
    public function exportToCsv($filters = [], $maxRecords = 5000) {
        try {
            // Validation des filtres
            $cleanFilters = $this->validateAndCleanFilters($filters);
            
            // Vérifier le nombre de logs à exporter
            $totalLogs = ActivityLogsControleur::countLogs($this->conn, $cleanFilters);
            
            if ($totalLogs > $maxRecords) {
                return [
                    'success' => false,
                    'message' => "Trop de logs à exporter ({$totalLogs}). Veuillez affiner vos filtres (max: {$maxRecords})."
                ];
            }
            
            // Récupérer tous les logs correspondants
            $logs = ActivityLogsControleur::getLogs($this->conn, $cleanFilters, $maxRecords, 0);
            
            // Préparer les données CSV
            $csvData = [
                // En-têtes
                ['Date/Heure', 'Utilisateur', 'Action', 'Type entité', 'ID entité', 'Description', 'Sévérité', 'Adresse IP']
            ];
            
            // Ajouter les données
            foreach ($logs as $log) {
                $csvData[] = [
                    $log['created_at_formatted'],
                    $log['user_name'] ?: 'Système',
                    $this->getActionTypeLabel($log['action_type']),
                    $log['entity_type'] ?: '',
                    $log['entity_id'] ?: '',
                    $log['description'],
                    ucfirst($log['severity']),
                    $log['ip_address'] ?: ''
                ];
            }
            
            return [
                'success' => true,
                'csv_data' => $csvData,
                'total_exported' => count($logs),
                'filename' => 'activity-logs-' . date('Y-m-d-H-i-s') . '.csv'
            ];
        } catch (Exception $e) {
            error_log("❌ ServiceActivityLogs::exportToCsv - " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur lors de l\'export: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Récupère les statistiques des logs d'activité
     * 
     * @return array Statistiques enrichies
     */
    public function getStatistics() {
        try {
            $stats = ActivityLogsControleur::getLogStats($this->conn);
            
            // Ajouter des calculs métier
            $errorRate = $stats['total_logs'] > 0 ? 
                ($stats['error_logs'] / $stats['total_logs']) * 100 : 0;
            
            $dailyAverage = $stats['week_logs'] / 7;
            
            return [
                'success' => true,
                'statistics' => [
                    'total_logs' => (int)$stats['total_logs'],
                    'error_logs' => (int)$stats['error_logs'],
                    'today_logs' => (int)$stats['today_logs'],
                    'week_logs' => (int)$stats['week_logs'],
                    'unique_users' => (int)$stats['unique_users'],
                    'error_rate_percent' => round($errorRate, 2),
                    'daily_average' => round($dailyAverage, 1),
                    'last_updated' => date('Y-m-d H:i:s')
                ]
            ];
        } catch (Exception $e) {
            error_log("❌ ServiceActivityLogs::getStatistics - " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Nettoyage des anciens logs (logique métier)
     * 
     * @param int $retentionDays Nombre de jours à conserver
     * @return array Résultat du nettoyage
     */
    public function cleanOldLogs($retentionDays = 365) {
        try {
            // Validation
            if ($retentionDays < 30) {
                return [
                    'success' => false,
                    'message' => 'La période de rétention ne peut pas être inférieure à 30 jours'
                ];
            }
            
            // Effectuer le nettoyage
            $deletedCount = ActivityLogsControleur::cleanOldLogs($this->conn, $retentionDays);
            
            // Logger cette action de maintenance
            if (class_exists('ActivityLogger')) {
                $logger = new ActivityLogger($this->conn);
                $logger->log([
                    'action_type' => 'system_maintenance',
                    'description' => "Nettoyage automatique des logs - {$deletedCount} entrées supprimées",
                    'details' => [
                        'retention_days' => $retentionDays,
                        'deleted_count' => $deletedCount
                    ],
                    'severity' => 'info'
                ]);
            }
            
            return [
                'success' => true,
                'deleted_count' => $deletedCount,
                'retention_days' => $retentionDays,
                'message' => "{$deletedCount} anciens logs supprimés avec succès"
            ];
        } catch (Exception $e) {
            error_log("❌ ServiceActivityLogs::cleanOldLogs - " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur lors du nettoyage: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Validation et nettoyage des filtres
     * 
     * @param array $filters Filtres bruts
     * @return array Filtres validés
     */
    private function validateAndCleanFilters($filters) {
        $cleanFilters = [];
        
        // Liste des filtres autorisés
        $allowedFilters = [
            'user_id', 'action_type', 'severity', 'user_name', 
            'entity_type', 'entity_id', 'date_from', 'date_to'
        ];
        
        foreach ($allowedFilters as $filter) {
            if (isset($filters[$filter]) && !empty($filters[$filter])) {
                $cleanFilters[$filter] = trim($filters[$filter]);
            }
        }
        
        // Validation spécifique des dates
        if (isset($cleanFilters['date_from'])) {
            if (!$this->isValidDate($cleanFilters['date_from'])) {
                unset($cleanFilters['date_from']);
            }
        }
        
        if (isset($cleanFilters['date_to'])) {
            if (!$this->isValidDate($cleanFilters['date_to'])) {
                unset($cleanFilters['date_to']);
            }
        }
        
        // Validation des énumérations
        $validSeverities = ['info', 'warning', 'error', 'critical'];
        if (isset($cleanFilters['severity']) && !in_array($cleanFilters['severity'], $validSeverities)) {
            unset($cleanFilters['severity']);
        }
        
        return $cleanFilters;
    }
    
    /**
     * Enrichit les logs avec des métadonnées supplémentaires
     * 
     * @param array $logs Logs bruts
     * @return array Logs enrichis
     */
    private function enrichLogs($logs) {
        foreach ($logs as &$log) {
            // Ajouter des labels lisibles
            $log['action_type_label'] = $this->getActionTypeLabel($log['action_type']);
            $log['severity_label'] = ucfirst($log['severity']);
            
            // Ajouter des indicateurs visuels
            $log['severity_color'] = $this->getSeverityColor($log['severity']);
            $log['action_icon'] = $this->getActionIcon($log['action_type']);
            
            // Formatter les détails s'ils existent
            if ($log['details'] && is_array($log['details'])) {
                $log['details_formatted'] = $this->formatDetails($log['details']);
            }
        }
        
        return $logs;
    }
    
    /**
     * Validation d'une date
     */
    private function isValidDate($date) {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
    
    /**
     * Labels traduits pour les types d'action
     */
    private function getActionTypeLabel($actionType) {
        $labels = [
            'auth_login' => 'Connexion',
            'auth_logout' => 'Déconnexion',
            'auth_failed' => 'Échec connexion',
            'auth_password_reset' => 'Reset mot de passe',
            'user_create' => 'Création utilisateur',
            'user_update' => 'Modification utilisateur',
            'user_delete' => 'Suppression utilisateur',
            'user_role_change' => 'Changement rôle',
            'facture_create' => 'Création facture',
            'facture_update' => 'Modification facture',
            'facture_delete' => 'Suppression facture',
            'facture_send' => 'Envoi facture',
            'client_create' => 'Création client',
            'client_update' => 'Modification client',
            'client_delete' => 'Suppression client',
            'system_error' => 'Erreur système',
            'system_backup' => 'Sauvegarde',
            'system_maintenance' => 'Maintenance',
            'access_denied' => 'Accès refusé'
        ];
        
        return $labels[$actionType] ?? $actionType;
    }
    
    /**
     * Couleurs pour les niveaux de sévérité
     */
    private function getSeverityColor($severity) {
        $colors = [
            'info' => '#17a2b8',
            'warning' => '#ffc107',
            'error' => '#dc3545',
            'critical' => '#721c24'
        ];
        
        return $colors[$severity] ?? '#6c757d';
    }
    
    /**
     * Icônes pour les types d'action
     */
    private function getActionIcon($actionType) {
        $icons = [
            'auth_login' => 'login',
            'auth_logout' => 'logout',
            'auth_failed' => 'lock',
            'user_create' => 'user-plus',
            'user_update' => 'user-edit',
            'user_delete' => 'user-minus',
            'facture_create' => 'file-plus',
            'facture_update' => 'file-edit',
            'facture_delete' => 'file-minus',
            'system_error' => 'alert-circle',
            'system_backup' => 'database',
            'system_maintenance' => 'tool'
        ];
        
        return $icons[$actionType] ?? 'activity';
    }
    
    /**
     * Formate les détails pour affichage
     */
    private function formatDetails($details) {
        $formatted = [];
        foreach ($details as $key => $value) {
            $formatted[] = ucfirst(str_replace('_', ' ', $key)) . ': ' . 
                          (is_array($value) ? json_encode($value) : $value);
        }
        return implode(', ', $formatted);
    }
}
?>