<?php
/**
 * login-api.php - Gestion des connexions avec configuration CORS
 * Version refactorisée avec configuration centralisée
 */


// Inclure la configuration centralisée - elle charge déjà Dotenv et les variables d'environnement
$config = require_once realpath(__DIR__ . '/bootstrap.php');
init_api_response();

require_once '/services/ServiceAuthentification.php';


// Désactiver l'affichage des erreurs/warnings seulement si non en mode développement
if (!is_dev_mode()) {
    ini_set('display_errors', 0);
    error_reporting(E_ERROR); // Rapporter uniquement les erreurs fatales
}

// Définir le chemin du dossier de logs
$logDir = $config['logs']['dir'];

ob_start(); // Start output buffering


// Gestion des requêtes OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Vérifier que la méthode est POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Méthode non autorisée'
    ]);
    exit;
}

// Initialiser la session
session_start();

try {
    // Lire les données JSON
    $rawData = file_get_contents('php://input');
    $data = json_decode($rawData, true);

    // Vérifier les données
    if (!$data || !isset($data['username']) || !isset($data['password'])) {
        throw new Exception('Données de connexion invalides');
    }

    // Créer le service d'authentification
    $serviceAuth = new ServiceAuthentification($conn);
    
    // Tenter l'authentification
    $resultat = $serviceAuth->authentifier($data['username'], $data['password']);

    if ($resultat['success']) {
        // Stocker les informations utilisateur en session
        $_SESSION['user_id'] = $resultat['utilisateur']['id_utilisateur'];
        $_SESSION['username'] = $resultat['utilisateur']['username'];
        $_SESSION['role'] = $resultat['utilisateur']['role'];
        $_SESSION['derniere_activite'] = time();

        // Log de connexion réussie
        error_log("Connexion réussie pour l'utilisateur: " . $data['username']);

        // Préparer la réponse
        $response = [
            'success' => true,
            'message' => 'Connexion réussie',
            'user' => [
                'id' => $resultat['utilisateur']['id_utilisateur'],
                'username' => $resultat['utilisateur']['username'],
                'role' => $resultat['utilisateur']['role']
            ],
            'timestamp' => time()
        ];
    } else {
        // Log de tentative de connexion échouée
        error_log("Échec de connexion pour l'utilisateur: " . $data['username']);

        // Réponse en cas d'échec
        $response = [
            'success' => false,
            'message' => $resultat['message'] ?? 'Échec de connexion'
        ];
        
        // Code d'erreur approprié
        http_response_code(401);
    }

    // Envoyer la réponse
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($response);
    exit;

} catch (Exception $e) {
    // Gestion des erreurs inattendues
    error_log("Erreur lors de la connexion: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur lors de la connexion'
    ]);
    exit;
}