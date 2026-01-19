<?php
require_once 'db.php';

try {
    // 1. Nettoyage total avant de commencer
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0; TRUNCATE TABLE examens; SET FOREIGN_KEY_CHECKS = 1;");

    // 2. Récupération des données
    $modules = $pdo->query("SELECT * FROM modules")->fetchAll(PDO::FETCH_ASSOC);
    $salles = $pdo->query("SELECT * FROM lieu_examen")->fetchAll(PDO::FETCH_ASSOC); // On prend tout (Amphis et Salles) en vrac
    $profs = $pdo->query("SELECT * FROM professeurs")->fetchAll(PDO::FETCH_ASSOC);

    // 3. Configuration restreinte pour FORCER les conflits temporels
  
    $jours = ['2026-06-15', '2026-06-16']; 
    $creneaux = ['09:00:00', '14:00:00'];

    echo "<h2>🎲 Génération Aléatoire (Mode Chaos)</h2>";
    echo "<ul>";

    $stmt = $pdo->prepare("INSERT INTO examens (module_id, date_heure, salle_id, prof_id, duree_minute) VALUES (?, ?, ?, ?, ?)");

    foreach ($modules as $mod) {
        // A. Choix totalement aléatoire (sans vérification)
        $date = $jours[array_rand($jours)];
        $heure = $creneaux[array_rand($creneaux)];
        $ts = "$date $heure";

        $salle = $salles[array_rand($salles)]; 
        $prof = $profs[array_rand($profs)];   

        // B. Insertion directe (C'est l'optimiseur qui devra diviser ce module plus tard)
        $stmt->execute([$mod['id'], $ts, $salle['id'], $prof['id'], 90]);

        echo "<li>🔴 Module <strong>{$mod['nom']}</strong> placé aléatoirement dans <strong>{$salle['nom']}</strong> ({$salle['capacite']} places) le $ts.</li>";
    }

    echo "</ul>";
    echo "<h3>⚠️ Bilan des problèmes à résoudre par l'optimisation :</h3>";
    echo "<p>1. <strong>Capacité :</strong> Des modules de 500 étudiants sont dans des salles de 20 places.</p>";
    echo "<p>2. <strong>Répartition :</strong> Les modules ne sont pas encore divisés (Amphi + Salles).</p>";
    echo "<p>3. <strong>Chevauchement :</strong> Plusieurs examens ont lieu dans la même salle à la même heure.</p>";
    
    echo "<br><a href='optimisation.php'><button>🚀 Lancer l'Optimisation (Correction)</button></a>";

} catch (Exception $e) {
    die("❌ Erreur : " . $e->getMessage());
}
?>
