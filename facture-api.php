<?php
/**
 * facture-api.php - API pour la gestion des factures
 * Version sécurisée avec authentification obligatoire
 */

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (!empty($origin)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    header("Access-Control-Allow-Origin: http://localhost:3000");
}
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Max-Age: 86400');

// ✅ CRITIQUE: Gérer OPTIONS immédiatement
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Utiliser la session centralisée comme les autres APIs
$config = require_once realpath(__DIR__ . '/bootstrap.php');

// ✅ VÉRIFICATION SESSION - Ajouter cette ligne
check_session_validity();

// Initialiser l'API avec CORS centralisé
init_api_response();

error_log('facture-api - Chargement de la configuration');

// Désactiver l'affichage des erreurs/warnings seulement si non en mode développement
if (!is_dev_mode()) {
    ini_set('display_errors', 0);
    error_reporting(E_ERROR);
}

ob_start(); // Start output buffering

// Inclure les dépendances nécessaires
require_once 'database.php';
require_once realpath(__DIR__ . '/services/ServiceFacture.php');
require_once realpath(__DIR__ . '/services/ServiceParametre.php');

// Debug session en mode développement
if (is_dev_mode()) {
    error_log("facture-api - Méthode: " . $_SERVER['REQUEST_METHOD']);
    error_log("facture-api - URL complète: " . $_SERVER['REQUEST_URI']);
    error_log("facture-api - Origin: " . ($_SERVER['HTTP_ORIGIN'] ?? 'Non défini'));
    error_log("facture-api - Paramètres GET: " . json_encode($_GET));
    error_log("facture-api - Session ID: " . session_id());
    error_log("facture-api - Session status: " . session_status());
    error_log("facture-api - Session content: " . json_encode($_SESSION));
    error_log("🔍 facture-api - Session initialisée par bootstrap.php");
}

try {
    // Créer les instances des services
    $serviceFacture = new ServiceFacture($conn);
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
        error_log("facture-api - Session trouvée - User ID: $userId, Role: $userRole");
    } else {
        error_log('facture-api - Aucune session utilisateur trouvée');
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
                'api' => 'facture-api.php'
            ]
        ]);
        exit;
    }
    
    // ====================================
    // VÉRIFICATION D'AUTHENTIFICATION POUR TOUTES LES ROUTES
    // ====================================
    
    // Toutes les routes de l'API facture nécessitent une authentification
    if (!$isAuthenticated) {
        error_log('facture-api - Accès refusé - Session expirée');
        
        // Headers déjà envoyés au début, juste renvoyer JSON
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Session expirée',
            'session_expired' => true,
            'code' => 401
        ]);
        exit;
    }
    
    // Log de l'accès autorisé
    if (is_dev_mode()) {
        error_log("✅ facture-api - Accès autorisé pour utilisateur: $userId (role: $userRole)");
    }
    
    // Logging pour debug en mode développement
    if (is_dev_mode()) {
        error_log("facture-api - Méthode: $method");
        error_log("facture-api - Paramètres GET: " . json_encode($_GET));
        if (in_array($method, ['POST', 'PUT'])) {
            $input = file_get_contents("php://input");
            if (!empty($input)) {
                error_log("facture-api - Données reçues: " . $input);
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
                error_log("facture-api - GET - Paramètres: " . json_encode($_GET) . " (user: $userId)");
            }
            
            // Statistiques
            if (isset($_GET['statistiques'])) { 
                $annee = isset($_GET['annee']) ? intval($_GET['annee']) : null;
                if (is_dev_mode()) {
                    error_log("facture-api - GET statistiques pour année $annee (user: $userId)");
                }
                $resultat = $serviceFacture->getStatistiques($annee);
                echo json_encode($resultat);
                break;
            }

            // Facture spécifique
            if (isset($_GET['id_facture'])) {
                 if (is_dev_mode()) {
                    error_log("facture-api - GET facture ID: " . $_GET['id_facture'] . " (user: $userId)");
                }
                $resultat = $serviceFacture->getFactureComplete($_GET['id_facture']);
                echo json_encode($resultat);
                break;
            }

            // Récupérer l'URL de visualisation d'une facture
            if (isset($_GET['getUrl']) && isset($_GET['id'])) {
                if (is_dev_mode()) {
                    error_log("facture-api - GET URL facture ID: " . $_GET['id'] . " (user: $userId)");
                }
                $resultat = $serviceFacture->getFactureUrl($_GET['id']);
                echo json_encode($resultat);
                break;
            }
            
            // ✅ NOUVEAU: Factures d'un client spécifique
            // GET /api/facture-api.php?id_client=123
            if (isset($_GET['id_client'])) {
                $id_client = intval($_GET['id_client']);
                
                if (is_dev_mode()) {
                    error_log("facture-api - GET factures du client ID: $id_client (user: $userId)");
                }
                
                $resultat = $serviceFacture->getFacturesClient($id_client);
                
                if (is_dev_mode()) {
                    error_log("facture-api - Factures du client #$id_client: " . 
                              (isset($resultat['factures']) ? count($resultat['factures']) : 'N/A') . " factures");
                }
                
                echo json_encode($resultat);
                break;
            }
            
            // Support pour pagination
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            
            // Liste des factures
            $options = [
                'annee' => isset($_GET['annee']) ? intval($_GET['annee']) : null,
                'page' => $page,
                'limit' => $limit
            ];
            
            if (is_dev_mode()) {
                error_log("facture-api - GET liste factures avec options: " . json_encode($options) . " (user: $userId)");
            }
            
            $resultat = $serviceFacture->listerFactures($options);
            
            if (is_dev_mode()) {
                error_log("facture-api - Résultat liste factures: " . json_encode($resultat));
                error_log("facture-api - Résultat liste factures (nombre): " . (isset($resultat['factures']) ? count($resultat['factures']) : 'N/A'));
            }
            
            echo json_encode($resultat);
            break;
            
        case 'POST':
            // ====================================
            // VÉRIFICATION DES DROITS POUR MODIFICATIONS
            // ====================================
            
            // Vérifier les droits de modification (admin ou gestionnaire)
            if ($userRole !== 'admin' && $userRole !== 'gestionnaire') {
                error_log("facture-api - Droits insuffisants pour POST - User: $userId, Role: $userRole");
                throw new Exception('Droits administrateur ou gestionnaire requis pour créer des factures', 403);
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
            
            // Imprimer un PDF de facture
            if (isset($_GET['imprimer']) && isset($_GET['id_facture'])) {
                $options = isset($data['options']) ? $data['options'] : [];
                
                if (is_dev_mode()) {
                    error_log("facture-api - POST impression facture ID: " . $_GET['id_facture'] . " avec options: " . json_encode($options) . " (user: $userId)");
                }
                
                $resultat = $serviceFacture->imprimerFacture($_GET['id_facture'], $options);
                echo json_encode($resultat);
                ob_end_flush();
                exit;
            }

            // Changer l'état d'une facture (avec protection contre l'état "Retard")
            if (isset($_GET['changerEtat']) && isset($_GET['id_facture'])) {
                if (!isset($data['nouvelEtat'])) {
                    throw new Exception('Nouvel état non spécifié');
                }
                
                // ✅ PROTECTION: Empêcher la persistance de l'état "Retard"
                if ($data['nouvelEtat'] === 'Retard') {
                    if (is_dev_mode()) {
                        error_log("⚠️ facture-api - Tentative de persistance de l'état 'Retard' bloquée (user: $userId)");
                    }
                    echo json_encode([
                        'success' => false,
                        'message' => 'L\'état "Retard" ne peut pas être persisté. Il est calculé automatiquement côté client.',
                        'code' => 'RETARD_NOT_PERSISTABLE'
                    ]);
                    break;
                }
                
                if (is_dev_mode()) {
                    error_log("facture-api - POST changement état facture ID: " . $_GET['id_facture'] . " vers: " . $data['nouvelEtat'] . " (user: $userId)");
                }
                
                $resultat = $serviceFacture->changerEtatFacture($_GET['id_facture'], $data['nouvelEtat']);
                echo json_encode($resultat);
                break;
            }

            // Envoyer une facture par email
            if (isset($_GET['envoyer']) && isset($_GET['id_facture'])) {
                if (is_dev_mode()) {
                    error_log("facture-api - POST envoi facture par email - ID: " . $_GET['id_facture'] . " (user: $userId)");
                    error_log("facture-api - Données email: " . json_encode($data));
                    
                    // Log spécifique pour le bypass
                    if (isset($data['bypassCapture']) && $data['bypassCapture']) {
                        error_log("🚨 BYPASS CAPTURE activé pour la facture ID: " . $_GET['id_facture'] . " par user: $userId");
                    }
                }
                
                $resultat = $serviceFacture->envoyerFactureParEmail($_GET['id_facture'], $data);
                echo json_encode($resultat);
                break;
            }


            // ✅ SUPPRIMÉ: Route mettreAJourRetards (plus nécessaire, calculé côté client)
            
            // Créer une facture
            if (!$data) {
                throw new Exception('Aucune donnée reçue pour créer la facture');
            }
            
            if (is_dev_mode()) {
                error_log("facture-api - POST création nouvelle facture (user: $userId)");
                error_log("facture-api - Données reçues pour création: " . json_encode($data));
            }

            // ✅ Sécurité : ignorer tout numero_facture envoyé par le frontend.
            //    La numérotation est exclusivement gérée par ServiceFacture::creerFacture()
            //    via FactureControleur::allouerNumeroFacture() dans une transaction atomique.
            unset($data['numero_facture']);
            
            $resultat = $serviceFacture->creerFacture($data);
            echo json_encode($resultat);
            break;
            
        case 'PUT':
            // Vérifier les droits de modification (admin ou gestionnaire)
            if ($userRole !== 'admin' && $userRole !== 'gestionnaire') {
                error_log("facture-api - Droits insuffisants pour PUT - User: $userId, Role: $userRole");
                throw new Exception('Droits administrateur ou gestionnaire requis pour modifier des factures', 403);
            }

           
            // Mettre à jour une facture existante
            $rawData = file_get_contents("php://input");
            $data = json_decode($rawData, true);
            
            // Validation des données JSON
            if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Données JSON invalides: ' . json_last_error_msg());
            }
            
            // L'ID peut être fourni dans l'URL ou dans le corps de la requête
            $id_facture = $_GET['id_facture'] ?? ($data['id_facture'] ?? null);
            
            if (!$id_facture) {
                throw new Exception('ID facture manquant');
            }
            
            if (is_dev_mode()) {
                error_log("facture-api - PUT modification facture ID: $id_facture (user: $userId)");
            }
            
            $resultat = $serviceFacture->modifierFacture($id_facture, $data);
            echo json_encode($resultat);
            break;
            
        case 'DELETE':
            // Vérifier les droits de modification (admin ou gestionnaire)
            if ($userRole !== 'admin' && $userRole !== 'gestionnaire') {
                error_log("facture-api - Droits insuffisants pour DELETE - User: $userId, Role: $userRole");
                throw new Exception('Droits administrateur ou gestionnaire requis pour supprimer des factures', 403);
            }
            
            // Supprimer une facture
            if (!isset($_GET['id_facture'])) {
                throw new Exception('ID facture manquant');
            }


            if (is_dev_mode()) {
                error_log("facture-api - DELETE facture ID: " . $_GET['id_facture'] . " (user: $userId)");
            }
            
            $resultat = $serviceFacture->supprimerFacture($_GET['id_facture']);
            echo json_encode($resultat);
            break;
            
        default:
            throw new Exception('Méthode HTTP non supportée: ' . $method);
    }
    
} catch (Exception $e) {
    error_log('❌ Erreur API Facture: ' . $e->getMessage());
    
    // Headers CORS déjà envoyés
    $httpCode = 400;
    if (strpos($e->getMessage(), 'Authentification') !== false) {
        $httpCode = 401;
    }
    
    http_response_code($httpCode);
    header('Content-Type: application/json');
    
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage(),
        'code' => $httpCode,
        'session_expired' => $httpCode === 401
    ]);
}
?>