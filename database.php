<?php

require_once realpath(__DIR__ . '/bootstrap.php');

$servername = env('DB_HOST', 'localhost');
$username = env('DB_USER');
$password = env('DB_PASS');
$dbname = env('DB_NAME');

try {
    // Créer une connexion PDO
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $username, $password);
    
    // Configurer PDO pour qu'il lance des exceptions en cas d'erreurs
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Configurer PDO pour retourner les résultats sous forme de tableau associatif
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    // En cas d'erreur de connexion
    error_log("Erreur de connexion à la base de données: " . $e->getMessage(), 0);
    die("Erreur de connexion à la base de données: " . $e->getMessage());
}
?>