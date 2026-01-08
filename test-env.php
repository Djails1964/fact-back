<?php
/**
 * test-env.php - Testeur des variables d'environnement
 * À placer dans fact-back/api/test-env.php
 */

// Inclure la configuration centralisée
$config = require_once realpath(__DIR__ . '/bootstrap.php');

// Initialiser l'API avec CORS
init_api_response();

// Variables d'environnement importantes à tester
$envVars = [
    'APP_ENV',
    'APP_NAME', 
    'APP_VERSION',
    'APP_URL_FRONT',
    'APP_URL_BACK',
    'REACT_HOST',
    'REACT_PORT',
    'CORS_ORIGIN',
    'CORS_METHODS',
    'CORS_HEADERS',
    'CORS_CREDENTIALS',
    'DB_HOST',
    'DB_NAME',
    'DB_USER',
    'SESSION_LIFETIME',
    'LOG_DIR',
    'LOG_LEVEL'
];

// Résultats du test
$results = [
    'success' => true,
    'timestamp' => date('Y-m-d H:i:s'),
    'server_info' => [
        'request_method' => $_SERVER['REQUEST_METHOD'],
        'request_uri' => $_SERVER['REQUEST_URI'],
        'http_origin' => $_SERVER['HTTP_ORIGIN'] ?? 'Non défini',
        'session_id' => session_id(),
        'session_status' => session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Inactive'
    ],
    'environment' => [],
    'computed_values' => [
        'react_url' => react_url(),
        'is_dev_mode' => is_dev_mode(),
        'log_dir_exists' => file_exists($config['logs']['dir']),
        'log_dir_writable' => is_writable($config['logs']['dir'])
    ],
    'config' => $config,
    'cors_test' => [],
    'database_test' => []
];

// Tester les variables d'environnement
foreach ($envVars as $var) {
    $value = env($var);
    $results['environment'][$var] = [
        'value' => $value,
        'defined' => $value !== null,
        'source' => 'unknown'
    ];
    
    // Déterminer la source
    if (isset($_ENV[$var])) {
        $results['environment'][$var]['source'] = '$_ENV';
    } elseif (isset($_SERVER[$var])) {
        $results['environment'][$var]['source'] = '$_SERVER';
    } elseif (getenv($var) !== false) {
        $results['environment'][$var]['source'] = 'getenv()';
    }
}

// Test CORS plus détaillé
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = [];

if (is_dev_mode()) {
    $reactUrl = react_url();
    $allowedOrigins = [
        $reactUrl,
        'http://localhost:3000',
        'http://127.0.0.1:3000',
        'http://localhost:3007',
        'http://127.0.0.1:3007'
    ];
} else {
    $corsOrigin = env('CORS_ORIGIN', '*');
    if ($corsOrigin !== '*') {
        $allowedOrigins = array_map('trim', explode(',', $corsOrigin));
    }
}

$results['cors_test'] = [
    'current_origin' => $origin,
    'allowed_origins' => $allowedOrigins,
    'origin_allowed' => in_array($origin, $allowedOrigins) || env('CORS_ORIGIN') === '*',
    'cors_headers_sent' => headers_list()
];

// Test de base de données (sans mots de passe sensibles)
try {
    require_once 'database.php';
    $results['database_test'] = [
        'connection' => 'SUCCESS',
        'host' => $config['database']['host'],
        'database' => $config['database']['name'],
        'user' => $config['database']['user']
    ];
} catch (Exception $e) {
    $results['database_test'] = [
        'connection' => 'FAILED',
        'error' => $e->getMessage()
    ];
    $results['success'] = false;
}

// Log pour debug
if (is_dev_mode()) {
    error_log('🧪 Test des variables d\'environnement - Origin: ' . $origin);
    error_log('🧪 React URL calculée: ' . react_url());
    error_log('🧪 Origines autorisées: ' . json_encode($allowedOrigins));
}

// Retourner les résultats
echo json_encode($results, JSON_PRETTY_PRINT);
?>