<?php
require_once 'db.php';
set_time_limit(300); 

try {
    // 1. Reset
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0; TRUNCATE TABLE examens; SET FOREIGN_KEY_CHECKS = 1;");

    // 2. Ressources simples
    $modules = $pdo->query("SELECT * FROM modules")->fetchAll(PDO::FETCH_ASSOC);
    $salles = $pdo->query("SELECT * FROM lieu_examen ORDER BY capacite DESC")->fetchAll(PDO::FETCH_ASSOC);
    $profs = $pdo->query("SELECT * FROM professeurs")->fetchAll(PDO::FETCH_ASSOC);

    if (empty($modules) || empty($salles) || empty($profs)) {
        die("Erreur : Une des tables (modules, salles ou profs) est VIDE !");
    }

    $jours = ['2026-06-15', '2026-06-16', '2026-06-17'];
    $creneaux = ['09:00:00', '14:00:00'];
    $salle_occupee = [];

    foreach ($modules as $mod) {
        $planifie = false;
        foreach ($jours as $j) {
            foreach ($creneaux as $h) {
                $ts = "$j $h";
                
                // On cherche la première salle libre (Priorité Amphi car trié par capacité DESC)
                foreach ($salles as $s) {
                    if (!isset($salle_occupee[$ts][$s['id']])) {
                        
                        // Insertion forcée
                        $prof_id = $profs[array_rand($profs)]['id'];
                        $stmt = $pdo->prepare("INSERT INTO examens (module_id, date_heure, salle_id, prof_id, duree_minute) VALUES (?, ?, ?, ?, 90)");
                        $stmt->execute([$mod['id'], $ts, $s['id'], $prof_id]);

                        $salle_occupee[$ts][$s['id']] = true;
                        $planifie = true;
                        break;
                    }
                }
                if ($planifie) break;
            }
            if ($planifie) break;
        }
    }
    header("Location: admin.php?msg=Ok_" . count($modules) . "_generes");
    exit();

} catch (Exception $e) {
    die("Erreur SQL : " . $e->getMessage());
}
