<?php
// Fichier : db.php

// 1. "MySQL Hostname" dans InfinityFree
$host = 'gateway01.eu-central-1.prod.aws.tidbcloud.com'; 

// 2. "MySQL Database Name" (Attention au préfixe epiz_... !)
$dbname = 'test';    

// 3. "MySQL Username"
$username = '2rT9U4KNKfbFsx3.root';          

// 4. "vPanel Password" (Clique sur "Show" pour le voir)
$password = '3n0fbS42AlIYrrlt';    

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // echo "Connexion réussie !"; // Tu pourras décommenter ça pour tester
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}
?>