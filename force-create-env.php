<?php
/**
 * force-create-env.php - Forcer la création du .env minimal
 * À créer dans /volume1/web/DEV-facturation-api/force-create-env.php
 */

echo "<h2>🔧 Création forcée du .env minimal</h2>";

$envPath = __DIR__ . '/.env';
$backupPath = __DIR__ . '/.env.backup';

// Sauvegarder l'ancien
if (file_exists($envPath)) {
    if (copy($envPath, $backupPath)) {
        echo "✅ Ancien .env sauvegardé dans .env.backup<br>";
    } else {
        echo "⚠️ Impossible de sauvegarder (on continue quand même)<br>";
    }
}

// Contenu minimal sans caractères spéciaux
$minimalEnv = 'APP_ENV=development
CORS_ORIGIN=https://dev-facturation
DB_HOST=localhost
DB_USER=root
DB_PASS=password123
DB_NAME=dev_factlagrange
SESSION_LIFETIME=1800
LOG_DIR=logs';

echo "<h3>Nouveau contenu .env :</h3>";
echo "<pre style='background: #f0f0f0; padding: 10px; border: 1px solid #ccc;'>";
echo htmlspecialchars($minimalEnv);
echo "</pre>";

// Supprimer l'ancien fichier et créer le nouveau
if (file_exists($envPath)) {
    unlink($envPath);
    echo "🗑️ Ancien .env supprimé<br>";
}

$result = file_put_contents($envPath, $minimalEnv);

if ($result !== false) {
    echo "<div style='color: green; background: #e6ffe6; padding: 15px; border-radius: 5px; margin: 15px 0;'>";
    echo "✅ <strong>.env minimal créé avec succès !</strong> ($result bytes)<br><br>";
    
    // Vérifier les permissions du nouveau fichier
    echo "Permissions du nouveau fichier: " . substr(sprintf('%o', fileperms($envPath)), -4) . "<br>";
    echo "Taille: " . filesize($envPath) . " bytes<br><br>";
    
    echo "🧪 <strong>Tests à faire maintenant :</strong><br>";
    echo "<a href='debug-dotenv-step.php' style='background: #28a745; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>1️⃣ Tester Dotenv</a>";
    echo "<a href='debug-bootstrap.php' style='background: #007bff; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>2️⃣ Tester Bootstrap</a>";
    echo "<a href='client-api-simple.php' style='background: #ffc107; color: black; padding: 10px 15px; text-decoration: none; border-radius: 5px;'>3️⃣ Tester API</a>";
    echo "</div>";
    
    // Créer un script de restauration
    $restoreScript = '<?php
echo "<h2>🔄 Restauration .env original</h2>";
$backupPath = __DIR__ . "/.env.backup";
$envPath = __DIR__ . "/.env";

if (file_exists($backupPath)) {
    if (copy($backupPath, $envPath)) {
        echo "<div style=\"color: green; background: #e6ffe6; padding: 15px; border-radius: 5px;\">";
        echo "✅ .env original restauré avec succès !<br><br>";
        echo "<a href=\"debug-dotenv-step.php\" style=\"background: #007bff; color: white; padding: 10px; text-decoration: none; border-radius: 5px;\">Tester Dotenv</a>";
        echo "</div>";
    } else {
        echo "<div style=\"color: red;\">❌ Erreur lors de la restauration</div>";
    }
} else {
    echo "<div style=\"color: red;\">❌ Pas de sauvegarde trouvée</div>";
}
?>';
    
    file_put_contents(__DIR__ . '/restore-env.php', $restoreScript);
    echo "<br><small>📋 Script de restauration créé : <a href='restore-env.php'>restore-env.php</a></small>";
    
} else {
    echo "<div style='color: red; background: #ffe6e6; padding: 15px; border-radius: 5px;'>";
    echo "❌ <strong>Échec de la création du .env</strong><br>";
    echo "Vérifiez les permissions du répertoire.";
    echo "</div>";
}
?>