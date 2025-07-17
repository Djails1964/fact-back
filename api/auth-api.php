<?php
/**
 * auth-api.php - API unifiée pour l'authentification et l'administration
 * Fusionne les fonctionnalités de auth-api.php et admin-api.php
 */

// SUPPRIMÉ : Gestion de session spécifique (conflictuelle)
/*
// Forcer l'ID de session AVANT que bootstrap.php ne démarre une session
if (isset($_GET['PHPSESSID']) && !empty($_GET['PHPSESSID']) && session_status() === PHP_SESSION_NONE) {
    session_id($_GET['PHPSESSID']);
    error_log("🔑 auth-api - Session ID forcé depuis URL: " . $_GET['PHPSESSID']);
}

// Démarrer la session maintenant si pas encore démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    error_log("📋 auth-api - Session démarrée avec ID: " . session_id());
}
*/

// CORRECTION : Utiliser la session centralisée comme les autres APIs
$config = require_once realpath(__DIR__ . '/../bootstrap.php');

// 🔧 NOUVEAU: Initialiser l'API avec CORS centralisé (comme facture-api.php)
init_api_response();

error_log('auth-api - Chargement de la configuration');
error_log('auth-api - Configuration: ' . json_encode($config));

// Désactiver l'affichage des erreurs/warnings seulement si non en mode développement
if (!is_dev_mode()) {
    ini_set('display_errors', 0);
    error_reporting(E_ERROR);
}

ob_start(); // Start output buffering

// Inclure les dépendances nécessaires
require_once 'database.php';
require_once '../services/ServiceAuthentification.php';

// Debug session en mode développement (APRÈS bootstrap)
if (is_dev_mode()) {
    error_log("auth-api - Méthode: " . $_SERVER['REQUEST_METHOD']);
    error_log("auth-api - URL complète: " . $_SERVER['REQUEST_URI']);
    error_log("auth-api - Origin: " . ($_SERVER['HTTP_ORIGIN'] ?? 'Non défini'));
    error_log("auth-api - Paramètres GET: " . json_encode($_GET));
    error_log("auth-api - Session ID: " . session_id());
    error_log("auth-api - Session status: " . session_status());
    error_log("auth-api - Session content: " . json_encode($_SESSION));
    error_log("🔍 auth-api - Session initialisée par bootstrap.php");
}

try {
    // Créer l'instance du service d'authentification
    $serviceAuth = new ServiceAuthentification($conn);
    
    // Déterminer l'action à effectuer
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? null;
    
    // Variables pour identifier le type de requête
    $isLoginRequest = isset($_GET['login']);
    $isLogoutRequest = isset($_GET['logout']);
    $isCheckSessionRequest = isset($_GET['check_session']);
    $isUtilisateursRequest = isset($_GET['utilisateurs']);
    $isResetPasswordRequest = isset($_GET['reset_password']);
    $isVerifyTokenRequest = isset($_GET['verify_token']);
    $isAdminAction = in_array($action, ['dashboard', 'utilisateurs', 'utilisateur', 'creer_utilisateur', 'modifier_utilisateur', 'supprimer_utilisateur']);
    
    error_log("auth-api - Action: $action, Method: $method");
    error_log("auth-api - Flags: login=$isLoginRequest, logout=$isLogoutRequest, check=$isCheckSessionRequest, users=$isUtilisateursRequest, admin=$isAdminAction");
    
    // ====================================
    // ROUTES PUBLIQUES (sans vérification de session)
    // ====================================

    // ====================================
    // ROUTE DE DEBUG SESSION (publique)
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
                ]
            ]
        ]);
        exit;
    }
    
    if ($isLoginRequest && $method === 'POST') {
        // Connexion utilisateur
        $rawData = file_get_contents("php://input");
        $data = json_decode($rawData, true);
        
        if (!$data || !isset($data['username']) || !isset($data['password'])) {
            throw new Exception('Nom d\'utilisateur et mot de passe requis', 400);
        }
        
        $result = $serviceAuth->authentifier($data['username'], $data['password']);
        
        if ($result['success']) {
            // Stocker les informations utilisateur en session
            $_SESSION['user_id'] = $result['utilisateur']['id_utilisateur'];
            $_SESSION['user_name'] = $result['utilisateur']['username'];
            $_SESSION['user_role'] = $result['utilisateur']['role'];
            
            error_log("auth-api - Connexion réussie pour: " . $result['utilisateur']['username']);
            
            echo json_encode([
                'success' => true,
                'message' => 'Connexion réussie',
                'user' => $result['utilisateur']
            ]);
        } else {
            echo json_encode($result);
        }
        exit;
    }
    
    if ($isResetPasswordRequest && $method === 'POST') {
        // Demande de réinitialisation de mot de passe
        $rawData = file_get_contents("php://input");
        $data = json_decode($rawData, true);
        
        if (!$data || !isset($data['email'])) {
            throw new Exception('Email requis', 400);
        }
        
        $result = $serviceAuth->demanderResetPassword($data['email']);
        echo json_encode($result);
        exit;
    }
    
    if ($isVerifyTokenRequest && $method === 'POST') {
        // Vérification et réinitialisation avec token
        $rawData = file_get_contents("php://input");
        $data = json_decode($rawData, true);
        
        if (!$data || !isset($data['token']) || !isset($data['password'])) {
            throw new Exception('Token et nouveau mot de passe requis', 400);
        }
        
        $result = $serviceAuth->resetPasswordAvecToken($data['token'], $data['password']);
        echo json_encode($result);
        exit;
    }
    
    if ($isVerifyTokenRequest && $method === 'GET') {
        // Vérification simple d'un token
        if (!isset($_GET['token'])) {
            throw new Exception('Token requis', 400);
        }
        
        $result = $serviceAuth->verifierTokenResetPassword($_GET['token']);
        echo json_encode($result);
        exit;
    }
    
    // ====================================
    // VÉRIFICATION DE SESSION POUR LES AUTRES ROUTES
    // ====================================
    
    $userId = null;
    $userRole = null;
    
    if (isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
        $userRole = $_SESSION['user_role'] ?? null;
        error_log("auth-api - Session trouvée - User ID: $userId, Role: $userRole");
    } else {
        // Pour les routes qui nécessitent une authentification
        if ($isCheckSessionRequest || $isUtilisateursRequest || $isAdminAction) {
            error_log('auth-api - Session manquante pour route protégée');
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Session utilisateur non valide',
                'code' => 401
            ]);
            exit;
        }
    }
    
    // ====================================
    // ROUTES AUTHENTIFIÉES
    // ====================================
    
    if ($isLogoutRequest) {
        // Déconnexion
        session_destroy();
        echo json_encode([
            'success' => true,
            'message' => 'Déconnexion réussie'
        ]);
        exit;
    }
    
    if ($isCheckSessionRequest) {
        // Vérification de session
        if ($userId) {
            $userResult = $serviceAuth->getUtilisateurParId($userId);
            if ($userResult['success']) {
                echo json_encode([
                    'success' => true,
                    'user' => $userResult['utilisateur']
                ]);
            } else {
                throw new Exception('Utilisateur non trouvé', 401);
            }
        } else {
            throw new Exception('Session non valide', 401);
        }
        exit;
    }
    
    // ====================================
    // ROUTES ADMIN (nécessitent role admin ou gestionnaire)
    // ====================================
    
    if ($isAdminAction) {
        // Vérifier les droits admin
        if ($userRole !== 'admin' && $userRole !== 'gestionnaire') {
            error_log("auth-api - Accès refusé pour role: $userRole");
            throw new Exception('Droits administrateur requis', 403);
        }
        
        error_log("auth-api - Accès admin autorisé pour: $userRole");
        
        switch ($action) {
            case 'dashboard':
                // Récupérer les statistiques du dashboard
                $utilisateursResult = $serviceAuth->getUtilisateurs();
                
                if (!$utilisateursResult['success']) {
                    throw new Exception('Erreur lors de la récupération des utilisateurs: ' . $utilisateursResult['message']);
                }
                
                $utilisateurs = $utilisateursResult['utilisateurs'];
                $totalUtilisateurs = count($utilisateurs);
                $utilisateursActifs = count(array_filter($utilisateurs, function($u) { 
                    return $u['compte_actif'] == 1; 
                }));
                
                // CORRECTION: Utiliser le service pour récupérer les tentatives
                error_log("🔍 Dashboard - Récupération tentatives via service...");
                $tentativesResult = $serviceAuth->getTentativesRecentes(10);
                
                if ($tentativesResult['success']) {
                    $tentativesRecentes = $tentativesResult['tentatives'];
                    error_log("✅ Dashboard - " . count($tentativesRecentes) . " tentatives récupérées via service");
                } else {
                    $tentativesRecentes = [];
                    error_log("❌ Dashboard - Échec récupération tentatives: " . $tentativesResult['message']);
                }
                
                // Récupérer les informations système
                $systemInfo = [
                    'phpVersion' => phpversion(),
                    'serverSoftware' => $_SERVER['SERVER_SOFTWARE'] ?? 'Inconnu',
                    'sessionLifetime' => ini_get('session.gc_maxlifetime'),
                    'memoryLimit' => ini_get('memory_limit'),
                    'maxExecutionTime' => ini_get('max_execution_time')
                ];
                
                // Ajouter la version de la base de données
                try {
                    if (isset($conn) && $conn instanceof mysqli) {
                        $systemInfo['dbVersion'] = 'MySQL ' . mysqli_get_server_info($conn);
                    } elseif (isset($conn) && $conn instanceof PDO) {
                        $systemInfo['dbVersion'] = 'MySQL ' . $conn->getAttribute(PDO::ATTR_SERVER_VERSION);
                    } else {
                        $systemInfo['dbVersion'] = 'Base de données connectée';
                    }
                } catch (Exception $e) {
                    $systemInfo['dbVersion'] = 'Erreur de connexion DB';
                }
                
                $stats = [
                    'utilisateurs' => [
                        'total' => $totalUtilisateurs,
                        'actifs' => $utilisateursActifs,
                        'inactifs' => $totalUtilisateurs - $utilisateursActifs
                    ],
                    'derniers_utilisateurs' => array_slice($utilisateurs, 0, 5),
                    'tentatives' => $tentativesRecentes, // Maintenant récupéré via le service !
                    'systemInfo' => $systemInfo
                ];
                
                error_log("📊 Dashboard - Stats: users=" . count($stats['derniers_utilisateurs']) . 
                        ", tentatives=" . count($stats['tentatives']));
                
                echo json_encode([
                    'success' => true,
                    'data' => $stats
                ]);
                break;
                
            case 'utilisateurs':
                // Récupérer tous les utilisateurs
                $result = $serviceAuth->getUtilisateurs();
                echo json_encode($result);
                break;
                
            case 'utilisateur':
                // Récupérer un utilisateur spécifique
                if (!isset($_GET['id'])) {
                    throw new Exception('ID utilisateur manquant');
                }
                
                $result = $serviceAuth->getUtilisateurParId($_GET['id']);
                echo json_encode($result);
                break;
                
            case 'creer_utilisateur':
                // Créer un nouvel utilisateur (POST seulement)
                if ($method !== 'POST') {
                    throw new Exception('Méthode POST requise pour créer un utilisateur');
                }
                
                $rawData = file_get_contents("php://input");
                $data = json_decode($rawData, true);
                
                if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception('Données JSON invalides: ' . json_last_error_msg());
                }
                
                $result = $serviceAuth->creerUtilisateur($data);
                echo json_encode($result);
                break;
                
            case 'modifier_utilisateur':
                // Modifier un utilisateur existant (PUT seulement)
                if ($method !== 'PUT') {
                    throw new Exception('Méthode PUT requise pour modifier un utilisateur');
                }
                
                if (!isset($_GET['id'])) {
                    throw new Exception('ID utilisateur manquant');
                }
                
                $rawData = file_get_contents("php://input");
                $data = json_decode($rawData, true);
                
                if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception('Données JSON invalides: ' . json_last_error_msg());
                }
                
                $result = $serviceAuth->modifierUtilisateur($_GET['id'], $data);
                echo json_encode($result);
                break;
                
            case 'supprimer_utilisateur':
                // Supprimer un utilisateur (DELETE seulement)
                if ($method !== 'DELETE') {
                    throw new Exception('Méthode DELETE requise pour supprimer un utilisateur');
                }
                
                if (!isset($_GET['id'])) {
                    throw new Exception('ID utilisateur manquant');
                }
                
                $result = $serviceAuth->supprimerUtilisateur($_GET['id']);
                echo json_encode($result);
                break;
                
            default:
                throw new Exception('Action admin non reconnue: ' . $action);
        }
        exit;
    }
    
    // ====================================
    // ROUTES UTILISATEUR STANDARD
    // ====================================
    
    if ($isUtilisateursRequest) {
        // Route pour récupérer les utilisateurs (backward compatibility)
        if ($userRole !== 'admin' && $userRole !== 'gestionnaire') {
            throw new Exception('Droits insuffisants', 403);
        }
        
        $result = $serviceAuth->getUtilisateurs();
        echo json_encode($result);
        exit;
    }
    
    // Gestion des autres méthodes HTTP pour les utilisateurs
    if ($method === 'POST' && !$action) {
        // Création d'utilisateur (legacy)
        if ($userRole !== 'admin' && $userRole !== 'gestionnaire') {
            throw new Exception('Droits insuffisants', 403);
        }
        
        $rawData = file_get_contents("php://input");
        $data = json_decode($rawData, true);
        
        if (isset($data['action']) && $data['action'] === 'createUser') {
            unset($data['action']); // Retirer l'action des données
            $result = $serviceAuth->creerUtilisateur($data);
            echo json_encode($result);
        } else {
            throw new Exception('Action non reconnue');
        }
        exit;
    }
    
    if ($method === 'PUT' && !$action) {
        // Modification d'utilisateur (legacy)
        if ($userRole !== 'admin' && $userRole !== 'gestionnaire') {
            throw new Exception('Droits insuffisants', 403);
        }
        
        $rawData = file_get_contents("php://input");
        $data = json_decode($rawData, true);
        
        if (isset($data['id'])) {
            $userId = $data['id'];
            unset($data['id']); // Retirer l'ID des données
            $result = $serviceAuth->modifierUtilisateur($userId, $data);
            echo json_encode($result);
        } else {
            throw new Exception('ID utilisateur manquant');
        }
        exit;
    }
    
    if ($method === 'DELETE' && !$action) {
        // Suppression d'utilisateur (legacy)
        if ($userRole !== 'admin' && $userRole !== 'gestionnaire') {
            throw new Exception('Droits insuffisants', 403);
        }
        
        if (!isset($_GET['id'])) {
            throw new Exception('ID utilisateur manquant');
        }
        
        $result = $serviceAuth->supprimerUtilisateur($_GET['id']);
        echo json_encode($result);
        exit;
    }
    
    // Si aucune route ne correspond
    throw new Exception('Route non reconnue', 404);
    
} catch (Exception $e) {
    $code = $e->getCode();
    
    // S'assurer que le code est un entier valide pour HTTP
    $httpCode = 500; // Code par défaut
    
    if (is_int($code) && $code >= 100 && $code <= 599) {
        $httpCode = $code;
    } elseif (strpos($e->getMessage(), 'non reconnue') !== false || strpos($e->getMessage(), 'Route non reconnue') !== false) {
        $httpCode = 404;
    } elseif (strpos($e->getMessage(), 'non autorisé') !== false || 
              strpos($e->getMessage(), 'administrateur requis') !== false ||
              strpos($e->getMessage(), 'Droits') !== false) {
        $httpCode = 403;
    } elseif (strpos($e->getMessage(), 'Session') !== false || 
              strpos($e->getMessage(), 'non trouvé') !== false ||
              strpos($e->getMessage(), 'non valide') !== false) {
        $httpCode = 401;
    } elseif (strpos($e->getMessage(), 'requis') !== false ||
              strpos($e->getMessage(), 'manquant') !== false) {
        $httpCode = 400;
    }
    
    error_log("❌ auth-api - Erreur ($httpCode): " . $e->getMessage());
    
    http_response_code($httpCode);
    
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
            'original_code' => $e->getCode(),
            'session_debug' => [
                'session_id' => session_id(),
                'session_status' => session_status(),
                'user_id_present' => isset($_SESSION['user_id']),
                'session_keys' => array_keys($_SESSION ?? []),
                'method' => $method,
                'action' => $action,
                'get_params' => $_GET
            ]
        ];
    }
    
    echo json_encode($errorResponse);
}
?>