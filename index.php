<?php
session_start();
require_once 'db.php';


// On enregistre le passage du prof sur l'accueil
tracerVisite("Accueil - Vitrine");

// Si déjà connecté, redirection automatique vers son espace
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
    <title>PlaningOptima | Excellence Académique</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
    
    <style>
        /* --- RESET & FOND FIXE --- */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        
        :root {
            --bg-dark: #020617; 
            --primary: #4361ee;
            --purple: #7209b7;
            --text-muted: #94a3b8;
        }

        body { 
            background: linear-gradient(to bottom, rgba(2, 6, 23, 0.75), rgba(2, 6, 23, 0.85)), 
                        url('https://images.unsplash.com/photo-1541339907198-e08756dedf3f?ixlib=rb-4.0.3&auto=format&fit=crop&w=1600&q=80');
            background-size: cover; background-position: center; background-attachment: fixed;
            color: white; overflow-x: hidden; scroll-behavior: smooth;
        }

        /* --- NAVIGATION --- */
        nav {
            position: fixed; top: 0; width: 100%; padding: 25px 50px;
            display: flex; justify-content: space-between; align-items: center;
            z-index: 1000; background: rgba(2, 6, 23, 0.3); backdrop-filter: blur(15px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        .logo { font-size: 1.6rem; font-weight: 800; }
        .logo span { color: var(--primary); }
        
        .btn-gradient {
            background: linear-gradient(90deg, var(--primary), var(--purple));
            color: white; padding: 12px 35px; border-radius: 50px;
            text-decoration: none; font-weight: 600; transition: 0.4s;
            display: inline-block;
        }
        .btn-gradient:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(67, 97, 238, 0.3); }

        /* --- HERO --- */
        .hero {
            height: 100vh; display: flex; flex-direction: column; 
            justify-content: center; align-items: center; text-align: center;
        }
        .hero h1 { font-size: 4.5rem; font-weight: 800; margin-bottom: 20px; }

        /* --- LAYOUT ZIG-ZAG --- */
        .zig-zag-container { max-width: 1200px; margin: 0 auto; padding: 50px 20px 50px; }
        .row-item {
            display: flex; align-items: center; gap: 60px; margin-bottom: 80px;
            background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(20px);
            padding: 50px; border-radius: 40px; border: 1px solid rgba(255, 255, 255, 0.08);
            transition: 0.5s ease;
        }
        .row-item.reverse { flex-direction: row-reverse; }
        .row-text h2 { 
            font-size: 4rem; font-weight: 800; margin-bottom: 10px;
            background: linear-gradient(90deg, #fff, var(--primary));
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }
        .row-text h3 { font-size: 1.8rem; margin-bottom: 20px; }
        .row-text p { color: var(--text-muted); line-height: 1.7; font-size: 1.1rem; }
        .row-img img {
            width: 100%; height: 380px; object-fit: cover; border-radius: 30px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.1);
        }

        /* --- SECTION CTA --- */
        .cta-section {
            padding: 100px 20px; text-align: center;
            background: linear-gradient(to bottom, transparent, rgba(67, 97, 238, 0.05));
        }
        .cta-section h2 { font-size: 2.5rem; margin-bottom: 30px; font-weight: 800; }

        /* --- FOOTER --- */
        footer {
            padding: 80px 50px 40px; background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(20px); border-top: 1px solid rgba(255,255,255,0.05);
        }
        .footer-grid {
            display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 50px;
            max-width: 1200px; margin: 0 auto 50px; text-align: left;
        }
        .footer-col h4 { margin-bottom: 20px; color: white; }
        .footer-col ul { list-style: none; }
        .footer-col ul li { margin-bottom: 12px; }
        .footer-col ul li a { color: var(--text-muted); text-decoration: none; transition: 0.3s; }
        .footer-col ul li a:hover { color: var(--primary); }
        .copyright { border-top: 1px solid rgba(255,255,255,0.05); padding-top: 30px; text-align: center; color: #475569; font-size: 0.9rem; }
    </style>
</head>
<body>

    <nav>
        <div class="logo">Planing<span>Optima</span></div>
        <a href="login.php" class="btn-gradient">Espace Membre</a>
    </nav>

    <header class="hero">
        <h1>L'excellence <br> académique.</h1>
        <p style="color: #cbd5e1; font-size: 1.3rem;">Une puissance algorithmique au service de votre faculté.</p>
        <a href="#start" class="btn-gradient" style="margin-top: 20px;">Découvrir</a>
    </header>

    <main class="zig-zag-container" id="start">
        <div class="row-item">
            <div class="row-text">
                <h2>130K</h2>
                <h3>Inscriptions traitées</h3>
                <p>Analyse de données massive en temps réel pour une gestion fluide des effectifs universitaires.</p>
            </div>
            <div class="row-img">
                <img src="https://images.pexels.com/photos/1438072/pexels-photo-1438072.jpeg" alt="Étudiants">
            </div>
        </div>

        <div class="row-item reverse">
            <div class="row-text">
                <h2>42s</h2>
                <h3>Vitesse d'Optimisation</h3>
                <p>Réduisez drastiquement le temps de planification grâce à notre moteur heuristique.</p>
            </div>
            <div class="row-img">
                <img src="https://images.pexels.com/photos/1198264/pexels-photo-1198264.jpeg?auto=compress&cs=tinysrgb&w=800" alt="Vitesse">
            </div>
        </div>

        <div class="row-item">
            <div class="row-text">
                <h2>0</h2>
                <h3>Conflits de Planning</h3>
                <p>Une fiabilité mathématique garantissant zéro chevauchement de salle ou de surveillant.</p>
            </div>
            <div class="row-img">
                <img src="https://images.pexels.com/photos/3243/pen-calendar-to-do-checklist.jpg?auto=compress&cs=tinysrgb&w=800" alt="Checklist">
            </div>
        </div>
    </main>

    <section class="cta-section">
        <h2>Prêt à optimiser vos sessions ?</h2>
        <a href="login.php" class="btn-gradient" style="font-size: 1.2rem; padding: 20px 50px;">Démarrer maintenant</a>
    </section>

    <footer>
        <div class="footer-grid">
            <div class="footer-col">
                <div class="logo" style="margin-bottom: 20px;">Planing<span>Optima</span></div>
                <p style="color: var(--text-muted); line-height: 1.6;">La plateforme de référence pour l'optimisation académique.</p>
            </div>
            <div class="footer-col">
                <h4>Navigation</h4>
                <ul>
                    <li><a href="index.php">Accueil</a></li>
                    <li><a href="login.php">Connexion</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Support</h4>
                <ul>
                    <li><a href="#">Aide</a></li>
                    <li><a href="#">Contact</a></li>
                </ul>
            </div>
        </div>
        <div class="copyright">&copy; 2026 PlaningOptima - Tous droits réservés.</div>
    </footer>

</body>
</html>
