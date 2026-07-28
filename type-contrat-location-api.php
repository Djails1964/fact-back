<?php
/**
 * type-contrat-location-api.php
 * API REST pour la gestion des types de contrat de location.
 *
 * GET  (aucun param)   → liste tous les types (actifs et inactifs)
 * PUT  ?id=<n>         → modifier les attributs éditables d'un type
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
require_once realpath(__DIR__ . '/services/ServiceTypeContratLocation.php');

try {
    $method   = $_SERVER['REQUEST_METHOD'];
    $userRole = $_SESSION['user_role'] ?? null;

    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authentification requise']);
        exit;
    }

    $service = new ServiceTypeContratLocation($conn);

    if (is_dev_mode()) {
        error_log("type-contrat-location-api - {$method} - user: {$_SESSION['user_id']} role: {$userRole}");
    }

    switch ($method) {

        case 'GET':
            $types = $service->lister(false); // tous, actifs et inactifs
            if (is_dev_mode()) {
                error_log("type-contrat-location-api [GET] → " . count($types) . " type(s)");
            }
            echo json_encode(['success' => true, 'types' => $types]);
            break;

        case 'PUT':
            if ($userRole !== 'admin' && $userRole !== 'gestionnaire') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Droits insuffisants']);
                exit;
            }
            $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
            if (!$id) {
                throw new Exception('ID requis');
            }
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                throw new Exception('Données JSON invalides');
            }
            if (is_dev_mode()) {
                error_log("type-contrat-location-api [PUT id={$id}] " . json_encode($data, JSON_UNESCAPED_UNICODE));
            }
            $result = $service->modifier($id, $data);
            echo json_encode($result);
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    }

} catch (Exception $e) {
    $msg  = $e->getMessage();
    $code = str_contains($msg, 'introuvable') ? 404
          : (str_contains($msg, 'obligatoire') ? 422
          : (str_contains($msg, 'invalide')    ? 400
          : 200));
    error_log("type-contrat-location-api - Erreur : {$msg}");
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $msg]);
}

$output = ob_get_clean();
echo $output;
?>