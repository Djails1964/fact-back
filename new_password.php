<?php
/**
 * Page de définition d'un nouveau mot de passe
 */

// Inclure la configuration
$config = require_once 'bootstrap.php';

// Vérifier si la fonctionnalité est activée
if (!env('ENABLE_PASSWORD_RESET', false)) {
    header('Location: index.php');
    exit;
}

// Initialiser la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Rediriger l'utilisateur s'il est déjà connecté
if (isset($_SESSION['user_id'])) {
    header('Location: facturation.php');
    exit;
}

// Variable pour stocker les messages
$success_message = '';
$error_message = '';
$token = '';
$validToken = false;
$userId = null;

// Vérifier le token dans l'URL
if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = trim($_GET['token']);
    
    // Inclure la connexion à la base de données
    require_once 'api/database.php';
    require_once 'services/ServiceAuthentification.php';
    
    try {
        // Créer l'instance du service d'authentification
        $serviceAuth = new ServiceAuthentification($conn);
        
        // Vérifier si le token est valide
        $tokenCheck = $serviceAuth->verifierTokenResetPassword($token);
        
        if ($tokenCheck['success']) {
            $validToken = true;
            $userId = $tokenCheck['userId'];
        } else {
            $error_message = 'Ce lien de réinitialisation est invalide ou a expiré. Veuillez refaire une demande.';
        }
    } catch (Exception $e) {
        error_log("Erreur lors de la vérification du token: " . $e->getMessage());
        $error_message = 'Une erreur est survenue. Veuillez réessayer plus tard.';
    }
} else {
    $error_message = 'Aucun token de réinitialisation fourni.';
}

// Traiter la définition du nouveau mot de passe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $password_confirm = isset($_POST['password_confirm']) ? $_POST['password_confirm'] : '';
    
    // Validation
    if (empty($password)) {
        $error_message = 'Veuillez saisir un mot de passe.';
    } elseif (strlen($password) < 8) {
        $error_message = 'Le mot de passe doit contenir au moins 8 caractères.';
    } elseif ($password !== $password_confirm) {
        $error_message = 'Les mots de passe ne correspondent pas.';
    } else {
        try {
            // Réinitialiser le mot de passe avec le token
            $result = $serviceAuth->resetPasswordAvecToken($token, $password);
            
            if ($result['success']) {
                $success_message = 'Votre mot de passe a été mis à jour avec succès. Vous pouvez maintenant vous connecter avec votre nouveau mot de passe.';
                
                // Rediriger après 3 secondes
                header("refresh:3;url=index.php");
            } else {
                $error_message = $result['message'];
            }
        } catch (Exception $e) {
            error_log("Erreur lors de la mise à jour du mot de passe: " . $e->getMessage());
            $error_message = 'Une erreur est survenue lors de la mise à jour du mot de passe. Veuillez réessayer plus tard.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Centre La Grange - Nouveau mot de passe</title>
    <style>
        /* Même style que reset_password.php */
        :root {
            --primary-color: #A51C30;
            --secondary-color: #F5F5F5;
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
        
        .password-container {
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
        
        input[type="password"] {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--light-accent);
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
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
            background-color: #8A1828;
        }
        
        .back-link {
            display: block;
            text-align: center;
            margin-top: 1rem;
            color: var(--primary-color);
            text-decoration: none;
            font-size: 0.9rem;
        }
        
        .back-link:hover {
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
        
        .success-message {
            color: #28a745;
            background-color: rgba(40, 167, 69, 0.1);
            padding: 0.75rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            display: <?php echo $success_message ? 'block' : 'none'; ?>;
        }
        
        .password-requirements {
            font-size: 0.8rem;
            color: #666;
            margin-top: 0.5rem;
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
</head>
<body>
    <div class="container">
        <div class="password-container">
            <div class="logo-container">
                <div class="arc-decoration">
                    <div class="arc"></div>
                </div>
                <h1 class="title">LA GRANGE</h1>
                <p class="subtitle">Définir un nouveau mot de passe</p>
            </div>
            
            <div class="error-message" id="error-message">
                <?php echo $error_message; ?>
            </div>
            
            <div class="success-message" id="success-message">
                <?php echo $success_message; ?>
            </div>
            
            <?php if ($validToken && !$success_message): ?>
            <form id="password-form" action="new_password.php?token=<?php echo htmlspecialchars($token); ?>" method="post">
                <div class="form-group">
                    <label for="password">Nouveau mot de passe</label>
                    <input type="password" id="password" name="password" required autocomplete="new-password">
                    <p class="password-requirements">Le mot de passe doit contenir au moins 8 caractères.</p>
                </div>
                
                <div class="form-group">
                    <label for="password_confirm">Confirmer le mot de passe</label>
                    <input type="password" id="password_confirm" name="password_confirm" required autocomplete="new-password">
                </div>
                
                <button type="submit" class="btn">Mettre à jour le mot de passe</button>
            </form>
            <?php elseif ($success_message): ?>
            <p>Vous allez être redirigé vers la page de connexion...</p>
            <?php endif; ?>
            
            <a href="index.php" class="back-link">Retour à la page de connexion</a>
        </div>
    </div>
    
    <footer class="footer">
        <p>&copy; <?php echo date('Y'); ?> Centre La Grange. Tous droits réservés.</p>
    </footer>
</body>
</html>