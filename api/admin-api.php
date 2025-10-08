<?php
/**
 * admin-api.php - API pour les fonctions d'administration
 * Version propre utilisant UNIQUEMENT ServiceAuthentification
 */

// Inclure la configuration centralisée avec CORS
$config = require_once realpath(__DIR__ . '/../bootstrap.php');

// ✅ VÉRIFICATION SESSION - Ajouter cette ligne
check_session_validity();

// Initialiser l'API avec CORS centralisé
init_api_response();

error_log('admin-api - Chargement de la configuration');
error_log('admin-api - Configuration: ' . json_encode($config));

// Désactiver l'affichage des erreurs/warnings seulement si non en mode développement
if (!is_dev_mode()) {
    ini_set('display_errors', 0);
    error_reporting(E_ERROR);
}

ob_start(); // Start output buffering

// Inclure les dépendances nécessaires
require_once 'database.php';
require_once '../services/ServiceAuthentification.php';

// Debug session en mode développement
if (is_dev_mode()) {
    error_log("admin-api - Méthode: " . $_SERVER['REQUEST_METHOD']);
    error_log("admin-api - URL complète: " . $_SERVER['REQUEST_URI']);
    error_log("admin-api - Origin: " . ($_SERVER['HTTP_ORIGIN'] ?? 'Non défini'));
    error_log("admin-api - Paramètres GET: " . json_encode($_GET));
    error_log("admin-api - Session ID: " . session_id());
    error_log("admin-api - Session status: " . session_status());
    error_log("admin-api - Session content: " . json_encode($_SESSION));
    error_log("admin-api - Cookies reçus: " . json_encode($_COOKIE));
    error_log("admin-api - User-Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'Non défini'));
}

try {
    // Créer l'instance du service d'authentification
    $serviceAuth = new ServiceAuthentification($conn);
    
    // Vérification de la session utilisateur
    if (!isset($_SESSION['user_id'])) {
        error_log('admin-api - ERREUR: user_id manquant en session');
        throw new Exception('Session utilisateur non valide', 401);
    }
    
    $userId = $_SESSION['user_id'];
    error_log("admin-api - Utilisateur depuis session: $userId");
    
    // Récupérer les informations de l'utilisateur via le service
    $userResult = $serviceAuth->getUtilisateurParId($userId);
    
    if (!$userResult['success']) {
        error_log("admin-api - Utilisateur non trouvé pour ID: $userId");
        throw new Exception('Utilisateur non trouvé', 401);
    }
    
    $user = $userResult['utilisateur'];
    error_log("admin-api - Utilisateur trouvé: " . $user['username'] . " (role: " . $user['role'] . ")");
    
    if ($user['role'] !== 'admin') {
        error_log('admin-api - Accès refusé - Role: ' . $user['role'] . ' (requis: admin)');
        throw new Exception('Droits administrateur requis', 403);
    }
    
    error_log('admin-api - ✅ Accès autorisé pour admin: ' . $user['username']);
    
    // Déterminer l'action à effectuer
    $action = $_GET['action'] ?? 'dashboard';
    error_log("admin-api - Action demandée: $action");
    
    // Traiter les requêtes selon l'action demandée
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
            
            // TODO: Ajouter d'autres statistiques si nécessaire
            // - Nombre de factures (via ServiceFacture)
            // - Chiffre d'affaires (via ServiceFacture)
            // - etc.
            
            $stats = [
                'utilisateurs' => [
                    'total' => $totalUtilisateurs,
                    'actifs' => $utilisateursActifs,
                    'inactifs' => $totalUtilisateurs - $utilisateursActifs
                ],
                'derniers_utilisateurs' => array_slice($utilisateurs, 0, 5) // 5 derniers utilisateurs
            ];
            
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
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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
            if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
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
            if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
                throw new Exception('Méthode DELETE requise pour supprimer un utilisateur');
            }
            
            if (!isset($_GET['id'])) {
                throw new Exception('ID utilisateur manquant');
            }
            
            $result = $serviceAuth->supprimerUtilisateur($_GET['id']);
            echo json_encode($result);
            break;
            
        default:
            throw new Exception('Action non reconnue: ' . $action);
    }
    
} catch (Exception $e) {
    $code = $e->getCode();
    
    // S'assurer que le code est un entier valide pour HTTP
    $httpCode = 500; // Code par défaut
    
    if (is_int($code) && $code >= 100 && $code <= 599) {
        $httpCode = $code;
    } elseif (strpos($e->getMessage(), 'non reconnue') !== false) {
        $httpCode = 404;
    } elseif (strpos($e->getMessage(), 'non autorisé') !== false || 
              strpos($e->getMessage(), 'administrateur requis') !== false) {
        $httpCode = 403;
    } elseif (strpos($e->getMessage(), 'Session') !== false || 
              strpos($e->getMessage(), 'non trouvé') !== false) {
        $httpCode = 401;
    }
    
    error_log("❌ admin-api - Erreur ($httpCode): " . $e->getMessage());
    
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
            'original_code' => $e->getCode(), // Afficher le code original pour debug
            'session_debug' => [
                'session_id' => session_id(),
                'session_status' => session_status(),
                'user_id_present' => isset($_SESSION['user_id']),
                'session_keys' => array_keys($_SESSION ?? [])
            ]
        ];
    }
    
    echo json_encode($errorResponse);
}
?>