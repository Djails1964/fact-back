<?php
/**
 * detect_auto_login.php
 * Script pour trouver des mécanismes de connexion automatique ou de persistance de session
 * À exécuter depuis le navigateur pour obtenir un rapport
 */

// Désactiver l'affichage des erreurs pour la production
// error_reporting(0);
// ini_set('display_errors', 0);

// Activer pour le débogage
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Fonction pour rechercher du texte dans des fichiers
function searchInFiles($directory, $searchTerms, $extensions = ['php']) {
    $results = [];
    
    // Vérifier si le répertoire existe
    if (!is_dir($directory)) {
        return ["Erreur: Le répertoire $directory n'existe pas."];
    }
    
    // Parcourir récursivement le répertoire
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
    
    foreach ($iterator as $file) {
        // Ignorer les répertoires
        if ($file->isDir()) {
            continue;
        }
        
        // Vérifier l'extension du fichier
        $extension = pathinfo($file->getPathname(), PATHINFO_EXTENSION);
        if (!in_array(strtolower($extension), $extensions)) {
            continue;
        }
        
        // Lire le contenu du fichier
        $content = file_get_contents($file->getPathname());
        
        // Rechercher les termes dans le contenu
        foreach ($searchTerms as $term) {
            if (stripos($content, $term) !== false) {
                $lineNumber = 1;
                $lines = explode("\n", $content);
                $matchingLines = [];
                
                // Trouver les lignes qui contiennent le terme
                foreach ($lines as $line) {
                    if (stripos($line, $term) !== false) {
                        $matchingLines[$lineNumber] = trim($line);
                    }
                    $lineNumber++;
                }
                
                if (!empty($matchingLines)) {
                    $relativePath = str_replace(realpath($directory) . DIRECTORY_SEPARATOR, '', $file->getPathname());
                    $results[] = [
                        'file' => $relativePath,
                        'term' => $term,
                        'lines' => $matchingLines
                    ];
                }
            }
        }
    }
    
    return $results;
}

// Termes de recherche liés à l'authentification persistante
$searchTerms = [
    'auto login',
    'autologin',
    'remember me',
    'remember_me',
    'persistent',
    'persistence',
    'cookie_lifetime',
    'remember_token',
    'stay logged',
    'keep session',
    'session.cookie_lifetime',
    'set_cookie',
    'setcookie',
    'session_regenerate_id',
    'session.use_cookies',
    'authenticated auto',
    '$_cookie',
    'reconnect',
    'connecter automatiquement',
    'forcer l\'authentification',
    'bypass',
    'jwt',
    'token',
    'refresh_token',
    'savetoken',
    'keep_user'
];

// Chemin racine pour la recherche (adaptez selon votre structure)
$rootPath = __DIR__; // Répertoire courant

// Effectuer la recherche
$results = searchInFiles($rootPath, $searchTerms);

// Fonction pour examiner les valeurs de session et de cookies
function analyzeSession() {
    $info = [];
    
    // Démarrer la session si elle n'est pas déjà démarrée
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Récupérer les informations sur les cookies de session
    $sessionCookieParams = session_get_cookie_params();
    $info['session_cookie_params'] = $sessionCookieParams;
    
    // Paramètres de session PHP
    $sessionSettings = [
        'session.cookie_lifetime' => ini_get('session.cookie_lifetime'),
        'session.gc_maxlifetime' => ini_get('session.gc_maxlifetime'),
        'session.use_cookies' => ini_get('session.use_cookies'),
        'session.use_only_cookies' => ini_get('session.use_only_cookies'),
        'session.cookie_httponly' => ini_get('session.cookie_httponly'),
        'session.cookie_secure' => ini_get('session.cookie_secure'),
        'session.cookie_samesite' => ini_get('session.cookie_samesite'),
        'session.name' => ini_get('session.name')
    ];
    $info['session_settings'] = $sessionSettings;
    
    // Vérifier les cookies persistants
    $persistentCookies = [];
    foreach ($_COOKIE as $name => $value) {
        // Ignorer le cookie de session standard
        if ($name === session_name()) {
            continue;
        }
        
        $persistentCookies[$name] = $value;
    }
    $info['persistent_cookies'] = $persistentCookies;
    
    // Informations sur les variables de session
    $info['session_variables'] = $_SESSION;
    
    return $info;
}

// Analyser la session
$sessionInfo = analyzeSession();

// Vérifier si des fichiers de configuration PHP existent
$configFiles = glob($rootPath . '/*.{php,ini}', GLOB_BRACE);
$configs = [];

foreach ($configFiles as $file) {
    if (preg_match('/(config|settings|setup|\.env|bootstrap|init)/i', basename($file))) {
        $configs[] = basename($file);
    }
}

// Préparation de l'affichage
$title = "Détection de mécanismes d'auto-login";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
            color: #333;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        h1, h2, h3 {
            color: #A51C30;
        }
        
        .section {
            margin-bottom: 30px;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 5px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        th, td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        
        th {
            background-color: #f2f2f2;
        }
        
        .file-path {
            font-weight: bold;
            color: #2c5282;
        }
        
        .search-term {
            color: #A51C30;
            font-weight: bold;
        }
        
        .code {
            font-family: monospace;
            background-color: #f0f0f0;
            padding: 1px 5px;
            border-radius: 3px;
        }
        
        pre {
            background-color: #f0f0f0;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
        }
        
        .danger {
            color: #A51C30;
            font-weight: bold;
        }
        
        .warning {
            color: #c05621;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><?php echo $title; ?></h1>
        
        <div class="section">
            <h2>Résumé</h2>
            <p>Cette page recherche des mécanismes potentiels d'authentification automatique ou persistante qui pourraient empêcher la déconnexion de fonctionner correctement.</p>
            <p>Recherche effectuée dans: <code><?php echo $rootPath; ?></code></p>
            <p>Nombre de correspondances trouvées: <strong><?php echo count($results); ?></strong></p>
        </div>
        
        <?php if (!empty($results)): ?>
        <div class="section">
            <h2>Fichiers contenant des mécanismes d'authentification potentiels</h2>
            <table>
                <thead>
                    <tr>
                        <th>Fichier</th>
                        <th>Terme recherché</th>
                        <th>Ligne(s) correspondante(s)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $result): ?>
                    <tr>
                        <td class="file-path"><?php echo htmlspecialchars($result['file']); ?></td>
                        <td class="search-term"><?php echo htmlspecialchars($result['term']); ?></td>
                        <td>
                            <?php foreach ($result['lines'] as $lineNumber => $line): ?>
                            <div>
                                <span class="code"><?php echo $lineNumber; ?>:</span> 
                                <?php echo htmlspecialchars($line); ?>
                            </div>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="section">
            <h2>Aucun mécanisme d'authentification potentiel trouvé</h2>
            <p>Aucune occurrence des termes recherchés n'a été trouvée dans les fichiers PHP.</p>
        </div>
        <?php endif; ?>
        
        <div class="section">
            <h2>Configuration des cookies et de la session</h2>
            
            <h3>Paramètres de cookie de session</h3>
            <pre><?php print_r($sessionInfo['session_cookie_params']); ?></pre>
            
            <h3>Paramètres de session PHP</h3>
            <pre><?php print_r($sessionInfo['session_settings']); ?></pre>
            
            <?php if (!empty($sessionInfo['persistent_cookies'])): ?>
            <h3 class="warning">Cookies persistants détectés</h3>
            <p>Ces cookies pourraient être utilisés pour maintenir une session active même après déconnexion:</p>
            <pre><?php print_r($sessionInfo['persistent_cookies']); ?></pre>
            <?php else: ?>
            <h3>Aucun cookie persistant détecté</h3>
            <?php endif; ?>
            
            <h3>Variables de session actuelles</h3>
            <pre><?php print_r($sessionInfo['session_variables']); ?></pre>
        </div>
        
        <?php if (!empty($configs)): ?>
        <div class="section">
            <h2>Fichiers de configuration détectés</h2>
            <p>Les fichiers suivants pourraient contenir des paramètres d'authentification:</p>
            <ul>
                <?php foreach ($configs as $config): ?>
                <li><?php echo htmlspecialchars($config); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        
        <div class="section">
            <h2>Tests supplémentaires</h2>
            <p>Voici quelques tests à effectuer pour identifier la source du problème:</p>
            <ol>
                <li>Essayez d'accéder directement à <a href="index.php" target="_blank">index.php</a> et vérifiez si vous êtes automatiquement redirigé vers facturation.php</li>
                <li>Essayez un autre navigateur ou une fenêtre de navigation privée</li>
                <li>Examinez les fichiers listés ci-dessus pour trouver des mécanismes d'authentification persistante</li>
                <li>Vérifiez si votre base de données contient des tokens de session ou d'authentification persistants</li>
            </ol>
        </div>
    </div>
</body>
</html>