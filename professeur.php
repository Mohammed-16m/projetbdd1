<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'prof') {
    header("Location: login.php"); exit();
}

$user_id = $_SESSION['user_id'];

$query = "SELECT e.date_heure, m.nom as module, l.nom as salle, d.etat_planning
          FROM examens e 
          JOIN modules m ON e.module_id = m.id 
          JOIN formations f ON m.formation_id = f.id
          JOIN departements d ON f.dept_id = d.id
          JOIN lieu_examen l ON e.salle_id = l.id 
          WHERE e.prof_id = ? AND d.etat_planning = 'valide'";

$stmt = $pdo->prepare($query);
$stmt->execute([$user_id]);
$surveillances = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mes Surveillances</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="sidebar">
        <h2>Espace Pro</h2>
        <a href="enseignant.php" class="active">📝 Surveillances</a>
        <a href="logout.php" class="logout">Déconnexion</a>
    </div>

    <div class="main-content">
        <h1>Planning de Surveillance</h1>
        <?php if (count($surveillances) > 0): ?>
            <div class="table-container">
                <table>
                    <thead><tr><th>Date</th><th>Module</th><th>Salle</th></tr></thead>
                    <tbody>
                        <?php foreach($surveillances as $s): ?>
                        <tr>
                            <td><?php echo date('d/m H:i', strtotime($s['date_heure'])); ?></td>
                            <td><?php echo $s['module']; ?></td>
                            <td><?php echo $s['salle']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="card" style="text-align:center; padding:50px;">
                <p>Aucune surveillance validée pour le moment.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>