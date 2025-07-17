<?php
/**
 * ActivityLogsControleur.php - Contrôleur pour l'accès aux données des logs d'activité
 * Emplacement: fact-back/controllers/ActivityLogsControleur.php
 */

class ActivityLogsControleur {
    
    /**
     * Récupère les logs d'activité avec filtres et pagination
     * 
     * @param PDO $conn Connexion à la base de données
     * @param array $filters Filtres à appliquer
     * @param int $limit Nombre de résultats
     * @param int $offset Décalage
     * @return array Liste des logs
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
                $whereConditions[] = "action_type = ?";
                $params[] = $filters['action_type'];
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
            
            $sql = "SELECT id, user_id, user_name, action_type, entity_type, entity_id, 
                           description, details, ip_address, user_agent, severity,
                           DATE_FORMAT(created_at, '%d/%m/%Y %H:%i:%s') as created_at_formatted,
                           created_at
                    FROM activity_logs 
                    {$whereClause}
                    ORDER BY created_at DESC 
                    LIMIT ? OFFSET ?";
            
            $params[] = $limit;
            $params[] = $offset;
            
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
            throw new Exception("Erreur lors de la récupération des logs d'activité");
        }
    }
    
    /**
     * Compte le nombre total de logs avec filtres
     * 
     * @param PDO $conn Connexion à la base de données
     * @param array $filters Filtres à appliquer
     * @return int Nombre total de logs
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
                $whereConditions[] = "action_type = ?";
                $params[] = $filters['action_type'];
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
     * Insère un nouveau log d'activité
     * 
     * @param PDO $conn Connexion à la base de données
     * @param array $data Données du log
     * @return bool Succès de l'insertion
     */
    public static function insertLog($conn, $data) {
        try {
            $sql = "INSERT INTO activity_logs 
                    (user_id, user_name, action_type, entity_type, entity_id, 
                     description, details, ip_address, user_agent, severity) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            
            $details = isset($data['details']) ? json_encode($data['details']) : null;
            
            $result = $stmt->execute([
                $data['user_id'] ?? null,
                $data['user_name'] ?? null,
                $data['action_type'],
                $data['entity_type'] ?? null,
                $data['entity_id'] ?? null,
                $data['description'],
                $details,
                $data['ip_address'] ?? $_SERVER['REMOTE_ADDR'] ?? null,
                $data['user_agent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? null,
                $data['severity'] ?? 'info'
            ]);
            
            if ($result) {
                error_log("✅ ActivityLogsControleur::insertLog - Log inséré: " . $data['action_type']);
            }
            
            return $result;
        } catch (PDOException $e) {
            error_log("❌ ActivityLogsControleur::insertLog - Erreur SQL: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Récupère les logs d'un utilisateur spécifique
     * 
     * @param PDO $conn Connexion à la base de données
     * @param int $userId ID de l'utilisateur
     * @param int $limit Limite de résultats
     * @return array Logs de l'utilisateur
     */
    public static function getUserLogs($conn, $userId, $limit = 50) {
        return self::getLogs($conn, ['user_id' => $userId], $limit, 0);
    }
    
    /**
     * Récupère les logs par type d'action
     * 
     * @param PDO $conn Connexion à la base de données
     * @param string $actionType Type d'action
     * @param int $limit Limite de résultats
     * @return array Logs filtrés par action
     */
    public static function getLogsByAction($conn, $actionType, $limit = 50) {
        return self::getLogs($conn, ['action_type' => $actionType], $limit, 0);
    }
    
    /**
     * Récupère les logs par sévérité
     * 
     * @param PDO $conn Connexion à la base de données
     * @param string $severity Niveau de sévérité
     * @param int $limit Limite de résultats
     * @return array Logs filtrés par sévérité
     */
    public static function getLogsBySeverity($conn, $severity, $limit = 50) {
        return self::getLogs($conn, ['severity' => $severity], $limit, 0);
    }
    
    /**
     * Récupère les logs d'une période donnée
     * 
     * @param PDO $conn Connexion à la base de données
     * @param string $dateFrom Date de début (Y-m-d)
     * @param string $dateTo Date de fin (Y-m-d)
     * @param int $limit Limite de résultats
     * @return array Logs de la période
     */
    public static function getLogsByDateRange($conn, $dateFrom, $dateTo, $limit = 50) {
        return self::getLogs($conn, [
            'date_from' => $dateFrom,
            'date_to' => $dateTo
        ], $limit, 0);
    }
    
    /**
     * Supprime les anciens logs (pour nettoyage périodique)
     * 
     * @param PDO $conn Connexion à la base de données
     * @param int $daysOld Nombre de jours à conserver
     * @return int Nombre de logs supprimés
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
     * Récupère les statistiques des logs
     * 
     * @param PDO $conn Connexion à la base de données
     * @return array Statistiques
     */
    public static function getLogStats($conn) {
        try {
            $sql = "SELECT 
                        COUNT(*) as total_logs,
                        COUNT(CASE WHEN severity = 'error' OR severity = 'critical' THEN 1 END) as error_logs,
                        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 1 END) as today_logs,
                        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as week_logs,
                        COUNT(DISTINCT user_id) as unique_users
                    FROM activity_logs";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("❌ ActivityLogsControleur::getLogStats - Erreur SQL: " . $e->getMessage());
            throw new Exception("Erreur lors de la récupération des statistiques");
        }
    }
}
?>