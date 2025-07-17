<?php
/**
 * activity-logs-api.php - API pour les logs d'activité
 * Emplacement: fact-back/api/activity-logs-api.php
 */

// Utiliser la même configuration que auth-api.php
require_once realpath(__DIR__ . '/../bootstrap.php');
init_api_response();

// Inclure les dépendances
require_once 'database.php';
require_once realpath(__DIR__ . '/../services/ServiceActivityLogs.php');

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
        
        switch ($action) {
            case 'get_logs':
                // Récupérer les paramètres de filtre et pagination
                $filters = [];
                $limit = (int)($_GET['limit'] ?? 50);
                $page = (int)($_GET['page'] ?? 1);
                $offset = ($page - 1) * $limit;
                
                // Construire les filtres
                if (!empty($_GET['action_type'])) {
                    $filters['action_type'] = $_GET['action_type'];
                }
                
                if (!empty($_GET['severity'])) {
                    $filters['severity'] = $_GET['severity'];
                }
                
                if (!empty($_GET['user_name'])) {
                    $filters['user_name'] = $_GET['user_name'];
                }
                
                if (!empty($_GET['date_from'])) {
                    $filters['date_from'] = $_GET['date_from'];
                }
                
                if (!empty($_GET['date_to'])) {
                    $filters['date_to'] = $_GET['date_to'];
                }
                
                if (!empty($_GET['user_id'])) {
                    $filters['user_id'] = $_GET['user_id'];
                }
                
                if (!empty($_GET['entity_type'])) {
                    $filters['entity_type'] = $_GET['entity_type'];
                }
                
                if (!empty($_GET['entity_id'])) {
                    $filters['entity_id'] = $_GET['entity_id'];
                }
                
                // Récupération des logs via le service
                $result = $serviceActivityLogs->getLogs($filters, $limit, $offset);
                echo json_encode($result);
                break;
                
            case 'export_csv':
                // Export CSV
                $filters = [];
                
                // Récupérer les mêmes filtres que pour get_logs
                if (!empty($_GET['action_type'])) {
                    $filters['action_type'] = $_GET['action_type'];
                }
                
                if (!empty($_GET['severity'])) {
                    $filters['severity'] = $_GET['severity'];
                }
                
                if (!empty($_GET['user_name'])) {
                    $filters['user_name'] = $_GET['user_name'];
                }
                
                if (!empty($_GET['date_from'])) {
                    $filters['date_from'] = $_GET['date_from'];
                }
                
                if (!empty($_GET['date_to'])) {
                    $filters['date_to'] = $_GET['date_to'];
                }
                
                if (!empty($_GET['user_id'])) {
                    $filters['user_id'] = $_GET['user_id'];
                }
                
                if (!empty($_GET['entity_type'])) {
                    $filters['entity_type'] = $_GET['entity_type'];
                }
                
                if (!empty($_GET['entity_id'])) {
                    $filters['entity_id'] = $_GET['entity_id'];
                }
                
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
                // Récupérer les statistiques
                $result = $serviceActivityLogs->getStatistics();
                echo json_encode($result);
                break;
                
            case 'clean_old_logs':
                // Nettoyage des anciens logs (action administrative)
                if ($userRole !== 'admin') {
                    throw new Exception('Seuls les administrateurs peuvent effectuer le nettoyage des logs', 403);
                }
                
                $retentionDays = (int)($_GET['retention_days'] ?? 365);
                $result = $serviceActivityLogs->cleanOldLogs($retentionDays);
                echo json_encode($result);
                break;
                
            default:
                throw new Exception('Action non reconnue: ' . $action, 400);
        }
        
    } elseif ($method === 'POST') {
        // Actions POST (futures extensions)
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
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
    } elseif (strpos($e->getMessage(), 'Action non reconnue') !== false) {
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