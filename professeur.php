<?php
session_start();
require_once 'db.php';

// Sécurité : accepte les deux versions du mot-clé rôle
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'prof' && $_SESSION['role'] !== 'professeur')) {
    header("Location: login.php"); 
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    // Requête qui fonctionne même si le département est validé
    $query = "SELECT e.date_heure, m.nom as module, l.nom as salle, l.batiment
              FROM examens e 
              JOIN modules m ON e.module_id = m.id 
              JOIN formations f ON m.formation_id = f.id
              JOIN departements d ON f.dept_id = d.id
              JOIN lieu_examen l ON e.salle_id = l.id 
              WHERE e.prof_id = ? AND d.etat_planning = 'valide'
              ORDER BY e.date_heure ASC";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    $surveillances = $stmt->fetchAll();
} catch (Exception $e) { 
    die("Erreur : " . $e->getMessage()); 
}
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
        <div class="header">
            <h1>Planning de Surveillance</h1>
            <div class="badge" style="color:#10b981; border-color:#10b981;">👨‍🏫 Professeur</div>
        </div>

        <?php if (count($surveillances) > 0): ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr><th>Date & Heure</th><th>Module</th><th>Lieu</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($surveillances as $s): ?>
                        <tr>
                            <td><b><?php echo date('d/m/2026 à H:i', strtotime($s['date_heure'])); ?></b></td>
                            <td><?php echo htmlspecialchars($s['module']); ?></td>
                            <td>
                                <span class="badge"><?php echo htmlspecialchars($s['salle']); ?></span>
                                <small><?php echo htmlspecialchars($s['batiment']); ?></small>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="table-container" style="text-align:center; padding:50px;">
                <h2 style="color:#64748b;">⏳ Aucune surveillance</h2>
                <p>Votre planning sera visible dès que le chef de département l'aura validé.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
