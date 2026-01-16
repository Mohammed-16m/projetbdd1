<?php
    ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db.php';


// On enregistre que le prof est sur la page de connexion
tracerVisite("Page de Connexion");

// Si déjà connecté, on le redirige vers son espace
if (isset($_SESSION['role'])) {
    header("Location: " . $_SESSION['role'] . ".php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion | ExamOptima</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    
    <style>
        /* --- STYLE GLOBAL --- */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        
        :root {
            --bg-dark: #0f172a;
            --primary: #4361ee;
            --purple: #7209b7;
            --danger: #ff4d4d;
        }

        body { 
            background: radial-gradient(circle at top left, var(--bg-dark), #020617);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            overflow: hidden;
        }

        /* --- ARRIÈRE-PLAN DYNAMIQUE --- */
        .bg-glow {
            position: absolute;
            width: 500px;
            height: 500px;
            background: linear-gradient(90deg, var(--primary), var(--purple));
            filter: blur(120px);
            border-radius: 50%;
            opacity: 0.2;
            z-index: -1;
            top: 10%;
            left: 10%;
        }

        /* --- CARTE DE CONNEXION --- */
        .login-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 50px;
            border-radius: 32px;
            width: 100%;
            max-width: 450px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
            text-align: center;
            animation: fadeIn 0.8s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .login-card h2 {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 10px;
        }

        .login-card p {
            color: #94a3b8;
            margin-bottom: 35px;
            font-size: 0.95rem;
        }

        /* Message d'erreur */
        .error-msg {
            color: var(--danger);
            background: rgba(255, 77, 77, 0.1);
            padding: 10px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 0.85rem;
            display: none; 
        }

        /* --- FORMULAIRE --- */
        .form-group {
            text-align: left;
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 8px;
            color: #cbd5e1;
            margin-left: 5px;
        }

        .form-group input {
            width: 100%;
            padding: 15px 20px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 14px;
            color: white;
            font-size: 1rem;
            transition: 0.3s;
            outline: none;
        }

        .form-group input:focus {
            border-color: var(--primary);
            background: rgba(255, 255, 255, 0.1);
            box-shadow: 0 0 15px rgba(67, 97, 238, 0.2);
        }

        /* --- BOUTON --- */
        .btn-login {
            width: 100%;
            padding: 16px;
            background: linear-gradient(90deg, var(--primary), var(--purple));
            border: none;
            border-radius: 14px;
            color: white;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: 0.3s;
            margin-top: 10px;
            box-shadow: 0 10px 20px rgba(67, 97, 238, 0.3);
        }

        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(67, 97, 238, 0.5);
            filter: brightness(1.1);
        }

        .extra-links {
            margin-top: 25px;
            font-size: 0.85rem;
            color: #64748b;
        }

        .extra-links a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            transition: 0.3s;
        }

        .extra-links a:hover { color: white; }

        .back-home {
            position: absolute;
            top: 40px;
            left: 40px;
            color: #94a3b8;
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: 0.3s;
        }

        .back-home:hover { color: white; }
    </style>
</head>
<body>

    <div class="bg-glow"></div>

    <a href="index.php" class="back-home"> ← Retour à l'accueil</a>

    <div class="login-card">
        <div style="margin-bottom: 20px;">
            <span style="font-size: 2.5rem;">🔒</span>
        </div>
        <h2>Bienvenue</h2>
        <p>Connectez-vous pour accéder à ExamOptima</p>

        <div id="errorBox" class="error-msg">Identifiant ou mot de passe incorrect.</div>

        <form action="auth.php" method="POST">
            <div class="form-group">
                <label>Nom d'utilisateur</label>
                <input type="text" name="username" placeholder="Entrez votre identifiant" required>
            </div>

            <div class="form-group">
                <label>Mot de passe</label>
                <input type="password" name="password" placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn-login">Se connecter</button>
        </form>

        <div class="extra-links">
            <p>Mot de passe oublié ? <a href="#">Réinitialiser</a></p>
            <p style="margin-top: 10px;">Pas encore de compte ? <a href="#">Contacter l'administrateur</a></p>
        </div>
    </div>

    <script>
        // Vérification des erreurs de connexion via l'URL
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('error')) {
            document.getElementById('errorBox').style.display = 'block';
        }
    </script>

</body>
</html>
