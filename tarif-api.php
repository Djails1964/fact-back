<?php
/**
 * tarif-api.php - Version corrigée avec accès admin fonctionnel
 */

// Utiliser la session centralisée
$config = require_once realpath(__DIR__ . '/bootstrap.php');

// ✅ VÉRIFICATION SESSION - Ajouter cette ligne
check_session_validity();

init_api_response();

if (!is_dev_mode()) {
    ini_set('display_errors', 0);
    error_reporting(E_ERROR);
}

ob_start();

require_once 'database.php';
require_once realpath(__DIR__ . '/services/ServiceTarif.php');

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
            // ===== NOUVEAU: Données initiales unifiées =====
            if (isset($_GET['donneesInitiales'])) {
                if (is_dev_mode()) {
                    error_log("📥 tarif-api - GET données initiales unifiées");
                }
                
                $actifsUniquement = isset($_GET['actifs']) && $_GET['actifs'] === 'true';
                $resultat = $serviceTarif->getDonneesInitiales($actifsUniquement);
                
            } else if (isset($_GET['donneesFacturation'])) {
                if (is_dev_mode()) {
                    error_log("📥 tarif-api - GET données facturation optimisées");
                }
                
                $resultat = $serviceTarif->getDonneesFacturation();
                
            } else if (isset($_GET['servicesAvecUnites'])) {
                if (is_dev_mode()) {
                    error_log("📥 tarif-api - GET services avec unités liées");
                }
                
                $actifsUniquement = isset($_GET['actifs']) && $_GET['actifs'] === 'true';
                $services = $serviceTarif->getServicesAvecUnites($actifsUniquement);
                $resultat = [
                    'success' => true,
                    'services' => $services
                ];
                
            } else if (isset($_GET['services'])) {
                if (is_dev_mode()) {
                    error_log("📥 tarif-api - GET services pour user: $userId");
                }
                $resultat = $serviceTarif->getServices();
                if (is_dev_mode()) {
                    error_log("📥 tarif-api - GET services - résultat: " . json_encode($resultat));
                }
                
            } else if (isset($_GET['unites'])) {
                $id_service = isset($_GET['id_service']) ? intval($_GET['id_service']) : null;
                if (is_dev_mode()) {
                    error_log("🔥 tarif-api - GET unités pour service: " . ($id_service ?? 'tous'));
                    error_log("🔥 tarif-api - id_service type: " . gettype($id_service));
                    error_log("🔥 tarif-api - id_service value: " . var_export($id_service, true));
                }
                $resultat = $serviceTarif->getUnites($id_service);
                if (is_dev_mode()) {
                    error_log("📥 tarif-api - GET unités pour service - résultat: " . json_encode($resultat));
                }
                
            } else if (isset($_GET['types_tarifs'])) {
                if (is_dev_mode()) {
                    error_log("📥 tarif-api - GET types tarifs");
                }
                $resultat = $serviceTarif->getTypesTarifs();
                
            } else if (isset($_GET['tarifs'])) {
                $id_service = isset($_GET['id_service']) ? intval($_GET['id_service']) : null;
                $id_unite = isset($_GET['id_unite']) ? intval($_GET['id_unite']) : null;
                $type_tarif_id = isset($_GET['type_tarif_id']) ? intval($_GET['type_tarif_id']) : null;
                $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
                
                if (is_dev_mode()) {
                    error_log("📥 tarif-api - GET tarifs avec filtres");
                }
                $resultat = $serviceTarif->getTarifs($id_service, $id_unite, $type_tarif_id, $date);
                
            } else if (isset($_GET['allTarifs'])) {
                $id_service = isset($_GET['id_service']) ? intval($_GET['id_service']) : null;
                $id_unite = isset($_GET['id_unite']) ? intval($_GET['id_unite']) : null;
                $type_tarif_id = isset($_GET['type_tarif_id']) ? intval($_GET['type_tarif_id']) : null;
                
                if (is_dev_mode()) {
                    error_log("📥 tarif-api - GET tous les tarifs");
                }
                $resultat = $serviceTarif->getAllTarifs($id_service, $id_unite, $type_tarif_id);
                
            } else if (isset($_GET['allTarifsSpeciaux'])) {
                $id_client = isset($_GET['id_client']) ? intval($_GET['id_client']) : null;
                $id_service = isset($_GET['id_service']) ? intval($_GET['id_service']) : null;
                $id_unite = isset($_GET['id_unite']) ? intval($_GET['id_unite']) : null;
                
                if (is_dev_mode()) {
                    error_log("📥 tarif-api - GET tarifs spéciaux pour client: $id_client");
                }
                $resultat = $serviceTarif->getAllTarifsSpeciaux($id_client, $id_service, $id_unite);
                
            } else if (isset($_GET['tarifsSpeciaux'])) {
                $id_client = isset($_GET['id_client']) ? intval($_GET['id_client']) : null;
                $id_service = isset($_GET['id_service']) ? intval($_GET['id_service']) : null;
                $id_unite = isset($_GET['id_unite']) ? intval($_GET['id_unite']) : null;
                $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
                
                if (is_dev_mode()) {
                    error_log("📥 tarif-api - GET tarifs spéciaux actifs pour client: $id_client");
                }
                $resultat = $serviceTarif->getTarifsSpeciaux($id_client, $id_service, $id_unite, $date);
                
            } else if (isset($_GET['tarifClient'])) {
                if (!isset($_GET['id_client']) || !isset($_GET['id_service']) || !isset($_GET['id_unite'])) {
                    throw new Exception('Paramètres manquants: id_client, id_service et id_unite sont requis');
                }
                
                $id_client = intval($_GET['id_client']);
                $id_service = intval($_GET['id_service']);
                $id_unite = intval($_GET['id_unite']);
                $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
                
                if (is_dev_mode()) {
                    error_log("📥 tarif-api - GET tarif pour client: $id_client, service: $id_service, unite: $id_unite");
                }
                $resultat = $serviceTarif->getTarifClient($id_client, $id_service, $id_unite, $date);
                
            } else if (isset($_GET['servicesUnites'])) {
                if (is_dev_mode()) {
                    error_log("📥 tarif-api - GET relations services-unités");
                }
                $resultat = $serviceTarif->getServicesUnites();
                
            } else if (isset($_GET['estTherapeute'])) {
                if (!isset($_GET['id_client'])) {
                    throw new Exception('Paramètre id_client manquant');
                }
                
                $id_client = intval($_GET['id_client']);
                if (is_dev_mode()) {
                    error_log("📥 tarif-api - Check thérapeute pour client: $id_client");
                }
                $resultat = $serviceTarif->estTherapeute($id_client);
                
            } else if (isset($_GET['possedeTarifSpecial'])) {
                if (!isset($_GET['id_client'])) {
                    throw new Exception('Paramètre id_client manquant');
                }
                
                $id_client = intval($_GET['id_client']);
                $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
                
                if (is_dev_mode()) {
                    error_log("📥 tarif-api - Check tarif spécial pour client: $id_client");
                }
                $resultat = $serviceTarif->possedeTarifSpecialDefini($id_client, $date);
                
            } else if (isset($_GET['uniteDefautService'])) {
                $id_service = intval($_GET['uniteDefautService']);
                if (is_dev_mode()) {
                    error_log("📥 tarif-api - GET unité par défaut pour service: $id_service");
                }
                $resultat = $serviceTarif->getUniteDefault($id_service);
                if (is_dev_mode()) {
                    error_log("🔧 tarif-api - Resultat GET unité par défaut pour service: " . json_encode($resultat));
                }
                
            } else if (isset($_GET['unitesClient'])) {
                if (!isset($_GET['id_client'])) {
                    throw new Exception('Paramètre id_client manquant');
                }
                
                $id_client = intval($_GET['id_client']);
                $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
                
                if (is_dev_mode()) {
                    error_log("📥 tarif-api - GET unités applicables pour client: $id_client");
                }
                $resultat = $serviceTarif->getUnitesApplicablesPourClient($id_client, $date);
                
            } else if (isset($_GET['checkUniteUsage'])) {
                $id_unite = intval($_GET['checkUniteUsage']);
                if (is_dev_mode()) {
                    error_log("📥 tarif-api - Check usage unité: $id_unite");
                }
                $resultat = $serviceTarif->checkUniteUsage($id_unite);
                
            } else if (isset($_GET['checkServiceUsage'])) {
                $service_id = intval($_GET['checkServiceUsage']);
                error_log("tarif-api - GET[checkServiceUsage] - serviceId :". $service_id);
                if (is_dev_mode()) {
                    error_log("📥 tarif-api - Check usage service: $service_id");
                }
                $resultat = $serviceTarif->checkServiceUsage($service_id);
                
            } else if (isset($_GET['checkServiceUniteUsageInFacture']) && isset($_GET['id_service']) && isset($_GET['id_unite'])) {
                $id_service = intval($_GET['id_service']);
                $id_unite = intval($_GET['id_unite']);
                
                if (is_dev_mode()) {
                    error_log("📥 tarif-api - Check usage service-unité dans factures: $id_service-$id_unite");
                }
                $resultat = $serviceTarif->checkServiceUniteUsageInFacture($id_service, $id_unite);
                
            } else if (isset($_GET['checkTypeTarifUsage'])) {
                $type_tarif_id = intval($_GET['checkTypeTarifUsage']);
                if (is_dev_mode()) {
                    error_log("📥 tarif-api - Check usage type tarif: $type_tarif_id");
                }
                $resultat = $serviceTarif->checkTypeTarifUsage($type_tarif_id);
                
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
                            if (!isset($data['code_service']) || !isset($data['nom_service'])) {
                                error_log("❌ tarif-api - POST createService - Données incomplètes");
                                error_log("Données reçues: " . json_encode($data));
                                throw new Exception('Données incomplètes pour la création d\'un service');
                            }
                            $resultat = $serviceTarif->createService($data);
                            break;
                            
                        case 'createUnite':
                            if (!isset($data['code_unite']) || !isset($data['nom_unite'])) {
                                throw new Exception('Données incomplètes pour la création d\'une unité');
                            }
                            $resultat = $serviceTarif->createUnite($data);
                            break;
                            
                        case 'createTypeTarif':
                            if (!isset($data['code_type_tarif']) || !isset($data['nom_type_tarif'])) {
                                throw new Exception('Données incomplètes pour la création d\'un type de tarif');
                            }
                            $resultat = $serviceTarif->createTypeTarif($data);
                            break;
                            
                        case 'createTarif':
                            error_log('tarif-api - createTarif - données reçues: ' . json_encode($data));
                            if (!isset($data['id_service']) || !isset($data['id_unite']) || !isset($data['id_type_tarif']) || !isset($data['prix_tarif_standard'])) {
                                throw new Exception('Données incomplètes pour la création d\'un tarif');
                            }
                            $resultat = $serviceTarif->createTarif($data);
                            break;
                            
                        case 'createTarifSpecial':
                            if (!isset($data['id_client']) || !isset($data['id_service']) || !isset($data['id_unite']) || !isset($data['prix'])) {
                                throw new Exception('Données incomplètes pour la création d\'un tarif spécial');
                            }
                            $resultat = $serviceTarif->createTarifSpecial($data);
                            break;
                            
                        case 'linkServiceUnite':
                            if (!isset($data['id_service']) || !isset($data['id_unite'])) {
                                throw new Exception('id_service et id_unite sont requis');
                            }
                            $id_service = intval($data['id_service']);
                            $id_unite = intval($data['id_unite']);
                            $resultat = $serviceTarif->linkServiceUnite($id_service, $id_unite);
                            break;
                            
                        case 'updateServiceUniteDefault':
                            if (!isset($data['id_service']) || !isset($data['id_unite'])) {
                                throw new Exception('id_service et id_unite sont requis');
                            }
                            $id_service = intval($data['id_service']);
                            $id_unite = intval($data['id_unite']);
                            $resultat = $serviceTarif->updateServiceUniteDefault($id_service, $id_unite);
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
                    if (!isset($_GET['id_service']) || !isset($_GET['id_unite'])) {
                        throw new Exception('id_service et id_unite sont requis pour la suppression');
                    }
                    
                    $id_service = intval($_GET['id_service']);
                    $id_unite = intval($_GET['id_unite']);
                    $resultat = $serviceTarif->unlinkServiceUnite($id_service, $id_unite);
                    
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