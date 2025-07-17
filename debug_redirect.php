<?php
/**
 * debug_redirect.php - Diagnostic de redirection
 * Créer ce fichier dans votre dossier backend pour identifier d'où vient la redirection
 */

// Capturer toute sortie pour empêcher les redirections automatiques
ob_start();

echo "<!DOCTYPE html><html><head><title>Debug Redirection</title></head><body>";
echo "<h1>🔍 Debug de redirection</h1>";

echo "<h2>1. Test avant inclusion de bootstrap</h2>";
echo "<pre>";
echo "URL actuelle: " . $_SERVER['REQUEST_URI'] . "\n";
echo "Method: " . $_SERVER['REQUEST_METHOD'] . "\n";
echo "Headers reçus:\n";
foreach (getallheaders() as $name => $value) {
    echo "$name: $value\n";
}
echo "\nParamètres GET:\n";
print_r($_GET);
echo "\nStatus session avant bootstrap: " . session_status() . "\n";
echo "</pre>";

echo "<h2>2. Inclusion de bootstrap.php</h2>";
echo "<pre>";
try {
    require_once 'bootstrap.php';
    echo "✅ Bootstrap inclus sans erreur\n";
    echo "Session status après bootstrap: " . session_status() . "\n";
    echo "Session ID: " . session_id() . "\n";
} catch (Exception $e) {
    echo "❌ Erreur bootstrap: " . $e->getMessage() . "\n";
}
echo "</pre>";

echo "<h2>3. Test de session</h2>";
echo "<pre>";
try {
    ensure_session_started();
    echo "✅ Session démarrée\n";
    echo "Session ID: " . session_id() . "\n";
    echo "Contenu session:\n";
    print_r($_SESSION);
} catch (Exception $e) {
    echo "❌ Erreur session: " . $e->getMessage() . "\n";
}
echo "</pre>";

echo "<h2>4. Vérification des headers de redirection</h2>";
echo "<pre>";
$headers = headers_list();
echo "Headers PHP envoyés:\n";
foreach ($headers as $header) {
    echo "$header\n";
    if (stripos($header, 'location:') === 0) {
        echo "⚠️ REDIRECTION DÉTECTÉE: $header\n";
    }
}

if (empty($headers)) {
    echo "Aucun header envoyé\n";
}
echo "</pre>";

echo "<h2>5. Test de fonction is_dev_mode()</h2>";
echo "<pre>";
if (function_exists('is_dev_mode')) {
    echo "is_dev_mode(): " . (is_dev_mode() ? 'true' : 'false') . "\n";
} else {
    echo "❌ Fonction is_dev_mode() non définie\n";
}
echo "</pre>";

echo "<h2>6. Test des paramètres request_id</h2>";
echo "<pre>";
$requestId = $_GET['request_id'] ?? '';
echo "Request ID reçu: " . ($requestId ?: 'AUCUN') . "\n";

if ($requestId) {
    echo "Format valide: " . (preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $requestId) ? 'Oui' : 'Non') . "\n";
    
    if (isset($_SESSION['pending_emails'][$requestId])) {
        echo "✅ Données trouvées en session\n";
        echo "Détails: " . json_encode($_SESSION['pending_emails'][$requestId], JSON_PRETTY_PRINT) . "\n";
    } else {
        echo "❌ Données non trouvées en session\n";
        echo "Request IDs disponibles: " . implode(', ', array_keys($_SESSION['pending_emails'] ?? [])) . "\n";
    }
}
echo "</pre>";

echo "<h2>7. Test des includes/requires</h2>";
echo "<pre>";
echo "Fichiers inclus:\n";
foreach (get_included_files() as $file) {
    echo "- " . basename($file) . " (" . $file . ")\n";
}
echo "</pre>";

echo "<h2>8. Variables d'environnement importantes</h2>";
echo "<pre>";
$envVars = ['APP_ENV', 'MAIL_SEND_MODE', 'APP_URL_BACK', 'APP_URL_FRONT'];
foreach ($envVars as $var) {
    if (function_exists('env')) {
        echo "$var: " . (env($var) ?: 'NON DÉFINI') . "\n";
    }
}
echo "</pre>";

// Arrêter la capture de sortie et afficher
$output = ob_get_clean();
echo $output;

echo "<h2>9. Conclusion</h2>";
echo "<div style='background: #f0f0f0; padding: 15px; border-radius: 5px;'>";
echo "<p><strong>Si vous voyez cette page complètement</strong> : Pas de redirection automatique</p>";
echo "<p><strong>Si vous êtes redirigé vers login</strong> : La redirection vient d'un des éléments ci-dessus</p>";
echo "<p><strong>Prochaine étape</strong> : Analyser les logs PHP et les headers détectés</p>";
echo "</div>";

echo "</body></html>";
?>