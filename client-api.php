<?php
/**
 * client-api.php - API pour la gestion des clients
 * Version sécurisée avec authentification obligatoire
 */

// Utiliser la session centralisée comme auth-api.php
$config = require_once realpath(__DIR__ . '/bootstrap.php');

// ✅ VÉRIFICATION SESSION - Ajouter cette ligne
check_session_validity();

// Initialiser l'API avec CORS centralisé
init_api_response();

error_log('client-api - Chargement de la configuration');

// Désactiver l'affichage des erreurs/warnings seulement si non en mode développement
if (!is_dev_mode()) {
    ini_set('display_errors', 0);
    error_reporting(E_ERROR);
}

ob_start(); // Start output buffering

// Inclure les dépendances nécessaires
require_once 'database.php';
require_once realpath(__DIR__ . '/services/ServiceClient.php');

// Debug session en mode développement (comme auth-api.php)
if (is_dev_mode()) {
    error_log("client-api - Méthode: " . $_SERVER['REQUEST_METHOD']);
    error_log("client-api - URL complète: " . $_SERVER['REQUEST_URI']);
    error_log("client-api - Origin: " . ($_SERVER['HTTP_ORIGIN'] ?? 'Non défini'));
    error_log("client-api - Paramètres GET: " . json_encode($_GET));
    error_log("client-api - Session ID: " . session_id());
    error_log("client-api - Session status: " . session_status());
    error_log("client-api - Session content: " . json_encode($_SESSION));
    error_log("🔍 client-api - Session initialisée par bootstrap.php");
}

try {
    // Créer l'instance du service
    $serviceClient = new ServiceClient($conn);
    
    // Déterminer la méthode HTTP
    $method = $_SERVER['REQUEST_METHOD'];
    
    // ====================================
    // VÉRIFICATION DE SESSION POUR ROUTES PROTÉGÉES
    // ====================================
    
    $userId = null;
    $userRole = null;
    $isAuthenticated = false;
    
    // Vérifier si l'utilisateur est connecté via la session
    if (isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
        $userRole = $_SESSION['user_role'] ?? null;
        $isAuthenticated = true;
        error_log("client-api - Session trouvée - User ID: $userId, Role: $userRole");
        error_log("client-api - Session keys: " . json_encode(array_keys($_SESSION)));
        error_log("client-api - Cookies reçues: " . json_encode($_COOKIE));
        error_log("client-api - Méthode: $method");
        error_log("client-api - Paramètres GET: " . json_encode($_GET));
    } else {
        error_log('client-api - Aucune session utilisateur trouvée');
    }
    
    // ====================================
    // ROUTE DE DEBUG SESSION (publique, comme auth-api.php)
    // ====================================
    
    if (isset($_GET['debug_session']) && is_dev_mode()) {
        // Route de debug pour diagnostiquer les problèmes de session
        echo json_encode([
            'success' => true,
            'debug_info' => [
                'session_id' => session_id(),
                'session_status' => session_status(),
                'session_status_name' => [
                    PHP_SESSION_DISABLED => 'DISABLED',
                    PHP_SESSION_NONE => 'NONE', 
                    PHP_SESSION_ACTIVE => 'ACTIVE'
                ][session_status()] ?? 'UNKNOWN',
                'session_data' => $_SESSION,
                'cookie_params' => session_get_cookie_params(),
                'headers_sent' => headers_sent(),
                'headers_list' => headers_list(),
                'cookies_received' => $_COOKIE,
                'get_params' => $_GET,
                'server_info' => [
                    'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'],
                    'HTTP_ORIGIN' => $_SERVER['HTTP_ORIGIN'] ?? 'NONE',
                    'HTTP_COOKIE' => $_SERVER['HTTP_COOKIE'] ?? 'NONE',
                    'REMOTE_ADDR' => $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN'
                ],
                'user_authenticated' => $isAuthenticated,
                'user_id' => $userId,
                'user_role' => $userRole,
                'api' => 'client-api.php'
            ]
        ]);
        exit;
    }
    
    // ====================================
    // VÉRIFICATION D'AUTHENTIFICATION POUR TOUTES LES ROUTES
    // ====================================
    
    // Toutes les routes de l'API client nécessitent une authentification
    if (!$isAuthenticated) {
        error_log('client-api - Accès refusé - Authentification requise');
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Authentification requise pour accéder aux clients',
            'code' => 401,
            'debug' => is_dev_mode() ? [
                'session_id' => session_id(),
                'session_keys' => array_keys($_SESSION ?? []),
                'cookies' => $_COOKIE
            ] : null
        ]);
        exit;
    }
    
    // Log de l'accès autorisé
    if (is_dev_mode()) {
        error_log("✅ client-api - Accès autorisé pour utilisateur: $userId (role: $userRole)");
    }
    
    // Logging pour debug en mode développement
    if (is_dev_mode()) {
        error_log("client-api - Méthode: $method");
        error_log("client-api - Paramètres GET: " . json_encode($_GET));
        if (in_array($method, ['POST', 'PUT'])) {
            $input = file_get_contents("php://input");
            if (!empty($input)) {
                error_log("client-api - Données reçues: " . $input);
            }
        }
    }
    
    // ====================================
    // ROUTES PROTÉGÉES (nécessitent authentification)
    // ====================================
    
    // Traiter la requête en fonction de la méthode
    switch ($method) {
        case 'GET':
            // Client spécifique
            error_log("client-api - Traitement GET pour client");
            error_log("client-api - Paramètres GET: " . json_encode($_GET));
            error_log("client-api - GET ID: " . ($_GET['id'] ?? 'Aucun ID fourni'));
            error_log("client-api - GET checkFactures: " . ($_GET['checkFactures'] ?? 'Aucun paramètre checkFactures'));

            if (isset($_GET['id'])) {
                if (isset($_GET['checkFactures']) && $_GET['checkFactures'] === 'true') {
                    // Vérifier si le client a des factures
                    if (is_dev_mode()) {
                        error_log("client-api - Vérification factures pour client: " . $_GET['id'] . " (user: $userId)");
                    }
                    $resultat = $serviceClient->aUneFacture($_GET['id']);
                    error_log("client-api - Résultat vérification factures: " . json_encode($resultat));
                    echo json_encode($resultat);
                } else {
                    if (is_dev_mode()) {
                        error_log("client-api - Récupération client ID: " . $_GET['id'] . " (user: $userId)");
                    }
                    $resultat = $serviceClient->getClientParId($_GET['id']);
                    echo json_encode($resultat);
                }
                break;
            }
            
            // Support pour la recherche de clients
            if (isset($_GET['search'])) {
                $terme = $_GET['search'];
                if (is_dev_mode()) {
                    error_log("client-api - Recherche clients: '$terme' (user: $userId)");
                }
                // $resultat = $serviceClient->rechercherClients($terme);
                // Pour l'instant, retourner une liste vide
                $resultat = ['success' => true, 'clients' => [], 'message' => 'Recherche non encore implémentée'];
                echo json_encode($resultat);
                break;
            }
            
            // Support pour pagination
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            
            // Liste des clients
            if (is_dev_mode()) {
                error_log("client-api - Récupération liste clients (page: $page, limit: $limit) pour user: $userId");
            }
            $resultat = $serviceClient->listerClients($page, $limit);
            echo json_encode($resultat);
            break;
            
        case 'POST':
            // ====================================
            // VÉRIFICATION DES DROITS POUR MODIFICATIONS
            // ====================================
            
            // Vérifier les droits de modification (admin ou gestionnaire)
            if ($userRole !== 'admin' && $userRole !== 'gestionnaire') {
                error_log("client-api - Droits insuffisants pour POST - User: $userId, Role: $userRole");
                throw new Exception('Droits administrateur ou gestionnaire requis pour créer des clients', 403);
            }
            
            // Ajouter un nouveau client
            $rawData = file_get_contents("php://input");
            $data = json_decode($rawData, true);
            
            // Validation des données JSON
            if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Données JSON invalides: ' . json_last_error_msg());
            }
            
            if (!$data) {
                throw new Exception('Aucune donnée reçue');
            }
            
            if (is_dev_mode()) {
                error_log("client-api - Création client par user: $userId (role: $userRole)");
            }
            
            $resultat = $serviceClient->ajouterClient($data);
            echo json_encode($resultat);
            break;
            
        case 'PUT':
            // Vérifier les droits de modification (admin ou gestionnaire)
            if ($userRole !== 'admin' && $userRole !== 'gestionnaire') {
                error_log("client-api - Droits insuffisants pour PUT - User: $userId, Role: $userRole");
                throw new Exception('Droits administrateur ou gestionnaire requis pour modifier des clients', 403);
            }
            
            // Mettre à jour un client existant
            $rawData = file_get_contents("php://input");
            $data = json_decode($rawData, true);
            
            // Validation des données JSON
            if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Données JSON invalides: ' . json_last_error_msg());
            }
            
            // L'ID peut être fourni dans l'URL ou dans le corps de la requête
            $id = $_GET['id'] ?? ($data['id'] ?? null);
            
            if (!$id) {
                throw new Exception('ID client manquant');
            }
            
            if (is_dev_mode()) {
                error_log("client-api - Modification client ID: $id par user: $userId (role: $userRole)");
            }
            
            $resultat = $serviceClient->modifierClient($id, $data);
            echo json_encode($resultat);
            break;
            
        case 'DELETE':
            // Vérifier les droits de modification (admin ou gestionnaire)
            if ($userRole !== 'admin' && $userRole !== 'gestionnaire') {
                error_log("client-api - Droits insuffisants pour DELETE - User: $userId, Role: $userRole");
                throw new Exception('Droits administrateur ou gestionnaire requis pour supprimer des clients', 403);
            }
            
            // Supprimer un client
            if (!isset($_GET['id'])) {
                throw new Exception('ID client manquant');
            }
            
            if (is_dev_mode()) {
                error_log("client-api - Tentative suppression client ID: " . $_GET['id'] . " par user: $userId (role: $userRole)");
            }
            
            // Vérification de sécurité - empêcher la suppression si le client a des factures
            if (isset($_GET['force']) && $_GET['force'] === 'true') {
                // Suppression forcée (admin seulement)
                if ($userRole !== 'admin') {
                    throw new Exception('Seuls les administrateurs peuvent effectuer une suppression forcée', 403);
                }
                
                if (is_dev_mode()) {
                    error_log("client-api - Suppression forcée autorisée pour admin: $userId");
                }
                
                $resultat = $serviceClient->supprimerClient($_GET['id'], true);
            } else {
                // Vérifier d'abord s'il y a des factures
                $checkFactures = $serviceClient->aUneFacture($_GET['id']);

                if (!$checkFactures['success']) {
                    throw new Exception($checkFactures['message']);
                }

                if ($checkFactures['hasInvoices']) {
                    if (is_dev_mode()) {
                        error_log("client-api - Suppression refusée - Client a " . $checkFactures['count'] . " factures");
                    }
                    
                    http_response_code(409); // Conflict
                    echo json_encode([
                        'success' => false,
                        'message' => 'Impossible de supprimer ce client car il a des factures associées',
                        'code' => 'HAS_INVOICES',
                        'invoiceCount' => $checkFactures['count']
                    ]);
                    break;
                }
                
                if (is_dev_mode()) {
                    error_log("client-api - Suppression autorisée - Client sans factures");
                }
                
                $resultat = $serviceClient->supprimerClient($_GET['id']);
            }
            
            echo json_encode($resultat);
            break;
            
        default:
            throw new Exception('Méthode HTTP non supportée: ' . $method);
    }
    
} catch (Exception $e) {
    // Logging d'erreur plus détaillé avec contexte utilisateur
    $errorDetails = [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'method' => $_SERVER['REQUEST_METHOD'],
        'url' => $_SERVER['REQUEST_URI'],
        'user_id' => $userId ?? 'NON_CONNECTÉ',
        'user_role' => $userRole ?? 'NON_DÉFINI',
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    error_log('❌ Erreur API Client: ' . json_encode($errorDetails));
    
    // Codes d'erreur HTTP plus spécifiques
    $httpCode = 400; // Bad Request par défaut
    
    if (strpos($e->getMessage(), 'Authentification requise') !== false) {
        $httpCode = 401; // Unauthorized
    } elseif (strpos($e->getMessage(), 'Droits') !== false || 
              strpos($e->getMessage(), 'administrateur requis') !== false) {
        $httpCode = 403; // Forbidden
    } elseif (strpos($e->getMessage(), 'manquant') !== false || 
              strpos($e->getMessage(), 'requis') !== false) {
        $httpCode = 422; // Unprocessable Entity
    } elseif (strpos($e->getMessage(), 'non trouvé') !== false) {
        $httpCode = 404; // Not Found
    } elseif (strpos($e->getMessage(), 'non autorisé') !== false) {
        $httpCode = 403; // Forbidden
    }
    
    http_response_code($httpCode);
    
    // Réponse d'erreur enrichie pour React
    $errorResponse = [
        'success' => false, 
        'message' => $e->getMessage(),
        'code' => $httpCode,
        'timestamp' => time()
    ];
    
    // En mode développement, ajouter plus de détails
    if (is_dev_mode()) {
        $errorResponse['debug'] = [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'session_debug' => [
                'session_id' => session_id(),
                'session_status' => session_status(),
                'user_id_present' => isset($_SESSION['user_id']),
                'session_keys' => array_keys($_SESSION ?? []),
                'method' => $method,
                'get_params' => $_GET,
                'authenticated' => $isAuthenticated ?? false,
                'user_id' => $userId,
                'user_role' => $userRole
            ]
        ];
    }
    
    echo json_encode($errorResponse);
}
?>