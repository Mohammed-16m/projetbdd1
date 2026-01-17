<?php
session_start();
require_once 'db.php';

// Redirection intelligente si déjà connecté
if (isset($_SESSION['role'])) {
    $r = $_SESSION['role'];
    if ($r === 'admin') header("Location: admin.php");
    elseif ($r === 'chef' || $r === 'chef_dep') header("Location: chef_dept.php");
    elseif ($r === 'professeur' || $r === 'prof') header("Location: professeur.php");
    elseif ($r === 'etudiant') header("Location: etudiant.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Connexion | ExamOptima</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* Garde ton style CSS ici */
        body { background: #0f172a; color: white; display: flex; align-items: center; justify-content: center; height: 100vh; font-family: sans-serif; }
        .login-card { background: rgba(255,255,255,0.1); padding: 40px; border-radius: 20px; width: 400px; text-align: center; }
        .error-msg { color: #ff4d4d; background: rgba(255,0,0,0.1); padding: 10px; border-radius: 5px; margin-bottom: 20px; display: none; }
        input { width: 100%; padding: 12px; margin: 10px 0; border-radius: 8px; border: none; }
        button { width: 100%; padding: 12px; background: #4361ee; color: white; border: none; border-radius: 8px; cursor: pointer; }
    </style>
</head>
<body>
    <div class="login-card">
        <h2>Bienvenue</h2>
        <div id="errorBox" class="error-msg">Identifiant ou mot de passe incorrect.</div>
        <form action="auth.php" method="POST">
            <input type="text" name="username" placeholder="Nom d'utilisateur" required>
            <input type="password" name="password" placeholder="Mot de passe" required>
            <button type="submit">Se connecter</button>
        </form>
    </div>
    <script>
        if (new URLSearchParams(window.location.search).has('error')) {
            document.getElementById('errorBox').style.display = 'block';
        }
    </script>
</body>
</html>
