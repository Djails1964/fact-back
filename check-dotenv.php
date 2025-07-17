<?php
/**
 * check-dotenv.php - Vérifier l'installation de Dotenv
 * À créer dans /volume1/web/DEV-facturation-api/check-dotenv.php
 */

echo "<h2>🔍 Diagnostic Dotenv</h2>";

// Test 1: Autoloader
echo "<h3>1. Autoloader Composer</h3>";
try {
    require_once 'vendor/autoload.php';
    echo "✅ Autoloader chargé avec succès<br>";
} catch (Exception $e) {
    echo "❌ Erreur autoloader: " . $e->getMessage() . "<br>";
    exit;
}

// Test 2: Classe Dotenv
echo "<h3>2. Classe Dotenv</h3>";
if (class_exists('Dotenv\Dotenv')) {
    echo "✅ Classe Dotenv\Dotenv disponible<br>";
    echo "Version: ";
    try {
        $reflection = new ReflectionClass('Dotenv\Dotenv');
        echo $reflection->getFileName() . "<br>";
    } catch (Exception $e) {
        echo "Inconnue<br>";
    }
} else {
    echo "❌ Classe Dotenv\Dotenv NON disponible<br>";
}

// Test 3: Méthode createImmutable
echo "<h3>3. Méthode createImmutable</h3>";
try {
    if (method_exists('Dotenv\Dotenv', 'createImmutable')) {
        echo "✅ Méthode createImmutable disponible<br>";
    } else {
        echo "❌ Méthode createImmutable NON disponible<br>";
        
        // Vérifier les autres méthodes
        if (method_exists('Dotenv\Dotenv', 'create')) {
            echo "ℹ️ Méthode create() disponible (ancienne version)<br>";
        }
    }
} catch (Exception $e) {
    echo "❌ Erreur test méthode: " . $e->getMessage() . "<br>";
}

// Test 4: Test de création Dotenv
echo "<h3>4. Test de création Dotenv</h3>";
try {
    if (class_exists('Dotenv\Dotenv')) {
        if (method_exists('Dotenv\Dotenv', 'createImmutable')) {
            $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
            echo "✅ createImmutable réussi<br>";
        } else if (method_exists('Dotenv\Dotenv', 'create')) {
            $dotenv = Dotenv\Dotenv::create(__DIR__);
            echo "✅ create réussi (ancienne version)<br>";
        } else {
            echo "❌ Aucune méthode de création trouvée<br>";
        }
    }
} catch (Exception $e) {
    echo "❌ Erreur création Dotenv: " . $e->getMessage() . "<br>";
}

// Test 5: Test de chargement du .env
echo "<h3>5. Test de chargement .env</h3>";
if (file_exists('.env')) {
    echo "✅ Fichier .env existe<br>";
    
    try {
        if (isset($dotenv)) {
            $dotenv->load();
            echo "✅ Fichier .env chargé via Dotenv<br>";
            
            // Test des variables
            echo "APP_ENV: " . ($_ENV['APP_ENV'] ?? 'non défini') . "<br>";
            echo "CORS_ORIGIN: " . ($_ENV['CORS_ORIGIN'] ?? 'non défini') . "<br>";
        }
    } catch (Exception $e) {
        echo "❌ Erreur chargement .env: " . $e->getMessage() . "<br>";
    }
} else {
    echo "❌ Fichier .env n'existe pas<br>";
}

// Test 6: Dépendances dans vendor
echo "<h3>6. Dépendances dans vendor</h3>";
$vendorDirs = [
    'vendor/vlucas',
    'vendor/vlucas/phpdotenv',
    'vendor/symfony',
    'vendor/graham-campbell'
];

foreach ($vendorDirs as $dir) {
    if (is_dir($dir)) {
        echo "✅ $dir existe<br>";
    } else {
        echo "❌ $dir manquant<br>";
    }
}

// Test 7: Composer.json
echo "<h3>7. Composer.json</h3>";
if (file_exists('composer.json')) {
    $composer = json_decode(file_get_contents('composer.json'), true);
    if (isset($composer['require']['vlucas/phpdotenv'])) {
        echo "✅ vlucas/phpdotenv dans require: " . $composer['require']['vlucas/phpdotenv'] . "<br>";
    } else {
        echo "❌ vlucas/phpdotenv pas dans require<br>";
    }
} else {
    echo "❌ composer.json n'existe pas<br>";
}

// Test 8: Bootstrap original
echo "<h3>8. Test bootstrap original</h3>";
try {
    // Sauvegarder l'état actuel
    $originalEnv = $_ENV;
    
    // Essayer de charger le bootstrap
    include 'bootstrap.php';
    echo "✅ Bootstrap chargé sans erreur<br>";
} catch (Exception $e) {
    echo "❌ Erreur bootstrap: " . $e->getMessage() . "<br>";
    echo "Ligne: " . $e->getLine() . "<br>";
    echo "Fichier: " . basename($e->getFile()) . "<br>";
} catch (Error $e) {
    echo "❌ Erreur fatale bootstrap: " . $e->getMessage() . "<br>";
    echo "Ligne: " . $e->getLine() . "<br>";
    echo "Fichier: " . basename($e->getFile()) . "<br>";
}
?>