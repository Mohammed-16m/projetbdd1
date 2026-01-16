<?php
// 1. D'abord démarrer la session
session_start();

// 2. ENSUITE inclure la connexion (C'est ici que $pdo est créé)
require_once 'db.php'; 

// 3. Vérifier que la connexion existe bien
if (!isset($pdo)) {
    die("La variable pdo n'est pas définie. Vérifiez votre fichier db.php.");
}

// 4. Vérifier l'accès
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'etudiant') {
    header("Location: login.php"); 
    exit();
}

$user_id = $_SESSION['user_id'];

// 5. La requête (Version simplifiée pour tester l'affichage)
try {
    $query = "SELECT e.date_heure, m.nom as module, l.nom_salle as salle, l.batiment
              FROM inscriptions i
              JOIN modules m ON i.module_id = m.id
              JOIN examens e ON m.id = e.module_id
              JOIN lieu_examen l ON e.salle_id = l.id
              JOIN formations f ON m.formation_id = f.id
              JOIN departements d ON f.dept_id = d.id
              WHERE i.etudiant_id = ? AND d.etat_planning = 'valide'
              ORDER BY e.date_heure ASC";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    $mes_examens = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Erreur SQL : " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mon Planning</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="main-content">
        <h1>Mes Examens</h1>
        
        <?php if (count($mes_examens) > 0): ?>
            <table>
                <thead>
                    <tr><th>Date</th><th>Module</th><th>Salle</th></tr>
                </thead>
                <tbody>
                    <?php foreach($mes_examens as $ex): ?>
                    <tr>
                        <td><?php echo $ex['date_heure']; ?></td>
                        <td><?php echo $ex['module']; ?></td>
                        <td><?php echo $ex['salle']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>Aucun examen trouvé ou planning non validé par le chef.</p>
            
            <div style="font-size:12px; color:gray; margin-top:50px; border-top:1px solid #ccc;">
                DEBUG : <br>
                ID Étudiant : <?php echo $user_id; ?><br>
                <?php
                // Vérifions si le département est bien validé en base
                $check = $pdo->query("SELECT nom, etat_planning FROM departements")->fetchAll();
                echo "États des départements : ";
                print_r($check);
                ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
