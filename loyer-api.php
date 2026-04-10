<?php
/**
 * loyer-api.php - VERSION TABLE SÉPARÉE
 * API REST pour la gestion des loyers
 * ✅ Numérotation par client: LOY-{client}-{seq}
 */

$config = require_once realpath(__DIR__ . '/bootstrap.php');
check_session_validity();
init_api_response();

if (!is_dev_mode()) {
    ini_set('display_errors', 0);
    error_reporting(E_ERROR);
}

ob_start();

require_once 'database.php';
require_once realpath(__DIR__ . '/controllers/LoyerControleur.php');
require_once realpath(__DIR__ . '/services/ServiceLoyer.php');

try {
    $serviceLoyer = new ServiceLoyer($conn);
    $method = $_SERVER['REQUEST_METHOD'];
    
    // Vérification session
    $userId = $_SESSION['user_id'] ?? null;
    $isAuthenticated = isset($_SESSION['user_id']);
    
    if (!$isAuthenticated) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Authentification requise'
        ]);
        exit;
    }
    
    // Router
    switch ($method) {
        case 'GET':
            handleGet($serviceLoyer);
            break;
        case 'POST':
            handlePost($serviceLoyer);
            break;
        case 'PUT':
            handlePut($serviceLoyer);
            break;
        case 'DELETE':
            handleDelete($serviceLoyer);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    }
} catch (Exception $e) {
    error_log("loyer-api - Erreur: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$output = ob_get_clean();
echo $output;

function handleGet($serviceLoyer) {
    try {
        // Génération de numéro
        if (isset($_GET['action']) && $_GET['action'] === 'generer_numero') {
            $id_client = isset($_GET['id_client']) ? intval($_GET['id_client']) : null;
            
            if (!$id_client) {
                throw new Exception('ID client requis pour générer un numéro');
            }
            
            $numero = $serviceLoyer->genererNumeroLoyer($id_client);
            
            echo json_encode([
                'success' => true,
                'numero_loyer' => $numero
            ]);
            return;
        }
        
        // Récupérer un loyer spécifique
        if (isset($_GET['id'])) {
            $id = intval($_GET['id']);
            $loyer = $serviceLoyer->getLoyerParId($id);
            
            echo json_encode([
                'success' => true,
                'loyer' => $loyer
            ]);
            return;
        }
        
        // Lister les loyers avec filtres
        $filtres = [];
        
        if (isset($_GET['id_client'])) {
            $filtres['id_client'] = intval($_GET['id_client']);
        }
        
        if (isset($_GET['annee'])) {
            $filtres['annee'] = intval($_GET['annee']);
        }
        
        if (isset($_GET['statut'])) {
            $filtres['statut'] = $_GET['statut'];
        }
        
        $loyers = $serviceLoyer->listerLoyers($filtres);
        
        echo json_encode([
            'success' => true,
            'loyers' => $loyers
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

function handlePost($serviceLoyer) {
    try {
        $action = $_GET['action'] ?? null;

        // ── Actions sans body JSON ────────────────────────────────────────────
        if ($action === 'generer_confirmation') {
            $id_loyer = isset($_GET['id']) ? intval($_GET['id']) : null;
            if (!$id_loyer) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID loyer requis']);
                return;
            }
            $resultat = $serviceLoyer->genererConfirmationPDF($id_loyer);
            echo json_encode($resultat);
            return;
        }

        // ── Actions avec body JSON ────────────────────────────────────────────
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (!$data) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Données JSON invalides']);
            return;
        }

        // Paiement d'un détail de loyer
        if ($action === 'payer_detail') {
            $id_loyer = isset($_GET['id']) ? intval($_GET['id']) : null;
            if (!$id_loyer) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID loyer requis']);
                return;
            }
            $resultat = $serviceLoyer->payerDetail($id_loyer, $data);
            echo json_encode($resultat);
            return;
        }

        $resultat = $serviceLoyer->creerLoyer($data);
        
        http_response_code(201);
        echo json_encode($resultat);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

function handlePut($serviceLoyer) {
    try {
        if (!isset($_GET['id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID requis']);
            return;
        }
        
        $id = intval($_GET['id']);
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (!$data) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Données JSON invalides']);
            return;
        }

        // ✅ Route spéciale : lier une facture à ce loyer
        // PUT /loyer-api.php?id=X&action=lier_facture  { id_facture: Y }
        if (isset($_GET['action']) && $_GET['action'] === 'lier_facture') {
            if (empty($data['id_facture'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'id_facture requis']);
                return;
            }
            $resultat = $serviceLoyer->lierFacture($id, (int)$data['id_facture']);
            echo json_encode($resultat);
            return;
        }
        
        $resultat = $serviceLoyer->modifierLoyer($id, $data);
        
        echo json_encode($resultat);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

function handleDelete($serviceLoyer) {
    try {
        if (!isset($_GET['id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID requis']);
            return;
        }
        
        $id = intval($_GET['id']);
        $resultat = $serviceLoyer->supprimerLoyer($id);
        
        echo json_encode($resultat);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}