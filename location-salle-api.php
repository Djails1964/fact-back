<?php
/**
 * location-salle-api.php
 *
 * API REST pour la gestion des locations de salle.
 * ✅ Architecture maître / détail
 *
 * ─── CONTRATS (client affiché dans le tableau) ─────────────────────────────
 * GET    ?action=contrats&annee=2025          → liste des contrats de l'année
 * POST   ?action=contrat                      → ajouter un client { id_client, annee }
 * DELETE ?action=contrat&id_contrat=12        → retirer un client (+ ses détails)
 *
 * ─── DÉTAILS (saisies mensuelles) ──────────────────────────────────────────
 * GET    ?annee=2025                          → tous les détails de l'année (vue plate)
 * POST   (body JSON sans action)              → créer un détail { id_client, annee, mois, salle, id_service, id_unite, motif, quantite, note? }
 * PUT    ?id=42                               → modifier un détail
 * DELETE ?id=42                               → supprimer un détail
 *
 * ─── PARAMÈTRES ────────────────────────────────────────────────────────────
 * GET    ?action=salles                       → liste des salles disponibles
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
require_once realpath(__DIR__ . '/controllers/LocationSalleControleur.php');
require_once realpath(__DIR__ . '/services/ServiceLocationSalle.php');

// ─── Logging d'entrée (mode dev uniquement) ───────────────────────────────────
if (is_dev_mode()) {
    $method     = $_SERVER['REQUEST_METHOD'];
    $queryStr   = $_SERVER['QUERY_STRING'] ?? '';
    $userId     = $_SESSION['user_id']   ?? 'NON CONNECTÉ';
    $userName   = $_SESSION['user_name'] ?? '?';
    $rawBody    = ($method === 'POST' || $method === 'PUT')
                    ? file_get_contents('php://input')
                    : null;
    $bodyParsed = $rawBody ? json_decode($rawBody, true) : null;

    error_log(implode(PHP_EOL, [
        '┌─────────────────────────────────────────────────────',
        '│ location-salle-api  ▶  ' . $method . '  ' . ($queryStr ? '?' . $queryStr : '(no query)'),
        '│ User     : #' . $userId . ' — ' . $userName,
        '│ Action   : ' . ($_GET['action'] ?? '(détails)'),
        '│ Année    : ' . ($_GET['annee']  ?? date('Y') . ' (défaut)'),
        ($bodyParsed
            ? '│ Body     : ' . json_encode($bodyParsed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : '│ Body     : (vide)'),
        '└─────────────────────────────────────────────────────',
    ]));
}

try {
    $service = new ServiceLocationSalle($conn);
    $method  = $_SERVER['REQUEST_METHOD'];

    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authentification requise']);
        exit;
    }

    switch ($method) {
        case 'GET':    handleGet($service);    break;
        case 'POST':   handlePost($service);   break;
        case 'PUT':    handlePut($service);    break;
        case 'DELETE': handleDelete($service); break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    }

} catch (Exception $e) {
    $msg  = $e->getMessage();
    $code = str_contains($msg, 'introuvable') ? 404
          : (str_contains($msg, 'obligatoire') ? 422
          : (str_contains($msg, 'Droits')      ? 403
          : 200)); // ← 200 avec success:false pour que React puisse lire le message
    error_log("location-salle-api - Erreur : {$msg}");
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $msg]);
}

$output = ob_get_clean();
echo $output;

// ─────────────────────────────────────────────────────────────────────────────

function handleGet(ServiceLocationSalle $service): void
{
    $action = $_GET['action'] ?? null;

    // Types de contrat disponibles
    if ($action === 'types_contrat') {
        $types = $service->listerTypesContrat();
        if (is_dev_mode()) {
            error_log('location-salle-api [GET types_contrat] → ' . count($types) . ' type(s)');
        }
        echo json_encode(['success' => true, 'types_contrat' => $types]);
        return;
    }

    // Salles disponibles (paramètres)
    if ($action === 'salles') {
        $salles = $service->getSallesDisponibles();
        if (is_dev_mode()) {
            error_log('location-salle-api [GET salles] → ' . count($salles) . ' salle(s)');
        }
        echo json_encode(['success' => true, 'salles' => $salles]);
        return;
    }

    // Contrats (clients affichés dans le tableau pour une année)
    if ($action === 'contrats') {
        $annee    = isset($_GET['annee']) ? intval($_GET['annee']) : (int) date('Y');
        $contrats = $service->listerContrats($annee);
        if (is_dev_mode()) {
            error_log('location-salle-api [GET contrats] annee=' . $annee . ' → ' . count($contrats) . ' contrat(s)');
        }
        echo json_encode(['success' => true, 'contrats' => $contrats]);
        return;
    }

    // Détails de location pour une année (vue plate, défaut)
    $annee   = isset($_GET['annee']) ? intval($_GET['annee']) : (int) date('Y');
    $details = $service->listerDetails($annee);
    if (is_dev_mode()) {
        error_log('location-salle-api [GET détails] annee=' . $annee . ' → ' . count($details) . ' détail(s)');
    }
    echo json_encode(['success' => true, 'details' => $details]);
}

function handlePost(ServiceLocationSalle $service): void
{
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Données JSON invalides']);
        return;
    }

    $action = $_GET['action'] ?? null;

    // Ajout d'un contrat (client + salle + type de contrat)
    if ($action === 'contrat') {
        if (empty($data['id_client']) || empty($data['annee'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'id_client et annee sont requis']);
            return;
        }
        if (empty($data['id_salle']) || empty($data['id_type_contrat'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'id_salle et id_type_contrat sont requis']);
            return;
        }
        $idSalle       = (int) $data['id_salle'];
        $idTypeContrat = (int) $data['id_type_contrat'];
        if (is_dev_mode()) {
            error_log('location-salle-api [POST contrat] id_client=' . $data['id_client']
                . ' annee=' . $data['annee']
                . ' id_salle=' . $idSalle
                . ' id_type_contrat=' . $idTypeContrat);
        }
        $resultat = $service->creerContrat(
            (int) $data['id_client'],
            (int) $data['annee'],
            $idSalle,
            $idTypeContrat
        );
        if (is_dev_mode()) {
            error_log('location-salle-api [POST contrat] résultat : ' . json_encode($resultat, JSON_UNESCAPED_UNICODE));
        }
        http_response_code(201);
        echo json_encode($resultat);
        return;
    }

    // Création d'un détail de location (comportement par défaut)
    if (is_dev_mode()) {
        error_log('location-salle-api [POST détail] ' . json_encode([
            'client'     => $data['id_client']  ?? '?',
            'annee'      => $data['annee']       ?? '?',
            'mois'       => $data['mois']        ?? '?',
            'salle'      => $data['salle']       ?? '?',
            'id_unite'   => $data['id_unite']    ?? null,
            'id_service' => $data['id_service']  ?? null,
            'motif'      => $data['motif']       ?? null,
            'quantite'   => $data['quantite']    ?? '?',
        ], JSON_UNESCAPED_UNICODE));
    }
    $resultat = $service->creerDetail($data);
    if (is_dev_mode()) {
        error_log('location-salle-api [POST détail] résultat : ' . json_encode($resultat, JSON_UNESCAPED_UNICODE));
    }
    http_response_code(201);
    echo json_encode($resultat);
}

function handlePut(ServiceLocationSalle $service): void
{
    if (!isset($_GET['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID requis']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Données JSON invalides']);
        return;
    }

    $id = intval($_GET['id']);
    if (is_dev_mode()) {
        error_log('location-salle-api [PUT détail #' . $id . '] ' . json_encode([
            'mois'       => $data['mois']        ?? '?',
            'salle'      => $data['salle']       ?? '?',
            'id_unite'   => $data['id_unite']    ?? null,
            'id_service' => $data['id_service']  ?? null,
            'motif'      => $data['motif']       ?? null,
            'quantite'   => $data['quantite']    ?? '?',
            'note'       => $data['note']        ?? null,
        ], JSON_UNESCAPED_UNICODE));
    }
    $resultat = $service->modifierDetail($id, $data);
    if (is_dev_mode()) {
        error_log('location-salle-api [PUT détail #' . $id . '] résultat : ' . json_encode($resultat, JSON_UNESCAPED_UNICODE));
    }
    echo json_encode($resultat);
}

function handleDelete(ServiceLocationSalle $service): void
{
    $action = $_GET['action'] ?? null;

    // Retrait d'un client du tableau (contrat + tous ses détails)
    if ($action === 'contrat') {
        if (!isset($_GET['id_contrat'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'id_contrat requis']);
            return;
        }
        $idContrat = intval($_GET['id_contrat']);
        if (is_dev_mode()) {
            error_log('location-salle-api [DELETE contrat #' . $idContrat . ']');
        }
        $resultat = $service->supprimerContrat($idContrat);
        if (is_dev_mode()) {
            error_log('location-salle-api [DELETE contrat #' . $idContrat . '] résultat : ' . json_encode($resultat, JSON_UNESCAPED_UNICODE));
        }
        echo json_encode($resultat);
        return;
    }

    // Suppression d'un détail
    if (!isset($_GET['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID requis']);
        return;
    }
    $id = intval($_GET['id']);
    if (is_dev_mode()) {
        error_log('location-salle-api [DELETE détail #' . $id . ']');
    }
    $resultat = $service->supprimerDetail($id);
    if (is_dev_mode()) {
        error_log('location-salle-api [DELETE détail #' . $id . '] résultat : ' . json_encode($resultat, JSON_UNESCAPED_UNICODE));
    }
    echo json_encode($resultat);
}