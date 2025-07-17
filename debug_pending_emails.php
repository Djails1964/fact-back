<?php
/**
 * debug_pending_emails.php - VERSION SIMPLIFIÉE ET CORRIGÉE
 */

// Désactiver l'affichage des erreurs pour éviter les problèmes JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

// ÉTAPE 1: Gérer la session AVANT tout
if (isset($_GET['PHPSESSID']) && preg_match('/^[a-zA-Z0-9,-]{20,40}$/', $_GET['PHPSESSID'])) {
    session_id($_GET['PHPSESSID']);
    error_log("Session ID forcé depuis URL: " . $_GET['PHPSESSID']);
}


// Démarrer la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Test de sauvegarde temporaire - À ajouter après session_start()
if (isset($_GET['test_save'])) {
    $_SESSION['test_data'] = [
        'timestamp' => time(),
        'test' => 'sauvegarde fonctionnelle',
        'session_id' => session_id()
    ];
    error_log("Test sauvegarde - Session ID: " . session_id());
    error_log("Données test sauvegardées: " . json_encode($_SESSION['test_data']));
}


// ÉTAPE 2: Inclure bootstrap APRÈS la session
require_once 'bootstrap.php';

// ÉTAPE 3: Headers et CORS
setup_cors_headers();
header('Content-Type: application/json; charset=UTF-8');

try {
    $sessionId = session_id();
    $sessionFromUrl = $_GET['PHPSESSID'] ?? null;
    $sessionMatch = ($sessionFromUrl === $sessionId);
    
    error_log("=== DEBUG PENDING EMAILS SIMPLIFIÉ ===");
    error_log("Session from URL: " . ($sessionFromUrl ?: 'NONE'));
    error_log("Session actual: " . $sessionId);
    error_log("Session match: " . ($sessionMatch ? 'YES' : 'NO'));
    error_log("Pending emails count: " . count($_SESSION['pending_emails'] ?? []));
    
    $response = [
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'session_id' => $sessionId,
        'session_status' => session_status(),
        'pending_emails' => $_SESSION['pending_emails'] ?? [],
        'pending_emails_count' => count($_SESSION['pending_emails'] ?? []),
        'all_session_keys' => array_keys($_SESSION),
        'debug_session' => [
            'session_from_url' => $sessionFromUrl,
            'session_actual' => $sessionId,
            'session_match' => $sessionMatch,
            'forced_from_url' => !empty($sessionFromUrl)
        ],
        'authentication' => [
            'user_authenticated' => isset($_SESSION['user_id']),
            'user_id' => $_SESSION['user_id'] ?? null
        ],
        'environment' => [
            'is_development' => function_exists('is_dev_mode') ? is_dev_mode() : true,
            'php_version' => phpversion()
        ]
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    
    $errorResponse = [
        'success' => false,
        'error' => $e->getMessage(),
        'session_id' => session_id() ?: 'NONE',
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode($errorResponse, JSON_PRETTY_PRINT);
    
    error_log("Erreur debug_pending_emails.php: " . $e->getMessage());
}
?>