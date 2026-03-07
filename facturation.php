<?php
/**
 * facturation.php
 * 
 * Page principale de l'application de facturation, accessible uniquement après connexion
 */

// Inclure la configuration
$config = require_once 'bootstrap.php';

// Définir le chemin du dossier de logs
$logDir = $config['logs']['dir'];

// Vérifier l'authentification et la session
require_once 'check_session.php';

// Génération d'un token API pour les requêtes AJAX sécurisées
$apiToken = bin2hex(random_bytes(16));
$_SESSION['api_token'] = $apiToken;

// Obtenir les données utilisateur à transmettre à React
$userData = [
    'userId' => $_SESSION['user_id'],
    'username' => $_SESSION['username'],
    'role' => $_SESSION['role'],
    'nomComplet' => $_SESSION['nom_complet'],
    'sessionExpire' => time() + $config['session_lifetime']
];


// Déterminer si on est en mode développement ou production
$isDevelopment = is_dev_mode();

// Configuration dynamique des chemins pour le frontend React
$reactURL = react_url(); // Utilise la fonction du bootstrap

error_log("===== FACTURATION.PHP =====");
error_log("Utilisateur connecté : " . $_SESSION['username']);
error_log("Session ID: " . session_id());
error_log("API Token généré : " . $apiToken);
error_log("Mode développement : " . ($isDevelopment ? 'Oui' : 'Non'));
error_log("URL React : " . $reactURL);
error_log("Données utilisateur : " . json_encode($userData));
error_log("Configuration de l'application : " . json_encode($config)); 
error_log("Chemin de base des assets : " . ($isDevelopment ? $reactURL : 'build'));
error_log("Durée de session : " . $config['session_lifetime'] . " secondes");
error_log("Timestamp de la session : " . time());


// Chemin de base pour les assets selon l'environnement
$assetBasePath = $isDevelopment ? $reactURL : 'build';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="description" content="Système de facturation - Centre La Grange">
    <title><?= htmlspecialchars($config['app_name']) ?> - Facturation</title>
    
    <!-- Meta pour éviter le cache du navigateur -->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    
    <!-- Favicon -->
    <link rel="shortcut icon" href="<?= $assetBasePath ?>/ico_facturation_lagrange_512.png" type="image/x-icon">
    
    <!-- Styles initiaux pour éviter le FOUC (Flash Of Unstyled Content) -->
    <style>
        :root {
          --primary-color: #A51C30;
          --secondary-color: #F5F5F5;
        }
        
        body {
          margin: 0;
          padding: 0;
          font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen,
            Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
          background-color: var(--secondary-color);
        }
        
        #loading-container {
          position: fixed;
          top: 0;
          left: 0;
          right: 0;
          bottom: 0;
          display: flex;
          flex-direction: column;
          justify-content: center;
          align-items: center;
          z-index: 9999;
          background-color: var(--secondary-color);
        }
        
        .loading-spinner {
          width: 50px;
          height: 50px;
          border: 5px solid rgba(0, 0, 0, 0.1);
          border-radius: 50%;
          border-top-color: var(--primary-color);
          animation: spin 1s linear infinite;
          margin-bottom: 20px;
        }
        
        @keyframes spin {
          to { transform: rotate(360deg); }
        }
        
        .loading-text {
          font-size: 16px;
          color: #333;
        }
    </style>
    
    <!-- Configuration pour l'application React -->
    <script>
        // Configuration de l'application
        window.APP_CONFIG = {
            apiEndpoint: '<?= secure_url("api/") ?>',
            appName: '<?= htmlspecialchars($config['app_name']) ?>',
            appVersion: '<?= htmlspecialchars($config['version']) ?>',
            debugMode: <?= $config['debug_mode'] ? 'true' : 'false' ?>,
            sessionTimeout: <?= $config['session_lifetime'] ?>,
            apiToken: '<?= $apiToken ?>',
            reactUrl: '<?= $reactURL ?>'
        };
        
        // Données utilisateur
        window.USER_DATA = <?= json_encode($userData) ?>;

        console.log('Initialisation de facturation.php');
        console.log('Config:', window.APP_CONFIG);
        console.log('User data:', window.USER_DATA);
    </script>
</head>
<body>
    <!-- Conteneur pour l'application React -->
    <div id="root"></div>
    
    <!-- Indicateur de chargement initial -->
    <div id="loading-container">
      <div class="loading-spinner"></div>
      <p class="loading-text">Chargement de l'application...</p>
    </div>
    
    <!-- Message pour les navigateurs sans JavaScript -->
    <noscript>
        <div style="text-align: center; padding: 2rem; color: var(--primary-color);">
            JavaScript est nécessaire pour utiliser cette application. Veuillez activer JavaScript ou utiliser un navigateur moderne.
        </div>
    </noscript>
    
    <?php if ($isDevelopment): ?>
        <!-- En mode développement, les assets sont servis par le serveur CRA -->
        <script src="<?= $reactURL ?>/static/js/bundle.js"></script>
    <?php else: ?>
        <!-- En mode production avec CRA, il y a plusieurs fichiers JS générés -->
        <?php
        // En production, CRA génère des fichiers avec des noms basés sur des hashes
        // Il faut donc scanner le répertoire pour trouver les bonnes références
        // $buildDir = 'build';

  
        // Fonction pour récupérer les fichiers JS et CSS générés par CRA
        // function getCRAAssets($buildDir, $type) {
        //     error_log("Récupération des assets CRA pour le type: $type dans le répertoire: $buildDir");
        //     error_log("Vérification de l'existence du répertoire: " . is_dir($buildDir) ? 'Oui' : 'Non');
        //     error_log("Vérification de l'existence du sous-répertoire: " . ($type === 'js' ? "$buildDir/static/js" : "$buildDir/static/css"));
        //     $pattern = $type === 'js' ? '/static\/js\/([^.]+)\..*\.js$/' : '/static\/css\/([^.]+)\..*\.css$/';
        //     $assets = [];
            
        //     if (is_dir($buildDir)) {
        //         if ($type === 'js') {
        //             $jsDir = "$buildDir/static/js";
        //             if (is_dir($jsDir)) {
        //                 $files = scandir($jsDir);
        //                 foreach ($files as $file) {
        //                     if (preg_match('/^[^.]+\.[^.]+\.js$/', $file)) {
        //                         $assets[] = "static/js/$file";
        //                     }
        //                 }
        //             }
        //         } else {
        //             $cssDir = "$buildDir/static/css";
        //             if (is_dir($cssDir)) {
        //                 $files = scandir($cssDir);
        //                 foreach ($files as $file) {
        //                     if (preg_match('/^[^.]+\.[^.]+\.css$/', $file)) {
        //                         $assets[] = "static/css/$file";
        //                     }
        //                 }
        //             }
        //         }
        //     }
            
        //     return $assets;
        // }
        
        // Récupérer et inclure les CSS
        $cssFiles = get_cra_assets('css');

        // LOGS DE DEBUG - À AJOUTER TEMPORAIREMENT
        error_log("CSS Files trouvés: " . json_encode($cssFiles));
        foreach ($cssFiles as $cssFile) {
            error_log("Chargement CSS: " . $cssFile);
            echo '<link rel="stylesheet" href="' . htmlspecialchars($cssFile) . '">';
        }
        
        // Récupérer et inclure les JS
        $jsFiles = get_cra_assets('js');
        // LOGS DE DEBUG - À AJOUTER TEMPORAIREMENT
        error_log("JS Files trouvés: " . json_encode($jsFiles));
        foreach ($jsFiles as $jsFile) {
            error_log("Chargement JS: " . $jsFile);
            echo '<script src="' . htmlspecialchars($jsFile) . '"></script>';
        }
        ?>
    <?php endif; ?>
    <!-- Script de débogage pour surveiller le chargement -->

</body>
</html>