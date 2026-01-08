<?php
/**
 * logout-api.php - API de déconnexion avec redirection forcée
 * Version refactorisée avec configuration centralisée
 */

// Inclure la configuration centralisée - elle charge déjà Dotenv et les variables d'environnement
$config = require_once realpath(__DIR__ . '/bootstrap.php');

// Désactiver l'affichage des erreurs/warnings seulement si non en mode développement
if (!is_dev_mode()) {
    ini_set('display_errors', 0);
    error_reporting(E_ERROR); // Rapporter uniquement les erreurs fatales
}

// Définir le chemin du dossier de logs
$logDir = $config['logs']['dir'];

ob_start(); // Start output buffering


// Si c'est une requête OPTIONS, terminer immédiatement
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Initialiser la session
session_start();

// Enregistrer dans un log de débogage
error_log("Déconnexion demandée pour l'utilisateur: " . ($_SESSION['username'] ?? 'inconnu'));

// Détruire toutes les variables de session
$_SESSION = array();

// Si un cookie de session est utilisé, le détruire également
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
    
    // Ajouter un cookie de déconnexion pour forcer le client à se déconnecter
    setcookie('LOGGED_OUT', '1', time() + 300, '/');
    
    // Détruire toute autre cookie potentielle
    foreach ($_COOKIE as $cookieName => $cookieValue) {
        setcookie($cookieName, '', time() - 3600, '/');
    }
}

// Détruire la session
session_destroy();

// Vérifier que la session est bien détruite
session_start();
$sessionDestroyed = !isset($_SESSION['user_id']);
error_log("Session détruite: " . ($sessionDestroyed ? 'Oui' : 'Non'));

// Construire l'URL frontend en utilisant les variables d'environnement
$frontendURL = is_dev_mode() 
    ? "http://" . env('REACT_HOST', 'localhost') . ":" . env('REACT_PORT', '3007')
    : env('APP_URL_FRONT', 'http://localhost');

error_log("URL frontend: " . $frontendURL);

// Préparer la réponse avec un timestamp pour éviter le cache
$timestamp = time();
$response = [
    'success' => true,
    'message' => 'Déconnexion réussie',
    'sessionDestroyed' => $sessionDestroyed,
    'timestamp' => $timestamp,
    'redirect' => "{$frontendURL}/?logout=1&t=$timestamp"
];

// Envoyer la réponse JSON
header('Content-Type: application/json; charset=UTF-8');
echo json_encode($response);
exit;
?>