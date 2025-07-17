<?php
/**
 * Page de connexion à l'application avec nettoyage de session supplémentaire
 */

// Commencer par détruire toute session existante si on vient d'une déconnexion
if (isset($_GET['logged_out']) || isset($_COOKIE['LOGGED_OUT'])) {
    // Démarrer la session si elle n'est pas déjà démarrée
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Vider complètement la session
    $_SESSION = array();
    
    // Détruire le cookie de session
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Détruire la session
    session_destroy();
    
    // Détruire le cookie de déconnexion
    setcookie('LOGGED_OUT', '', time() - 3600, '/');
    
    // Redémarrer une nouvelle session propre
    session_start();
    
    // Ajouter un message de confirmation
    $_SESSION['login_error'] = 'Vous avez été déconnecté avec succès.';
}

// Inclure la configuration
$config = require_once 'bootstrap.php';

// Initialiser la session si pas déjà fait
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Rediriger l'utilisateur s'il est déjà connecté
if (isset($_SESSION['user_id'])) {
    header('Location: facturation.php');
    exit;
}

// Vérifier s'il y a un message d'erreur de connexion
$error_message = '';
if (isset($_SESSION['login_error'])) {
    $error_message = $_SESSION['login_error'];
    unset($_SESSION['login_error']); // Effacer le message après l'avoir récupéré
}

// Vérifier si la session a expiré
$session_expired = isset($_GET['session_expired']) && $_GET['session_expired'] == 1;
if ($session_expired) {
    $error_message = 'Votre session a expiré. Veuillez vous reconnecter.';
}

// Code de débogage - Commentez cette section en production
// Décommenter uniquement pour le débogage
/*
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Vérification des autorisations et chemins
$logPath = 'C:/wamp64/www/fact-back/debug_log.txt';
$tempPath = 'C:/wamp64/tmp';
$logsPath = 'C:/wamp64/logs';

echo "<pre>";
echo "Script exécuté depuis : " . __FILE__ . "\n";
echo "Script exécuté par : " . get_current_user() . "\n";

echo "\nVérification des chemins :\n";
echo "Log path ($logPath) : " . (file_exists($logPath) ? "Existe" : "N'existe pas") . "\n";
echo "Accessible en écriture : " . (is_writable($logPath) ? "Oui" : "Non") . "\n";

echo "\nDossier temp ($tempPath) : " . (file_exists($tempPath) ? "Existe" : "N'existe pas") . "\n";
echo "Temp path accessible en écriture : " . (is_writable($tempPath) ? "Oui" : "Non") . "\n";

echo "\nDossier logs ($logsPath) : " . (file_exists($logsPath) ? "Existe" : "N'existe pas") . "\n";
echo "Logs path accessible en écriture : " . (is_writable($logsPath) ? "Oui" : "Non") . "\n";

try {
    // Tentative d'écriture forcée
    $forcedLogPath = 'C:/wamp64/www/fact-back/forced_debug_log.txt';
    file_put_contents($forcedLogPath, 'Test log ' . date('Y-m-d H:i:s'));
    echo "\nÉcriture forcée réussie dans $forcedLogPath\n";
} catch (Exception $e) {
    echo "\nErreur d'écriture : " . $e->getMessage() . "\n";
}

echo "</pre>";
// Ne pas mettre exit ici
*/
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Centre La Grange - Connexion</title>
    <style>
        :root {
            --primary-color: #A51C30; /* Bordeaux/rouge du logo */
            --secondary-color: #F5F5F5; /* Fond légèrement grisé */
            --text-color: #333333;
            --light-accent: #E6E6E6;
            --box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --border-radius: 8px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: var(--secondary-color);
            color: var(--text-color);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        
        .container {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 2rem;
        }
        
        .login-container {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            width: 100%;
            max-width: 400px;
            padding: 2rem;
        }
        
        .logo-container {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .logo {
            max-width: 200px;
            margin-bottom: 1rem;
        }
        
        .title {
            color: var(--primary-color);
            margin-bottom: 0.5rem;
            font-weight: 600;
            font-size: 1.5rem;
        }
        
        .subtitle {
            color: var(--text-color);
            margin-bottom: 2rem;
            font-size: 0.9rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-color);
            font-weight: 500;
        }
        
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--light-accent);
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        
        .btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
            cursor: pointer;
            width: 100%;
            transition: background-color 0.3s;
        }
        
        .btn:hover {
            background-color: #8A1828; /* Version plus foncée */
        }
        
        .forgot-password {
            display: block;
            text-align: center;
            margin-top: 1rem;
            color: var(--primary-color);
            text-decoration: none;
            font-size: 0.9rem;
        }
        
        .forgot-password:hover {
            text-decoration: underline;
        }
        
        .footer {
            text-align: center;
            padding: 1.5rem;
            background-color: white;
            color: var(--text-color);
            font-size: 0.8rem;
        }
        
        .error-message {
            color: var(--primary-color);
            background-color: rgba(165, 28, 48, 0.1);
            padding: 0.75rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            display: <?php echo $error_message ? 'block' : 'none'; ?>;
        }
        
        /* Style pour l'arc décoratif inspiré du logo */
        .arc-decoration {
            position: relative;
            height: 60px;
            overflow: hidden;
            margin-bottom: 1rem;
        }
        
        .arc {
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 150px;
            height: 150px;
            border: 3px solid var(--primary-color);
            border-bottom: none;
            border-radius: 50%;
        }
    </style>
    <script>
        // Script pour forcer le navigateur à ne pas mettre en cache cette page
        window.onload = function() {
            // Vérifier si nous venons d'une déconnexion
            if (window.location.href.includes('logged_out') || 
                document.cookie.includes('LOGGED_OUT')) {
                console.log('Déconnexion détectée, nettoyage des données...');
                
                // Effacer les données locales potentiellement stockées
                try {
                    localStorage.clear();
                    sessionStorage.clear();
                    console.log('Stockages locaux nettoyés');
                } catch (e) {
                    console.error('Erreur lors du nettoyage du stockage:', e);
                }
            }
        };
    </script>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="logo-container">
                <div class="arc-decoration">
                    <div class="arc"></div>
                </div>
                <h1 class="title">LA GRANGE</h1>
                <p class="subtitle">Système de gestion du centre</p>
            </div>
            
            <div class="error-message" id="error-message">
                <?php echo $error_message; ?>
            </div>
            
            <form id="login-form" action="login.php" method="post">
                <div class="form-group">
                    <label for="username">Nom d'utilisateur</label>
                    <input type="text" id="username" name="username" required autocomplete="username">
                </div>
                
                <div class="form-group">
                    <label for="password">Mot de passe</label>
                    <input type="password" id="password" name="password" required autocomplete="current-password">
                </div>
                
                <button type="submit" class="btn">Se connecter</button>
                <?php if (env('ENABLE_PASSWORD_RESET', false)): ?>
                <a href="reset_password.php" class="forgot-password">Mot de passe oublié ?</a>
                <?php endif; ?>
            </form>
        </div>
    </div>
    
    <footer class="footer">
        <p>&copy; <?php echo date('Y'); ?> Centre La Grange. Tous droits réservés.</p>
    </footer>
</body>
</html>