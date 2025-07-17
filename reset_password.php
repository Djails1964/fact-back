<?php
/**
 * Page de réinitialisation de mot de passe
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

// Traiter la demande de réinitialisation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    
    // Validation de base
    if (empty($email)) {
        $error_message = 'Veuillez saisir votre adresse email.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Veuillez saisir une adresse email valide.';
    } else {
        // Inclure les dépendances nécessaires
        require_once 'api/database.php';
        require_once 'EmailService.php';
        require_once 'services/ServiceAuthentification.php';
        
        try {
            // Créer les services
            $emailService = new EmailService();
            $serviceAuth = new ServiceAuthentification($conn, $emailService);
            
            // Demander la réinitialisation
            $result = $serviceAuth->demanderResetPassword($email);
            
            if ($result['success']) {
                $success_message = 'Si cette adresse email est associée à un compte, un lien de réinitialisation vous sera envoyé.';
            } else {
                $error_message = 'Une erreur est survenue. ' . $result['message'];
                error_log("Erreur de réinitialisation pour $email: " . $result['message']);
            }
        } catch (Exception $e) {
            error_log("Erreur lors de la réinitialisation du mot de passe: " . $e->getMessage());
            $error_message = 'Une erreur est survenue. Veuillez réessayer plus tard.';
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
    <title>Centre La Grange - Réinitialisation du mot de passe</title>
    <style>
        /* Garder le même style que dans index.php */
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
        
        .reset-container {
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
        
        input[type="email"] {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--light-accent);
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        input[type="email"]:focus {
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
        <div class="reset-container">
            <div class="logo-container">
                <div class="arc-decoration">
                    <div class="arc"></div>
                </div>
                <h1 class="title">LA GRANGE</h1>
                <p class="subtitle">Réinitialisation du mot de passe</p>
            </div>
            
            <div class="error-message" id="error-message">
                <?php echo $error_message; ?>
            </div>
            
            <div class="success-message" id="success-message">
                <?php echo $success_message; ?>
            </div>
            
            <?php if (!$success_message): ?>
            <form id="reset-form" action="reset_password.php" method="post">
                <div class="form-group">
                    <label for="email">Adresse email</label>
                    <input type="email" id="email" name="email" required autocomplete="email" placeholder="Entrez votre adresse email">
                </div>
                
                <button type="submit" class="btn">Réinitialiser le mot de passe</button>
            </form>
            <?php endif; ?>
            
            <a href="index.php" class="back-link">Retour à la page de connexion</a>
        </div>
    </div>
    
    <footer class="footer">
        <p>&copy; <?php echo date('Y'); ?> Centre La Grange. Tous droits réservés.</p>
    </footer>
</body>
</html>