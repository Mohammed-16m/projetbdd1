<?php
session_start();
require_once 'db.php';

// 1. SÉCURITÉ
$roles_autorises = ['admin', 'doyen'];
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $roles_autorises)) {
    header("Location: login.php");
    exit();
}
$role_actuel = $_SESSION['role'];

// 2. RÉCUPÉRATION DES SPÉCIALITÉS (Pour le filtre)
try {
    $stmt_form = $pdo->query("SELECT id, nom FROM formations ORDER BY nom ASC");
    $liste_formations = $stmt_form->fetchAll();
} catch (PDOException $e) {
    $liste_formations = [];
}

// 3. CONSTRUCTION DE LA REQUÊTE PRINCIPALE AVEC FILTRE
try {
    // Stats globales
    $total_inscrits = $pdo->query("SELECT COUNT(*) FROM etudiants")->fetchColumn();
    $total_examens = $pdo->query("SELECT COUNT(*) FROM examens")->fetchColumn();
    $total_salles = $pdo->query("SELECT COUNT(*) FROM lieu_examen")->fetchColumn();

    // Base de la requête
    $sql = "SELECT e.date_heure, 
                   m.nom as module, 
                   f.nom as specialite, 
                   d.nom as departement,
                   l.nom as salle, 
                   CONCAT(p.nom, ' ', p.prenom) as prof 
            FROM examens e 
            JOIN modules m ON e.module_id = m.id 
            JOIN formations f ON m.formation_id = f.id 
            JOIN departements d ON f.dept_id = d.id 
            JOIN lieu_examen l ON e.salle_id = l.id 
            JOIN professeurs p ON e.prof_id = p.id";

    // Vérification si un filtre est appliqué
    $params = [];
    if (isset($_GET['filtre_formation']) && !empty($_GET['filtre_formation'])) {
        $sql .= " WHERE f.id = ?";
        $params[] = $_GET['filtre_formation'];
    }

    $sql .= " ORDER BY d.nom ASC, f.nom ASC, e.date_heure ASC";

    // Exécution
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $examens = $stmt->fetchAll();

} catch (PDOException $e) {
    die("Erreur BDD : " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Administration - ExamOptima</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .btn-warning { background: #f59e0b; color: white; border: none; }
        .btn-danger { background: #ef4444; color: white; border: none; }
        .header-actions { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .badge-dept { background: #e0e7ff; color: #3730a3; padding: 4px 8px; border-radius: 4px; font-weight: bold; font-size: 0.85em; }
        
        /* Style du select de filtre */
        .filter-select {
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background-color: var(--card-bg);
            color: white;
            font-size: 14px;
            outline: none;
            cursor: pointer;
        }
        .filter-select option {
            background-color: #333;
            color: white;
        }
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
                <h1>Tableau de Bord</h1>
                <p style="color: var(--text-muted);">Session Juin 2026</p>
            </div>

            <div class="header-actions">
                <?php if ($role_actuel === 'admin'): ?>
                    <button class="btn btn-danger" onclick="confirmerSuppression()">🗑️ Reset</button>
                    <button class="btn btn-warning" onclick="lancerAction('generer_conflits.php', 'Simulation...')">🎲 Aléatoire</button>
                    <button class="btn btn-primary" onclick="lancerAction('generer_edt.php', 'Optimisation...')">⚡ Optimiser</button>
                <?php else: ?>
                    <button class="btn btn-primary" onclick="window.print()">🖨️ Imprimer</button>
                <?php endif; ?>
            </div>
        </div>

        <div id="progress-container" style="display:none; margin-bottom: 20px; background: var(--card-bg); padding: 20px; border-radius: 15px;">
            <p id="statusText" style="margin-bottom:10px; font-weight: bold;">Traitement...</p>
            <div style="background: rgba(255,255,255,0.1); height: 10px; border-radius: 5px;">
                <div id="algo-progress" style="width: 0%; background: var(--primary); height: 100%; transition: 0.3s;"></div>
            </div>
        </div>

        <div class="stats-grid">
            <div class="card"><h3>Étudiants</h3><div class="value"><?php echo number_format($total_inscrits); ?></div></div>
            <div class="card"><h3>Examens</h3><div class="value"><?php echo $total_examens; ?></div></div>
            <div class="card"><h3>Salles</h3><div class="value"><?php echo $total_salles; ?></div></div>
        </div>

        <div class="table-container">
            <div style="padding: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <h3 style="margin:0;">
                    <?php 
                        if(isset($_GET['filtre_formation']) && !empty($_GET['filtre_formation'])) {
                            echo "Planning : Spécialité filtrée";
                        } else {
                            echo "Planning Global";
                        }
                    ?>
                </h3>

                <div style="display:flex; gap: 10px;">
                    <form method="GET" action="admin.php" style="margin:0;">
                        <select name="filtre_formation" class="filter-select" onchange="this.form.submit()">
                            <option value="">-- Toutes les Spécialités --</option>
                            <?php foreach($liste_formations as $f): ?>
                                <option value="<?php echo $f['id']; ?>" <?php if(isset($_GET['filtre_formation']) && $_GET['filtre_formation'] == $f['id']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($f['nom']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>

                    <input type="text" onkeyup="filtrerTableauJS()" placeholder="🔍 Recherche rapide..." style="padding: 8px; border-radius: 8px; border: 1px solid var(--border); background: transparent; color: white;">
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Département</th>
                        <th>Spécialité</th>
                        <th>Module</th>
                        <th>Date & Heure</th>
                        <th>Salle</th>
                        <th>Surveillant</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($examens)): ?>
                        <tr><td colspan="6" style="text-align:center; padding:50px; color: #888;">Aucun examen trouvé pour cette sélection.</td></tr>
                    <?php else: ?>
                        <?php foreach($examens as $ex): ?>
                        <tr>
                            <td><span class="badge-dept"><?php echo htmlspecialchars($ex['departement']); ?></span></td>
                            <td style="color: var(--text-muted);"><?php echo htmlspecialchars($ex['specialite']); ?></td>
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
    function confirmerSuppression() {
        if (confirm("⚠️ Tout effacer ?")) window.location.href = 'supprimer_edt.php';
    }

    function lancerAction(fichierPhp, message) {
        document.getElementById('progress-container').style.display = 'block';
        document.getElementById('statusText').innerText = message;
        let bar = document.getElementById('algo-progress');
        let width = 0;
        let iv = setInterval(() => {
            if(width >= 90) {
                clearInterval(iv);
                fetch(fichierPhp).then(() => {
                    bar.style.width = "100%";
                    setTimeout(() => window.location.href = 'admin.php', 500);
                });
            } else { width += 5; bar.style.width = width + "%"; }
        }, 100);
    }

    function filtrerTableauJS() {
        let input = document.querySelector('input[type="text"]').value.toUpperCase();
        let rows = document.querySelectorAll('tbody tr');
        rows.forEach(row => {
            row.style.display = row.innerText.toUpperCase().includes(input) ? '' : 'none';
        });
    }
    </script>
</body>
</html>
