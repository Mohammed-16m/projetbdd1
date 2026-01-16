<?php
session_start();
require_once 'db.php';
require_once 'traceur.php'; // Important pour enregistrer le résultat de la connexion

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

        // TRACEUR : Enregistrer la connexion RÉUSSIE
        tracerVisite("Connexion RÉUSSIE : Compte " . $user['role'] . " (" . $u . ")");

        // Redirection selon le rôle
        switch($user['role']) {
            case 'admin':
                header("Location: admin.php");
                break;
            case 'chef':
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
        // TRACEUR : Enregistrer l'ÉCHEC (très utile pour voir si le prof se trompe de mot de passe)
        tracerVisite("ÉCHEC Connexion : Tentative avec login '" . htmlspecialchars($u) . "'");

        // Redirection vers login.php (et non .html)
        header("Location: login.php?error=1");
        exit();
    }
} else {
    // Si on accède au fichier sans passer par le formulaire
    header("Location: login.php");
    exit();
}