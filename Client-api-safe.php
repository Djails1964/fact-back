<?php
/**
 * client-api-safe.php - Version sécurisée du Client API
 * À créer dans /volume1/web/DEV-facturation-api/client-api-safe.php
 */

// Gestion des erreurs pour debug
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Headers CORS et JSON
header('Access-Control-Allow-Origin: https://192.168.1.149');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

// Gérer OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // Chargement sécurisé de l'autoloader
    if (!file_exists('vendor/autoload.php')) {
        throw new Exception('Autoloader non trouvé');
    }
    require_once 'vendor/autoload.php';
    
    // Chargement sécurisé du bootstrap
    if (!file_exists('bootstrap.php')) {
        throw new Exception('Bootstrap non trouvé');
    }
    require_once 'bootstrap.php';
    
    // Chargement sécurisé de la base de données
    if (!file_exists('database.php')) {
        throw new Exception('Fichier database.php non trouvé');
    }
    require_once 'database.php';
    
    // Test de connexion BD
    $conn = getConnection();
    if (!$conn) {
        throw new Exception('Impossible de se connecter à la base de données');
    }
    
    // Récupérer la méthode HTTP
    $method = $_SERVER['REQUEST_METHOD'];
    
    // Réponse selon la méthode
    switch ($method) {
        case 'GET':
            // Récupérer tous les clients ou un client spécifique
            $clientId = $_GET['id'] ?? null;
            
            if ($clientId) {
                // Récupérer un client spécifique
                $response = [
                    'success' => true,
                    'message' => 'Client récupéré (simulation)',
                    'data' => [
                        'id' => $clientId,
                        'nom' => 'Client Test',
                        'email' => 'test@example.com'
                    ]
                ];
            } else {
                // Récupérer tous les clients
                $response = [
                    'success' => true,
                    'message' => 'Liste des clients (simulation)',
                    'data' => [
                        [
                            'id' => 1,
                            'nom' => 'Client 1',
                            'email' => 'client1@example.com'
                        ],
                        [
                            'id' => 2,
                            'nom' => 'Client 2',
                            'email' => 'client2@example.com'
                        ]
                    ]
                ];
            }
            break;
            
        case 'POST':
            // Créer un nouveau client
            $input = json_decode(file_get_contents('php://input'), true);
            
            $response = [
                'success' => true,
                'message' => 'Client créé avec succès (simulation)',
                'data' => [
                    'id' => rand(100, 999),
                    'nom' => $input['nom'] ?? 'Nouveau Client',
                    'email' => $input['email'] ?? 'nouveau@example.com'
                ]
            ];
            break;
            
        case 'PUT':
            // Modifier un client existant
            $input = json_decode(file_get_contents('php://input'), true);
            $clientId = $_GET['id'] ?? null;
            
            if (!$clientId) {
                throw new Exception('ID client requis pour la modification');
            }
            
            $response = [
                'success' => true,
                'message' => 'Client modifié avec succès (simulation)',
                'data' => [
                    'id' => $clientId,
                    'nom' => $input['nom'] ?? 'Client Modifié',
                    'email' => $input['email'] ?? 'modifie@example.com'
                ]
            ];
            break;
            
        case 'DELETE':
            // Supprimer un client
            $clientId = $_GET['id'] ?? null;
            
            if (!$clientId) {
                throw new Exception('ID client requis pour la suppression');
            }
            
            $response = [
                'success' => true,
                'message' => 'Client supprimé avec succès (simulation)',
                'data' => ['id' => $clientId]
            ];
            break;
            
        default:
            throw new Exception('Méthode HTTP non supportée: ' . $method);
    }
    
    // Envoyer la réponse
    http_response_code(200);
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Gestion des erreurs
    http_response_code(500);
    
    $errorResponse = [
        'success' => false,
        'error' => 'Erreur serveur',
        'message' => $e->getMessage(),
        'debug' => [
            'file' => basename($e->getFile()),
            'line' => $e->getLine(),
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ];
    
    // Log l'erreur
    error_log("API Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    
    echo json_encode($errorResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>