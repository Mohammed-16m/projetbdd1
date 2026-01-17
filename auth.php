<?php
ob_start();
session_start();
require_once 'db.php';

if (isset($_POST['username']) && isset($_POST['password'])) {
    $u = $_POST['username'];
    $p = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$u]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && $p === $user['password']) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role']; // Stocke 'professeur' ou 'etudiant', etc.

        // Redirection vers les FICHIERS réels
        switch($user['role']) {
            case 'admin':
                header("Location: admin.php"); break;
            case 'chef':
            case 'chef_dep':
                header("Location: chef_dept.php"); break;
            case 'professeur':
            case 'prof':
                header("Location: professeur.php"); break;
            case 'etudiant':
                header("Location: etudiant.php"); break;
            default:
                header("Location: index.php");
        }
        exit();
    } else {
        header("Location: login.php?error=1");
        exit();
    }
}
