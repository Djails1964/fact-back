<?php
/**
 * debug-bootstrap.php - Debug détaillé du bootstrap
 * À créer dans /volume1/web/DEV-facturation-api/debug-bootstrap.php
 */

echo "<h2>🔍 Debug Bootstrap Détaillé</h2>";

// Étape 1: Autoloader
echo "<h3>Étape 1: Autoloader</h3>";
try {
    require_once 'vendor/autoload.php';
    echo "✅ Autoloader chargé<br>";
} catch (Exception $e) {
    echo "❌ Erreur autoloader: " . $e->getMessage() . "<br>";
    exit;
}

// Étape 2: Dotenv
echo "<h3>Étape 2: Dotenv</h3>";
try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
    echo "✅ Dotenv chargé<br>";
    echo "APP_ENV: " . ($_ENV['APP_ENV'] ?? 'non défini') . "<br>";
} catch (Exception $e) {
    echo "❌ Erreur Dotenv: " . $e->getMessage() . "<br>";
}

// Étape 3: Variables requises
echo "<h3>Étape 3: Variables requises</h3>";
$requiredVars = ['APP_ENV', 'CORS_ORIGIN', 'DB_HOST', 'DB_USER', 'DB_PASS', 'DB_NAME', 'SESSION_LIFETIME'];
$missingVars = [];

foreach ($requiredVars as $var) {
    $value = $_ENV[$var] ?? 'NON DÉFINI';
    echo "$var: $value<br>";
    if ($value === 'NON DÉFINI') {
        $missingVars[] = $var;
    }
}

if (!empty($missingVars)) {
    echo "<div style='color: red;'>❌ Variables manquantes: " . implode(', ', $missingVars) . "</div>";
} else {
    echo "✅ Toutes les variables requises sont présentes<br>";
}

// Étape 4: Test de validation Dotenv
echo "<h3>Étape 4: Test validation Dotenv</h3>";
try {
    $dotenv->required($requiredVars);
    echo "✅ Validation des variables réussie<br>";
} catch (Exception $e) {
    echo "❌ Validation échouée: " . $e->getMessage() . "<br>";
}

// Étape 5: Fichier helpers
echo "<h3>Étape 5: Fichier utils/helpers.php</h3>";
if (file_exists(__DIR__ . '/utils/helpers.php')) {
    echo "✅ Fichier utils/helpers.php existe<br>";
    try {
        require_once __DIR__ . '/utils/helpers.php';
        echo "✅ Fichier utils/helpers.php chargé<br>";
    } catch (Exception $e) {
        echo "❌ Erreur chargement helpers: " . $e->getMessage() . "<br>";
    }
} else {
    echo "❌ Fichier utils/helpers.php n'existe pas<br>";
}

// Étape 6: Configuration des logs
echo "<h3>Étape 6: Configuration des logs</h3>";

// Fonction env locale pour test
function env_test($key, $default = null) {
    return $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key) ?? $default;
}

$logDir = realpath(__DIR__ . '/' . env_test('LOG_DIR', 'logs'));
echo "LOG_DIR from env: " . env_test('LOG_DIR', 'logs') . "<br>";
echo "Log directory path: $logDir<br>";
echo "Directory exists: " . (is_dir($logDir) ? 'OUI' : 'NON') . "<br>";

if (!is_dir($logDir)) {
    $created = mkdir($logDir, 0755, true);
    echo "Directory created: " . ($created ? 'OUI' : 'NON') . "<br>";
    if ($created) {
        $logDir = realpath($logDir);
    }
}

echo "Final log directory: $logDir<br>";
echo "Directory writable: " . (is_writable($logDir) ? 'OUI' : 'NON') . "<br>";

// Test configuration PHP logs
$logFile = $logDir . '/php-errors.log';
echo "Log file path: $logFile<br>";

ini_set('log_errors', 1);
ini_set('error_log', $logFile);

echo "PHP log_errors: " . ini_get('log_errors') . "<br>";
echo "PHP error_log: " . ini_get('error_log') . "<br>";

// Test écriture
echo "<h3>Étape 7: Test écriture logs</h3>";

// Test 1: error_log direct
error_log("TEST DEBUG: Bootstrap debug - " . date('Y-m-d H:i:s'));
echo "Test error_log() exécuté<br>";

// Test 2: file_put_contents direct
$directResult = file_put_contents($logFile, date('Y-m-d H:i:s') . " - Test direct file_put_contents\n", FILE_APPEND | LOCK_EX);
echo "Test file_put_contents: " . ($directResult !== false ? "✅ Réussi ($directResult bytes)" : "❌ Échoué") . "<br>";

// Test 3: Vérifier le fichier
if (file_exists($logFile)) {
    $size = filesize($logFile);
    $modified = date('Y-m-d H:i:s', filemtime($logFile));
    echo "Fichier log: ✅ Existe ($size bytes, modifié: $modified)<br>";
    
    // Afficher le contenu
    $content = file_get_contents($logFile);
    $lines = explode("\n", trim($content));
    echo "Contenu (dernières 5 lignes):<br>";
    echo "<pre style='background: #f0f0f0; padding: 10px;'>";
    echo htmlspecialchars(implode("\n", array_slice($lines, -5)));
    echo "</pre>";
} else {
    echo "Fichier log: ❌ N'existe pas<br>";
}

// Étape 8: Test bootstrap complet
echo "<h3>Étape 8: Test bootstrap complet</h3>";
try {
    // Sauvegarder les constantes déjà définies
    $wasInitialized = defined('APP_INITIALIZED');
    
    if (!$wasInitialized) {
        include 'bootstrap.php';
        echo "✅ Bootstrap complet chargé sans erreur<br>";
    } else {
        echo "ℹ️ Bootstrap déjà initialisé<br>";
    }
    
    // Test des fonctions
    if (function_exists('env')) {
        echo "✅ Fonction env() disponible<br>";
        echo "env('APP_ENV'): " . env('APP_ENV', 'non défini') . "<br>";
    }
    
    if (function_exists('is_dev_mode')) {
        echo "✅ Fonction is_dev_mode() disponible: " . (is_dev_mode() ? 'ON' : 'OFF') . "<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Erreur bootstrap complet: " . $e->getMessage() . "<br>";
    echo "Ligne: " . $e->getLine() . "<br>";
    echo "Fichier: " . basename($e->getFile()) . "<br>";
    echo "Trace:<br><pre>" . $e->getTraceAsString() . "</pre>";
} catch (Error $e) {
    echo "❌ Erreur fatale bootstrap: " . $e->getMessage() . "<br>";
    echo "Ligne: " . $e->getLine() . "<br>";
    echo "Fichier: " . basename($e->getFile()) . "<br>";
}
?>