<?php
ob_start(); 
session_start();
require_once 'db.php';

// 1. Vérification de session
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'etudiant') {
    header("Location: login.php"); 
    exit();
}

$user_id = $_SESSION['user_id'];

/**
 * LA REQUÊTE EXPLIQUÉE :
 * On sélectionne les examens UNIQUEMENT si le département a validé (etat_planning = 'valide')
 * On fait le lien entre l'inscription de l'étudiant et la salle assignée par l'optimiseur.
 */
$query = "SELECT e.date_heure, m.nom as module, l.nom_salle as salle, l.batiment, d.etat_planning
          FROM inscriptions i 
          JOIN modules m ON i.module_id = m.id 
          JOIN formations f ON m.formation_id = f.id
          JOIN departements d ON f.dept_id = d.id
          JOIN examens e ON m.id = e.module_id 
          JOIN lieu_examen l ON e.salle_id = l.id 
          WHERE i.etudiant_id = ? 
          AND d.etat_planning = 'valide'
          GROUP BY m.id"; // On groupe par module pour éviter les doublons

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    $mes_examens = $stmt->fetchAll();
} catch (PDOException $e) {
    // En cas d'erreur de colonne, on affiche un message propre
    $mes_examens = [];
    $error_db = "Erreur de chargement du planning.";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mon Planning - ExamOptima</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .badge { background: #e0e7ff; color: #4338ca; padding: 4px 8px; border-radius: 4px; font-weight: bold; }
        .table-container { margin-top: 20px; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 12px; border-bottom: 1px solid #eee; }
        tr:hover { background-color: #f9fafb; }
    </style>
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
                        <tr>
                            <th>Date & Heure</th>
                            <th>Module</th>
                            <th>Lieu</th>
                            <th>Bâtiment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($mes_examens as $ex): ?>
                        <tr>
                            <td>
                                <strong><?php echo date('d/m/2026', strtotime($ex['date_heure'])); ?></strong><br>
                                <small><?php echo date('H:i', strtotime($ex['date_heure'])); ?></small>
                            </td>
                            <td><b><?php echo htmlspecialchars($ex['module']); ?></b></td>
                            <td><span class="badge"><?php echo htmlspecialchars($ex['salle']); ?></span></td>
                            <td><?php echo htmlspecialchars($ex['batiment']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="card" style="text-align:center; padding:50px; background: #fffbeb; border: 1px solid #fde68a; border-radius: 12px;">
                <h2 style="color:#b45309;">⏳ Planning non disponible</h2>
                <p style="color: #92400e;">Votre emploi du temps n'a pas encore été publié par votre département ou vous n'avez aucune inscription enregistrée.</p>
                <p style="font-size: 0.9em; color: #d97706;">Vérifiez ultérieurement.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
