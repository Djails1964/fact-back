<?php
/**
 * facturation_blocked.php
 * Remplaçant temporaire de facturation.php qui refuse l'accès
 * et affiche les détails de la session
 */

// Désactiver le cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// Forcer le contenu texte
header("Content-Type: text/html; charset=UTF-8");

// Démarrer la session
session_start();

// Enregistrer les données dans un fichier de log
$log_data = "=== DIAGNOSTIC FACTURATION.PHP ===\n";
$log_data .= "Date: " . date('Y-m-d H:i:s') . "\n";
$log_data .= "IP: " . $_SERVER['REMOTE_ADDR'] . "\n";
$log_data .= "User Agent: " . $_SERVER['HTTP_USER_AGENT'] . "\n";
$log_data .= "Referer: " . (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'None') . "\n";
$log_data .= "SESSION: " . print_r($_SESSION, true) . "\n";
$log_data .= "COOKIES: " . print_r($_COOKIE, true) . "\n";
$log_data .= "GET: " . print_r($_GET, true) . "\n";
$log_data .= "POST: " . print_r($_POST, true) . "\n";
$log_data .= "==============================\n";

// Enregistrer dans un fichier
file_put_contents('C:/wamp64/www/fact-back/facturation_access.log', $log_data, FILE_APPEND);

// Détruire la session active
$_SESSION = array();
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], 
             $params["secure"], $params["httponly"]);
}
session_destroy();

// Afficher un message avec les détails
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Accès bloqué</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        h1 {
            color: #A51C30;
        }
        .info-box {
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        pre {
            background-color: #f0f0f0;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
        }
        .actions {
            margin-top: 20px;
        }
        .button {
            display: inline-block;
            background-color: #A51C30;
            color: white;
            padding: 10px 15px;
            text-decoration: none;
            border-radius: 5px;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <h1>Accès à la facturation bloqué</h1>
    
    <div class="info-box">
        <h2>Diagnostic de session</h2>
        <p>Cette page a remplacé temporairement facturation.php pour diagnostiquer les problèmes de déconnexion.</p>
        <p><strong>Votre session a été détruite.</strong></p>
        
        <h3>Contenu de la session avant destruction:</h3>
        <pre><?php print_r($_SESSION); ?></pre>
        
        <h3>Cookies:</h3>
        <pre><?php print_r($_COOKIE); ?></pre>
    </div>
    
    <div class="actions">
        <a href="index.php" class="button">Page d'accueil</a>
        <a href="session_diagnostic.php" class="button">Outil de diagnostic</a>
        <a href="#" class="button" onclick="clearEverything()">Effacer toutes les données</a>
    </div>
    
    <script>
        // Fonction pour effacer toutes les données du navigateur
        function clearEverything() {
            // Supprimer les cookies
            document.cookie.split(";").forEach(function(c) {
                document.cookie = c.replace(/^ +/, "").replace(/=.*/, 
                    "=;expires=" + new Date().toUTCString() + ";path=/");
            });
            
            // Vider le stockage local
            localStorage.clear();
            sessionStorage.clear();
            
            // Rediriger
            alert("Toutes les données ont été effacées. Vous allez être redirigé vers la page d'accueil.");
            window.location.href = "index.php?purged=1&t=" + Date.now();
        }
    </script>
</body>
</html>