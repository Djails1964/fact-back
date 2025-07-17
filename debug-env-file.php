<?php
/**
 * debug-env-file.php - Debug du fichier .env
 * À créer dans /volume1/web/DEV-facturation-api/debug-env-file.php
 */

echo "<h2>🔍 Debug Fichier .env</h2>";

// Test 1: Existence du fichier
echo "<h3>1. Existence du fichier</h3>";
$envPath = __DIR__ . '/.env';
echo "Chemin: $envPath<br>";
echo "Existe: " . (file_exists($envPath) ? '✅ OUI' : '❌ NON') . "<br>";

if (!file_exists($envPath)) {
    echo "<div style='color: red; background: #ffe6e6; padding: 10px; border-radius: 5px;'>";
    echo "<strong>❌ PROBLÈME TROUVÉ !</strong><br>";
    echo "Le fichier .env n'existe pas. C'est la cause du problème !<br>";
    echo "Vous devez créer le fichier .env dans : $envPath";
    echo "</div>";
    
    // Créer un fichier .env par défaut
    echo "<h3>2. Création d'un fichier .env par défaut</h3>";
    $defaultEnv = '# Configuration Centre La Grange
APP_ENV=development
APP_NAME="Centre La Grange"
APP_VERSION=1.5.0
APP_URL_FRONT=https://dev-facturation
APP_URL_BACK=https://192.168.1.149/DEV-facturation-api

# URL de l\'API backend
REACT_APP_API_URL=https://192.168.1.149/DEV-facturation-api/api

# Configuration React
REACT_HOST=localhost
REACT_PORT=3007
REACT_BUILD_DIR=build

# CORS
CORS_ORIGIN=https://dev-facturation
CORS_METHODS="GET, POST, PUT, DELETE, OPTIONS"
CORS_HEADERS="Content-Type, Authorization, X-Requested-With"
CORS_CREDENTIALS=true

# Base de données
DB_HOST=localhost
DB_USER=root
DB_PASS=vo62QHsCp6%q833R
DB_NAME=dev_factlagrange

# Configuration de session
SESSION_LIFETIME=1800
TIMEZONE=Europe/Zurich

# Chemins des logs
LOG_DIR=logs';

    $result = file_put_contents($envPath, $defaultEnv);
    if ($result !== false) {
        echo "✅ Fichier .env créé avec succès ($result bytes)<br>";
        echo "<a href='debug-dotenv-step.php' style='background: #007bff; color: white; padding: 10px; text-decoration: none; border-radius: 5px;'>🔄 Retester Dotenv</a>";
    } else {
        echo "❌ Impossible de créer le fichier .env<br>";
    }
    
    exit;
}

// Test 2: Permissions
echo "<h3>2. Permissions</h3>";
echo "Lisible: " . (is_readable($envPath) ? '✅ OUI' : '❌ NON') . "<br>";
echo "Permissions: " . substr(sprintf('%o', fileperms($envPath)), -4) . "<br>";

// Test 3: Taille et contenu
echo "<h3>3. Informations fichier</h3>";
$size = filesize($envPath);
echo "Taille: $size bytes<br>";
echo "Dernière modification: " . date('Y-m-d H:i:s', filemtime($envPath)) . "<br>";

if ($size === 0) {
    echo "<div style='color: red;'>❌ Le fichier .env est vide !</div>";
    exit;
}

// Test 4: Lecture du contenu
echo "<h3>4. Contenu du fichier</h3>";
try {
    $content = file_get_contents($envPath);
    if ($content === false) {
        echo "❌ Impossible de lire le fichier<br>";
        exit;
    }
    
    echo "✅ Fichier lu avec succès<br>";
    echo "Nombre de caractères: " . strlen($content) . "<br>";
    
    // Analyser ligne par ligne
    $lines = explode("\n", $content);
    echo "Nombre de lignes: " . count($lines) . "<br>";
    
} catch (Exception $e) {
    echo "❌ Erreur lecture: " . $e->getMessage() . "<br>";
    exit;
}

// Test 5: Analyse des lignes
echo "<h3>5. Analyse des lignes</h3>";
$validLines = 0;
$emptyLines = 0;
$commentLines = 0;
$problematicLines = [];

foreach ($lines as $lineNum => $line) {
    $originalLine = $line;
    $line = trim($line);
    
    if (empty($line)) {
        $emptyLines++;
    } elseif (strpos($line, '#') === 0) {
        $commentLines++;
    } elseif (strpos($line, '=') !== false) {
        $validLines++;
        
        // Vérifier la syntaxe
        $parts = explode('=', $line, 2);
        $key = trim($parts[0]);
        $value = isset($parts[1]) ? trim($parts[1]) : '';
        
        // Vérifications
        if (empty($key)) {
            $problematicLines[] = ($lineNum + 1) . ": Clé vide - '$originalLine'";
        } elseif (preg_match('/[^A-Z0-9_]/', $key)) {
            $problematicLines[] = ($lineNum + 1) . ": Clé invalide '$key' - '$originalLine'";
        }
        
        echo "Ligne " . ($lineNum + 1) . ": <strong>" . htmlspecialchars($key) . "</strong> = " . htmlspecialchars($value) . "<br>";
    } else {
        $problematicLines[] = ($lineNum + 1) . ": Syntaxe invalide - '$originalLine'";
    }
}

echo "<br>Résumé:<br>";
echo "- Lignes valides: $validLines<br>";
echo "- Lignes vides: $emptyLines<br>";
echo "- Commentaires: $commentLines<br>";
echo "- Problématiques: " . count($problematicLines) . "<br>";

if (!empty($problematicLines)) {
    echo "<div style='color: red; background: #ffe6e6; padding: 10px; border-radius: 5px;'>";
    echo "<strong>❌ LIGNES PROBLÉMATIQUES TROUVÉES :</strong><br>";
    foreach ($problematicLines as $problem) {
        echo "• " . htmlspecialchars($problem) . "<br>";
    }
    echo "</div>";
}

// Test 6: Variables requises
echo "<h3>6. Variables requises</h3>";
$requiredVars = ['APP_ENV', 'CORS_ORIGIN', 'DB_HOST', 'DB_USER', 'DB_PASS', 'DB_NAME', 'SESSION_LIFETIME'];
$foundVars = [];

foreach ($lines as $line) {
    $line = trim($line);
    if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
        $key = trim(explode('=', $line, 2)[0]);
        $value = trim(explode('=', $line, 2)[1] ?? '');
        $foundVars[$key] = $value;
    }
}

$missingVars = [];
foreach ($requiredVars as $var) {
    if (isset($foundVars[$var]) && !empty($foundVars[$var])) {
        echo "✅ $var: " . htmlspecialchars($foundVars[$var]) . "<br>";
    } else {
        echo "❌ $var: MANQUANT<br>";
        $missingVars[] = $var;
    }
}

if (empty($problematicLines) && empty($missingVars)) {
    echo "<div style='color: green; background: #e6ffe6; padding: 10px; border-radius: 5px; margin-top: 15px;'>";
    echo "✅ Le fichier .env semble correct !<br>";
    echo "<a href='debug-dotenv-step.php' style='background: #28a745; color: white; padding: 10px; text-decoration: none; border-radius: 5px;'>🔄 Retester Dotenv</a>";
    echo "</div>";
}
?>