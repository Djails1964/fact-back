<?php
/**
 * paiement-api.php - API pour la gestion des paiements
 * Version sécurisée avec authentification obligatoire
 */

// Utiliser la session centralisée comme les autres APIs
$config = require_once realpath(__DIR__ . '/../bootstrap.php');

// Initialiser l'API avec CORS centralisé
init_api_response();

error_log('paiement-api - Chargement de la configuration');

// Désactiver l'affichage des erreurs/warnings seulement si non en mode développement
if (!is_dev_mode()) {
    ini_set('display_errors', 0);
    error_reporting(E_ERROR);
}

ob_start(); // Start output buffering

// Inclure les dépendances nécessaires
require_once 'database.php';
require_once realpath(__DIR__ . '/../controllers/PaiementControleur.php');
require_once realpath(__DIR__ . '/../services/ServicePaiement.php');

// Debug session en mode développement
if (is_dev_mode()) {
    error_log("paiement-api - Méthode: " . $_SERVER['REQUEST_METHOD']);
    error_log("paiement-api - URL complète: " . $_SERVER['REQUEST_URI']);
    error_log("paiement-api - Origin: " . ($_SERVER['HTTP_ORIGIN'] ?? 'Non défini'));
    error_log("paiement-api - Paramètres GET: " . json_encode($_GET));
    error_log("paiement-api - Session ID: " . session_id());
    error_log("paiement-api - Session status: " . session_status());
    error_log("paiement-api - Session content: " . json_encode($_SESSION));
    error_log("🔍 paiement-api - Session initialisée par bootstrap.php");
}

try {
    // Créer l'instance du service
    $servicePaiement = new ServicePaiement($conn);
    
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
        error_log("paiement-api - Session trouvée - User ID: $userId, Role: $userRole");
    } else {
        error_log('paiement-api - Aucune session utilisateur trouvée');
    }
    
    // ====================================
    // ROUTE DE DEBUG SESSION (publique)
    // ====================================
    
    if (isset($_GET['debug_session']) && is_dev_mode()) {
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
                'api' => 'paiement-api.php'
            ]
        ]);
        exit;
    }
    
    // ====================================
    // VÉRIFICATION D'AUTHENTIFICATION POUR TOUTES LES ROUTES
    // ====================================
    
    // Toutes les routes de l'API paiement nécessitent une authentification
    if (!$isAuthenticated) {
        error_log('paiement-api - Accès refusé - Authentification requise');
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Authentification requise pour accéder aux paiements',
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
        error_log("✅ paiement-api - Accès autorisé pour utilisateur: $userId (role: $userRole)");
    }
    
    // Logging pour debug en mode développement
    if (is_dev_mode()) {
        error_log("paiement-api - Méthode: $method");
        error_log("paiement-api - Paramètres GET: " . json_encode($_GET));
        if (in_array($method, ['POST', 'PUT'])) {
            $input = file_get_contents("php://input");
            if (!empty($input)) {
                error_log("paiement-api - Données reçues: " . $input);
            }
        }
    }
    
    // ====================================
    // ROUTES PROTÉGÉES (nécessitent authentification)
    // ====================================
    
    // Traiter la requête en fonction de la méthode
    switch ($method) {
        case 'GET':
            // ===== ROUTES GET (accessible à tous les utilisateurs authentifiés) =====
            
            if (is_dev_mode()) {
                error_log("paiement-api - GET - Paramètres: " . json_encode($_GET) . " (user: $userId)");
            }
            
            // Statistiques globales des paiements
            if (isset($_GET['statistiques'])) { 
                $annee = isset($_GET['annee']) ? intval($_GET['annee']) : null;
                if (is_dev_mode()) {
                    error_log("paiement-api - GET statistiques pour année $annee (user: $userId)");
                }
                $resultat = $servicePaiement->getStatistiquesGlobales($annee);
                echo json_encode($resultat);
                break;
            }
            
            // Paiement spécifique
            if (isset($_GET['id'])) {
                if (is_dev_mode()) {
                    error_log("paiement-api - GET paiement ID: " . $_GET['id'] . " (user: $userId)");
                }
                $resultat = $servicePaiement->getPaiement($_GET['id']);
                echo json_encode($resultat);
                break;
            }
            
            // Paiements par facture
            if (isset($_GET['facture_id'])) {
                if (is_dev_mode()) {
                    error_log("paiement-api - GET paiements facture ID: " . $_GET['facture_id'] . " (user: $userId)");
                }
                $resultat = $servicePaiement->getPaiementsParFacture($_GET['facture_id']);
                echo json_encode($resultat);
                break;
            }
            
            // Support pour pagination et filtrage
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            
            // Options de filtrage
            $options = [
                'annee' => isset($_GET['annee']) ? intval($_GET['annee']) : null,
                'mois' => isset($_GET['mois']) ? intval($_GET['mois']) : null,
                'methode' => isset($_GET['methode']) ? $_GET['methode'] : null,
                'client_id' => isset($_GET['client_id']) ? intval($_GET['client_id']) : null,
                'facture_id' => isset($_GET['facture_id']) ? intval($_GET['facture_id']) : null,
                'page' => $page,
                'limit' => $limit
            ];
            
            if (is_dev_mode()) {
                error_log("paiement-api - GET liste paiements avec options: " . json_encode($options) . " (user: $userId)");
            }
            
            $resultat = $servicePaiement->listerPaiements($options);
            
            if (is_dev_mode()) {
                error_log("paiement-api - Résultat liste paiements (nombre): " . (isset($resultat['data']) ? count($resultat['data']) : 'N/A'));
            }
            
            echo json_encode($resultat);
            break;
            
        case 'POST':
            // ====================================
            // VÉRIFICATION DES DROITS POUR MODIFICATIONS
            // ====================================
            
            // Vérifier les droits de modification (admin ou gestionnaire)
            if ($userRole !== 'admin' && $userRole !== 'gestionnaire') {
                error_log("paiement-api - Droits insuffisants pour POST - User: $userId, Role: $userRole");
                throw new Exception('Droits administrateur ou gestionnaire requis pour créer des paiements', 403);
            }
            
            // Validation des données JSON pour les requêtes POST
            $rawData = file_get_contents("php://input");
            $data = null;
            
            if (!empty($rawData)) {
                $data = json_decode($rawData, true);
                
                if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception('Données JSON invalides: ' . json_last_error_msg());
                }
            }
            
            // Créer un paiement
            if (!$data) {
                throw new Exception('Aucune donnée reçue pour créer le paiement');
            }
            
            if (is_dev_mode()) {
                error_log("paiement-api - POST création nouveau paiement (user: $userId)");
                error_log("paiement-api - Données paiement: " . json_encode($data));
            }
            
            $resultat = $servicePaiement->creerPaiement($data);
            echo json_encode($resultat);
            break;
            
        case 'PUT':
            // Vérifier les droits de modification (admin ou gestionnaire)
            if ($userRole !== 'admin' && $userRole !== 'gestionnaire') {
                error_log("paiement-api - Droits insuffisants pour PUT - User: $userId, Role: $userRole");
                throw new Exception('Droits administrateur ou gestionnaire requis pour modifier des paiements', 403);
            }
            
            // Mettre à jour un paiement existant
            $rawData = file_get_contents("php://input");
            $data = json_decode($rawData, true);
            
            // Validation des données JSON
            if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Données JSON invalides: ' . json_last_error_msg());
            }
            
            // L'ID peut être fourni dans l'URL ou dans le corps de la requête
            $id = $_GET['id'] ?? ($data['id'] ?? null);
            
            if (!$id) {
                throw new Exception('ID paiement manquant');
            }
            
            if (is_dev_mode()) {
                error_log("paiement-api - PUT modification paiement ID: $id (user: $userId)");
            }
            
            $resultat = $servicePaiement->modifierPaiement($id, $data);
            echo json_encode($resultat);
            break;
            
        case 'DELETE':
            // Vérifier les droits de modification (admin ou gestionnaire)
            if ($userRole !== 'admin' && $userRole !== 'gestionnaire') {
                error_log("paiement-api - Droits insuffisants pour DELETE - User: $userId, Role: $userRole");
                throw new Exception('Droits administrateur ou gestionnaire requis pour supprimer des paiements', 403);
            }
            
            // Supprimer un paiement
            if (!isset($_GET['id'])) {
                throw new Exception('ID paiement manquant');
            }

            if (is_dev_mode()) {
                error_log("paiement-api - DELETE paiement ID: " . $_GET['id'] . " (user: $userId)");
            }
            
            $resultat = $servicePaiement->supprimerPaiement($_GET['id']);
            echo json_encode($resultat);
            break;
            
        default:
            throw new Exception('Méthode HTTP non supportée: ' . $method);
    }
    
} catch (Exception $e) {
    // Logging d'erreur avec contexte utilisateur
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
    
    error_log('❌ Erreur API Paiement: ' . json_encode($errorDetails));
    
    // Codes d'erreur HTTP spécifiques
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