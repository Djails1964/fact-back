<?php
/**
 * tarif-api.php - Version corrigée avec accès admin fonctionnel
 */

// Utiliser la session centralisée
$config = require_once realpath(__DIR__ . '/../bootstrap.php');
init_api_response();

if (!is_dev_mode()) {
    ini_set('display_errors', 0);
    error_reporting(E_ERROR);
}

ob_start();

require_once 'database.php';
require_once realpath(__DIR__ . '/../ServiceTarif.php');

// Debug session en mode développement
if (is_dev_mode()) {
    error_log("tarif-api - Méthode: " . $_SERVER['REQUEST_METHOD']);
    error_log("tarif-api - URL complète: " . $_SERVER['REQUEST_URI']);
    error_log("tarif-api - Session ID: " . session_id());
    error_log("tarif-api - Session content: " . json_encode($_SESSION));
}

try {
    $serviceTarif = new ServiceTarif($conn);
    $method = $_SERVER['REQUEST_METHOD'];
    $resultat = null;
    
    // ====================================
    // VÉRIFICATION DE SESSION
    // ====================================
    
    $userId = $_SESSION['user_id'] ?? null;
    $userRole = $_SESSION['user_role'] ?? null;
    $isAuthenticated = !empty($userId);
    
    if (is_dev_mode()) {
        error_log("🔍 Utilisateur: ID=$userId, Role=$userRole, Auth=" . ($isAuthenticated ? 'OUI' : 'NON'));
    }
    
    // ====================================
    // ROUTE DE DEBUG SESSION
    // ====================================
    
    if (isset($_GET['debug_session']) && is_dev_mode()) {
        echo json_encode([
            'success' => true,
            'debug_info' => [
                'session_id' => session_id(),
                'session_status' => session_status(),
                'session_data' => $_SESSION,
                'user_authenticated' => $isAuthenticated,
                'user_id' => $userId,
                'user_role' => $userRole,
                'api' => 'tarif-api.php CORRIGÉ'
            ]
        ]);
        exit;
    }
    
    // ====================================
    // VÉRIFICATION D'AUTHENTIFICATION
    // ====================================
    
    if (!$isAuthenticated) {
        error_log('❌ tarif-api - Accès refusé - Authentification requise');
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Authentification requise pour accéder aux tarifs',
            'code' => 401
        ]);
        exit;
    }
    
    if (is_dev_mode()) {
        error_log("✅ tarif-api - Accès autorisé pour utilisateur: $userId (role: $userRole)");
    }
    
    // ====================================
    // TRAITEMENT DES REQUÊTES
    // ====================================
    
    switch ($method) {
        case 'GET':
            // ===== ROUTES GET (accessible à tous les utilisateurs authentifiés) =====
            
            if (isset($_GET['services'])) {
                if (is_dev_mode()) {
                    error_log("📥 tarif-api - GET services pour user: $userId");
                }
                $resultat = $serviceTarif->getServices();
                
            } else if (isset($_GET['unites'])) {
                $serviceId = isset($_GET['serviceId']) ? intval($_GET['serviceId']) : null;
                if (is_dev_mode()) {
                    error_log("📥 tarif-api - GET unités pour service: " . ($serviceId ?? 'tous'));
                }
                $resultat = $serviceTarif->getUnites($serviceId);
                
            } else if (isset($_GET['typesTarifs'])) {
                if (is_dev_mode()) {
                    error_log("📥 tarif-api - GET types tarifs");
                }
                $resultat = $serviceTarif->getTypesTarifs();
                
            } else if (isset($_GET['tarifs'])) {
                $serviceId = isset($_GET['serviceId']) ? intval($_GET['serviceId']) : null;
                $uniteId = isset($_GET['uniteId']) ? intval($_GET['uniteId']) : null;
                $typeTarifId = isset($_GET['typeTarifId']) ? intval($_GET['typeTarifId']) : null;
                $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
                
                if (is_dev_mode()) {
                    error_log("📥 tarif-api - GET tarifs avec filtres");
                }
                $resultat = $serviceTarif->getTarifs($serviceId, $uniteId, $typeTarifId, $date);
                
            } else if (isset($_GET['allTarifs'])) {
                $serviceId = isset($_GET['serviceId']) ? intval($_GET['serviceId']) : null;
                $uniteId = isset($_GET['uniteId']) ? intval($_GET['uniteId']) : null;
                $typeTarifId = isset($_GET['typeTarifId']) ? intval($_GET['typeTarifId']) : null;
                
                if (is_dev_mode()) {
                    error_log("📥 tarif-api - GET tous les tarifs");
                }
                $resultat = $serviceTarif->getAllTarifs($serviceId, $uniteId, $typeTarifId);
                
            } else if (isset($_GET['allTarifsSpeciaux'])) {
                $clientId = isset($_GET['clientId']) ? intval($_GET['clientId']) : null;
                $serviceId = isset($_GET['serviceId']) ? intval($_GET['serviceId']) : null;
                $uniteId = isset($_GET['uniteId']) ? intval($_GET['uniteId']) : null;
                
                if (is_dev_mode()) {
                    error_log("📥 tarif-api - GET tarifs spéciaux pour client: $clientId");
                }
                $resultat = $serviceTarif->getAllTarifsSpeciaux($clientId, $serviceId, $uniteId);
                
            } else if (isset($_GET['tarifsSpeciaux'])) {
                $clientId = isset($_GET['clientId']) ? intval($_GET['clientId']) : null;
                $serviceId = isset($_GET['serviceId']) ? intval($_GET['serviceId']) : null;
                $uniteId = isset($_GET['uniteId']) ? intval($_GET['uniteId']) : null;
                $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
                
                if (is_dev_mode()) {
                    error_log("📥 tarif-api - GET tarifs spéciaux actifs pour client: $clientId");
                }
                $resultat = $serviceTarif->getTarifsSpeciaux($clientId, $serviceId, $uniteId, $date);
                
            } else if (isset($_GET['tarifClient'])) {
                if (!isset($_GET['clientId']) || !isset($_GET['serviceId']) || !isset($_GET['uniteId'])) {
                    throw new Exception('Paramètres manquants: clientId, serviceId et uniteId sont requis');
                }
                
                $clientId = intval($_GET['clientId']);
                $serviceId = intval($_GET['serviceId']);
                $uniteId = intval($_GET['uniteId']);
                $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
                
                if (is_dev_mode()) {
                    error_log("📥 tarif-api - GET tarif pour client: $clientId, service: $serviceId, unite: $uniteId");
                }
                $resultat = $serviceTarif->getTarifClient($clientId, $serviceId, $uniteId, $date);
                
            } else if (isset($_GET['servicesUnites'])) {
                if (is_dev_mode()) {
                    error_log("📥 tarif-api - GET relations services-unités");
                }
                $resultat = $serviceTarif->getServicesUnites();
                
            } else if (isset($_GET['estTherapeute'])) {
                if (!isset($_GET['clientId'])) {
                    throw new Exception('Paramètre clientId manquant');
                }
                
                $clientId = intval($_GET['clientId']);
                if (is_dev_mode()) {
                    error_log("📥 tarif-api - Check thérapeute pour client: $clientId");
                }
                $resultat = $serviceTarif->estTherapeute($clientId);
                
            } else if (isset($_GET['possedeTarifSpecial'])) {
                if (!isset($_GET['clientId'])) {
                    throw new Exception('Paramètre clientId manquant');
                }
                
                $clientId = intval($_GET['clientId']);
                $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
                
                if (is_dev_mode()) {
                    error_log("📥 tarif-api - Check tarif spécial pour client: $clientId");
                }
                $resultat = $serviceTarif->possedeTarifSpecialDefini($clientId, $date);
                
            } else if (isset($_GET['uniteDefautService'])) {
                $serviceId = intval($_GET['uniteDefautService']);
                if (is_dev_mode()) {
                    error_log("📥 tarif-api - GET unité par défaut pour service: $serviceId");
                }
                $resultat = $serviceTarif->getUniteDefault($serviceId);
                
            } else if (isset($_GET['unitesClient'])) {
                if (!isset($_GET['clientId'])) {
                    throw new Exception('Paramètre clientId manquant');
                }
                
                $clientId = intval($_GET['clientId']);
                $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
                
                if (is_dev_mode()) {
                    error_log("📥 tarif-api - GET unités applicables pour client: $clientId");
                }
                $resultat = $serviceTarif->getUnitesApplicablesPourClient($clientId, $date);
                
            } else if (isset($_GET['checkUniteUsage'])) {
                $uniteId = intval($_GET['checkUniteUsage']);
                if (is_dev_mode()) {
                    error_log("📥 tarif-api - Check usage unité: $uniteId");
                }
                $resultat = $serviceTarif->checkUniteUsage($uniteId);
                
            } else if (isset($_GET['checkServiceUsage'])) {
                $serviceId = intval($_GET['checkServiceUsage']);
                if (is_dev_mode()) {
                    error_log("📥 tarif-api - Check usage service: $serviceId");
                }
                $resultat = $serviceTarif->checkServiceUsage($serviceId);
                
            } else if (isset($_GET['checkServiceUniteUsageInFacture']) && isset($_GET['serviceId']) && isset($_GET['uniteId'])) {
                $serviceId = intval($_GET['serviceId']);
                $uniteId = intval($_GET['uniteId']);
                
                if (is_dev_mode()) {
                    error_log("📥 tarif-api - Check usage service-unité dans factures: $serviceId-$uniteId");
                }
                $resultat = $serviceTarif->checkServiceUniteUsageInFacture($serviceId, $uniteId);
                
            } else if (isset($_GET['checkTypeTarifUsage'])) {
                $typeTarifId = intval($_GET['checkTypeTarifUsage']);
                if (is_dev_mode()) {
                    error_log("📥 tarif-api - Check usage type tarif: $typeTarifId");
                }
                $resultat = $serviceTarif->checkTypeTarifUsage($typeTarifId);
                
            } else if (isset($_GET['checkTarifSpecialUsage'])) {
                $tarifSpecialId = intval($_GET['checkTarifSpecialUsage']);
                if (is_dev_mode()) {
                    error_log("📥 tarif-api - Check usage tarif spécial: $tarifSpecialId");
                }
                $resultat = $serviceTarif->checkTarifSpecialUsage($tarifSpecialId);
                
            } else if (isset($_GET['checkTarifUsage'])) {
                $tarifId = intval($_GET['checkTarifUsage']);
                if (is_dev_mode()) {
                    error_log("📥 tarif-api - Check usage tarif: $tarifId");
                }
                $resultat = $serviceTarif->checkTarifUsage($tarifId);
                
            } else {
                throw new Exception('Paramètres de requête insuffisants ou incorrects');
            }
            break;
            
        case 'POST':
        case 'PUT':
        case 'DELETE':
            // ===== ROUTES DE MODIFICATION (admin/gestionnaire seulement) =====
            
            // CORRECTION: Vérification simplifiée et claire des droits
            $hasModificationRights = in_array($userRole, ['admin', 'gestionnaire']);
            
            if (is_dev_mode()) {
                error_log("🔒 tarif-api - Vérification droits modification:");
                error_log("- Méthode: $method");
                error_log("- User Role: '$userRole'");
                error_log("- Droits modification: " . ($hasModificationRights ? 'OUI' : 'NON'));
            }
            
            if (!$hasModificationRights) {
                error_log("❌ tarif-api - Droits insuffisants pour $method - User: $userId, Role: $userRole");
                
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'message' => 'Droits administrateur ou gestionnaire requis pour modifier les tarifs',
                    'code' => 403,
                    'debug' => is_dev_mode() ? [
                        'user_id' => $userId,
                        'user_role' => $userRole,
                        'required_roles' => ['admin', 'gestionnaire'],
                        'method' => $method
                    ] : null
                ]);
                exit;
            }
            
            if (is_dev_mode()) {
                error_log("✅ tarif-api - Droits confirmés pour $method - User: $userId (role: $userRole)");
            }
            
            // Traitement des modifications selon la méthode
            if ($method === 'POST') {
                $rawData = file_get_contents("php://input");
                $data = json_decode($rawData, true);
                
                if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception('Données JSON invalides: ' . json_last_error_msg());
                }
                
                if (!$data) {
                    throw new Exception('Aucune donnée reçue');
                }
                
                if (isset($data['action'])) {
                    if (is_dev_mode()) {
                        error_log("📝 tarif-api - POST action: " . $data['action']);
                    }
                    
                    switch ($data['action']) {
                        case 'createService':
                            if (!isset($data['code']) || !isset($data['nom'])) {
                                throw new Exception('Données incomplètes pour la création d\'un service');
                            }
                            $resultat = $serviceTarif->createService($data);
                            break;
                            
                        case 'createUnite':
                            if (!isset($data['code']) || !isset($data['nom'])) {
                                throw new Exception('Données incomplètes pour la création d\'une unité');
                            }
                            $resultat = $serviceTarif->createUnite($data);
                            break;
                            
                        case 'createTypeTarif':
                            if (!isset($data['code']) || !isset($data['nom'])) {
                                throw new Exception('Données incomplètes pour la création d\'un type de tarif');
                            }
                            $resultat = $serviceTarif->createTypeTarif($data);
                            break;
                            
                        case 'createTarif':
                            if (!isset($data['serviceId']) || !isset($data['uniteId']) || !isset($data['typeTarifId']) || !isset($data['prix'])) {
                                throw new Exception('Données incomplètes pour la création d\'un tarif');
                            }
                            $resultat = $serviceTarif->createTarif($data);
                            break;
                            
                        case 'createTarifSpecial':
                            if (!isset($data['clientId']) || !isset($data['serviceId']) || !isset($data['uniteId']) || !isset($data['prix'])) {
                                throw new Exception('Données incomplètes pour la création d\'un tarif spécial');
                            }
                            $resultat = $serviceTarif->createTarifSpecial($data);
                            break;
                            
                        case 'linkServiceUnite':
                            if (!isset($data['serviceId']) || !isset($data['uniteId'])) {
                                throw new Exception('serviceId et uniteId sont requis');
                            }
                            $serviceId = intval($data['serviceId']);
                            $uniteId = intval($data['uniteId']);
                            $resultat = $serviceTarif->linkServiceUnite($serviceId, $uniteId);
                            break;
                            
                        case 'updateServiceUniteDefault':
                            if (!isset($data['serviceId']) || !isset($data['uniteId'])) {
                                throw new Exception('serviceId et uniteId sont requis');
                            }
                            $serviceId = intval($data['serviceId']);
                            $uniteId = intval($data['uniteId']);
                            $resultat = $serviceTarif->updateServiceUniteDefault($serviceId, $uniteId);
                            break;
                            
                        default:
                            throw new Exception('Action POST non reconnue: ' . $data['action']);
                    }
                } else {
                    throw new Exception('Action non spécifiée dans les données POST');
                }
                
            } elseif ($method === 'PUT') {
                $rawData = file_get_contents("php://input");
                $data = json_decode($rawData, true);
                
                if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception('Données JSON invalides: ' . json_last_error_msg());
                }
                
                if (!$data || !isset($data['id']) || !isset($data['action'])) {
                    throw new Exception('Données JSON invalides ou incomplètes (id et action requis)');
                }
                
                if (is_dev_mode()) {
                    error_log("📝 tarif-api - PUT action: " . $data['action'] . " pour ID: " . $data['id']);
                }
                
                switch ($data['action']) {
                    case 'updateService':
                        $resultat = $serviceTarif->updateService($data['id'], $data);
                        break;
                        
                    case 'updateUnite':
                        $resultat = $serviceTarif->updateUnite($data['id'], $data);
                        break;
                        
                    case 'updateTypeTarif':
                        $resultat = $serviceTarif->updateTypeTarif($data['id'], $data);
                        break;
                        
                    case 'updateTarif':
                        $resultat = $serviceTarif->updateTarif($data['id'], $data);
                        break;
                        
                    case 'updateTarifSpecial':
                        $resultat = $serviceTarif->updateTarifSpecial($data['id'], $data);
                        break;
                        
                    default:
                        throw new Exception('Action PUT non reconnue: ' . $data['action']);
                }
                
            } elseif ($method === 'DELETE') {
                if (is_dev_mode()) {
                    error_log("🗑️ tarif-api - DELETE avec paramètres: " . json_encode($_GET));
                }
                
                if (isset($_GET['type']) && $_GET['type'] === 'serviceUnite') {
                    if (!isset($_GET['serviceId']) || !isset($_GET['uniteId'])) {
                        throw new Exception('serviceId et uniteId sont requis pour la suppression');
                    }
                    
                    $serviceId = intval($_GET['serviceId']);
                    $uniteId = intval($_GET['uniteId']);
                    $resultat = $serviceTarif->unlinkServiceUnite($serviceId, $uniteId);
                    
                } else if (isset($_GET['id']) && isset($_GET['type'])) {
                    $id = intval($_GET['id']);
                    $type = $_GET['type'];
                    
                    switch ($type) {
                        case 'service':
                            $resultat = $serviceTarif->deleteService($id);
                            break;
                            
                        case 'unite':
                            $resultat = $serviceTarif->deleteUnite($id);
                            break;
                            
                        case 'typeTarif':
                            $resultat = $serviceTarif->deleteTypeTarif($id);
                            break;
                        
                        case 'tarif':
                            $resultat = $serviceTarif->deleteTarif($id);
                            break;
                            
                        case 'tarifSpecial':
                            $resultat = $serviceTarif->deleteTarifSpecial($id);
                            break;
                            
                        default:
                            throw new Exception('Type de suppression non reconnu: ' . $type);
                    }
                } else {
                    throw new Exception('Paramètres requis pour la suppression manquants (id et type requis)');
                }
            }
            break;
            
        default:
            throw new Exception('Méthode HTTP non supportée: ' . $method);
    }
    
    echo json_encode($resultat);
    
} catch (Exception $e) {
    $errorDetails = [
        'message' => $e->getMessage(),
        'method' => $_SERVER['REQUEST_METHOD'],
        'url' => $_SERVER['REQUEST_URI'],
        'user_id' => $userId ?? 'NON_CONNECTÉ',
        'user_role' => $userRole ?? 'NON_DÉFINI',
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    error_log('❌ Erreur API Tarif: ' . json_encode($errorDetails));
    
    $httpCode = 400;
    if (strpos($e->getMessage(), 'Authentification requise') !== false) {
        $httpCode = 401;
    } elseif (strpos($e->getMessage(), 'Droits') !== false || 
              strpos($e->getMessage(), 'administrateur requis') !== false) {
        $httpCode = 403;
    } elseif (strpos($e->getMessage(), 'manquant') !== false || 
              strpos($e->getMessage(), 'requis') !== false || 
              strpos($e->getMessage(), 'incomplètes') !== false) {
        $httpCode = 422;
    } elseif (strpos($e->getMessage(), 'non trouvé') !== false) {
        $httpCode = 404;
    }
    
    http_response_code($httpCode);
    
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage(),
        'code' => $httpCode,
        'debug' => is_dev_mode() ? $errorDetails : null
    ]);
}
?>