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
        // On force le SSL mais on désactive la vérification stricte du certificat
        // car le serveur Docker de Render n'a pas le fichier CA de TiDB
        PDO::MYSQL_ATTR_SSL_CA       => true,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
    ];

    $pdo = new PDO($dsn, $user, $pass, $options);

} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}
?>



