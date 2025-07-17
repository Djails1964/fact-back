<?php
/**
 * super-logout.php
 * Script de déconnexion radical qui nettoie tout et force un rechargement complet
 */

// Désactiver le cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

// Initialiser la session
session_start();

// Journaliser l'action
$log_message = "SUPER LOGOUT - " . date('Y-m-d H:i:s') . " - User: " . ($_SESSION['username'] ?? 'unknown');
error_log($log_message);

// Détruire toutes les variables de session
$_SESSION = array();

// Détruire tous les cookies de session et autres
if (isset($_SERVER['HTTP_COOKIE'])) {
    $cookies = explode(';', $_SERVER['HTTP_COOKIE']);
    foreach($cookies as $cookie) {
        $parts = explode('=', $cookie);
        $name = trim($parts[0]);
        setcookie($name, '', time()-1000);
        setcookie($name, '', time()-1000, '/');
    }
}

// Déconnecter explicitement
session_destroy();

// Générer un token anti-cache
$random_token = md5(uniqid(rand(), true));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Déconnexion</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background-color: #f5f5f5;
        }
        .logout-box {
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 400px;
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
    <div class="logout-box">
        <h1>Déconnexion en cours</h1>
        <div class="loader"></div>
        <p>Veuillez patienter, vous allez être redirigé...</p>
    </div>

    <script>
        // Nettoyage radical du côté client
        function radicalCleanup() {
            // Supprimer tous les cookies
            document.cookie.split(";").forEach(function(c) {
                document.cookie = c.replace(/^ +/, "").replace(/=.*/, 
                    "=;expires=" + new Date().toUTCString() + ";path=/");
            });
            
            // Nettoyer le stockage local
            try {
                localStorage.clear();
                sessionStorage.clear();
                console.log("Stockages locaux nettoyés");
            } catch (e) {
                console.error("Erreur lors du nettoyage du stockage:", e);
            }
            
            // Attendre un court instant
            setTimeout(function() {
                // Rediriger avec un paramètre anti-cache vers la page d'accueil
                var timestamp = new Date().getTime();
                var randomToken = "<?php echo $random_token; ?>";
                
                // Forcer un rechargement complet de la page avec window.location.replace
                // (qui remplace l'entrée courante dans l'historique)
                window.location.replace("index.php?logout=1&r=" + randomToken + "&t=" + timestamp);
                
                // Au cas où la redirection échoue, recharger la page après 2 secondes
                setTimeout(function() {
                    window.location.href = "/";
                    window.location.reload(true);
                }, 2000);
            }, 1500);
        }
        
        // Exécuter le nettoyage après le chargement de la page
        window.onload = radicalCleanup;
    </script>
</body>
</html>