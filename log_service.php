<?php
/**
 * log_service.php
 * Service pour enregistrer les logs JavaScript dans un fichier
 */

// Inclure les fichiers nécessaires
require_once '../bootstrap.php';

// Vérifier que la méthode est POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    exit('Méthode non autorisée');
}

// Récupérer les données JSON
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['message']) || !isset($data['level'])) {
    header('HTTP/1.1 400 Bad Request');
    exit('Données manquantes');
}

// Configurer le chemin du fichier de log
$logPath = env('LOG_PATH', __DIR__ . '/../logs');
$logFile = $logPath . '/frontend_' . date('Y-m-d') . '.log';

// Créer le répertoire si nécessaire
if (!file_exists($logPath)) {
    mkdir($logPath, 0755, true);
}

// Formater le message de log
$timestamp = date('Y-m-d H:i:s');
$level = strtoupper($data['level']);
$message = $data['message'];
$source = isset($data['source']) ? $data['source'] : 'frontend';
$logEntry = "[$timestamp] [$level] [$source] $message" . PHP_EOL;

// Écrire dans le fichier
file_put_contents($logFile, $logEntry, FILE_APPEND);

// Répondre avec succès
header('Content-Type: application/json');
echo json_encode(['success' => true]);