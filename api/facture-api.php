<?php
/**
 * facture-api.php - API pour la gestion des factures
 * Version sécurisée avec authentification obligatoire
 */

// Utiliser la session centralisée comme les autres APIs
$config = require_once realpath(__DIR__ . '/../bootstrap.php');

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
require_once realpath(__DIR__ . '/../ServiceFacture.php');
require_once realpath(__DIR__ . '/../services/ServiceParametre.php');

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
        error_log('facture-api - Accès refusé - Authentification requise');
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Authentification requise pour accéder aux factures',
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

            
            // Récupérer l'historique des paiements d'une facture
            if (isset($_GET['historiquePaiements']) && isset($_GET['id'])) {
                if (is_dev_mode()) {
                    error_log("facture-api - GET historique paiements pour facture ID: " . $_GET['id'] . " (user: $userId)");
                }
                
                try {
                    $resultat = $serviceFacture->getHistoriquePaiements($_GET['id']);
                    echo json_encode($resultat);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'message' => $e->getMessage()
                    ]);
                }
                break;
            }

            // Récupérer les statistiques de paiement d'une facture
            if (isset($_GET['statistiquesPaiement']) && isset($_GET['id'])) {
                if (is_dev_mode()) {
                    error_log("facture-api - GET statistiques paiement pour facture ID: " . $_GET['id'] . " (user: $userId)");
                }
                
                try {
                    $resultat = $serviceFacture->getStatistiquesPaiement($_GET['id']);
                    echo json_encode($resultat);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'message' => $e->getMessage()
                    ]);
                }
                break;
            }
            
            // Facture spécifique
            if (isset($_GET['id'])) {
                if (is_dev_mode()) {
                    error_log("facture-api - GET facture ID: " . $_GET['id'] . " (user: $userId)");
                }
                $resultat = $serviceFacture->getFactureComplete($_GET['id']);
                echo json_encode($resultat);
                break;
            }

            // Paramètres (prochainNumeroFacture)
            if (isset($_GET['prochainNumeroFacture'])) {
                if (is_dev_mode()) {
                    error_log("facture-api - GET prochain numéro facture (user: $userId)");
                }
                $resultat = $serviceFacture->getProchainNumeroFacture($_GET['prochainNumeroFacture']);
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
                error_log("facture-api - Résultat liste factures (nombre): " . (isset($resultat['data']) ? count($resultat['data']) : 'N/A'));
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
            if (isset($_GET['imprimer']) && isset($_GET['id'])) {
                $options = isset($data['options']) ? $data['options'] : [];
                
                if (is_dev_mode()) {
                    error_log("facture-api - POST impression facture ID: " . $_GET['id'] . " avec options: " . json_encode($options) . " (user: $userId)");
                }
                
                $resultat = $serviceFacture->imprimerFacture($_GET['id'], $options);
                echo json_encode($resultat);
                ob_end_flush();
                exit;
            }

            // Changer l'état d'une facture
            if (isset($_GET['changerEtat']) && isset($_GET['id'])) {
                if (!isset($data['nouvelEtat'])) {
                    throw new Exception('Nouvel état non spécifié');
                }
                
                if (is_dev_mode()) {
                    error_log("facture-api - POST changement état facture ID: " . $_GET['id'] . " vers: " . $data['nouvelEtat'] . " (user: $userId)");
                }
                
                $resultat = $serviceFacture->changerEtatFacture($_GET['id'], $data['nouvelEtat']);
                echo json_encode($resultat);
                break;
            }

            // Envoyer une facture par email
            if (isset($_GET['envoyer']) && isset($_GET['id'])) {
                if (is_dev_mode()) {
                    error_log("facture-api - POST envoi facture par email - ID: " . $_GET['id'] . " (user: $userId)");
                    error_log("facture-api - Données email: " . json_encode($data));
                    
                    // Log spécifique pour le bypass
                    if (isset($data['bypassCapture']) && $data['bypassCapture']) {
                        error_log("🚨 BYPASS CAPTURE activé pour la facture ID: " . $_GET['id'] . " par user: $userId");
                    }
                }
                
                $resultat = $serviceFacture->envoyerFactureParEmail($_GET['id'], $data);
                echo json_encode($resultat);
                break;
            }

            // Enregistrer un paiement (nouvelle version avec paiements multiples)
            if (isset($_GET['paiement']) && isset($_GET['id'])) {
                if (is_dev_mode()) {
                    error_log("facture-api - POST enregistrement paiement pour facture ID: " . $_GET['id'] . " (user: $userId)");
                    error_log("facture-api - Données paiement: " . json_encode($data));
                }
                
                try {
                    $resultat = $serviceFacture->enregistrerPaiement($_GET['id'], $data);
                    
                    // Ajouter des informations supplémentaires au résultat
                    if ($resultat['success']) {
                        $resultat['factureId'] = $_GET['id'];
                    }
                    
                    echo json_encode($resultat);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'message' => $e->getMessage()
                    ]);
                }
                break;
            }

            // Mettre à jour les factures en retard
            if (isset($_GET['mettreAJourRetards'])) {
                if (is_dev_mode()) {
                    error_log("facture-api - POST mise à jour factures en retard (user: $userId)");
                }
                
                $resultat = $serviceFacture->mettreAJourFacturesEnRetard();
                echo json_encode($resultat);
                break;
            }
            
            // Créer une facture
            if (!$data) {
                throw new Exception('Aucune donnée reçue pour créer la facture');
            }
            
            if (is_dev_mode()) {
                error_log("facture-api - POST création nouvelle facture (user: $userId)");
            }
            
            $resultat = $serviceFacture->creerFacture($data);
            echo json_encode($resultat);
            break;
            
        case 'PUT':
            // Vérifier les droits de modification (admin ou gestionnaire)
            if ($userRole !== 'admin' && $userRole !== 'gestionnaire') {
                error_log("facture-api - Droits insuffisants pour PUT - User: $userId, Role: $userRole");
                throw new Exception('Droits administrateur ou gestionnaire requis pour modifier des factures', 403);
            }

            // Modifier un paiement existant
            if (isset($_GET['modifierPaiement']) && isset($_GET['id'])) {
                if (is_dev_mode()) {
                    error_log("facture-api - PUT modification paiement ID: " . $_GET['id'] . " (user: $userId)");
                    error_log("facture-api - Données: " . json_encode($data));
                }
                
                try {
                    $resultat = $serviceFacture->modifierPaiement($_GET['id'], $data);
                    echo json_encode($resultat);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'message' => $e->getMessage()
                    ]);
                }
                break;
            }
            
            // Mettre à jour une facture existante
            $rawData = file_get_contents("php://input");
            $data = json_decode($rawData, true);
            
            // Validation des données JSON
            if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Données JSON invalides: ' . json_last_error_msg());
            }
            
            // L'ID peut être fourni dans l'URL ou dans le corps de la requête
            $id = $_GET['id'] ?? ($data['id'] ?? null);
            
            if (!$id) {
                throw new Exception('ID facture manquant');
            }
            
            if (is_dev_mode()) {
                error_log("facture-api - PUT modification facture ID: $id (user: $userId)");
            }
            
            $resultat = $serviceFacture->modifierFacture($id, $data);
            echo json_encode($resultat);
            break;
            
        case 'DELETE':
            // Vérifier les droits de modification (admin ou gestionnaire)
            if ($userRole !== 'admin' && $userRole !== 'gestionnaire') {
                error_log("facture-api - Droits insuffisants pour DELETE - User: $userId, Role: $userRole");
                throw new Exception('Droits administrateur ou gestionnaire requis pour supprimer des factures', 403);
            }
            
            // Supprimer une facture
            if (!isset($_GET['id'])) {
                throw new Exception('ID facture manquant');
            }

            // Supprimer un paiement
            if (isset($_GET['supprimerPaiement']) && isset($_GET['id'])) {
                if (is_dev_mode()) {
                    error_log("facture-api - DELETE paiement ID: " . $_GET['id'] . " (user: $userId)");
                }
                
                try {
                    $resultat = $serviceFacture->supprimerPaiement($_GET['id']);
                    echo json_encode($resultat);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'message' => $e->getMessage()
                    ]);
                }
                break;
            }
            
            if (is_dev_mode()) {
                error_log("facture-api - DELETE facture ID: " . $_GET['id'] . " (user: $userId)");
            }
            
            $resultat = $serviceFacture->supprimerFacture($_GET['id']);
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
    
    error_log('❌ Erreur API Facture: ' . json_encode($errorDetails));
    
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