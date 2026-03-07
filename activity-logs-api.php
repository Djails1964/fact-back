<?php
/**
 * activity-logs-api.php - API pour les logs d'activité - Version corrigée avec architecture Service
 * Emplacement: fact-back/api/activity-logs-api.php
 */

// Utiliser la même configuration que auth-api.php
require_once realpath(__DIR__ . '/bootstrap.php');
init_api_response();

// Inclure les dépendances
require_once 'database.php';
require_once realpath(__DIR__ . '/services/ServiceActivityLogs.php');

// Debug session en mode développement (comme auth-api.php)
if (is_dev_mode()) {
    error_log("activity-logs-api.php - Méthode: " . $_SERVER['REQUEST_METHOD']);
    error_log("activity-logs-api.php - URL complète: " . $_SERVER['REQUEST_URI']);
    error_log("activity-logs-api.php - Origin: " . ($_SERVER['HTTP_ORIGIN'] ?? 'Non défini'));
    error_log("activity-logs-api.php - Paramètres GET: " . json_encode($_GET));
    error_log("activity-logs-api.php - Session ID: " . session_id());
    error_log("activity-logs-api.php - Session status: " . session_status());
    error_log("activity-logs-api.php - Session content: " . json_encode($_SESSION));
    error_log("🔍 activity-logs-api.php - Session initialisée par bootstrap.php");
}

try {
    // Vérifier la session utilisateur
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $userId = $_SESSION['user_id'] ?? null;
    $userRole = $_SESSION['user_role'] ?? null;
    $userName = $_SESSION['user_name'] ?? null;
    
    if (!$userId) {
        throw new Exception('Session utilisateur non valide', 401);
    }
    
    // Vérifier les droits admin pour consulter les logs
    if ($userRole !== 'admin' && $userRole !== 'gestionnaire') {
        throw new Exception('Droits administrateur requis pour consulter les logs', 403);
    }
    
    // Créer l'instance du service
    $serviceActivityLogs = new ServiceActivityLogs($conn);
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'GET') {
        $action = $_GET['action'] ?? 'get_logs';
        error_log("🔍 activity-logs-api.php - Action GET: {$action} - User: {$userName} (ID: {$userId})");
        
        switch ($action) {
            case 'get_logs':
                // ✅ VALIDATION: Limiter et valider les paramètres de pagination
                $limit = min(1000, max(1, (int)($_GET['limit'] ?? 50))); // Entre 1 et 1000
                $page = max(1, (int)($_GET['page'] ?? 1));
                $offset = ($page - 1) * $limit;
                
                // Construire les filtres avec validation
                $filters = [];
                
                error_log("🔍 activity-logs-api.php - Filtres GET: " . json_encode($_GET));
                if (!empty($_GET['action_type'])) {
                    $filters['action_type'] = filter_var($_GET['action_type'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                    error_log("🔍 activity-logs-api.php - Filtre action_type: " . $filters['action_type']);
                }
                
                if (!empty($_GET['severity']) && in_array($_GET['severity'], ['info', 'warning', 'error', 'critical'])) {
                    $filters['severity'] = $_GET['severity'];
                }
                
                if (!empty($_GET['user_name'])) {
                    $filters['user_name'] = filter_var($_GET['user_name'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                }
                
                if (!empty($_GET['date_from']) && DateTime::createFromFormat('Y-m-d', $_GET['date_from'])) {
                    $filters['date_from'] = $_GET['date_from'];
                }
                
                if (!empty($_GET['date_to']) && DateTime::createFromFormat('Y-m-d', $_GET['date_to'])) {
                    $filters['date_to'] = $_GET['date_to'];
                }
                
                if (!empty($_GET['user_id']) && is_numeric($_GET['user_id'])) {
                    $filters['user_id'] = (int)$_GET['user_id'];
                }
                
                if (!empty($_GET['entity_type'])) {
                    $filters['entity_type'] = filter_var($_GET['entity_type'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                }
                
                if (!empty($_GET['entity_id']) && is_numeric($_GET['entity_id'])) {
                    $filters['entity_id'] = (int)$_GET['entity_id'];
                }
                
                // ✅ ARCHITECTURE CORRECTE: Récupération via le service
                error_log("🔍 activity-logs-api.php - Appel du service getLogs avec filtres: " . json_encode($filters) . ", limit: {$limit}, offset: {$offset}");
                $result = $serviceActivityLogs->getLogs($filters, $limit, $offset);
                echo json_encode($result);
                break;
                
            case 'export_csv':
                // Export CSV avec validation des filtres
                $filters = [];
                
                // Récupérer les mêmes filtres que pour get_logs avec validation
                if (!empty($_GET['action_type'])) {
                    $filters['action_type'] = filter_var($_GET['action_type'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                }
                
                if (!empty($_GET['severity']) && in_array($_GET['severity'], ['info', 'warning', 'error', 'critical'])) {
                    $filters['severity'] = $_GET['severity'];
                }
                
                if (!empty($_GET['user_name'])) {
                    $filters['user_name'] = filter_var($_GET['user_name'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                }
                
                if (!empty($_GET['date_from']) && DateTime::createFromFormat('Y-m-d', $_GET['date_from'])) {
                    $filters['date_from'] = $_GET['date_from'];
                }
                
                if (!empty($_GET['date_to']) && DateTime::createFromFormat('Y-m-d', $_GET['date_to'])) {
                    $filters['date_to'] = $_GET['date_to'];
                }
                
                if (!empty($_GET['user_id']) && is_numeric($_GET['user_id'])) {
                    $filters['user_id'] = (int)$_GET['user_id'];
                }
                
                if (!empty($_GET['entity_type'])) {
                    $filters['entity_type'] = filter_var($_GET['entity_type'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                }
                
                if (!empty($_GET['entity_id']) && is_numeric($_GET['entity_id'])) {
                    $filters['entity_id'] = (int)$_GET['entity_id'];
                }
                
                // ✅ ARCHITECTURE CORRECTE: Export via le service
                $exportResult = $serviceActivityLogs->exportToCsv($filters);
                
                if ($exportResult['success']) {
                    // Définir les en-têtes pour le téléchargement CSV
                    header('Content-Type: text/csv; charset=utf-8');
                    header('Content-Disposition: attachment; filename="' . $exportResult['filename'] . '"');
                    
                    $output = fopen('php://output', 'w');
                    
                    // Écrire les données CSV
                    foreach ($exportResult['csv_data'] as $row) {
                        fputcsv($output, $row);
                    }
                    
                    fclose($output);
                    exit;
                } else {
                    throw new Exception($exportResult['message']);
                }
                break;
                
            case 'statistics':
                // ✅ ARCHITECTURE CORRECTE: Statistiques via le service
                $result = $serviceActivityLogs->getStatistics();
                echo json_encode($result);
                break;
                
            case 'recent':
                // ✅ NOUVEAU: Récupérer les logs récents (24h) - VIA SERVICE
                $limit = min(200, max(10, (int)($_GET['limit'] ?? 50)));
                
                // Utiliser le service avec des filtres pour les 24 dernières heures
                $filters = [
                    'date_from' => date('Y-m-d', strtotime('-1 day'))
                ];
                
                $result = $serviceActivityLogs->getLogs($filters, $limit, 0);
                
                echo json_encode([
                    'success' => $result['success'],
                    'logs' => $result['logs'] ?? [],
                    'total' => count($result['logs'] ?? []),
                    'period' => '24 heures',
                    'message' => $result['message'] ?? null
                ]);
                break;
                
            case 'top_actions':
                // ✅ NOUVEAU: Actions les plus fréquentes - AJOUTER AU SERVICE
                $limit = min(20, max(5, (int)($_GET['limit'] ?? 10)));
                $days = min(90, max(1, (int)($_GET['days'] ?? 30)));
                
                $result = $serviceActivityLogs->getTopActions($limit, $days);
                echo json_encode($result);
                break;
                
            case 'top_users':
                // ✅ NOUVEAU: Utilisateurs les plus actifs - AJOUTER AU SERVICE
                $limit = min(20, max(5, (int)($_GET['limit'] ?? 10)));
                $days = min(90, max(1, (int)($_GET['days'] ?? 30)));
                
                $result = $serviceActivityLogs->getTopUsers($limit, $days);
                echo json_encode($result);
                break;
                
            case 'daily_evolution':
                // ✅ NOUVEAU: Évolution quotidienne - AJOUTER AU SERVICE
                $days = min(90, max(7, (int)($_GET['days'] ?? 30)));
                
                $result = $serviceActivityLogs->getDailyEvolution($days);
                echo json_encode($result);
                break;
                
            case 'entity_logs':
                // ✅ NOUVEAU: Logs pour une entité spécifique - VIA SERVICE
                $entityType = filter_var($_GET['entity_type'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                $entityId = (int)($_GET['entity_id'] ?? 0);
                $limit = min(100, max(5, (int)($_GET['limit'] ?? 20)));
                
                if (empty($entityType) || $entityId <= 0) {
                    throw new Exception('entity_type et entity_id sont requis', 400);
                }
                
                // Utiliser le service avec des filtres spécifiques à l'entité
                $filters = [
                    'entity_type' => $entityType,
                    'entity_id' => $entityId
                ];
                
                $result = $serviceActivityLogs->getLogs($filters, $limit, 0);
                
                echo json_encode([
                    'success' => $result['success'],
                    'logs' => $result['logs'] ?? [],
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                    'total' => $result['total'] ?? 0,
                    'message' => $result['message'] ?? null
                ]);
                break;
                
            case 'clean_old_logs':
                // ✅ ARCHITECTURE CORRECTE: Nettoyage via le service
                if ($userRole !== 'admin') {
                    throw new Exception('Seuls les administrateurs peuvent effectuer le nettoyage des logs', 403);
                }
                
                $retentionDays = min(730, max(30, (int)($_GET['retention_days'] ?? 365))); // Entre 30 jours et 2 ans
                $result = $serviceActivityLogs->cleanOldLogs($retentionDays);
                echo json_encode($result);
                break;
                
            default:
                throw new Exception('Action non reconnue: ' . $action, 400);
        }
        
    } elseif ($method === 'POST') {
        // Actions POST avec validation du JSON
        $rawData = file_get_contents('php://input');
        $data = json_decode($rawData, true);
        
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Données JSON invalides: ' . json_last_error_msg(), 400);
        }
        
        $action = $data['action'] ?? '';
        
        switch ($action) {
            case 'mark_resolved':
                // ✅ PLACEHOLDER: Marquer un log comme résolu - AJOUTER AU SERVICE
                $logId = (int)($data['log_id'] ?? 0);
                
                if ($logId <= 0) {
                    throw new Exception('ID de log invalide', 400);
                }
                
                $result = $serviceActivityLogs->markLogAsResolved($logId, $userId);
                echo json_encode($result);
                break;
                
            case 'archive':
                // ✅ PLACEHOLDER: Archiver un log - AJOUTER AU SERVICE
                $logId = (int)($data['log_id'] ?? 0);
                
                if ($logId <= 0) {
                    throw new Exception('ID de log invalide', 400);
                }
                
                $result = $serviceActivityLogs->archiveLog($logId, $userId);
                echo json_encode($result);
                break;
                
            default:
                throw new Exception('Action POST non supportée: ' . $action, 400);
        }
        
    } else {
        throw new Exception('Méthode HTTP non supportée: ' . $method, 405);
    }
    
} catch (Exception $e) {
    $code = $e->getCode();
    $httpCode = 500;
    
    // Déterminer le code HTTP approprié
    if (is_int($code) && $code >= 100 && $code <= 599) {
        $httpCode = $code;
    } elseif (strpos($e->getMessage(), 'Session') !== false) {
        $httpCode = 401;
    } elseif (strpos($e->getMessage(), 'Droits') !== false || strpos($e->getMessage(), 'administrateur') !== false) {
        $httpCode = 403;
    } elseif (strpos($e->getMessage(), 'Action non reconnue') !== false || strpos($e->getMessage(), 'invalide') !== false) {
        $httpCode = 400;
    } elseif (strpos($e->getMessage(), 'Méthode HTTP') !== false) {
        $httpCode = 405;
    }
    
    http_response_code($httpCode);
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'code' => $httpCode,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
    // Logger l'erreur
    error_log("❌ activity-logs-api.php - Erreur {$httpCode}: " . $e->getMessage());
}
?>