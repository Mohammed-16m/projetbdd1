<?php
session_start();
require_once 'db.php';

// Sécurité Doyen
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'doyen') {
    header("Location: login.php"); exit();
}

// 1. Statistiques Globales
$nb_examens = $pdo->query("SELECT COUNT(*) FROM examens")->fetchColumn();
$nb_etudiants = $pdo->query("SELECT COUNT(*) FROM etudiants")->fetchColumn();
$nb_salles = $pdo->query("SELECT COUNT(*) FROM lieu_examen")->fetchColumn();

// 2. Répartition par Département (Requête optimisée)
$statsDept = $pdo->query("SELECT d.nom, COUNT(e.id) as nb 
                          FROM departements d 
                          LEFT JOIN formations f ON f.dept_id = d.id 
                          LEFT JOIN modules m ON m.formation_id = f.id 
                          LEFT JOIN examens e ON e.module_id = m.id 
                          GROUP BY d.id, d.nom")->fetchAll();

// Calcul du maximum pour l'échelle du graphique
$max_exams = 0;
foreach($statsDept as $s) { if($s['nb'] > $max_exams) $max_exams = $s['nb']; }
$max_scale = ($max_exams > 0) ? $max_exams : 1; 
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Espace Doyen - ExamOptima</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* Styles spécifiques pour le graphique de l'espace Doyen */
        .chart-container {
            display: flex;
            align-items: flex-end;
            justify-content: space-around;
            height: 250px;
            padding: 20px;
            background: rgba(255,255,255,0.02);
            border-radius: 12px;
            margin-top: 20px;
        }
        .bar-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            max-width: 80px;
        }
        .bar {
            width: 100%;
            background: linear-gradient(to top, var(--primary), #6366f1);
            border-radius: 6px 6px 0 0;
            transition: height 0.5s ease;
            position: relative;
        }
        .bar:hover { filter: brightness(1.2); cursor: pointer; }
        .bar-value {
            position: absolute;
            top: -25px;
            width: 100%;
            text-align: center;
            font-size: 0.8rem;
            font-weight: bold;
            color: var(--primary);
        }
        .dept-name {
            font-size: 0.75rem;
            color: var(--text-muted);
            text-align: center;
            transform: rotate(-15deg);
            margin-top: 5px;
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>ExamOptima</h2>
        <a href="doyen.php" class="active">📊 Statistiques Globales</a>
        <a href="admin.php">📅 Voir Planning</a>
        <a href="logout.php" class="logout">Déconnexion</a>
    </div>

    <div class="main-content">
        <div class="header">
            <div>
                <h1>Tableau de Bord Stratégique</h1>
                <p style="color: var(--text-muted);">Vue d'ensemble de la session d'examens 2026</p>
            </div>
            <div class="badge" style="background: rgba(99, 102, 241, 0.1); color: #6366f1; border: 1px solid #6366f1;">
                Rapport Décisionnel
            </div>
        </div>

        <div class="stats-grid">
            <div class="card">
                <h3>Total Épreuves</h3>
                <div class="value"><?php echo $nb_examens; ?></div>
                <p style="color: var(--text-muted);">Séances planifiées</p>
            </div>
            <div class="card">
                <h3>Étudiants Concernés</h3>
                <div class="value"><?php echo $nb_etudiants; ?></div>
                <p style="color: var(--text-muted);">Inscriptions actives</p>
            </div>
            <div class="card">
                <h3>Capacité Logistique</h3>
                <div class="value"><?php echo $nb_salles; ?></div>
                <p style="color: var(--text-muted);">Lieux mobilisés</p>
            </div>
        </div>

        <div class="card" style="margin-top: 20px;">
            <h3 style="margin-bottom: 30px;">Volume d'Examens par Département</h3>
            <div class="chart-container">
                <?php foreach($statsDept as $d): 
                    $height = ($d['nb'] / $max_scale) * 200; // Normalisation à 200px
                ?>
                    <div class="bar-wrapper">
                        <div class="bar" style="height: <?php echo $height; ?>px;">
                            <div class="bar-value"><?php echo $d['nb']; ?></div>
                        </div>
                        <span class="dept-name"><?php echo htmlspecialchars($d['nom']); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="table-container" style="margin-top: 20px;">
            <table style="width: 100%;">
                <thead>
                    <tr>
                        <th>Département</th>
                        <th>Charge de travail</th>
                        <th>Taux de complétion</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($statsDept as $d): ?>
                    <tr>
                        <td><b><?php echo htmlspecialchars($d['nom']); ?></b></td>
                        <td><?php echo $d['nb']; ?> examens prévus</td>
                        <td>
                            <div style="width: 100%; background: #334155; height: 8px; border-radius: 4px; overflow: hidden;">
                                <div style="width: <?php echo ($nb_examens > 0) ? ($d['nb']/$nb_examens)*100 : 0; ?>%; background: var(--primary); height: 100%;"></div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>