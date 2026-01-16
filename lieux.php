<?php
session_start();
require_once 'db.php';

// Sécurité Admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}

// Récupération des salles depuis la BDD
$salles = $pdo->query("SELECT * FROM lieu_examen ORDER BY capacite DESC")->fetchAll();
$total_places = $pdo->query("SELECT SUM(capacite) FROM lieu_examen")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Salles & Amphis - ExamOptima</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="sidebar">
        <h2>ExamOptima</h2>
        <a href="admin.php">🏠 Dashboard</a>
        <a href="lieux.php" class="active">🏛️ Salles & Amphis</a>
        <a href="conflits.php">⚠️ Analyse Conflits</a>
        <a href="logout.php" class="logout">Déconnexion</a>
    </div>

    <div class="main-content">
        <div class="header">
            <h1>Gestion des Salles</h1>
            <button class="btn btn-primary" onclick="alert('Fonctionnalité à venir !')">+ Ajouter un lieu</button>
        </div>

        <div class="stats-grid">
            <div class="card">
                <h3>Capacité Totale</h3>
                <div class="value"><?php echo $total_places; ?></div>
                <p style="color: var(--text-muted);">Places assises disponibles</p>
            </div>
            <div class="card">
                <h3>Nombre de Salles</h3>
                <div class="value"><?php echo count($salles); ?></div>
                <p style="color: var(--text-muted);">Configuration actuelle</p>
            </div>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Nom du lieu</th>
                        <th>Bâtiment</th>
                        <th>Type</th>
                        <th>Capacité</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($salles as $s): ?>
                    <tr>
                        <td><b><?php echo htmlspecialchars($s['nom']); ?></b></td>
                        <td><?php echo htmlspecialchars($s['batiment']); ?></td>
                        <td><span class="badge" style="background: rgba(255,255,255,0.05);"><?php echo htmlspecialchars($s['type']); ?></span></td>
                        <td><?php echo $s['capacite']; ?> places</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>