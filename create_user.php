<?php
// Script pour créer un nouvel utilisateur
require_once 'bootstrap.php';
require_once 'api/database.php';
require_once 'services/ServiceAuthentification.php';

// Vérifier si le script est exécuté
echo "<h1>Création d'un nouvel utilisateur</h1>";

// Données de l'utilisateur
$userData = [
    'username' => 'test_admin',
    'password' => 'Test@1234!',
    'nom' => 'Test',
    'prenom' => 'Admin',
    'email' => 'test@example.com',
    'role' => 'admin' // ou 'user' selon vos besoins
];

try {
    // Créer le service d'authentification
    $authService = new ServiceAuthentification($conn);
    
    // Créer l'utilisateur
    $result = $authService->creerUtilisateur($userData);
    
    // Afficher le résultat
    echo "<pre>";
    print_r($result);
    echo "</pre>";
    
    if ($result['success']) {
        echo "<p style='color:green'>Utilisateur créé avec succès !</p>";
    } else {
        echo "<p style='color:red'>Erreur : " . $result['message'] . "</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>Exception : " . $e->getMessage() . "</p>";
}
?>