<?php
/**
 * facture-api.php - Version debug pour identifier le problème
 */

// PREMIER DEBUG - Vérifier que ce fichier est bien appelé
error_log("🚀 FACTURE-API.PHP - DÉBUT D'EXÉCUTION");
error_log("🚀 Méthode: " . ($_SERVER['REQUEST_METHOD'] ?? 'UNDEFINED'));
error_log("🚀 URI: " . ($_SERVER['REQUEST_URI'] ?? 'UNDEFINED'));
error_log("🚀 Origin: " . ($_SERVER['HTTP_ORIGIN'] ?? 'UNDEFINED'));

// Inclure la configuration centralisée
try {
    $config = require_once realpath(__DIR__ . '/../bootstrap.php');
    error_log("🚀 Bootstrap chargé avec succès");
} catch (Exception $e) {
    error_log("❌ Erreur bootstrap: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Configuration non disponible']);
    exit;
}

// Désactiver l'affichage des erreurs si pas en mode développement
if (!is_dev_mode()) {
    ini_set('display_errors', 0);
    error_reporting(E_ERROR);
}

// Start output buffering
ob_start();

error_log("🔥 FACTURE API - Nouvelle requête reçue");
error_log("🔥 Method: " . $_SERVER['REQUEST_METHOD']);
error_log("🔥 URL complète: " . $_SERVER['REQUEST_URI']);
error_log("🔥 Origin: " . ($_SERVER['HTTP_ORIGIN'] ?? 'Non défini'));
error_log("🔥 User-Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'Non défini'));

// TEST IMMÉDIAT - Renvoyer une réponse JSON simple pour OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    error_log("🔥 DÉTECTION OPTIONS - Test direct sans middleware");
    
    // Headers CORS minimaux pour test
    header("Access-Control-Allow-Origin: " . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Allow-Credentials: true');
    header('Content-Type: application/json');
    
    http_response_code(200);
    echo json_encode(['status' => 'options_test_ok', 'message' => 'Test direct OPTIONS']);
    exit;
}

// Inclure et exécuter le middleware CORS pour les autres méthodes
error_log("🔥 CHARGEMENT DU MIDDLEWARE CORS");
try {
    require_once realpath(__DIR__ . '/../middleware/cors_middleware.php');
    $corsResult = handle_cors();
    error_log("🔥 CORS Middleware résultat: " . ($corsResult ? 'true' : 'false'));
    
    if (!$corsResult) {
        error_log("🔥 CORS Middleware a demandé l'arrêt");
        exit;
    }
} catch (Exception $e) {
    error_log("❌ Erreur CORS middleware: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Erreur CORS', 'details' => $e->getMessage()]);
    exit;
}

// À partir d'ici, l'origine est validée et les en-têtes CORS sont définis
header('Content-Type: application/json');
error_log("🔥 APRÈS CORS - Headers définis");

// TEST - Renvoyer une réponse simple pour toutes les méthodes restantes
error_log("🔥 TEST - Renvoi d'une réponse de test");
echo json_encode([
    'success' => true,
    'message' => 'Test API - CORS passé',
    'method' => $_SERVER['REQUEST_METHOD'],
    'origin' => $_SERVER['HTTP_ORIGIN'] ?? 'none'
]);
exit;

// LE RESTE DU CODE ORIGINAL SERA COMMENTÉ POUR CE TEST
/*
// Inclure les dépendances nécessaires
require_once 'database.php';
require_once realpath(__DIR__ . '/../ServiceFacture.php');
require_once realpath(__DIR__ . '/../ServiceParametre.php');

try {
    // ... reste du code original
} catch (Exception $e) {
    error_log('Erreur API Facture: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
*/
?>