<?php
error_log('Check Session - Starting session check');

/**
 * check_session.php
 * 
 * Script pour vérifier l'authentification de l'utilisateur et protéger les pages
 * À inclure en haut de chaque page nécessitant une authentification
 */

// Inclure les fichiers nécessaires
require_once 'bootstrap.php';

// Démarrer la session si pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Durée d'inactivité avant déconnexion (en secondes)
$session_timeout = intval(env('SESSION_LIFETIME', 1800)); // 30 minutes par défaut

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    error_log('Check Session - No user_id found. Session details: ' . print_r($_SESSION, true));
    // L'utilisateur n'est pas connecté, rediriger vers la page de connexion
    $_SESSION['login_error'] = 'Veuillez vous connecter pour accéder à cette page.';
    header('Location: index.php');
    exit;
}

// Vérifier si la session a expiré
if (isset($_SESSION['derniere_activite']) && (time() - $_SESSION['derniere_activite'] > $session_timeout)) {
    error_log('Check Session - Session timeout. Last activity: ' . $_SESSION['derniere_activite'] . ', Current time: ' . time());
    // Détruire la session
    session_unset();
    session_destroy();
    
    // Rediriger vers la page de connexion avec un message
    session_start();
    $_SESSION['login_error'] = 'Votre session a expiré. Veuillez vous reconnecter.';
    header('Location: index.php');
    exit;
}

// Mettre à jour la dernière activité
$_SESSION['derniere_activite'] = time();

/**
 * Vérifie les autorisations basées sur le rôle
 * 
 * @param array $roles_autorises Tableau des rôles autorisés
 * @return bool True si l'utilisateur a les autorisations, false sinon
 */
function verifierAutorisation($roles_autorises = []) {
    // Si aucun rôle n'est spécifié, autoriser tous les utilisateurs connectés
    if (empty($roles_autorises)) {
        return true;
    }
    
    // Vérifier si le rôle de l'utilisateur est dans la liste des rôles autorisés
    if (isset($_SESSION['role']) && in_array($_SESSION['role'], $roles_autorises)) {
        return true;
    }
    
    // Rediriger vers une page d'erreur d'autorisation
    header('Location: access_denied.php');
    exit;
}
?>