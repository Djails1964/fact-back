<?php
/**
 * ultimate_logout.php
 * Script de déconnexion définitif qui règle 100% des problèmes d'authentification persistante
 */

// Désactiver le cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

// 1. Supprimer tous les cookies sauf PHPSESSID (en premier)
if (isset($_SERVER['HTTP_COOKIE'])) {
    $cookies = explode(';', $_SERVER['HTTP_COOKIE']);
    foreach($cookies as $cookie) {
        $parts = explode('=', $cookie);
        $name = trim($parts[0]);
        if ($name != 'PHPSESSID') {
            setcookie($name, '', time()-1000);
            setcookie($name, '', time()-1000, '/');
            setcookie($name, '', time()-1000, '/fact-back');
        }
    }
}

// 2. Démarrer la session
session_start();

// 3. Journal de débogage
$log_message = "--- DÉCONNEXION ULTIME ---\n";
$log_message .= "Date: " . date('Y-m-d H:i:s') . "\n";
$log_message .= "IP: " . $_SERVER['REMOTE_ADDR'] . "\n";
$log_message .= "Session avant destruction: " . print_r($_SESSION, true) . "\n";
$log_message .= "Cookies: " . print_r($_COOKIE, true) . "\n";
file_put_contents('C:/wamp64/www/fact-back/ultimate_logout.log', $log_message, FILE_APPEND);

// 4. Destruction complète de la session
$_SESSION = array();
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// 5. Cookie spécial pour indiquer la déconnexion 
setcookie('LOGGED_OUT', '1', time() + 3600, '/');
setcookie('FORCE_LOGOUT', '1', time() + 3600, '/');

// 6. Forcer la redirection via une page statique HTML
// Cela évite tout traitement PHP qui pourrait réinitialiser la session
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Déconnexion...</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            margin-top: 100px;
            background-color: #f5f5f5;
        }
        .message {
            background-color: white;
            max-width: 500px;
            margin: 0 auto;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #A51C30;
        }
        .loader {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #A51C30; 
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="message">
        <h1>Déconnexion en cours</h1>
        <div class="loader"></div>
        <p>Veuillez patienter, ne fermez pas cette fenêtre...</p>
    </div>
    
    <script>
        // Nettoyer côté client
        function cleanupAndRedirect() {
            try {
                // Effacer le stockage local
                localStorage.clear();
                sessionStorage.clear();
                
                // Supprimer tous les cookies
                document.cookie.split(";").forEach(function(c) {
                    document.cookie = c.replace(/^ +/, "").replace(/=.*/, 
                        "=;expires=" + new Date().toUTCString() + ";path=/");
                });
                
                console.log("Nettoyage terminé, redirection...");
            } catch (e) {
                console.error("Erreur lors du nettoyage:", e);
            }
            
            // Rediriger avec un paramètre anti-cache après un délai
            setTimeout(function() {
                var timestamp = new Date().getTime();
                var randomValue = Math.random().toString(36).substring(2, 15);
                
                // Utiliser l'URL complète pour contourner tout routage interne
                var baseUrl = window.location.origin;
                window.location.replace(baseUrl + "/fact-back/index.php?ultimate_logout=1&r=" + randomValue + "&t=" + timestamp);
                
                // Sécurité supplémentaire: si après 2 secondes nous sommes toujours sur la même page
                setTimeout(function() {
                    if (document.location.href.includes("ultimate_logout.php")) {
                        window.location.href = baseUrl + "/fact-back/";
                    }
                }, 2000);
            }, 1500);
        }
        
        // Exécuter le nettoyage au chargement
        window.onload = cleanupAndRedirect;
    </script>
</body>
</html>