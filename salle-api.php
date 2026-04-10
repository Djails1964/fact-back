<?php
/**
 * salle-api.php
 * API REST pour la gestion des salles de location.
 *
 * Architecture : salle-api.php → ServiceSalle → SalleControleur (SQL)
 *
 * GET    (aucun param)                         → liste des salles actives
 * GET    ?actif=0                              → toutes les salles (y compris inactives)
 * GET    ?id=<n>                               → une salle par id
 * GET    ?type_document=1&id_service=<n>       → type_document pour un id_service
 * POST   (body JSON)                           → créer une salle
 * PUT    ?id=<n> (body JSON)                   → modifier une salle
 * DELETE ?id=<n>                               → supprimer une salle
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
require_once realpath(__DIR__ . '/services/ServiceSalle.php');

try {
    $method = $_SERVER['REQUEST_METHOD'];

    // ── Authentification ──────────────────────────────────────────────────────
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authentification requise']);
        exit;
    }

    $userRole = $_SESSION['user_role'] ?? null;
    $service  = new ServiceSalle($conn);

    if (is_dev_mode()) {
        error_log("salle-api - {$method} - user: {$_SESSION['user_id']} role: {$userRole}");
        error_log("salle-api - GET params: " . json_encode($_GET));
    }

    // ── Routage ───────────────────────────────────────────────────────────────
    switch ($method) {

        // ====================================================================
        case 'GET':
        // ====================================================================

            // Cas spécial : type_document depuis id_service (utilisé par LoyerGestion)
            if (isset($_GET['type_document']) && isset($_GET['id_service'])) {
                $idService    = (int) $_GET['id_service'];
                $typeDocument = $service->getTypeDocumentByService($idService);
                echo json_encode(['success' => true, 'type_document' => $typeDocument]);
                break;
            }

            // Récupérer une salle par id
            if (isset($_GET['id'])) {
                $salle = $service->getById((int) $_GET['id']);
                if ($salle) {
                    echo json_encode(['success' => true, 'salle' => $salle]);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Salle introuvable']);
                }
                break;
            }

            // Liste des salles
            $actifSeulement = !isset($_GET['actif']) || $_GET['actif'] !== '0';
            $salles = $service->lister($actifSeulement);
            echo json_encode(['success' => true, 'salles' => $salles]);
            break;

        // ====================================================================
        case 'POST':
        // ====================================================================
            if ($userRole !== 'admin' && $userRole !== 'gestionnaire') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Droits insuffisants']);
                exit;
            }

            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                throw new Exception('Données JSON invalides ou manquantes');
            }

            if (is_dev_mode()) {
                error_log("salle-api - POST data: " . json_encode($data));
            }

            $result = $service->creer($data);
            http_response_code(201);
            echo json_encode($result);
            break;

        // ====================================================================
        case 'PUT':
        // ====================================================================
            if ($userRole !== 'admin' && $userRole !== 'gestionnaire') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Droits insuffisants']);
                exit;
            }

            $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
            if (!$id) {
                throw new Exception('ID salle manquant');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                throw new Exception('Données JSON invalides ou manquantes');
            }

            if (is_dev_mode()) {
                error_log("salle-api - PUT id={$id} data: " . json_encode($data));
            }

            $result = $service->modifier($id, $data);
            echo json_encode($result);
            break;

        // ====================================================================
        case 'DELETE':
        // ====================================================================
            if ($userRole !== 'admin' && $userRole !== 'gestionnaire') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Droits insuffisants']);
                exit;
            }

            $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
            if (!$id) {
                throw new Exception('ID salle manquant');
            }

            if (is_dev_mode()) {
                error_log("salle-api - DELETE id={$id}");
            }

            $result = $service->supprimer($id);
            echo json_encode($result);
            break;

        // ====================================================================
        default:
            throw new Exception('Méthode HTTP non supportée : ' . $method);
    }

} catch (Exception $e) {
    $msg  = $e->getMessage();
    $code = str_contains($msg, 'Droits')       ? 403
          : (str_contains($msg, 'obligatoire') ? 422
          : (str_contains($msg, 'introuvable') ? 404
          : (str_contains($msg, 'Impossible')  ? 409
          : 400)));

    error_log("salle-api - Erreur {$code}: {$msg}");
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $msg]);
}
?>