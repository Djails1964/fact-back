<?php
/**
 * test-autoloader.php - Test de l'autoloader
 * À placer dans /volume1/web/DEV-facturation-api/test-autoloader.php
 */

echo "<h2>🔍 Diagnostic Autoloader</h2>";

// Test 1: Répertoire courant
echo "<h3>1. Répertoire courant</h3>";
echo "Répertoire courant: " . getcwd() . "<br>";
echo "Script actuel: " . __FILE__ . "<br>";
echo "Dossier du script: " . __DIR__ . "<br>";

// Test 2: Vérifier l'existence des fichiers
echo "<h3>2. Fichiers d'autoloader</h3>";
$autoloaderPaths = [
    __DIR__ . '/vendor/autoload.php',
    './vendor/autoload.php',
    'vendor/autoload.php',
    '../vendor/autoload.php'
];

foreach ($autoloaderPaths as $path) {
    $exists = file_exists($path);
    $realPath = $exists ? realpath($path) : 'N/A';
    echo "Path: $path<br>";
    echo "Existe: " . ($exists ? '✅ OUI' : '❌ NON') . "<br>";
    echo "Chemin réel: $realPath<br><br>";
}

// Test 3: Contenu du dossier vendor
echo "<h3>3. Contenu du dossier vendor</h3>";
if (is_dir('vendor')) {
    echo "Dossier vendor existe ✅<br>";
    $vendorFiles = scandir('vendor');
    echo "Contenu: " . implode(', ', array_filter($vendorFiles, function($f) { return $f[0] !== '.'; })) . "<br>";
} else {
    echo "Dossier vendor n'existe pas ❌<br>";
}

// Test 4: Tenter de charger l'autoloader
echo "<h3>4. Test de chargement</h3>";
foreach ($autoloaderPaths as $path) {
    if (file_exists($path)) {
        echo "Tentative de chargement: $path<br>";
        try {
            require_once $path;
            echo "✅ Chargement réussi !<br>";
            
            // Test PHPMailer
            if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                echo "✅ PHPMailer disponible<br>";
            } else {
                echo "❌ PHPMailer non disponible<br>";
            }
            break;
        } catch (Exception $e) {
            echo "❌ Erreur: " . $e->getMessage() . "<br>";
        }
    }
}

// Test 5: Variables d'environnement et include_path
echo "<h3>5. Configuration PHP</h3>";
echo "Include path: " . get_include_path() . "<br>";
echo "Document root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') . "<br>";
echo "Script name: " . ($_SERVER['SCRIPT_NAME'] ?? 'N/A') . "<br>";

// Test 6: Permissions
echo "<h3>6. Permissions</h3>";
if (file_exists('vendor/autoload.php')) {
    $perms = fileperms('vendor/autoload.php');
    echo "Permissions autoload.php: " . substr(sprintf('%o', $perms), -4) . "<br>";
    echo "Lisible: " . (is_readable('vendor/autoload.php') ? '✅' : '❌') . "<br>";
}
?>