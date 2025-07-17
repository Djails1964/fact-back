<?php
// Fichier de debug temporaire : debug_session.php
// À créer dans le même dossier que email_client_sender.php pour diagnostiquer

require_once 'bootstrap.php';

// Forcer l'affichage des erreurs pour ce debug
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<!DOCTYPE html><html><head><title>Debug Session</title></head><body>";
echo "<h1>🔍 Debug des Sessions</h1>";

echo "<h2>1. Informations de session actuelles</h2>";
echo "<pre>";
echo "Session status: " . session_status() . " (1=disabled, 2=none, 3=active)\n";
echo "Session ID: " . (session_id() ?: 'AUCUN') . "\n";
echo "Session name: " . session_name() . "\n";
echo "Session save path: " . session_save_path() . "\n";
echo "</pre>";

echo "<h2>2. Paramètres GET reçus</h2>";
echo "<pre>";
print_r($_GET);
echo "</pre>";

echo "<h2>3. Cookies reçus</h2>";
echo "<pre>";
print_r($_COOKIE);
echo "</pre>";

echo "<h2>4. Test de démarrage de session</h2>";
echo "<pre>";
try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
        echo "✅ Session démarrée avec succès\n";
    } else {
        echo "ℹ️ Session déjà active\n";
    }
    
    echo "Session ID après démarrage: " . session_id() . "\n";
    echo "Contenu de \$_SESSION:\n";
    print_r($_SESSION);
    
} catch (Exception $e) {
    echo "❌ Erreur lors du démarrage de session: " . $e->getMessage() . "\n";
}
echo "</pre>";

echo "<h2>5. Test de transmission de session par URL</h2>";
$sessionName = session_name();
$sessionIdFromUrl = $_GET[$sessionName] ?? null;

echo "<pre>";
echo "Session name attendu: {$sessionName}\n";
echo "Session ID depuis URL: " . ($sessionIdFromUrl ?: 'AUCUN') . "\n";

if ($sessionIdFromUrl) {
    echo "\n🔄 Test de forçage de session depuis URL...\n";
    
    try {
        // Sauvegarder l'ID actuel
        $currentSessionId = session_id();
        
        // Fermer la session actuelle si active
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        
        // Forcer l'ID depuis l'URL
        session_id($sessionIdFromUrl);
        session_start();
        
        echo "✅ Session forcée avec ID: " . session_id() . "\n";
        echo "Contenu après forçage:\n";
        print_r($_SESSION);
        
    } catch (Exception $e) {
        echo "❌ Erreur lors du forçage: " . $e->getMessage() . "\n";
    }
} else {
    echo "\n⚠️ Aucun ID de session fourni dans l'URL\n";
}
echo "</pre>";

echo "<h2>6. Configuration des cookies de session</h2>";
echo "<pre>";
echo "session.cookie_lifetime: " . ini_get('session.cookie_lifetime') . "\n";
echo "session.cookie_domain: " . ini_get('session.cookie_domain') . "\n";
echo "session.cookie_path: " . ini_get('session.cookie_path') . "\n";
echo "session.cookie_secure: " . ini_get('session.cookie_secure') . "\n";
echo "session.cookie_httponly: " . ini_get('session.cookie_httponly') . "\n";
echo "session.cookie_samesite: " . ini_get('session.cookie_samesite') . "\n";
echo "</pre>";

echo "<h2>7. URL de test suggérée</h2>";
$currentSessionId = session_id();
$testUrl = "debug_session.php?{$sessionName}={$currentSessionId}&test=1";
echo "<p>Pour tester la transmission de session, ouvrez cette URL dans un nouvel onglet :</p>";
echo "<p><a href='{$testUrl}' target='_blank'>{$testUrl}</a></p>";

echo "</body></html>";
?>