<?php
ob_start(); // À AJOUTER ICI
session_start();
require_once 'db.php';

if (isset($_POST['username']) && isset($_POST['password'])) {
    $u = $_POST['username'];
    $p = $_POST['password'];

    // Préparation de la requête pour éviter les injections SQL
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$u]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Vérification de l'utilisateur et du mot de passe
    if ($user && $p === $user['password']) {
        // Initialisation de la session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];

        // Redirection selon le rôle
        // Note : J'ai ajouté 'chef_dep' car c'est ce qu'on a mis dans TiDB
        switch($user['role']) {
            case 'admin':
                header("Location: admin.php");
                break;
            case 'chef':
            case 'chef_dep': // Ajout de cette sécurité
                header("Location: chef_dept.php");
                break;
            case 'doyen':
                header("Location: doyen.php"); 
                break;
            case 'professeur':
                header("Location: professeur.php");
                break;
            case 'etudiant':
                header("Location: etudiant.php");
                break;
            default:
                header("Location: index.php");
        }
        exit();
    } else {
        // Suppression de la ligne tracerVisite(...) qui causait l'erreur
        
        // Redirection vers login.php avec message d'erreur
        header("Location: login.php?error=1");
        exit();
    }
} else {
    header("Location: login.php");
    exit();
}
