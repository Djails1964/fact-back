<?php
/**
 * ActivityLogsControleur.php - Contrôleur avec validation des tailles de colonnes
 * Version avec constantes d'application
 * Emplacement: fact-back/controllers/ActivityLogsControleur.php
 */

require_once realpath(__DIR__ . '/../constants/ActivityLogsConstants.php');

class ActivityLogsControleur {
    
    // Limites de taille des colonnes (basées sur la nouvelle structure)
    const MAX_ACTION_TYPE_LENGTH = 100;     // Nouvelle taille après migration
    const MAX_ENTITY_TYPE_LENGTH = 100;     // Nouvelle taille après migration
    const MAX_USER_NAME_LENGTH = 255;       // Nouvelle taille après migration
    const MAX_DESCRIPTION_LENGTH = 2000;    // TEXT peut contenir plus
    const MAX_IP_LENGTH = 45;              // IPv6 max length
    
    /**
     * ✅ CORRIGÉ: Récupère les logs d'activité avec filtres et pagination
     */
    public static function getLogs($conn, $filters = [], $limit = 50, $offset = 0) {
        try {
            $whereConditions = [];
            $params = [];
            
            // Construction des filtres
            if (!empty($filters['user_id'])) {
                $whereConditions[] = "user_id = ?";
                $params[] = $filters['user_id'];
            }
            
            if (!empty($filters['action_type'])) {
                $types = array_map('trim', explode(',', $filters['action_type']));
                if (count($types) === 1) {
                    $whereConditions[] = "action_type = ?";
                    $params[] = $types[0];
                } else {
                    $placeholders = implode(',', array_fill(0, count($types), '?'));
                    $whereConditions[] = "action_type IN ({$placeholders})";
                    foreach ($types as $t) { $params[] = $t; }
                }
            }
            
            if (!empty($filters['severity'])) {
                $whereConditions[] = "severity = ?";
                $params[] = $filters['severity'];
            }
            
            if (!empty($filters['user_name'])) {
                $whereConditions[] = "user_name LIKE ?";
                $params[] = '%' . $filters['user_name'] . '%';
            }
            
            if (!empty($filters['entity_type'])) {
                $whereConditions[] = "entity_type = ?";
                $params[] = $filters['entity_type'];
            }
            
            if (!empty($filters['entity_id'])) {
                $whereConditions[] = "entity_id = ?";
                $params[] = $filters['entity_id'];
            }
            
            if (!empty($filters['date_from'])) {
                $whereConditions[] = "created_at >= ?";
                $params[] = $filters['date_from'] . ' 00:00:00';
            }
            
            if (!empty($filters['date_to'])) {
                $whereConditions[] = "created_at <= ?";
                $params[] = $filters['date_to'] . ' 23:59:59';
            }
            
            $whereClause = empty($whereConditions) ? '' : 'WHERE ' . implode(' AND ', $whereConditions);
            
            $limit = (int)$limit;
            $offset = (int)$offset;
            
            $sql = "SELECT id, user_id, user_name, action_type, entity_type, entity_id, 
                           description, details, ip_address, user_agent, severity,
                           DATE_FORMAT(created_at, '%d/%m/%Y %H:%i:%s') as created_at_formatted,
                           created_at
                    FROM activity_logs 
                    {$whereClause}
                    ORDER BY created_at DESC 
                    LIMIT {$limit} OFFSET {$offset}";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Décoder les détails JSON
            foreach ($logs as &$log) {
                if ($log['details']) {
                    $log['details'] = json_decode($log['details'], true);
                }
            }
            
            return $logs;
        } catch (PDOException $e) {
            error_log("❌ ActivityLogsControleur::getLogs - Erreur SQL: " . $e->getMessage());
            error_log("❌ SQL généré: " . ($sql ?? 'SQL non défini'));
            throw new Exception("Erreur lors de la récupération des logs d'activité");
        }
    }
    
    /**
     * ✅ CORRIGÉ: Compte le nombre total de logs avec filtres
     */
    public static function countLogs($conn, $filters = []) {
        try {
            $whereConditions = [];
            $params = [];
            
            // Mêmes filtres que getLogs()
            if (!empty($filters['user_id'])) {
                $whereConditions[] = "user_id = ?";
                $params[] = $filters['user_id'];
            }
            
            if (!empty($filters['action_type'])) {
                $types = array_map('trim', explode(',', $filters['action_type']));
                if (count($types) === 1) {
                    $whereConditions[] = "action_type = ?";
                    $params[] = $types[0];
                } else {
                    $placeholders = implode(',', array_fill(0, count($types), '?'));
                    $whereConditions[] = "action_type IN ({$placeholders})";
                    foreach ($types as $t) { $params[] = $t; }
                }
            }
            
            if (!empty($filters['severity'])) {
                $whereConditions[] = "severity = ?";
                $params[] = $filters['severity'];
            }
            
            if (!empty($filters['user_name'])) {
                $whereConditions[] = "user_name LIKE ?";
                $params[] = '%' . $filters['user_name'] . '%';
            }
            
            if (!empty($filters['entity_type'])) {
                $whereConditions[] = "entity_type = ?";
                $params[] = $filters['entity_type'];
            }
            
            if (!empty($filters['entity_id'])) {
                $whereConditions[] = "entity_id = ?";
                $params[] = $filters['entity_id'];
            }
            
            if (!empty($filters['date_from'])) {
                $whereConditions[] = "created_at >= ?";
                $params[] = $filters['date_from'] . ' 00:00:00';
            }
            
            if (!empty($filters['date_to'])) {
                $whereConditions[] = "created_at <= ?";
                $params[] = $filters['date_to'] . ' 23:59:59';
            }
            
            $whereClause = empty($whereConditions) ? '' : 'WHERE ' . implode(' AND ', $whereConditions);
            
            $sql = "SELECT COUNT(*) as total FROM activity_logs {$whereClause}";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("❌ ActivityLogsControleur::countLogs - Erreur SQL: " . $e->getMessage());
            throw new Exception("Erreur lors du comptage des logs d'activité");
        }
    }
    
    /**
     * ✅ CORRIGÉ: Insère un nouveau log avec validation des tailles
     */
    public static function insertLog($conn, $data) {
        try {
            // ✅ VALIDATION ET TRONCATURE DES DONNÉES
            $cleanData = self::validateAndTruncateLogData($data);
            
            $sql = "INSERT INTO activity_logs 
                    (user_id, user_name, action_type, entity_type, entity_id, 
                     description, details, ip_address, user_agent, severity) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            
            $details = isset($cleanData['details']) ? json_encode($cleanData['details']) : null;
            
            $result = $stmt->execute([
                $cleanData['user_id'],
                $cleanData['user_name'],
                $cleanData['action_type'],
                $cleanData['entity_type'],
                $cleanData['entity_id'],
                $cleanData['description'],
                $details,
                $cleanData['ip_address'],
                $cleanData['user_agent'],
                $cleanData['severity']
            ]);
            
            if ($result) {
                error_log("✅ ActivityLogsControleur::insertLog - Log inséré: " . $cleanData['action_type']);
            }
            
            return $result;
        } catch (PDOException $e) {
            error_log("❌ ActivityLogsControleur::insertLog - Erreur SQL: " . $e->getMessage());
            error_log("❌ Données: " . print_r($data, true));
            return false;
        }
    }
    
    /**
     * ✅ NOUVEAU: Valide et tronque les données avant insertion
     * 
     * @param array $data Données brutes
     * @return array Données nettoyées
     */
    private static function validateAndTruncateLogData($data) {

        if (is_dev_mode()) { 
            error_log("✅ ActivityLogsControleur::validateAndTruncateLogData - Validation des données de log");
            error_log("✅ Données reçues: " . print_r($data, true));
        }

        $clean = [];
        
        // user_id - doit être un entier ou null
        $clean['user_id'] = isset($data['user_id']) && is_numeric($data['user_id']) ? 
            (int)$data['user_id'] : null;
        
        // user_name - tronquer si trop long
        $clean['user_name'] = isset($data['user_name']) ? 
            self::truncateString($data['user_name'], self::MAX_USER_NAME_LENGTH) : null;
        
        // action_type - obligatoire, tronquer si trop long
        if (empty($data['action_type'])) {
            throw new InvalidArgumentException('action_type est obligatoire');
        }
        $clean['action_type'] = self::truncateString($data['action_type'], self::MAX_ACTION_TYPE_LENGTH);
        
        // entity_type - optionnel, tronquer si présent
        $clean['entity_type'] = isset($data['entity_type']) ? 
            self::truncateString($data['entity_type'], self::MAX_ENTITY_TYPE_LENGTH) : null;
        
        // entity_id - doit être un entier ou null
        $clean['entity_id'] = isset($data['entity_id']) && is_numeric($data['entity_id']) ? 
            (int)$data['entity_id'] : null;
        
        // description - obligatoire, tronquer si trop long
        if (empty($data['description'])) {
            throw new InvalidArgumentException('description est obligatoire');
        }
        $clean['description'] = self::truncateString($data['description'], self::MAX_DESCRIPTION_LENGTH);
        
        // details - garder tel quel (sera encodé en JSON)
        $clean['details'] = $data['details'] ?? null;
        
        // ip_address - valider et tronquer
        $clean['ip_address'] = isset($data['ip_address']) ? 
            self::truncateString($data['ip_address'], self::MAX_IP_LENGTH) : 
            ($_SERVER['REMOTE_ADDR'] ?? null);
        
        // user_agent - tronquer si présent
        $clean['user_agent'] = isset($data['user_agent']) ? 
            self::truncateString($data['user_agent'], 500) : // Limite raisonnable pour user_agent
            ($_SERVER['HTTP_USER_AGENT'] ?? null);
        
        // severity - valider énumération avec les constantes ou fallback
        if (isset($data['severity']) && ActivityLogsConstants::isValidSeverity($data['severity'])) {
            $clean['severity'] = $data['severity'];
        } else {
            $clean['severity'] = ActivityLogsConstants::SEVERITY_INFO; // Valeur par défaut
        }
        
        return $clean;
    }
    
    /**
     * ✅ NOUVEAU: Utilitaire pour tronquer les chaînes de caractères
     * 
     * @param string $string Chaîne à tronquer
     * @param int $maxLength Longueur maximale
     * @return string Chaîne tronquée
     */
    private static function truncateString($string, $maxLength) {
        if (empty($string)) {
            return null;
        }
        
        $string = trim($string);
        
        if (strlen($string) <= $maxLength) {
            return $string;
        }
        
        // Tronquer et ajouter "..." pour indiquer la troncature
        $truncated = substr($string, 0, $maxLength - 3) . '...';
        
        // Logger la troncature
        error_log("⚠️ ActivityLogsControleur - Chaîne tronquée: " . 
                 substr($string, 0, 50) . " -> " . 
                 substr($truncated, 0, 50));
        
        return $truncated;
    }
    
    /**
     * ✅ NOUVEAU: Récupère les logs pour une entité spécifique
     */
    public static function getEntityLogs($conn, $entityType, $entityId, $limit = 20) {
        return self::getLogs($conn, [
            'entity_type' => $entityType,
            'entity_id' => $entityId
        ], $limit, 0);
    }
    
    /**
     * Récupère les logs d'un utilisateur spécifique
     */
    public static function getUserLogs($conn, $userId, $limit = 50) {
        return self::getLogs($conn, ['user_id' => $userId], $limit, 0);
    }
    
    /**
     * Récupère les logs par type d'action
     */
    public static function getLogsByAction($conn, $actionType, $limit = 50) {
        return self::getLogs($conn, ['action_type' => $actionType], $limit, 0);
    }
    
    /**
     * Récupère les logs par sévérité
     */
    public static function getLogsBySeverity($conn, $severity, $limit = 50) {
        return self::getLogs($conn, ['severity' => $severity], $limit, 0);
    }
    
    /**
     * Récupère les logs d'une période donnée
     */
    public static function getLogsByDateRange($conn, $dateFrom, $dateTo, $limit = 50) {
        return self::getLogs($conn, [
            'date_from' => $dateFrom,
            'date_to' => $dateTo
        ], $limit, 0);
    }
    
    /**
     * ✅ NOUVEAU: Récupère les logs des dernières 24 heures
     */
    public static function getRecentLogs($conn, $limit = 100) {
        try {
            $limit = (int)$limit;
            
            $sql = "SELECT id, user_id, user_name, action_type, entity_type, entity_id, 
                           description, details, ip_address, user_agent, severity,
                           DATE_FORMAT(created_at, '%d/%m/%Y %H:%i:%s') as created_at_formatted,
                           created_at
                    FROM activity_logs 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                    ORDER BY created_at DESC 
                    LIMIT {$limit}";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Décoder les détails JSON
            foreach ($logs as &$log) {
                if ($log['details']) {
                    $log['details'] = json_decode($log['details'], true);
                }
            }
            
            return $logs;
        } catch (PDOException $e) {
            error_log("❌ ActivityLogsControleur::getRecentLogs - Erreur SQL: " . $e->getMessage());
            throw new Exception("Erreur lors de la récupération des logs récents");
        }
    }
    
    /**
     * Supprime les anciens logs (pour nettoyage périodique)
     */
    public static function cleanOldLogs($conn, $daysOld = 365) {
        try {
            $sql = "DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$daysOld]);
            
            $deletedRows = $stmt->rowCount();
            error_log("🧹 ActivityLogsControleur::cleanOldLogs - {$deletedRows} anciens logs supprimés");
            
            return $deletedRows;
        } catch (PDOException $e) {
            error_log("❌ ActivityLogsControleur::cleanOldLogs - Erreur SQL: " . $e->getMessage());
            throw new Exception("Erreur lors du nettoyage des anciens logs");
        }
    }
    
    /**
     * ✅ AMÉLIORÉ: Récupère les statistiques des logs avec plus de détails
     */
    public static function getLogStats($conn) {
        try {
            $sql = "SELECT 
                        COUNT(*) as total_logs,
                        COUNT(CASE WHEN severity = 'error' OR severity = 'critical' THEN 1 END) as error_logs,
                        COUNT(CASE WHEN severity = 'critical' THEN 1 END) as critical_logs,
                        COUNT(CASE WHEN severity = 'warning' THEN 1 END) as warning_logs,
                        COUNT(CASE WHEN severity = 'info' THEN 1 END) as info_logs,
                        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 1 END) as today_logs,
                        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as week_logs,
                        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as month_logs,
                        COUNT(DISTINCT user_id) as unique_users,
                        COUNT(DISTINCT entity_type) as entity_types_count,
                        MIN(created_at) as oldest_log,
                        MAX(created_at) as newest_log
                    FROM activity_logs";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Ajouter des statistiques calculées
            $stats['error_rate'] = $stats['total_logs'] > 0 ? 
                round(($stats['error_logs'] / $stats['total_logs']) * 100, 2) : 0;
            
            $stats['daily_average'] = $stats['week_logs'] > 0 ? 
                round($stats['week_logs'] / 7, 1) : 0;
            
            return $stats;
        } catch (PDOException $e) {
            error_log("❌ ActivityLogsControleur::getLogStats - Erreur SQL: " . $e->getMessage());
            throw new Exception("Erreur lors de la récupération des statistiques");
        }
    }
    
    /**
     * ✅ NOUVEAU: Récupère les actions les plus fréquentes
     */
    public static function getTopActions($conn, $limit = 10, $days = 30) {
        try {
            $limit = (int)$limit;
            $days = (int)$days;
            
            $sql = "SELECT 
                        action_type,
                        COUNT(*) as count,
                        COUNT(CASE WHEN severity = 'error' OR severity = 'critical' THEN 1 END) as error_count,
                        MAX(created_at) as last_occurrence
                    FROM activity_logs 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)
                    GROUP BY action_type
                    ORDER BY count DESC
                    LIMIT {$limit}";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("❌ ActivityLogsControleur::getTopActions - Erreur SQL: " . $e->getMessage());
            throw new Exception("Erreur lors de la récupération des actions fréquentes");
        }
    }
    
    /**
     * ✅ NOUVEAU: Récupère les utilisateurs les plus actifs
     */
    public static function getTopUsers($conn, $limit = 10, $days = 30) {
        try {
            $limit = (int)$limit;
            $days = (int)$days;
            
            $sql = "SELECT 
                        user_id,
                        user_name,
                        COUNT(*) as activity_count,
                        COUNT(CASE WHEN severity = 'error' OR severity = 'critical' THEN 1 END) as error_count,
                        MAX(created_at) as last_activity,
                        COUNT(DISTINCT action_type) as distinct_actions
                    FROM activity_logs 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)
                      AND user_id IS NOT NULL
                    GROUP BY user_id, user_name
                    ORDER BY activity_count DESC
                    LIMIT {$limit}";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("❌ ActivityLogsControleur::getTopUsers - Erreur SQL: " . $e->getMessage());
            throw new Exception("Erreur lors de la récupération des utilisateurs actifs");
        }
    }
    
    /**
     * ✅ NOUVEAU: Récupère l'évolution des logs par jour
     */
    public static function getDailyEvolution($conn, $days = 30) {
        try {
            $days = (int)$days;
            
            $sql = "SELECT 
                        DATE(created_at) as date,
                        COUNT(*) as total_logs,
                        COUNT(CASE WHEN severity = 'error' OR severity = 'critical' THEN 1 END) as error_logs,
                        COUNT(DISTINCT user_id) as active_users,
                        COUNT(DISTINCT action_type) as distinct_actions
                    FROM activity_logs 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)
                    GROUP BY DATE(created_at)
                    ORDER BY date DESC";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("❌ ActivityLogsControleur::getDailyEvolution - Erreur SQL: " . $e->getMessage());
            throw new Exception("Erreur lors de la récupération de l'évolution");
        }
    }
}
?>