<?php
$host = 'gateway01.eu-central-1.prod.aws.tidbcloud.com'; // ex: gateway01.eu-central-1.prod.aws.tidbcloud.com
$port = '4000';
$db   = 'test'; 
$user = '2rT9U4KNKfbFsx3.root';
$pass = '3n0fbS42AlIYrrlt';

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
    
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ATTR_ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // C'EST CETTE LIGNE QUI CORRIGE L'ERREUR 1105 :
        PDO::MYSQL_ATTR_SSL_CA       => true, 
        // Sur certains serveurs, si 'true' ne marche pas, on peut mettre :
         //PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
    ];

    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    // En cas d'erreur, on l'affiche proprement pour déboguer
    die("Erreur de connexion : " . $e->getMessage());
}
?>

