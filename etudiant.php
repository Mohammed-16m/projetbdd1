<?php
session_start();
require_once 'db.php';

// 1. Vérification de l'accès Étudiant
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'etudiant') {
    header("Location: login.php"); exit();
}

$user_id = $_SESSION['user_id'];

try {
    // On ajoute la condition i.salle_id = e.salle_id dans le JOIN
    // Cela garantit que l'étudiant ne voit QUE sa salle assignée
    $query = "SELECT f.nom as formation, m.nom as module, e.date_heure, l.nom as salle, l.batiment
              FROM inscriptions i 
              JOIN modules m ON i.module_id = m.id 
              JOIN formations f ON m.formation_id = f.id
              JOIN departements d ON f.dept_id = d.id
              JOIN examens e ON (m.id = e.module_id AND i.salle_id = e.salle_id) 
              JOIN lieu_examen l ON e.salle_id = l.id 
              WHERE i.etudiant_id = ? AND d.etat_planning = 'valide'
              ORDER BY e.date_heure ASC";
              
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    $examens = $stmt->fetchAll();

} catch (Exception $e) { die($e->getMessage()); }
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Étudiant - Mon Planning</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="sidebar">
        <h2>ExamOptima</h2>
        <a href="etudiant.php" class="active">📅 Mon Planning</a>
        <a href="logout.php" class="logout">Déconnexion</a>
    </div>

    <div class="main-content">
        <div class="header">
            <h1>Mes Examens</h1>
            <div class="badge" style="color:#3b82f6; border-color:#3b82f6;">🎓 Espace Étudiant</div>
        </div>

        <?php if (count($examens) > 0): ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Formation</th>
                            <th>Module</th>
                            <th>Date & Heure</th>
                            <th>Salle</th>
                            <th>Bâtiment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($examens as $ex): ?>
                        <tr>
                            <td><b><?php echo htmlspecialchars($ex['formation']); ?></b></td>
                            <td><?php echo htmlspecialchars($ex['module']); ?></td>
                            <td><?php echo date('d/m H:i', strtotime($ex['date_heure'])); ?></td>
                            <td><span class="badge"><?php echo htmlspecialchars($ex['salle']); ?></span></td>
                            <td><?php echo htmlspecialchars($ex['batiment']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="table-container" style="text-align:center; padding:50px;">
                <h2 style="color:#f59e0b;">⏳ Planning en attente</h2>
                <p>Votre emploi du temps n'a pas encore été publié ou aucune inscription n'a été trouvée.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
