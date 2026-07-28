<?php
/**
 * motif-location-api.php
 * API REST pour les motifs de location.
 *
 * GET  ?id_type_contrat=<n>  → motifs d'un type de contrat
 * GET  (sans param)          → tous les motifs groupés par type de contrat
 * POST                       → créer un motif
 * PUT  ?id=<n>               → modifier un motif
 * DELETE ?id=<n>             → supprimer un motif
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
require_once realpath(__DIR__ . '/services/ServiceMotifLocation.php');

try {
    $method   = $_SERVER['REQUEST_METHOD'];
    $userRole = $_SESSION['user_role'] ?? null;

    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authentification requise']);
        exit;
    }

    $service = new ServiceMotifLocation($conn);

    switch ($method) {

        case 'GET':
            if (isset($_GET['id_type_contrat'])) {
                $idType = (int)$_GET['id_type_contrat'];
                $actifs = !isset($_GET['tous']);
                $motifs = $service->listerParTypeContrat($idType, $actifs);
                echo json_encode(['success' => true, 'motifs' => $motifs]);
            } else {
                $actifs = !isset($_GET['tous']);
                $motifs = $service->listerTous($actifs);
                echo json_encode(['success' => true, 'motifs' => $motifs]);
            }
            break;

        case 'POST':
            if ($userRole !== 'admin' && $userRole !== 'gestionnaire') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Droits insuffisants']);
                exit;
            }
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) throw new Exception('Données JSON invalides');
            $result = $service->creer($data);
            http_response_code(201);
            echo json_encode($result);
            break;

        case 'PUT':
            if ($userRole !== 'admin' && $userRole !== 'gestionnaire') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Droits insuffisants']);
                exit;
            }
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if (!$id) throw new Exception('ID requis');
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) throw new Exception('Données JSON invalides');
            $result = $service->modifier($id, $data);
            echo json_encode($result);
            break;

        case 'DELETE':
            if ($userRole !== 'admin' && $userRole !== 'gestionnaire') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Droits insuffisants']);
                exit;
            }
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if (!$id) throw new Exception('ID requis');
            $result = $service->supprimer($id);
            echo json_encode($result);
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    }

} catch (Exception $e) {
    $msg  = $e->getMessage();
    $code = str_contains($msg, 'introuvable') ? 404
          : (str_contains($msg, 'requis')     ? 422
          : (str_contains($msg, 'invalide')   ? 400
          : 200));
    error_log("motif-location-api - Erreur : {$msg}");
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $msg]);
}

$output = ob_get_clean();
echo $output;
?>