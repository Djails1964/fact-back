<?php
/**
 * logout.php - Script de déconnexion amélioré
 */

// Inclure la configuration
$config = require_once 'bootstrap.php';

// Initialiser la session
session_start();

// Détruire toutes les variables de session
$_SESSION = array();

// Si un cookie de session est utilisé, le détruire également
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Détruire la session
session_destroy();

// Envoyer des en-têtes pour empêcher le cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

// Configuration dynamique des URL
$isDevelopment = is_dev_mode();
$reactHost = env('REACT_HOST', 'localhost');
$reactPort = env('REACT_PORT', '3007');

// Construire l'URL frontend en utilisant les variables d'environnement
$frontendURL = $isDevelopment 
    ? "http://{$reactHost}:{$reactPort}" 
    : env('APP_URL_FRONT', 'http://localhost');

// Ajouter des en-têtes CORS pour permettre la communication avec le frontend React
header("Access-Control-Allow-Origin: " . env('CORS_ORIGIN', '*'));
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: " . env('CORS_METHODS', 'GET, POST, OPTIONS'));
header("Access-Control-Allow-Headers: " . env('CORS_HEADERS', 'Content-Type, Authorization, X-Requested-With'));

// Si c'est une requête AJAX, renvoyer un statut JSON
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success', 
        'message' => 'Déconnexion réussie',
        'redirect' => "{$frontendURL}/?logout=1&nocache=" . time()
    ]);
    exit;
}

// Pour les demandes normales, rediriger vers la page d'accueil
$timestamp = time();
error_log("Redirection vers le frontend: {$frontendURL}/?logout=1&nocache={$timestamp}");
header("Location: {$frontendURL}/#/?logout=1&nocache={$timestamp}");
exit;