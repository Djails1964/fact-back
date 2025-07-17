<?php
/**
 * login.php
 * 
 * Script de traitement de la connexion
 */

// Inclure les fichiers nécessaires
require_once 'bootstrap.php';
require_once 'api/database.php';
require_once 'services/ServiceAuthentification.php';

// Initialisation de la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirection si déjà connecté
if (isset($_SESSION['user_id'])) {
    header('Location: facturation.php');
    exit;
}

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer les données du formulaire
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    // Validation de base
    $errors = [];
    
    if (empty($username)) {
        $errors[] = 'Le nom d\'utilisateur est requis';
    }
    
    if (empty($password)) {
        $errors[] = 'Le mot de passe est requis';
    }
    
    // Si pas d'erreurs, tenter la connexion
    if (empty($errors)) {
        try {
            // Créer l'instance du service d'authentification
            $serviceAuth = new ServiceAuthentification($conn);
            
            // Tenter l'authentification
            $result = $serviceAuth->authentifier($username, $password);
            
            if ($result['success']) {
                // Connexion réussie - créer la session
                $_SESSION['user_id'] = $result['utilisateur']['id_utilisateur'];
                $_SESSION['username'] = $result['utilisateur']['username'];
                $_SESSION['role'] = $result['utilisateur']['role'];
                $_SESSION['nom_complet'] = trim($result['utilisateur']['prenom'] . ' ' . $result['utilisateur']['nom']);
                $_SESSION['derniere_activite'] = time();
                
                // Redirection vers le tableau de bord
                header('Location: facturation.php');
                exit;
            } else {
                // Échec de la connexion
                $_SESSION['login_error'] = $result['message'];
                header('Location: index.php');
                exit;
            }
        } catch (Exception $e) {
            error_log("Erreur d'authentification: " . $e->getMessage());
            $_SESSION['login_error'] = 'Une erreur est survenue. Veuillez réessayer plus tard.';
            header('Location: index.php');
            exit;
        }
    } else {
        // Des erreurs de validation
        $_SESSION['login_error'] = implode('<br>', $errors);
        header('Location: index.php');
        exit;
    }
} else {
    // Accès direct au script sans soumission de formulaire
    header('Location: index.php');
    exit;
}
?>