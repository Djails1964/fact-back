<?php
/**
 * create-minimal-env.php - Créer un .env minimal pour test
 * À créer dans /volume1/web/DEV-facturation-api/create-minimal-env.php
 */

echo "<h2>🔧 Création .env minimal</h2>";

// Sauvegarder l'ancien .env
$oldEnvPath = __DIR__ . '/.env';
$backupPath = __DIR__ . '/.env.backup';

if (file_exists($oldEnvPath)) {
    if (copy($oldEnvPath, $backupPath)) {
        echo "✅ Ancien .env sauvegardé dans .env.backup<br>";
    } else {
        echo "❌ Impossible de sauvegarder l'ancien .env<br>";
    }
}

// Créer un .env ULTRA MINIMAL
$minimalEnv = 'APP_ENV=development
CORS_ORIGIN=https://dev-facturation
DB_HOST=localhost
DB_USER=root
DB_PASS=password123
DB_NAME=dev_factlagrange
SESSION_LIFETIME=1800';

echo "<h3>Contenu du .env minimal :</h3>";
echo "<pre style='background: #f0f0f0; padding: 10px;'>";
echo htmlspecialchars($minimalEnv);
echo "</pre>";

// Écrire le fichier
$result = file_put_contents($oldEnvPath, $minimalEnv);

if ($result !== false) {
    echo "<div style='color: green; background: #e6ffe6; padding: 10px; border-radius: 5px;'>";
    echo "✅ .env minimal créé avec succès ($result bytes)<br>";
    echo "<br>";
    echo "<a href='debug-dotenv-step.php' style='background: #28a745; color: white; padding: 10px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>🔄 Tester Dotenv</a>";
    echo "<a href='restore-env.php' style='background: #6c757d; color: white; padding: 10px; text-decoration: none; border-radius: 5px;'>📋 Restaurer ancien .env</a>";
    echo "</div>";
} else {
    echo "<div style='color: red; background: #ffe6e6; padding: 10px; border-radius: 5px;'>";
    echo "❌ Impossible de créer le .env minimal<br>";
    echo "</div>";
}

// Créer aussi un script de restauration
$restoreScript = '<?php
// restore-env.php - Restaurer l\'ancien .env
echo "<h2>🔄 Restauration .env</h2>";

$backupPath = __DIR__ . "/.env.backup";
$envPath = __DIR__ . "/.env";

if (file_exists($backupPath)) {
    if (copy($backupPath, $envPath)) {
        echo "✅ .env restauré avec succès<br>";
        echo "<a href=\'debug-dotenv-step.php\' style=\'background: #007bff; color: white; padding: 10px; text-decoration: none; border-radius: 5px;\'>🔄 Tester Dotenv</a>";
    } else {
        echo "❌ Impossible de restaurer le .env<br>";
    }
} else {
    echo "❌ Pas de sauvegarde trouvée<br>";
}
?>';

file_put_contents(__DIR__ . '/restore-env.php', $restoreScript);
echo "<br><small>Script de restauration créé : restore-env.php</small>";
?>