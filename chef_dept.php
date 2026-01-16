<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'chef_dep') {
    header("Location: login.php"); exit();
}

$user_id = $_SESSION['user_id'];

try {
    // 1. Récupérer le département piloté par ce chef
    $stmtDept = $pdo->prepare("SELECT id, nom, etat_planning FROM departements WHERE chef_id = ?");
    $stmtDept->execute([$user_id]);
    $dept = $stmtDept->fetch();

    // 2. Action de validation
    if (isset($_POST['valider_planning']) && $dept) {
        $upd = $pdo->prepare("UPDATE departements SET etat_planning = 'valide' WHERE id = ?");
        $upd->execute([$dept['id']]);
        header("Location: chef_dept.php?msg=Planning_publie"); exit();
    }

    // 3. Récupérer les examens du département
    $examens = [];
    if ($dept) {
        $query = "SELECT f.nom as formation, m.nom as module, p.nom_affichage as prof, e.date_heure, l.nom as salle
                  FROM examens e 
                  JOIN modules m ON e.module_id = m.id 
                  JOIN formations f ON m.formation_id = f.id 
                  JOIN professeurs p ON e.prof_id = p.id 
                  JOIN lieu_examen l ON e.salle_id = l.id
                  WHERE f.dept_id = ? ORDER BY e.date_heure ASC";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$dept['id']]);
        $examens = $stmt->fetchAll();
    }
} catch (Exception $e) { die($e->getMessage()); }
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Chef Dept - <?php echo $dept['nom']; ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="sidebar">
        <h2>Dept <?php echo htmlspecialchars($dept['nom']); ?></h2>
        <a href="chef_dept.php" class="active">✅ Validation EDT</a>
        <a href="enseignant.php">👨‍🏫 Mes Enseignants</a>
        <a href="logout.php" class="logout">Déconnexion</a>
    </div>

    <div class="main-content">
        <div class="header">
            <h1>Validation Départementale</h1>
            <?php if ($dept['etat_planning'] == 'en_attente'): ?>
                <form method="POST"><button type="submit" name="valider_planning" class="btn" style="background:#10b981;">✅ Publier le Planning</button></form>
            <?php else: ?>
                <div class="badge" style="color:#10b981; border-color:#10b981;">✅ Planning Publié</div>
            <?php endif; ?>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr><th>Formation</th><th>Module</th><th>Date</th><th>Salle</th><th>Surveillant</th></tr>
                </thead>
                <tbody>
                    <?php foreach($examens as $ex): ?>
                    <tr>
                        <td><b><?php echo $ex['formation']; ?></b></td>
                        <td><?php echo $ex['module']; ?></td>
                        <td><?php echo date('d/m H:i', strtotime($ex['date_heure'])); ?></td>
                        <td><span class="badge"><?php echo $ex['salle']; ?></span></td>
                        <td><?php echo $ex['prof']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>