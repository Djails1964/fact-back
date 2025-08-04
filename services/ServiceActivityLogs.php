<?php
/**
 * ServiceActivityLogs.php - Service pour la gestion des logs d'activité - Version complète
 * Emplacement: fact-back/services/ServiceActivityLogs.php
 */

require_once realpath(__DIR__ . '/../controllers/ActivityLogsControleur.php');
require_once realpath(__DIR__ . '/../constants/ActivityLogsConstants.php');

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
     * ✅ NOUVEAU: Récupère les actions les plus fréquentes
     * 
     * @param int $limit Nombre d'actions à retourner
     * @param int $days Période en jours
     * @return array Actions les plus fréquentes
     */
    public function getTopActions($limit = 10, $days = 30) {
        try {
            $topActions = ActivityLogsControleur::getTopActions($this->conn, $limit, $days);
            
            // Enrichir les données avec les labels
            foreach ($topActions as &$action) {
                $action['action_label'] = $this->getActionTypeLabel($action['action_type']);
                $action['error_rate'] = $action['count'] > 0 ? 
                    round(($action['error_count'] / $action['count']) * 100, 1) : 0;
            }
            
            return [
                'success' => true,
                'actions' => $topActions,
                'period_days' => $days,
                'total_actions' => count($topActions)
            ];
        } catch (Exception $e) {
            error_log("❌ ServiceActivityLogs::getTopActions - " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération des actions fréquentes: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * ✅ NOUVEAU: Récupère les utilisateurs les plus actifs
     * 
     * @param int $limit Nombre d'utilisateurs à retourner
     * @param int $days Période en jours
     * @return array Utilisateurs les plus actifs
     */
    public function getTopUsers($limit = 10, $days = 30) {
        try {
            $topUsers = ActivityLogsControleur::getTopUsers($this->conn, $limit, $days);
            
            // Enrichir les données avec des calculs métier
            foreach ($topUsers as &$user) {
                $user['error_rate'] = $user['activity_count'] > 0 ? 
                    round(($user['error_count'] / $user['activity_count']) * 100, 1) : 0;
                $user['daily_average'] = round($user['activity_count'] / $days, 1);
            }
            
            return [
                'success' => true,
                'users' => $topUsers,
                'period_days' => $days,
                'total_users' => count($topUsers)
            ];
        } catch (Exception $e) {
            error_log("❌ ServiceActivityLogs::getTopUsers - " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération des utilisateurs actifs: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * ✅ NOUVEAU: Récupère l'évolution quotidienne des logs
     * 
     * @param int $days Nombre de jours à analyser
     * @return array Évolution quotidienne
     */
    public function getDailyEvolution($days = 30) {
        try {
            $evolution = ActivityLogsControleur::getDailyEvolution($this->conn, $days);
            
            // Enrichir avec des calculs métier
            $totalLogs = 0;
            $totalErrors = 0;
            
            foreach ($evolution as &$day) {
                $day['error_rate'] = $day['total_logs'] > 0 ? 
                    round(($day['error_logs'] / $day['total_logs']) * 100, 1) : 0;
                
                $totalLogs += $day['total_logs'];
                $totalErrors += $day['error_logs'];
            }
            
            return [
                'success' => true,
                'evolution' => $evolution,
                'period_days' => $days,
                'summary' => [
                    'total_logs' => $totalLogs,
                    'total_errors' => $totalErrors,
                    'average_per_day' => round($totalLogs / $days, 1),
                    'global_error_rate' => $totalLogs > 0 ? round(($totalErrors / $totalLogs) * 100, 1) : 0
                ]
            ];
        } catch (Exception $e) {
            error_log("❌ ServiceActivityLogs::getDailyEvolution - " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération de l\'évolution: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * ✅ NOUVEAU: Marque un log comme résolu (pour les erreurs)
     * 
     * @param int $logId ID du log
     * @param int $userId ID de l'utilisateur qui résout
     * @return array Résultat de l'opération
     */
    public function markLogAsResolved($logId, $userId) {
        try {
            // TODO: Ajouter une colonne 'resolved_at' et 'resolved_by' à la table si nécessaire
            // Pour l'instant, on simule avec un log d'activité
            
            if (class_exists('ActivityLogger')) {
                $logger = new ActivityLogger($this->conn);
                $logger->log([
                    'user_id' => $userId,
                    'action_type' => 'log_resolved',
                    'entity_type' => 'activity_log',
                    'entity_id' => $logId,
                    'description' => "Log #{$logId} marqué comme résolu",
                    'severity' => 'info'
                ]);
            }
            
            return [
                'success' => true,
                'message' => 'Log marqué comme résolu',
                'log_id' => $logId,
                'resolved_by' => $userId,
                'resolved_at' => date('Y-m-d H:i:s')
            ];
        } catch (Exception $e) {
            error_log("❌ ServiceActivityLogs::markLogAsResolved - " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur lors de la résolution du log: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * ✅ NOUVEAU: Archive un log (le masque de la vue normale)
     * 
     * @param int $logId ID du log
     * @param int $userId ID de l'utilisateur qui archive
     * @return array Résultat de l'opération
     */
    public function archiveLog($logId, $userId) {
        try {
            // TODO: Ajouter une colonne 'archived_at' et 'archived_by' à la table si nécessaire
            // Pour l'instant, on simule avec un log d'activité
            
            if (class_exists('ActivityLogger')) {
                $logger = new ActivityLogger($this->conn);
                $logger->log([
                    'user_id' => $userId,
                    'action_type' => 'log_archived',
                    'entity_type' => 'activity_log',
                    'entity_id' => $logId,
                    'description' => "Log #{$logId} archivé",
                    'severity' => 'info'
                ]);
            }
            
            return [
                'success' => true,
                'message' => 'Log archivé',
                'log_id' => $logId,
                'archived_by' => $userId,
                'archived_at' => date('Y-m-d H:i:s')
            ];
        } catch (Exception $e) {
            error_log("❌ ServiceActivityLogs::archiveLog - " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur lors de l\'archivage du log: ' . $e->getMessage()
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
        
        // Validation des énumérations avec les constantes
        if (isset($cleanFilters['severity']) && !ActivityLogsConstants::isValidSeverity($cleanFilters['severity'])) {
            unset($cleanFilters['severity']);
        }
        
        // Validation de l'action_type
        if (isset($cleanFilters['action_type']) && !ActivityLogsConstants::isValidActionType($cleanFilters['action_type'])) {
            unset($cleanFilters['action_type']);
        }
        
        // Validation de l'entity_type
        if (isset($cleanFilters['entity_type']) && !ActivityLogsConstants::isValidEntityType($cleanFilters['entity_type'])) {
            unset($cleanFilters['entity_type']);
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
            
            // Ajouter des métadonnées temporelles
            $log['time_ago'] = $this->getTimeAgo($log['created_at']);
            $log['is_recent'] = $this->isRecentLog($log['created_at']);
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
     * Labels traduits pour les types d'action (utilise les constantes)
     */
    private function getActionTypeLabel($actionType) {
        return ActivityLogsConstants::getActionLabel($actionType);
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
            'auth_password_reset' => 'key',
            'user_create' => 'user-plus',
            'user_update' => 'user-edit',
            'user_delete' => 'user-minus',
            'user_role_change' => 'user-check',
            'facture_create' => 'file-plus',
            'facture_update' => 'file-edit',
            'facture_delete' => 'file-minus',
            'facture_send' => 'send',
            'client_create' => 'users',
            'client_update' => 'user-edit',
            'client_delete' => 'user-x',
            'paiement_create' => 'credit-card',
            'paiement_update' => 'edit',
            'paiement_delete' => 'trash',
            'system_error' => 'alert-circle',
            'system_backup' => 'database',
            'system_maintenance' => 'tool',
            'access_denied' => 'shield-off',
            'log_resolved' => 'check-circle',
            'log_archived' => 'archive'
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
    
    /**
     * Calcule le temps écoulé depuis un log
     */
    private function getTimeAgo($datetime) {
        $time = time() - strtotime($datetime);
        
        if ($time < 60) return 'à l\'instant';
        if ($time < 3600) return floor($time/60) . ' min';
        if ($time < 86400) return floor($time/3600) . ' h';
        if ($time < 2592000) return floor($time/86400) . ' j';
        if ($time < 31536000) return floor($time/2592000) . ' mois';
        
        return floor($time/31536000) . ' an' . (floor($time/31536000) > 1 ? 's' : '');
    }
    
    /**
     * Détermine si un log est récent (moins de 1 heure)
     */
    private function isRecentLog($datetime) {
        return (time() - strtotime($datetime)) < 3600;
    }
    
    /**
     * ✅ NOUVEAU: Récupère les logs par utilisateur (pour profil utilisateur)
     * 
     * @param int $userId ID de l'utilisateur
     * @param int $limit Limite de résultats
     * @return array Logs de l'utilisateur
     */
    public function getUserLogs($userId, $limit = 50) {
        try {
            $filters = ['user_id' => $userId];
            return $this->getLogs($filters, $limit, 0);
        } catch (Exception $e) {
            error_log("❌ ServiceActivityLogs::getUserLogs - " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération des logs utilisateur: ' . $e->getMessage(),
                'logs' => [],
                'total' => 0
            ];
        }
    }
    
    /**
     * ✅ NOUVEAU: Récupère les logs par type d'action
     * 
     * @param string $actionType Type d'action
     * @param int $limit Limite de résultats
     * @return array Logs filtrés
     */
    public function getLogsByAction($actionType, $limit = 50) {
        try {
            $filters = ['action_type' => $actionType];
            return $this->getLogs($filters, $limit, 0);
        } catch (Exception $e) {
            error_log("❌ ServiceActivityLogs::getLogsByAction - " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération des logs par action: ' . $e->getMessage(),
                'logs' => [],
                'total' => 0
            ];
        }
    }
    
    /**
     * ✅ NOUVEAU: Récupère les logs d'erreur pour le monitoring
     * 
     * @param int $hours Nombre d'heures à analyser
     * @param int $limit Limite de résultats
     * @return array Logs d'erreur récents
     */
    public function getRecentErrors($hours = 24, $limit = 100) {
        try {
            $dateFrom = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));
            
            // Utiliser le contrôleur directement pour une requête spécifique aux erreurs
            $logs = ActivityLogsControleur::getLogs($this->conn, [
                'severity' => 'error',
                'date_from' => substr($dateFrom, 0, 10) // Format Y-m-d
            ], $limit, 0);
            
            // Enrichir les logs
            $enrichedLogs = $this->enrichLogs($logs);
            
            return [
                'success' => true,
                'logs' => $enrichedLogs,
                'total' => count($enrichedLogs),
                'period_hours' => $hours,
                'message' => count($enrichedLogs) > 0 ? 
                    count($enrichedLogs) . " erreur(s) détectée(s) dans les {$hours} dernières heures" :
                    "Aucune erreur détectée dans les {$hours} dernières heures"
            ];
        } catch (Exception $e) {
            error_log("❌ ServiceActivityLogs::getRecentErrors - " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur lors de la récupération des erreurs récentes: ' . $e->getMessage(),
                'logs' => [],
                'total' => 0
            ];
        }
    }
    
    /**
     * ✅ NOUVEAU: Résumé de sécurité (tentatives de connexion, accès refusés, etc.)
     * 
     * @param int $days Période d'analyse
     * @return array Résumé de sécurité
     */
    public function getSecuritySummary($days = 7) {
        try {
            $securityActions = [
                'auth_failed', 'access_denied', 'auth_login', 
                'auth_logout', 'auth_password_reset'
            ];
            
            $summary = [];
            $totalSecurityEvents = 0;
            
            foreach ($securityActions as $action) {
                $count = ActivityLogsControleur::countLogs($this->conn, [
                    'action_type' => $action,
                    'date_from' => date('Y-m-d', strtotime("-{$days} days"))
                ]);
                
                $summary[$action] = [
                    'count' => $count,
                    'label' => $this->getActionTypeLabel($action)
                ];
                
                $totalSecurityEvents += $count;
            }
            
            // Calculer le niveau de risque
            $riskLevel = 'low';
            if ($summary['auth_failed']['count'] > 50 || $summary['access_denied']['count'] > 20) {
                $riskLevel = 'high';
            } elseif ($summary['auth_failed']['count'] > 20 || $summary['access_denied']['count'] > 10) {
                $riskLevel = 'medium';
            }
            
            return [
                'success' => true,
                'summary' => $summary,
                'total_security_events' => $totalSecurityEvents,
                'period_days' => $days,
                'risk_level' => $riskLevel,
                'recommendations' => $this->getSecurityRecommendations($riskLevel, $summary)
            ];
        } catch (Exception $e) {
            error_log("❌ ServiceActivityLogs::getSecuritySummary - " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erreur lors de la génération du résumé de sécurité: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Génère des recommandations de sécurité basées sur l'analyse
     */
    private function getSecurityRecommendations($riskLevel, $summary) {
        $recommendations = [];
        
        if ($riskLevel === 'high') {
            $recommendations[] = "🔴 Niveau de risque élevé détecté";
        }
        
        if ($summary['auth_failed']['count'] > 20) {
            $recommendations[] = "⚠️ Nombre élevé d'échecs de connexion - Vérifier les tentatives d'intrusion";
        }
        
        if ($summary['access_denied']['count'] > 10) {
            $recommendations[] = "⚠️ Accès refusés fréquents - Réviser les permissions utilisateurs";
        }
        
        if (empty($recommendations)) {
            $recommendations[] = "✅ Aucun problème de sécurité majeur détecté";
        }
        
        return $recommendations;
    }
}
?>