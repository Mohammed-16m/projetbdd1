<?php
session_start();
require_once 'db.php';

// Sécurité : Vérifier si c'est un Chef de Département
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'chef_dep') {
    header("Location: login.php"); 
    exit();
}

$chef_id = $_SESSION['user_id'];

try {
    // 1. Récupérer le département du Chef connecté
    $stmtDept = $pdo->prepare("SELECT id, nom FROM departements WHERE chef_id = ?");
    $stmtDept->execute([$chef_id]);
    $mon_dept = $stmtDept->fetch();

    if (!$mon_dept) {
        die("Erreur : Aucun département associé à ce compte.");
    }

    // 2. Récupérer les enseignants de ce département et leur charge de surveillance
    $query = "SELECT p.*, COUNT(e.id) as nb_surveillances 
              FROM professeurs p 
              LEFT JOIN examens e ON p.id = e.prof_id 
              WHERE p.dept_id = ? 
              GROUP BY p.id";
    
    $stmtProf = $pdo->prepare($query);
    $stmtProf->execute([$mon_dept['id']]);
    $enseignants = $stmtProf->fetchAll();

} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mes Enseignants - Chef de Département</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="sidebar">
        <h2>ExamOptima</h2>
        <a href="chef_dep.php">🏠 Dashboard</a>
        <a href="enseignant.php" class="active">👨‍🏫 Mes Enseignants</a>
        <a href="logout.php" class="logout">Déconnexion</a>
    </div>

    <div class="main-content">
        <div class="header">
            <div>
                <h1>Équipe Pédagogique</h1>
                <p style="color: var(--text-muted);">Département : <b><?php echo htmlspecialchars($mon_dept['nom']); ?></b></p>
            </div>
            <div class="badge" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6; border: 1px solid #3b82f6;">
                <?php echo count($enseignants); ?> Professeurs
            </div>
        </div>

        <div class="stats-grid">
            <div class="card">
                <h3>Moyenne Surveillance</h3>
                <div class="value">
                    <?php 
                        $total = array_sum(array_column($enseignants, 'nb_surveillances'));
                        echo (count($enseignants) > 0) ? round($total / count($enseignants), 1) : 0;
                    ?>
                </div>
                <p style="color: var(--text-muted);">Séances par enseignant</p>
            </div>
        </div>

        <div class="table-container" style="margin-top: 20px;">
            <table>
                <thead>
                    <tr>
                        <th>Nom de l'Enseignant</th>
                        <th>Email / Contact</th>
                        <th>Spécialité</th>
                        <th>Charge de Surveillance</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($enseignants as $p): ?>
                    <tr>
                        <td>
                            <div style="font-weight: bold;"><?php echo htmlspecialchars($p['nom_affichage']); ?></div>
                        </td>
                        <td><span style="font-size: 0.9rem; color: var(--text-muted);">pro_<?php echo strtolower($p['id']); ?>@univ.dz</span></td>
                        <td><?php echo htmlspecialchars($p['specialite'] ?? 'Généraliste'); ?></td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <b><?php echo $p['nb_surveillances']; ?></b>
                                <div style="flex: 1; background: #334155; height: 6px; border-radius: 3px; width: 60px;">
                                    <div style="width: <?php echo min($p['nb_surveillances'] * 10, 100); ?>%; background: #3b82f6; height: 100%;"></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php if($p['nb_surveillances'] > 0): ?>
                                <span class="badge" style="background: rgba(16, 185, 129, 0.1); color: #10b981;">Actif</span>
                            <?php else: ?>
                                <span class="badge" style="background: rgba(239, 68, 68, 0.1); color: #ef4444;">Libre</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>