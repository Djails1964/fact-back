<?php
/**
 * parametre-api.php - API pour la gestion des paramètres
 * Version sécurisée avec authentification obligatoire
 */

// Utiliser la session centralisée comme les autres APIs
$config = require_once realpath(__DIR__ . '/bootstrap.php');

// ✅ VÉRIFICATION SESSION - Ajouter cette ligne
check_session_validity();

// Initialiser l'API avec CORS centralisé
init_api_response();

error_log('parametre-api - Chargement de la configuration');

// Désactiver l'affichage des erreurs/warnings seulement si non en mode développement
if (!is_dev_mode()) {
    ini_set('display_errors', 0);
    error_reporting(E_ERROR);
}

ob_start(); // Start output buffering

// Inclure les dépendances nécessaires
require_once 'database.php';
require_once realpath(__DIR__ . '/services/ServiceParametre.php');

// Debug session en mode développement
if (is_dev_mode()) {
    error_log("parametre-api - Méthode: " . $_SERVER['REQUEST_METHOD']);
    error_log("parametre-api - URL complète: " . $_SERVER['REQUEST_URI']);
    error_log("parametre-api - Origin: " . ($_SERVER['HTTP_ORIGIN'] ?? 'Non défini'));
    error_log("parametre-api - Paramètres GET: " . json_encode($_GET));
    error_log("parametre-api - Session ID: " . session_id());
    error_log("parametre-api - Session status: " . session_status());
    error_log("parametre-api - Session content: " . json_encode($_SESSION));
    error_log("🔍 parametre-api - Session initialisée par bootstrap.php");
}

try {
    // Créer l'instance du service
    $serviceParametre = new ServiceParametre($conn);
    
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
        error_log("parametre-api - Session trouvée - User ID: $userId, Role: $userRole");
    } else {
        error_log('parametre-api - Aucune session utilisateur trouvée');
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
                'api' => 'parametre-api.php'
            ]
        ]);
        exit;
    }
    
    // ====================================
    // VÉRIFICATION D'AUTHENTIFICATION POUR TOUTES LES ROUTES
    // ====================================
    
    // Toutes les routes de l'API paramètre nécessitent une authentification
    if (!$isAuthenticated) {
        error_log('parametre-api - Accès refusé - Authentification requise');
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Authentification requise pour accéder aux paramètres',
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
        error_log("✅ parametre-api - Accès autorisé pour utilisateur: $userId (role: $userRole)");
    }
    
    // Logging pour debug en mode développement
    if (is_dev_mode()) {
        error_log("parametre-api - Méthode: $method");
        error_log("parametre-api - Paramètres GET: " . json_encode($_GET));
        if (in_array($method, ['POST', 'PUT'])) {
            $input = file_get_contents("php://input");
            if (!empty($input)) {
                error_log("parametre-api - Données reçues: " . $input);
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
            
            if (isset($_GET['prochainNumeroFacture'])) {
                // Récupérer le prochain numéro de facture pour l'année spécifiée
                $annee_parametre = intval($_GET['prochainNumeroFacture']);
                
                if (is_dev_mode()) {
                    error_log("parametre-api - GET prochain numéro facture pour année: $annee_parametre (user: $userId)");
                }
                
                $resultat = $serviceParametre->getProchainNumeroFacture($annee_parametre);
                echo json_encode($resultat);
                
            } else if (isset($_GET['parametres'])) {
                // Utiliser la nouvelle fonction getParametres avec filtrage hiérarchique
                $groupe_parametre = isset($_GET['groupe_parametre']) ? $_GET['groupe_parametre'] : null;
                $sous_groupe_parametre = isset($_GET['sous_groupe_parametre']) ? $_GET['sous_groupe_parametre'] : null;
                $categorie = isset($_GET['categorie']) ? $_GET['categorie'] : null;
                
                if (is_dev_mode()) {
                    error_log("parametre-api - GET paramètres: groupe_parametre=$groupe_parametre, sous_groupe_parametre=$sous_groupe_parametre, categorie=$categorie (user: $userId)");
                }
                
                $resultat = $serviceParametre->getParametres($groupe_parametre, $sous_groupe_parametre, $categorie);
                echo json_encode($resultat);
                
            } else if (isset($_GET['tarifsLocationSalle']) && $_GET['tarifsLocationSalle'] === 'true') {
                // Récupérer spécifiquement les tarifs de location de salle
                if (is_dev_mode()) {
                    error_log("parametre-api - GET tarifs location salle (user: $userId)");
                }
                
                $resultat = $serviceParametre->getTarifsLocationSalle();
                echo json_encode($resultat);
                
            } else if (isset($_GET['groupe_parametre']) && isset($_GET['sous_groupe_parametre'])) {
                // Récupérer les paramètres par groupe_parametre et sous-groupe_parametre
                $groupe_parametre = $_GET['groupe_parametre'];
                $sous_groupe_parametre = $_GET['sous_groupe_parametre'];
                
                if (is_dev_mode()) {
                    error_log("parametre-api - GET paramètres par sous-groupe_parametre: $groupe_parametre/$sous_groupe_parametre (user: $userId)");
                }
                
                $resultat = $serviceParametre->getParametresParSousGroupe($groupe_parametre, $sous_groupe_parametre);
                error_log("parametre-api - Résultat paramètres par sous-groupe_parametre: " . json_encode($resultat, JSON_PRETTY_PRINT));
                echo json_encode($resultat);
                
            } else if (isset($_GET['groupe_parametre']) && !isset($_GET['nom_parametre'])) {
                // Récupérer tous les paramètres d'un groupe_parametre spécifique
                $groupe_parametre = $_GET['groupe_parametre'];
                
                if (is_dev_mode()) {
                    error_log("parametre-api - GET paramètres par groupe_parametre: $groupe_parametre (user: $userId)");
                }
                
                $resultat = $serviceParametre->getParametresParGroupe($groupe_parametre);
                echo json_encode($resultat);
                
            } else if (isset($_GET['tousGroupes']) && $_GET['tousGroupes'] === 'true') {
                // Récupérer tous les paramètres organisés par groupe_parametre
                if (is_dev_mode()) {
                    error_log("parametre-api - GET tous les paramètres par groupe_parametre (user: $userId)");
                }
                
                $resultat = $serviceParametre->getParametresParGroupe();
                echo json_encode($resultat);
                
            } else if (isset($_GET['allTarifs']) && $_GET['allTarifs'] === 'true') {
                // Récupérer tous les tarifs
                if (is_dev_mode()) {
                    error_log("parametre-api - GET tous les tarifs (user: $userId)");
                }
                
                $resultat = $serviceParametre->getAllTarifs();
                echo json_encode($resultat);
                
            } else if (isset($_GET['nom_parametre']) && isset($_GET['groupe_parametre'])) {
                // Récupérer un paramètre spécifique avec groupe_parametre obligatoire
                $nom_parametre = $_GET['nom_parametre'];
                $groupe_parametre = $_GET['groupe_parametre'];
                $sous_groupe_parametre = isset($_GET['sous_groupe_parametre']) ? $_GET['sous_groupe_parametre'] : null;
                $categorie = isset($_GET['categorie']) ? $_GET['categorie'] : null;
                $annee_parametre = isset($_GET['annee_parametre']) ? intval($_GET['annee_parametre']) : null;
                
                if (is_dev_mode()) {
                    error_log("parametre-api - GET paramètre spécifique: $nom_parametre dans $groupe_parametre (user: $userId)");
                }
                
                $resultat = $serviceParametre->getParametre(
                    $nom_parametre, 
                    $groupe_parametre, 
                    $sous_groupe_parametre, 
                    $categorie, 
                    $annee_parametre
                );
                
                if (is_dev_mode()) {
                    error_log("parametre-api - Résultat paramètre: " . json_encode($resultat, JSON_PRETTY_PRINT));
                }
                
                echo json_encode($resultat);
                
            } else if (isset($_GET['nom_parametre'])) {
                // Pour la rétrocompatibilité - Récupérer un paramètre sans préciser le groupe_parametre
                $annee_parametre = isset($_GET['annee_parametre']) ? intval($_GET['annee_parametre']) : null;
                $nom_parametre = $_GET['nom_parametre'];
                
                if (is_dev_mode()) {
                    error_log("parametre-api - GET paramètre simple (rétrocompatibilité): $nom_parametre, année: $annee_parametre (user: $userId)");
                }
                
                $resultat = $serviceParametre->getParametreSimple($annee_parametre, $nom_parametre);
                echo json_encode($resultat);
                
            } else {
                // Aucun paramètre valide spécifié
                error_log('parametre-api - Aucun paramètre valide spécifié dans la requête GET');
                throw new Exception('Paramètres de requête insuffisants ou incorrects');
            }
            break;
            
        case 'POST':
            // ====================================
            // VÉRIFICATION DES DROITS POUR MODIFICATIONS
            // ====================================
            
            // Vérifier les droits de modification (admin ou gestionnaire)
            if ($userRole !== 'admin' && $userRole !== 'gestionnaire') {
                error_log("parametre-api - Droits insuffisants pour POST - User: $userId, Role: $userRole");
                throw new Exception('Droits administrateur ou gestionnaire requis pour créer des paramètres', 403);
            }
            
            // Validation des données JSON
            $rawData = file_get_contents("php://input");
            $data = json_decode($rawData, true);
            
            if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Données JSON invalides: ' . json_last_error_msg());
            }
            
            if (!$data) {
                throw new Exception('Aucune donnée reçue');
            }
            
            // Vérifier que les données obligatoires sont présentes
            if (!isset($data['nom_parametre']) || !isset($data['valeur_parametre']) || !isset($data['groupe_parametre'])) {
                throw new Exception('Données de paramètre incomplètes (nom_parametre, valeur_parametre et groupe_parametre sont obligatoires)');
            }
            
            if (is_dev_mode()) {
                error_log("parametre-api - POST enregistrement paramètre: " . $data['nom_parametre'] . " (user: $userId)");
            }
            
            $resultat = $serviceParametre->enregistrerParametre($data);
            echo json_encode($resultat);
            break;
            
        case 'PUT':
            // Vérifier les droits de modification (admin ou gestionnaire)
            if ($userRole !== 'admin' && $userRole !== 'gestionnaire') {
                error_log("parametre-api - Droits insuffisants pour PUT - User: $userId, Role: $userRole");
                throw new Exception('Droits administrateur ou gestionnaire requis pour modifier des paramètres', 403);
            }
            
            $rawData = file_get_contents("php://input");
            $data = json_decode($rawData, true);
            
            if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Données JSON invalides: ' . json_last_error_msg());
            }
            
            // L'ID peut être fourni dans l'URL ou dans le corps de la requête
            $id = $_GET['id'] ?? ($data['id'] ?? null);
            
            if (!$id) {
                throw new Exception('ID paramètre manquant');
            }
            
            if (is_dev_mode()) {
                error_log("parametre-api - PUT modification paramètre ID: $id (user: $userId)");
            }
            
            // Utiliser la même méthode que POST si elle supporte la mise à jour
            $resultat = $serviceParametre->enregistrerParametre($data);
            echo json_encode($resultat);
            break;
            
        case 'DELETE':
            // Vérifier les droits de modification (admin ou gestionnaire)
            if ($userRole !== 'admin' && $userRole !== 'gestionnaire') {
                error_log("parametre-api - Droits insuffisants pour DELETE - User: $userId, Role: $userRole");
                throw new Exception('Droits administrateur ou gestionnaire requis pour supprimer des paramètres', 403);
            }
            
            if (!isset($_GET['id'])) {
                throw new Exception('ID paramètre manquant');
            }
            
            if (is_dev_mode()) {
                error_log("parametre-api - DELETE paramètre ID: " . $_GET['id'] . " (user: $userId)");
            }
            
            // Si la méthode existe dans le service
            if (method_exists($serviceParametre, 'supprimerParametre')) {
                $resultat = $serviceParametre->supprimerParametre($_GET['id']);
                echo json_encode($resultat);
            } else {
                throw new Exception('Suppression de paramètres non implémentée');
            }
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
    
    error_log('❌ Erreur API Parametre: ' . json_encode($errorDetails));
    
    // Codes d'erreur HTTP spécifiques
    $httpCode = 400; // Bad Request par défaut
    
    if (strpos($e->getMessage(), 'Authentification requise') !== false) {
        $httpCode = 401; // Unauthorized
    } elseif (strpos($e->getMessage(), 'Droits') !== false || 
              strpos($e->getMessage(), 'administrateur requis') !== false) {
        $httpCode = 403; // Forbidden
    } elseif (strpos($e->getMessage(), 'manquant') !== false || 
              strpos($e->getMessage(), 'requis') !== false || 
              strpos($e->getMessage(), 'obligatoires') !== false) {
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