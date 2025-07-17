<?php
/**
 * dotenv-version-debug.php - Diagnostic version Dotenv
 * À créer dans /volume1/web/DEV-facturation-api/dotenv-version-debug.php
 */

echo "<h2>🔍 Diagnostic Version Dotenv</h2>";

// Autoloader
require_once 'vendor/autoload.php';

// Test 1: Informations de version
echo "<h3>1. Informations PHP et Dotenv</h3>";
echo "PHP Version: " . phpversion() . "<br>";

// Test 2: Classe Dotenv
echo "<h3>2. Classes Dotenv disponibles</h3>";
$dotenvClasses = [
    'Dotenv\Dotenv',
    'Dotenv\Loader',
    'Dotenv\Parser',
    'Dotenv\Repository\RepositoryBuilder',
    'Dotenv\Store\StoreBuilder'
];

foreach ($dotenvClasses as $class) {
    if (class_exists($class)) {
        echo "✅ $class<br>";
        try {
            $reflection = new ReflectionClass($class);
            echo "&nbsp;&nbsp;📁 " . $reflection->getFileName() . "<br>";
        } catch (Exception $e) {
            echo "&nbsp;&nbsp;❌ Erreur reflection: " . $e->getMessage() . "<br>";
        }
    } else {
        echo "❌ $class<br>";
    }
}

// Test 3: Méthodes disponibles
echo "<h3>3. Méthodes Dotenv disponibles</h3>";
if (class_exists('Dotenv\Dotenv')) {
    $methods = get_class_methods('Dotenv\Dotenv');
    echo "Méthodes disponibles: " . implode(', ', $methods) . "<br>";
    
    $hasCreateImmutable = method_exists('Dotenv\Dotenv', 'createImmutable');
    $hasCreate = method_exists('Dotenv\Dotenv', 'create');
    $hasLoad = method_exists('Dotenv\Dotenv', 'load');
    
    echo "createImmutable: " . ($hasCreateImmutable ? '✅' : '❌') . "<br>";
    echo "create: " . ($hasCreate ? '✅' : '❌') . "<br>";
    echo "load: " . ($hasLoad ? '✅' : '❌') . "<br>";
}

// Test 4: Composer lock
echo "<h3>4. Versions installées (composer.lock)</h3>";
if (file_exists('composer.lock')) {
    $composerLock = json_decode(file_get_contents('composer.lock'), true);
    if (isset($composerLock['packages'])) {
        foreach ($composerLock['packages'] as $package) {
            if (strpos($package['name'], 'dotenv') !== false || strpos($package['name'], 'vlucas') !== false) {
                echo "📦 " . $package['name'] . ": " . $package['version'] . "<br>";
            }
        }
    }
} else {
    echo "❌ composer.lock non trouvé<br>";
}

// Test 5: Test de création avec gestion d'erreur détaillée
echo "<h3>5. Test de création avec erreur détaillée</h3>";
try {
    // Essayer la méthode moderne
    if (method_exists('Dotenv\Dotenv', 'createImmutable')) {
        echo "🔄 Tentative createImmutable...<br>";
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
        echo "✅ createImmutable réussi<br>";
        
        // Maintenant le chargement
        echo "🔄 Tentative load...<br>";
        $dotenv->load();
        echo "✅ load réussi !<br>";
        
        // Vérifier les variables
        echo "Variables chargées:<br>";
        $testVars = ['APP_ENV', 'CORS_ORIGIN', 'DB_HOST'];
        foreach ($testVars as $var) {
            echo "- $var: " . ($_ENV[$var] ?? 'NON DÉFINI') . "<br>";
        }
        
    } else {
        echo "❌ createImmutable non disponible<br>";
        
        // Essayer l'ancienne méthode
        if (method_exists('Dotenv\Dotenv', 'create')) {
            echo "🔄 Tentative create...<br>";
            $dotenv = Dotenv\Dotenv::create(__DIR__);
            echo "✅ create réussi<br>";
            
            $dotenv->load();
            echo "✅ load réussi !<br>";
        }
    }
    
} catch (Exception $e) {
    echo "<div style='color: red; background: #ffe6e6; padding: 15px; border-radius: 5px;'>";
    echo "❌ <strong>ERREUR TROUVÉE !</strong><br>";
    echo "<strong>Type:</strong> " . get_class($e) . "<br>";
    echo "<strong>Message:</strong> " . $e->getMessage() . "<br>";
    echo "<strong>Fichier:</strong> " . basename($e->getFile()) . "<br>";
    echo "<strong>Ligne:</strong> " . $e->getLine() . "<br>";
    echo "<strong>Trace:</strong><br>";
    echo "<pre style='background: #f8f8f8; padding: 10px; font-size: 12px; max-height: 300px; overflow-y: auto;'>";
    echo $e->getTraceAsString();
    echo "</pre>";
    echo "</div>";
} catch (Error $e) {
    echo "<div style='color: red; background: #ffe6e6; padding: 15px; border-radius: 5px;'>";
    echo "❌ <strong>ERREUR FATALE !</strong><br>";
    echo "<strong>Type:</strong> " . get_class($e) . "<br>";
    echo "<strong>Message:</strong> " . $e->getMessage() . "<br>";
    echo "<strong>Fichier:</strong> " . basename($e->getFile()) . "<br>";
    echo "<strong>Ligne:</strong> " . $e->getLine() . "<br>";
    echo "</div>";
}

// Test 6: Contenu du fichier .env
echo "<h3>6. Contenu du fichier .env</h3>";
$envContent = file_get_contents('.env');
echo "Taille: " . strlen($envContent) . " bytes<br>";
echo "Contenu:<br>";
echo "<pre style='background: #f8f8f8; padding: 10px; border: 1px solid #ccc;'>";
echo htmlspecialchars($envContent);
echo "</pre>";

echo "<h3>7. Test de lecture manuelle</h3>";
$lines = explode("\n", $envContent);
echo "Nombre de lignes: " . count($lines) . "<br>";
foreach ($lines as $i => $line) {
    if (!empty(trim($line))) {
        echo "Ligne " . ($i + 1) . ": " . htmlspecialchars($line) . "<br>";
    }
}
?>