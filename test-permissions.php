<?php
/**
 * test-permissions.php - Test des permissions
 * À créer dans /volume1/web/DEV-facturation-api/test-permissions.php
 */

echo "<h2>🔐 Test des permissions</h2>";

$dir = __DIR__;
$envFile = $dir . '/.env';
$testFile = $dir . '/test-write.txt';

echo "<h3>1. Répertoire courant</h3>";
echo "Chemin: $dir<br>";
echo "Writable: " . (is_writable($dir) ? '✅ OUI' : '❌ NON') . "<br>";
echo "Permissions: " . substr(sprintf('%o', fileperms($dir)), -4) . "<br>";

echo "<h3>2. Fichier .env</h3>";
if (file_exists($envFile)) {
    echo "Existe: ✅ OUI<br>";
    echo "Writable: " . (is_writable($envFile) ? '✅ OUI' : '❌ NON') . "<br>";
    echo "Permissions: " . substr(sprintf('%o', fileperms($envFile)), -4) . "<br>";
    echo "Propriétaire: " . fileowner($envFile) . "<br>";
    echo "Groupe: " . filegroup($envFile) . "<br>";
} else {
    echo "Existe: ❌ NON<br>";
}

echo "<h3>3. Test d'écriture</h3>";
$testContent = "Test écriture - " . date('Y-m-d H:i:s');
$result = file_put_contents($testFile, $testContent);

if ($result !== false) {
    echo "✅ Écriture réussie ($result bytes)<br>";
    unlink($testFile); // Nettoyer
} else {
    echo "❌ Écriture échouée<br>";
}

echo "<h3>4. Utilisateur PHP</h3>";
echo "User: " . get_current_user() . "<br>";
echo "UID: " . getmyuid() . "<br>";
echo "GID: " . getmygid() . "<br>";

echo "<h3>5. Instructions</h3>";
if (!is_writable($envFile)) {
    echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; border: 1px solid #ffeaa7;'>";
    echo "<strong>⚠️ Solution:</strong><br>";
    echo "1. Via SSH: <code>chmod 664 /volume1/web/DEV-facturation-api/.env</code><br>";
    echo "2. Ou via File Station: Propriétés → Permissions → Autoriser écriture<br>";
    echo "</div>";
}
?>