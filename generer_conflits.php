<?php
require_once 'db.php';

try {
    // 1. Nettoyage
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0; TRUNCATE TABLE examens; SET FOREIGN_KEY_CHECKS = 1;");

    // 2. Récupération des données
    $modules = $pdo->query("SELECT * FROM modules")->fetchAll(PDO::FETCH_ASSOC);
    $salles = $pdo->query("SELECT * FROM lieu_examen")->fetchAll(PDO::FETCH_ASSOC);
    $profs = $pdo->query("SELECT * FROM professeurs")->fetchAll(PDO::FETCH_ASSOC);

    // 3. Configuration (On restreint les jours pour créer des conflits d'étudiants)
    $jours = ['2026-06-15', '2026-06-16', '2026-06-17']; // Seulement 3 jours pour 5 modules par formation
    $creneaux = ['09:00:00', '14:00:00'];

    // Trackers pour ne pas mettre 2 exams dans la même salle au même moment
    $salle_occupee = [];
    $prof_occupe = [];

    echo "<h2>🎲 Génération d'un planning aléatoire (avec conflits logiques)</h2>";

    foreach ($modules as $mod) {
        $place_trouvee = false;
        
        // On essaie de trouver un créneau au hasard
        while (!$place_trouvee) {
            $j = $jours[array_rand($jours)];
            $h = $creneaux[array_rand($creneaux)];
            $ts = "$j $h";
            
            $salle = $salles[array_rand($salles)];
            $prof = $profs[array_rand($profs)];

            // On vérifie JUSTE que la salle et le prof ne sont pas déjà pris à cet instant précis
            if (!isset($salle_occupee[$ts][$salle['id']]) && !isset($prof_occupe[$ts][$prof['id']])) {
                
                $stmt = $pdo->prepare("INSERT INTO examens (module_id, date_heure, salle_id, prof_id) VALUES (?, ?, ?, ?)");
                $stmt->execute([$mod['id'], $ts, $salle['id'], $prof['id']]);

                $salle_occupee[$ts][$salle['id']] = true;
                $prof_occupe[$ts][$prof['id']] = true;
                $place_trouvee = true;
            }
        }
    }

    echo "<p style='color:green;'>✅ Planning aléatoire généré. Les salles et profs sont respectés par créneau, mais il reste des conflits de capacité et d'étudiants !</p>";
    echo "<a href='admin.php'>Voir le planning</a>";

} catch (Exception $e) {
    die("Erreur : " . $e->getMessage());
}