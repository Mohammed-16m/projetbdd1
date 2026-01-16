<?php
session_start();
require_once 'db.php';
require_once 'traceur.php';

// 1. SÉCURITÉ : Admin et Doyen autorisés
$roles_autorises = ['admin', 'doyen'];
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $roles_autorises)) {
    header("Location: login.php"); // Changé en .php
    exit();
}

$role_actuel = $_SESSION['role'];

// 2. TRACAGE : On identifie qui regarde quoi
$titre_trace = ($role_actuel === 'doyen') ? "Vue Doyen" : "Vue Admin";
tracerVisite($titre_trace);

// 3. RÉCUPÉRATION DES DONNÉES
try {
    $total_inscrits = $pdo->query("SELECT COUNT(*) FROM etudiants")->fetchColumn();
    $total_examens = $pdo->query("SELECT COUNT(*) FROM examens")->fetchColumn();
    $total_salles = $pdo->query("SELECT COUNT(*) FROM lieu_examen")->fetchColumn();

    $query = "SELECT e.date_heure, m.nom as module, l.nom as salle, p.nom_affichage as prof 
              FROM examens e 
              JOIN modules m ON e.module_id = m.id 
              JOIN lieu_examen l ON e.salle_id = l.id 
              JOIN professeurs p ON e.prof_id = p.id 
              ORDER BY e.date_heure ASC";
    $examens = $pdo->query($query)->fetchAll();
} catch (PDOException $e) {
    die("Erreur BDD : " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?php echo ($role_actuel === 'doyen') ? "Planning Global" : "Administration"; ?> - ExamOptima</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* Style pour différencier les deux boutons de génération */
        .btn-warning { background: #f59e0b; color: white; border: none; }
        .btn-warning:hover { background: #d97706; transform: scale(1.03); }
        .header-actions { display: flex; gap: 10px; align-items: center; }
    </style>
</head>
<body>

    <div class="sidebar">
        <h2>ExamOptima</h2>
        <?php if ($role_actuel === 'admin'): ?>
            <a href="admin.php" class="active">🏠 Dashboard</a>
            <a href="lieux.php">🏛️ Salles et Amphis</a>
            <a href="conflits.php">⚠️ Analyse Conflits</a>
        <?php else: ?>
            <a href="doyen.php">📊 Statistiques Globales</a>
            <a href="admin.php" class="active">📅 Voir Planning</a>
        <?php endif; ?>
        <a href="logout.php" class="logout">Déconnexion</a>
    </div>

    <div class="main-content">
        
        <div class="header">
            <div>
                <h1><?php echo ($role_actuel === 'admin') ? "Tableau de Bord Administrateur" : "Planning Global des Examens"; ?></h1>
                <p style="color: var(--text-muted);">Session d'examens Juin 2026</p>
            </div>

            <div class="header-actions">
                <?php if ($role_actuel === 'admin'): ?>
                    <button class="btn btn-warning" onclick="lancerAction('generer_conflits.php', 'Création d\'un planning brut...')">
                        🎲 Générer Aléatoire
                    </button>
                    <button class="btn btn-primary" onclick="lancerAction('generer_edt.php', 'Optimisation des contraintes...')">
                        ⚡ Lancer l'Optimisation
                    </button>
                <?php else: ?>
                    <button class="btn btn-primary" onclick="window.print()">🖨️ Imprimer</button>
                <?php endif; ?>
            </div>
        </div>

        <div id="progress-container" style="display:none; margin-bottom: 30px; background: var(--card-bg); padding: 20px; border-radius: 15px;">
            <p id="statusText" style="margin-bottom:10px; font-weight: bold;">Initialisation...</p>
            <div class="progress-container" style="background: rgba(255,255,255,0.1); height: 12px; border-radius: 6px; overflow: hidden;">
                <div id="algo-progress" style="width: 0%; background: var(--primary); height: 100%; transition: 0.3s;"></div>
            </div>
        </div>

        <div class="stats-grid">
            <div class="card">
                <h3>Étudiants</h3>
                <div class="value"><?php echo number_format($total_inscrits); ?></div>
            </div>
            <div class="card">
                <h3>Examens</h3>
                <div class="value"><?php echo $total_examens; ?></div>
            </div>
            <div class="card">
                <h3>Salles</h3>
                <div class="value"><?php echo $total_salles; ?></div>
            </div>
        </div>

        <div class="table-container">
            <div style="padding: 20px; display: flex; justify-content: space-between; align-items: center;">
                <h3>Planning Actuel</h3>
                <input type="text" onkeyup="filtrerTableau()" placeholder="Rechercher..." style="padding: 8px; border-radius: 8px; border: 1px solid var(--border); background: transparent; color: white;">
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Module</th>
                        <th>Date & Heure</th>
                        <th>Salle</th>
                        <th>Surveillant</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($examens)): ?>
                        <tr><td colspan="4" style="text-align:center; padding:50px;">Aucune donnée. Cliquez sur un bouton de génération.</td></tr>
                    <?php else: ?>
                        <?php foreach($examens as $ex): ?>
                        <tr>
                            <td><b><?php echo htmlspecialchars($ex['module']); ?></b></td>
                            <td><?php echo date('d/m H:i', strtotime($ex['date_heure'])); ?></td>
                            <td><span class="badge badge-success"><?php echo htmlspecialchars($ex['salle']); ?></span></td>
                            <td>Dr. <?php echo htmlspecialchars($ex['prof']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
    // Fonction unifiée pour gérer les deux types de générations
    function lancerAction(fichierPhp, messageInitial) {
        const container = document.getElementById('progress-container');
        const bar = document.getElementById('algo-progress');
        const status = document.getElementById('statusText');
        
        container.style.display = 'block';
        status.innerText = messageInitial;
        
        let progress = 0;
        const interval = setInterval(() => {
            progress += 5;
            bar.style.width = progress + "%";
            
            if (progress >= 90) {
                clearInterval(interval);
                fetch(fichierPhp)
                    .then(() => {
                        bar.style.width = "100%";
                        status.innerText = "Terminé ! Rechargement...";
                        setTimeout(() => window.location.reload(), 800);
                    });
            }
        }, 80);
    }

    function filtrerTableau() {
        let input = document.querySelector('input').value.toUpperCase();
        let rows = document.querySelectorAll('tbody tr');
        rows.forEach(row => {
            row.style.display = row.innerText.toUpperCase().includes(input) ? '' : 'none';
        });
    }
    </script>
</body>
</html>