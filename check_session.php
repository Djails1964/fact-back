<?php
/**
 * check_session_forced.php
 * Version forcée qui garantit la déconnexion en cas de problème
 */

// Désactiver tout cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// Supprimer tous les cookies sauf PHPSESSID
if (isset($_SERVER['HTTP_COOKIE'])) {
    $cookies = explode(';', $_SERVER['HTTP_COOKIE']);
    foreach($cookies as $cookie) {
        $parts = explode('=', $cookie);
        $name = trim($parts[0]);
        if ($name != 'PHPSESSID') {
            setcookie($name, '', time()-1000);
            setcookie($name, '', time()-1000, '/');
        }
    }
}

// Démarrer ou reprendre la session
ensure_session_started();

// PROTECTION FORCÉE - Si on vient du script de déconnexion, détruire TOUTES les sessions
if (isset($_GET['logout']) || isset($_GET['super_logout']) || isset($_GET['ultra_logout'])) {
    // Enregistrer dans les logs
    error_log("Forçage de déconnexion via paramètre URL");
    
    // Destruction complète de la session
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    
    // Redémarrer une session vide
    session_start();
    
    // Rediriger vers la page d'accueil avec un paramètre anti-cache
    header("Location: index.php?forced_logout=1&t=" . time());
    exit;
}

// PROTECTION SUPPLÉMENTAIRE - Si user_id existe mais qu'on a demandé une déconnexion cookie
if (isset($_COOKIE['LOGGED_OUT']) && isset($_SESSION['user_id'])) {
    // Enregistrer dans les logs
    error_log("Forçage de déconnexion via cookie LOGGED_OUT");
    
    // Destruction complète de la session
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    
    // Redémarrer une session vide
    session_start();
    
    // Supprimer le cookie LOGGED_OUT
    setcookie('LOGGED_OUT', '', time() - 3600, '/');
    
    // Rediriger vers la page d'accueil avec un paramètre anti-cache
    header("Location: index.php?forced_cookie_logout=1&t=" . time());
    exit;
}

// Durée d'inactivité avant déconnexion (en secondes)
$session_timeout = 1800; // 30 minutes par défaut

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    // L'utilisateur n'est pas connecté, rediriger vers la page de connexion
    $_SESSION['login_error'] = 'Veuillez vous connecter pour accéder à cette page.';
    header('Location: index.php?no_session=1&t=' . time());
    exit;
}

// Vérifier si la session a expiré
if (isset($_SESSION['derniere_activite']) && (time() - $_SESSION['derniere_activite'] > $session_timeout)) {
    // Détruire la session
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    
    // Rediriger vers la page de connexion avec un message
    session_start();
    $_SESSION['login_error'] = 'Votre session a expiré. Veuillez vous reconnecter.';
    header('Location: index.php?session_expired=1&t=' . time());
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