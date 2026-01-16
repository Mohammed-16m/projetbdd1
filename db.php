<?php
$host = 'gateway01.eu-central-1.prod.aws.tidbcloud.com';
$port = '4000';
$db   = 'test'; 
$user = '2rT9U4KNKfbFsx3.root';
$pass = '3n0fbS42AlIYrrlt';

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
    
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_SSL_CA       => true,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
    ];

    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    // On utilise error_log pour ne pas afficher l'erreur en public mais garder une trace
    error_log($e->getMessage());
    die("Erreur de connexion à la base de données.");
}
// NE PAS METTRE DE ?> 
