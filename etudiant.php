<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'etudiant') {
    header("Location: login.php"); exit();
}

$user_id = $_SESSION['user_id'];

// Requête avec jointure sur l'état du département
$query = "SELECT e.date_heure, m.nom as module, l.nom as salle, l.batiment, p.nom_affichage as prof, d.etat_planning
          FROM inscriptions i 
          JOIN modules m ON i.module_id = m.id 
          JOIN formations f ON m.formation_id = f.id
          JOIN departements d ON f.dept_id = d.id
          JOIN examens e ON (m.id = e.module_id AND i.salle_id = e.salle_id) 
          JOIN lieu_examen l ON e.salle_id = l.id 
          JOIN professeurs p ON e.prof_id = p.id 
          WHERE i.etudiant_id = ? AND d.etat_planning = 'valide'";

$stmt = $pdo->prepare($query);
$stmt->execute([$user_id]);
$mes_examens = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mon Planning - ExamOptima</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="sidebar">
        <h2>ExamOptima</h2>
        <a href="etudiant.php" class="active">📅 Mon Planning</a>
        <a href="logout.php" class="logout">Déconnexion</a>
    </div>

    <div class="main-content">
        <h1>Mes Examens</h1>
        
        <?php if (count($mes_examens) > 0): ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr><th>Date</th><th>Module</th><th>Salle</th><th>Bâtiment</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($mes_examens as $ex): ?>
                        <tr>
                            <td><?php echo date('d/m H:i', strtotime($ex['date_heure'])); ?></td>
                            <td><b><?php echo $ex['module']; ?></b></td>
                            <td><span class="badge"><?php echo $ex['salle']; ?></span></td>
                            <td><?php echo $ex['batiment']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="card" style="text-align:center; padding:50px;">
                <h2 style="color:#f59e0b;">⏳ Planning indisponible</h2>
                <p>Votre emploi du temps est en cours de validation par le Chef de Département.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>