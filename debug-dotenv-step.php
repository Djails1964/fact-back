<?php
/**
 * debug-dotenv-step.php - Debug Dotenv étape par étape
 * À créer dans /volume1/web/DEV-facturation-api/debug-dotenv-step.php
 */

echo "<h2>🔍 Debug Dotenv Étape par Étape</h2>";

// Autoloader
try {
    require_once 'vendor/autoload.php';
    echo "✅ Autoloader OK<br>";
} catch (Exception $e) {
    echo "❌ Autoloader: " . $e->getMessage() . "<br>";
    exit;
}

// Étape 1: Création Dotenv
echo "<h3>Étape 1: Création Dotenv</h3>";
try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    echo "✅ Dotenv créé<br>";
} catch (Exception $e) {
    echo "❌ Création Dotenv: " . $e->getMessage() . "<br>";
    exit;
}

// Étape 2: Chargement .env
echo "<h3>Étape 2: Chargement .env</h3>";
try {
    $dotenv->load();
    echo "✅ .env chargé<br>";
} catch (Exception $e) {
    echo "❌ Chargement .env: " . $e->getMessage() . "<br>";
    echo "Type: " . get_class($e) . "<br>";
    echo "Ligne: " . $e->getLine() . "<br>";
    echo "Fichier: " . basename($e->getFile()) . "<br>";
    exit;
}

// Étape 3: Vérification des variables chargées
echo "<h3>Étape 3: Variables chargées</h3>";
$expectedVars = ['APP_ENV', 'CORS_ORIGIN', 'DB_HOST', 'DB_USER', 'DB_PASS', 'DB_NAME', 'SESSION_LIFETIME'];
foreach ($expectedVars as $var) {
    $value = $_ENV[$var] ?? 'NON DÉFINI';
    $status = $value !== 'NON DÉFINI' ? '✅' : '❌';
    echo "$status $var: $value<br>";
}

// Étape 4: Test validation UNE PAR UNE
echo "<h3>Étape 4: Test validation une par une</h3>";
foreach ($expectedVars as $var) {
    try {
        $dotenv->required([$var]);
        echo "✅ $var: validation OK<br>";
    } catch (Exception $e) {
        echo "❌ $var: " . $e->getMessage() . "<br>";
    }
}

// Étape 5: Test validation TOUTES ENSEMBLE
echo "<h3>Étape 5: Test validation toutes ensemble</h3>";
try {
    $dotenv->required($expectedVars);
    echo "✅ Toutes les variables validées<br>";
} catch (Exception $e) {
    echo "❌ Validation globale: " . $e->getMessage() . "<br>";
    echo "Type: " . get_class($e) . "<br>";
}

// Étape 6: Contenu du fichier .env
echo "<h3>Étape 6: Contenu du fichier .env</h3>";
if (file_exists('.env')) {
    $envContent = file_get_contents('.env');
    $lines = explode("\n", $envContent);
    echo "Nombre de lignes: " . count($lines) . "<br>";
    echo "Lignes avec '=':<br>";
    
    foreach ($lines as $lineNum => $line) {
        $line = trim($line);
        if (!empty($line) && strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            echo ($lineNum + 1) . ": " . htmlspecialchars($key) . " = " . htmlspecialchars($value) . "<br>";
        } elseif (!empty($line) && strpos($line, '#') !== 0) {
            echo ($lineNum + 1) . ": <span style='color: red;'>" . htmlspecialchars($line) . " (PROBLÉMATIQUE)</span><br>";
        }
    }
} else {
    echo "❌ Fichier .env n'existe pas<br>";
}

echo "<h3>Fin du diagnostic</h3>";
echo "Si vous voyez ce message, Dotenv fonctionne correctement !";
?>