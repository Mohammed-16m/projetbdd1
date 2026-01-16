<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html"); exit();
}

try {
    $nb_examens = $pdo->query("SELECT COUNT(*) FROM examens")->fetchColumn();

    // 1. Capacité : Somme des places par module/créneau
    $sqlCap = "SELECT COUNT(*) FROM (
                SELECT e.module_id, SUM(l.capacite) as cap_totale, 
                (SELECT COUNT(*) FROM inscriptions i WHERE i.module_id = e.module_id) as inscrits
                FROM examens e JOIN lieu_examen l ON e.salle_id = l.id 
                GROUP BY e.module_id, e.date_heure HAVING cap_totale < inscrits
               ) as sub";
    $conflits_capacite = $pdo->query($sqlCap)->fetchColumn();

    // 2. Surveillants : Un prof ne peut pas être sur 2 MODULES différents en même temps
    $sqlProf = "SELECT COUNT(*) FROM examens e1 
                JOIN examens e2 ON e1.prof_id = e2.prof_id 
                AND e1.date_heure = e2.date_heure AND e1.module_id <> e2.module_id";
    $conflits_prof = $pdo->query($sqlProf)->fetchColumn();

    // 3. Étudiants : Max 1 module par jour
    $sqlEtud = "SELECT COUNT(*) FROM (
                SELECT COUNT(DISTINCT e.module_id) as nb FROM examens e 
                JOIN modules m ON e.module_id = m.id 
                GROUP BY m.formation_id, DATE(e.date_heure) HAVING nb > 1
               ) as sub";
    $conflits_etudiant = $pdo->query($sqlEtud)->fetchColumn();

    $total_erreurs = $conflits_capacite + $conflits_prof + $conflits_etudiant;
} catch (Exception $e) { $error = $e->getMessage(); }
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Analyse - ExamOptima</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .status-dot { height: 10px; width: 10px; border-radius: 50%; display: inline-block; margin-right: 8px; }
        .dot-green { background-color: #10b981; box-shadow: 0 0 10px rgba(16,185,129,0.4); }
        .dot-red { background-color: #ef4444; box-shadow: 0 0 10px rgba(239,68,68,0.4); }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>ExamOptima</h2>
        <a href="admin.php">🏠 Dashboard</a>
        <a href="lieux.php">🏛️ Salles & Amphis</a>
        <a href="conflits.php" class="active">⚠️ Analyse Conflits</a>
        <a href="logout.php" class="logout">Déconnexion</a>
    </div>

    <div class="main-content">
        <div class="header">
            <h1>Analyse des Contraintes</h1>
            <div class="badge" style="border: 1px solid <?php echo ($total_erreurs > 0) ? '#ef4444' : '#10b981'; ?>; color: <?php echo ($total_erreurs > 0) ? '#ef4444' : '#10b981'; ?>;">
                <?php echo ($total_erreurs > 0) ? "⚠️ $total_erreurs Conflits" : "✅ Planning Conforme"; ?>
            </div>
        </div>

        <div class="stats-grid">
            <div class="card">
                <h3>Total Anomalies</h3>
                <div class="value" style="color: <?php echo ($total_erreurs > 0) ? '#ef4444' : '#10b981'; ?>;"><?php echo $total_erreurs; ?></div>
                <p style="color: var(--text-muted);">Erreurs détectées</p>
            </div>
            <div class="card">
                <h3>Lignes Scannées</h3>
                <div class="value"><?php echo $nb_examens; ?></div>
                <p style="color: var(--text-muted);">Entrées du planning</p>
            </div>
        </div>

        <div class="table-container">
            <table>
                <thead><tr><th>Règle Métier</th><th>Statut</th><th>Détails</th></tr></thead>
                <tbody>
                    <tr>
                        <td><b>Capacité Logistique</b></td>
                        <td><?php echo ($conflits_capacite > 0) ? '<span class="status-dot dot-red"></span> Échec' : '<span class="status-dot dot-green"></span> OK'; ?></td>
                        <td>Vérifie si la somme des salles suffit pour les inscrits.</td>
                    </tr>
                    <tr>
                        <td><b>Disponibilité Surveillant</b></td>
                        <td><?php echo ($conflits_prof > 0) ? '<span class="status-dot dot-red"></span> Échec' : '<span class="status-dot dot-green"></span> OK'; ?></td>
                        <td>Un prof ne peut pas surveiller deux examens différents.</td>
                    </tr>
                    <tr>
                        <td><b>Charge Étudiante</b></td>
                        <td><?php echo ($conflits_etudiant > 0) ? '<span class="status-dot dot-red"></span> Échec' : '<span class="status-dot dot-green"></span> OK'; ?></td>
                        <td>Maximum un seul examen par jour par formation.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>