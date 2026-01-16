<?php
ob_start();
session_start();
require_once 'db.php'; 

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'etudiant') {
    header("Location: login.php"); 
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    // Correction : On utilise l.nom car c'est le nom exact dans ta table lieu_examen
    $query = "SELECT e.date_heure, m.nom as module, l.nom as salle, l.batiment
              FROM inscriptions i 
              JOIN modules m ON i.module_id = m.id 
              JOIN formations f ON m.formation_id = f.id
              JOIN departements d ON f.dept_id = d.id
              JOIN examens e ON m.id = e.module_id 
              JOIN lieu_examen l ON e.salle_id = l.id 
              WHERE i.etudiant_id = ? AND d.etat_planning = 'valide'
              ORDER BY e.date_heure ASC";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    $mes_examens = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Erreur technique : " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mon Planning - ExamOptima</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .table-container { margin: 20px; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 12px; border-bottom: 1px solid #eee; text-align: left; }
        th { background-color: #f8fafc; color: #64748b; }
        .badge-salle { background: #dcfce7; color: #166534; padding: 4px 8px; border-radius: 5px; font-weight: bold; }
        .status-msg { text-align: center; margin-top: 50px; padding: 30px; border: 2px dashed #cbd5e1; border-radius: 15px; }
    </style>
</head>
<body>
    <div class="main-content">
        <h1>📅 Mon Planning d'Examens</h1>

        <?php if (count($mes_examens) > 0): ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Date & Heure</th>
                            <th>Module</th>
                            <th>Salle / Amphi</th>
                            <th>Bâtiment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($mes_examens as $ex): ?>
                        <tr>
                            <td><strong><?php echo date('d/m/2026 à H:i', strtotime($ex['date_heure'])); ?></strong></td>
                            <td><?php echo htmlspecialchars($ex['module']); ?></td>
                            <td><span class="badge-salle"><?php echo htmlspecialchars($ex['salle']); ?></span></td>
                            <td><?php echo htmlspecialchars($ex['batiment']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="status-msg">
                <h2 style="color: #64748b;">⏳ Aucun examen affiché</h2>
                <p>Cela peut signifier deux choses :</p>
                <ul style="display: inline-block; text-align: left;">
                    <li>Le Chef de Département n'a pas encore <b>validé</b> le planning.</li>
                    <li>Vous n'êtes inscrit à aucun module pour cette session.</li>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
