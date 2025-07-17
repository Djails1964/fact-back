<?php
/**
 * get_email_data.php - VERSION DEBUG RENFORCÉE
 */

// IMPORTANT: Inclure le bootstrap EN PREMIER
require_once 'bootstrap.php';

// S'assurer que la session est démarrée
ensure_session_started();

// Définir les en-têtes pour JSON
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// CORS si nécessaire
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

try {
    // Debug initial RENFORCÉ
    error_log("=== DEBUG get_email_data.php RENFORCÉ ===");
    error_log("Session ID: " . session_id());
    error_log("Method: " . $_SERVER['REQUEST_METHOD']);
    error_log("Request URI: " . $_SERVER['REQUEST_URI']);
    error_log("Query String: " . ($_SERVER['QUERY_STRING'] ?? 'null'));
    error_log("GET params: " . print_r($_GET, true));
    error_log("Session pending_emails keys: " . (isset($_SESSION['pending_emails']) ? implode(', ', array_keys($_SESSION['pending_emails'])) : 'SESSION_VIDE'));
    
    // Vérifier la méthode HTTP
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Méthode non autorisée: ' . $_SERVER['REQUEST_METHOD']);
    }
    
    // Récupérer l'ID de la requête
    $requestId = $_GET['request_id'] ?? '';
    error_log("Request ID demandé: '$requestId'");
    
    if (empty($requestId)) {
        error_log("ERREUR: Request ID vide");
        throw new Exception('ID de requête manquant');
    }
    
    // NOUVELLE: Validation d'ID plus permissive
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $requestId)) {
        error_log("ERREUR: Format d'ID invalide: '$requestId'");
        throw new Exception('Format d\'ID de requête invalide');
    }
    
    // Vérifier si les données existent en session
    if (!isset($_SESSION['pending_emails'])) {
        error_log("ERREUR: Aucune pending_emails en session");
        error_log("Session complète: " . print_r($_SESSION, true));
        throw new Exception('Aucune requête d\'email en attente');
    }
    
    if (!isset($_SESSION['pending_emails'][$requestId])) {
        error_log("ERREUR: Requête $requestId non trouvée");
        error_log("Request IDs disponibles: " . implode(', ', array_keys($_SESSION['pending_emails'])));
        error_log("Comparaison exacte:");
        foreach (array_keys($_SESSION['pending_emails']) as $key) {
            error_log("  - '$key' === '$requestId' ? " . ($key === $requestId ? 'OUI' : 'NON'));
        }
        throw new Exception('Requête d\'email non trouvée ou expirée');
    }
    
    $emailData = $_SESSION['pending_emails'][$requestId];
    error_log("Données trouvées pour request ID: $requestId");
    
    // Vérifier l'âge de la requête (max 1 heure)
    $maxAge = 3600; // 1 heure en secondes
    $age = time() - $emailData['timestamp'];
    error_log("Âge de la requête: $age secondes (max: $maxAge)");
    
    if ($age > $maxAge) {
        // Nettoyer la requête expirée
        unset($_SESSION['pending_emails'][$requestId]);
        error_log("ERREUR: Requête expirée (âge: $age sec)");
        throw new Exception('Requête expirée');
    }
    
    // Marquer comme utilisée
    $_SESSION['pending_emails'][$requestId]['used'] = true;
    
    // Préparer les données pour la réponse
    $response = [
        'success' => true,
        'emailData' => $emailData['emailData'],
        'attachmentInfo' => $emailData['attachmentInfo'] ?? [],
        'timestamp' => $emailData['timestamp'],
        'debug' => [
            'session_id' => session_id(),
            'request_id' => $requestId,
            'age_seconds' => $age,
            'found_keys' => array_keys($_SESSION['pending_emails'])
        ]
    ];
    
    error_log("✅ Réponse préparée avec succès");
    error_log("Nombre de pièces jointes: " . count($response['attachmentInfo']));
    
    // Retourner les données
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Log de l'erreur avec détails complets
    error_log("❌ ERREUR get_email_data.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Retourner l'erreur avec détails de debug
    http_response_code(400);
    $errorResponse = [
        'success' => false,
        'message' => $e->getMessage(),
        'debug' => [
            'session_id' => session_id(),
            'request_id' => $_GET['request_id'] ?? 'NON_FOURNI',
            'session_status' => session_status(),
            'session_keys' => array_keys($_SESSION ?? []),
            'pending_emails_keys' => array_keys($_SESSION['pending_emails'] ?? []),
            'method' => $_SERVER['REQUEST_METHOD'],
            'query_string' => $_SERVER['QUERY_STRING'] ?? null
        ]
    ];
    
    echo json_encode($errorResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

// Fonction utilitaire pour nettoyer (inchangée)
function cleanupOldEmailRequests() {
    if (!isset($_SESSION['pending_emails'])) {
        return;
    }
    
    $currentTime = time();
    $maxAge = 3600;
    $cleaned = 0;
    
    foreach ($_SESSION['pending_emails'] as $requestId => $data) {
        if (($currentTime - $data['timestamp']) > $maxAge) {
            unset($_SESSION['pending_emails'][$requestId]);
            $cleaned++;
        }
    }
    
    if ($cleaned > 0) {
        error_log("Nettoyé $cleaned anciennes requêtes d'email");
    }
}

cleanupOldEmailRequests();
?>