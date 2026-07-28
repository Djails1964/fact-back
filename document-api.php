<?php
/**
 * document-api.php - API pour servir les documents (PDF de factures)
 * Endpoint sécurisé avec authentification obligatoire
 */

// --- NOUVEAU : Forcer la session AVANT le bootstrap ---
if (isset($_GET['PHPSESSID']) && !empty($_GET['PHPSESSID'])) {
    if (session_status() === PHP_SESSION_NONE) {
        session_id($_GET['PHPSESSID']);
    }
}
// ------------------------------------------------------

// CORS headers (gardez votre logique actuelle)
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
header("Access-Control-Allow-Origin: " . ($origin ?: "http://localhost:3000"));
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, OPTIONS');

// Maintenant on charge le bootstrap
require_once realpath(__DIR__ . '/bootstrap.php');

// Vérification session
check_session_validity();

// Initialiser l'API avec CORS centralisé
init_api_response();

// Vérifier l'authentification
$userId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['user_role'] ?? null;

if (!$userId) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Session expirée',
        'session_expired' => true,
        'code' => 401
    ]);
    exit;
}

// Inclure les dépendances
require_once 'database.php';
require_once realpath(__DIR__ . '/services/ServiceParametre.php');

try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method !== 'GET') {
        throw new Exception('Méthode non autorisée', 405);
    }
    
    // ====================================
    // GET: Récupérer un document PDF
    // ====================================
    
    // Paramètres possibles:
    // - facture: nom du fichier de facture (factfilename)
    // - type: type de document (par défaut 'facture')
    
    $filename = $_GET['facture'] ?? null;
    $type = $_GET['type'] ?? 'facture';
    
    if (!$filename) {
        throw new Exception('Paramètre "facture" requis', 400);
    }
    
    // Sécurité: Nettoyer le nom de fichier pour éviter les attaques path traversal
    $filename = basename($filename);
    
    // Vérifier que le fichier a une extension PDF
    if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'pdf') {
        throw new Exception('Seuls les fichiers PDF sont autorisés', 400);
    }
    
    // Récupérer le répertoire de stockage depuis les paramètres
    // ✅ 'type=confirmation' → dossier dédié aux confirmations de paiement
    // (contrats au forfait), distinct des factures standard.
    $serviceParametre = new ServiceParametre($conn);
    $estConfirmation = ($type === 'confirmation');
    $outputDir = $estConfirmation ? 'storage/confirmations' : 'storage/factures'; // Valeurs par défaut

    try {
        $paramResult = $estConfirmation
            ? $serviceParametre->getParametre('OutputDirConfirmation', 'Facture', 'Chemin')
            : $serviceParametre->getParametre('OutputDir', 'Facture', 'Chemin');
        if ($paramResult && $paramResult['success'] && isset($paramResult['parametre']['valeur_parametre'])) {
            $outputDir = $paramResult['parametre']['valeur_parametre'];
        }
    } catch (Exception $e) {
        error_log("document-api - Erreur récupération OutputDir: " . $e->getMessage());
    }
    
    // Construire le chemin complet du fichier
    $filePath = realpath(__DIR__ . '/' . $outputDir . '/' . $filename);
    
    // Vérification de sécurité supplémentaire
    $allowedBasePath = realpath(__DIR__ . '/' . $outputDir);
    
    if (!$filePath || strpos($filePath, $allowedBasePath) !== 0) {
        error_log("document-api - Tentative d'accès non autorisé: $filename (user: $userId)");
        throw new Exception('Fichier non trouvé ou accès non autorisé', 404);
    }
    
    if (!file_exists($filePath)) {
        error_log("document-api - Fichier non trouvé: $filePath (user: $userId)");
        throw new Exception('Fichier non trouvé', 404);
    }
    
    if (!is_readable($filePath)) {
        error_log("document-api - Fichier non lisible: $filePath (user: $userId)");
        throw new Exception('Fichier non accessible', 500);
    }
    
    // Log de l'accès
    if (function_exists('is_dev_mode') && is_dev_mode()) {
        error_log("document-api - Accès PDF autorisé: $filename (user: $userId)");
    }
    
    // Envoyer le fichier PDF
    $fileSize = filesize($filePath);
    
    // Nettoyer tout buffer de sortie
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Headers pour le PDF
    header('Content-Type: application/pdf');
    header('Content-Length: ' . $fileSize);
    header('Content-Disposition: inline; filename="' . $filename . '"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    
    // Envoyer le fichier
    readfile($filePath);
    exit;
    
} catch (Exception $e) {
    $code = $e->getCode() ?: 500;
    if ($code < 100 || $code > 599) {
        $code = 500;
    }
    
    error_log("document-api - Erreur: " . $e->getMessage() . " (code: $code, user: $userId)");
    
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'code' => $code
    ]);
    exit;
}