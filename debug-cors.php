<?php
// debug-cors.php - Version corrigée SANS redéclaration de fonction
require_once 'bootstrap.php';

// IMPORTANT: Appeler init_api_response() EN PREMIER
init_api_response();

// Informations de diagnostic
$diagnostics = [
    'timestamp' => date('Y-m-d H:i:s'),
    'request_method' => $_SERVER['REQUEST_METHOD'],
    'request_uri' => $_SERVER['REQUEST_URI'],
    'http_origin' => $_SERVER['HTTP_ORIGIN'] ?? 'NON_DÉFINI',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'NON_DÉFINI',
    'server_name' => $_SERVER['SERVER_NAME'] ?? 'NON_DÉFINI',
    'https' => is_https() ? 'OUI' : 'NON',
    
    // Configuration environnement
    'env_config' => [
        'APP_ENV' => env('APP_ENV'),
        'CORS_ORIGIN' => env('CORS_ORIGIN'),
        'CORS_METHODS' => env('CORS_METHODS'),
        'CORS_HEADERS' => env('CORS_HEADERS'),
        'CORS_CREDENTIALS' => env('CORS_CREDENTIALS')
    ],
    
    // En-têtes envoyés
    'sent_headers' => [],
    
    // Test des origines autorisées
    'origin_check' => []
];

// Capturer les en-têtes qui ont été envoyés
if (function_exists('headers_list')) {
    $diagnostics['sent_headers'] = headers_list();
}

// Tester les origines autorisées
$corsOrigin = env('CORS_ORIGIN', '*');
if ($corsOrigin !== '*') {
    $allowedOrigins = array_map('trim', explode(',', $corsOrigin));
    $currentOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
    
    foreach ($allowedOrigins as $origin) {
        $diagnostics['origin_check'][$origin] = [
            'matches_current' => ($origin === $currentOrigin),
            'is_current_origin' => $currentOrigin
        ];
    }
}

// Ajouter les informations de session
$diagnostics['session_info'] = [
    'session_status' => session_status(),
    'session_id' => session_id(),
    'session_name' => session_name()
];

echo json_encode($diagnostics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>