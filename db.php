<?php
$host = 'gateway01.eu-central-1.prod.aws.tidbcloud.com'; // ex: gateway01.eu-central-1.prod.aws.tidbcloud.com
$port = '4000';
$db   = 'test'; 
$user = '2rT9U4KNKfbFsx3.root';
$pass = '3n0fbS42AlIYrrlt';

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
    
    $options = [
        // Correction de la ligne 12 : On utilise bien PDO:: devant chaque constante
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // Option SSL indispensable pour TiDB Cloud
        PDO::MYSQL_ATTR_SSL_CA       => true,
    ];

    $pdo = new PDO($dsn, $user, $pass, $options);

} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}
?>


