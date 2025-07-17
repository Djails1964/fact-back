<?php
/**
 * session_diagnostic.php
 * Script pour diagnostiquer et gérer l'état de la session
 */

// Désactiver le cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// Démarrer la session
session_start();

// Fonction pour afficher les informations de session de manière lisible
function displaySessionInfo() {
    echo "<h3>Informations de session:</h3>";
    echo "<pre>";
    echo "Session ID: " . session_id() . "\n";
    echo "Session variables: \n";
    print_r($_SESSION);
    echo "</pre>";
    
    echo "<h3>Cookies:</h3>";
    echo "<pre>";
    print_r($_COOKIE);
    echo "</pre>";
    
    echo "<h3>Paramètres de Session PHP:</h3>";
    echo "<pre>";
    echo "session.cookie_lifetime: " . ini_get("session.cookie_lifetime") . "\n";
    echo "session.use_cookies: " . ini_get("session.use_cookies") . "\n";
    echo "session.cookie_path: " . ini_get("session.cookie_path") . "\n";
    echo "session.cookie_domain: " . ini_get("session.cookie_domain") . "\n";
    echo "session.cookie_secure: " . ini_get("session.cookie_secure") . "\n";
    echo "session.cookie_httponly: " . ini_get("session.cookie_httponly") . "\n";
    echo "session.name: " . ini_get("session.name") . "\n";
    echo "</pre>";
}

// Traiter les actions de test
$action = isset($_GET['action']) ? $_GET['action'] : '';
$message = '';

switch ($action) {
    case 'destroy':
        // Détruire la session
        $oldSessionId = session_id();
        $_SESSION = array();
        
        // Détruire le cookie de session
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, 
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
        
        // Démarrer une nouvelle session pour afficher les informations
        session_start();
        $newSessionId = session_id();
        
        $message = "Session détruite! Ancien ID: $oldSessionId, Nouvel ID: $newSessionId";
        break;
        
    case 'create':
        // Créer des données de test dans la session
        $_SESSION['user_id'] = 999;
        $_SESSION['username'] = 'test_user';
        $_SESSION['role'] = 'tester';
        $_SESSION['derniere_activite'] = time();
        
        $message = "Données de test créées dans la session.";
        break;
        
    case 'check':
        // Ne rien faire, juste vérifier l'état actuel
        $message = "Vérification de l'état de la session.";
        break;
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnostic de Session</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        h1, h2, h3 {
            color: #A51C30;
        }
        pre {
            background-color: #f5f5f5;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
        }
        .message {
            background-color: #e8f5e9;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .action-buttons a {
            display: inline-block;
            background-color: #A51C30;
            color: white;
            padding: 10px 15px;
            text-decoration: none;
            border-radius: 5px;
        }
        .navigation {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }
    </style>
</head>
<body>
    <h1>Diagnostic de Session</h1>
    
    <?php if (!empty($message)): ?>
    <div class="message">
        <?php echo $message; ?>
    </div>
    <?php endif; ?>
    
    <div class="action-buttons">
        <a href="?action=check">Vérifier la session</a>
        <a href="?action=create">Créer des données de test</a>
        <a href="?action=destroy">Détruire la session</a>
        <a href="session_diagnostic.php">Rafraîchir</a>
    </div>
    
    <h2>État actuel de la session</h2>
    <?php displaySessionInfo(); ?>
    
    <div class="navigation">
        <h3>Navigation</h3>
        <ul>
            <li><a href="index.php">Page de login</a></li>
            <li><a href="facturation.php">Page de facturation</a></li>
            <li><a href="logout.php">Déconnexion</a></li>
        </ul>
        
        <h3>Tests JavaScript</h3>
        <button onclick="clearAllCookies()">Supprimer tous les cookies via JavaScript</button>
        <button onclick="redirectToLogin()">Rediriger vers la page de login</button>
    </div>
    
    <script>
        // Fonction pour supprimer tous les cookies
        function clearAllCookies() {
            const cookies = document.cookie.split(";");
            
            for (let i = 0; i < cookies.length; i++) {
                const cookie = cookies[i];
                const eqPos = cookie.indexOf("=");
                const name = eqPos > -1 ? cookie.substr(0, eqPos).trim() : cookie.trim();
                document.cookie = name + "=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/";
            }
            
            alert("Tous les cookies ont été supprimés. La page va être rechargée.");
            location.reload();
        }
        
        // Fonction pour rediriger vers la page de login
        function redirectToLogin() {
            window.location.replace("index.php?nocache=" + new Date().getTime());
        }
    </script>
</body>
</html>