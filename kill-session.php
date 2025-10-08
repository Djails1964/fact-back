<?php
// kill-session.php - Fichier temporaire pour tester la perte de session
header("Access-Control-Allow-Origin: http://localhost:3000");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// Gérer la requête OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

session_start();
$sessionId = session_id();
$_SESSION = array();
session_destroy();

echo json_encode([
    'success' => true,
    'message' => 'Session détruite',
    'session_id_destroyed' => $sessionId
]);
?>