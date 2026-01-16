<?php
require_once 'db.php';
set_time_limit(300); 

try {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0; TRUNCATE TABLE examens; SET FOREIGN_KEY_CHECKS = 1;");

    $modules = $pdo->query("SELECT * FROM modules")->fetchAll(PDO::FETCH_ASSOC);
    $salles = $pdo->query("SELECT * FROM lieu_examen ORDER BY capacite DESC")->fetchAll(PDO::FETCH_ASSOC);
    $profs = $pdo->query("SELECT * FROM professeurs")->fetchAll(PDO::FETCH_ASSOC);

    echo "<h2>Rapport d'optimisation</h2>";

    $salle_occupee = [];
    $prof_occupe = [];

    foreach ($modules as $mod) {
        // On force un nombre d'étudiants à 100 si la table inscription est vide pour tester
        $stmtEtu = $pdo->prepare("SELECT COUNT(*) FROM inscriptions WHERE module_id = ?");
        $stmtEtu->execute([$mod['id']]);
        $total_a_placer = $stmtEtu->fetchColumn();

        if ($total_a_placer == 0) {
            echo "⚠️ Module <b>{$mod['nom']}</b> sauté : Aucun étudiant inscrit.<br>";
            continue; 
        }

        $planifie = false;
        // On simplifie la recherche pour forcer le résultat
        foreach (['2026-06-15', '2026-06-16'] as $j) {
            foreach (['09:00:00', '14:00:00'] as $h) {
                $ts = "$j $h";
                
                $selection_salles = [];
                $cap_cumulee = 0;

                foreach ($salles as $s) {
                    if (!isset($salle_occupee[$ts][$s['id']])) {
                        $selection_salles[] = $s;
                        $cap_cumulee += $s['capacite'];
                        if ($cap_cumulee >= $total_a_placer) break;
                    }
                }

                if ($cap_cumulee >= $total_a_placer) {
                    // On a les salles, vérifions les profs
                    $temp_profs = [];
                    foreach ($selection_salles as $salle) {
                        foreach ($profs as $p) {
                            if (!isset($prof_occupe[$ts][$p['id']]) && !in_array($p['id'], $temp_profs)) {
                                $temp_profs[] = $p['id'];
                                break;
                            }
                        }
                    }

                    if (count($temp_profs) == count($selection_salles)) {
                        // INSERTION
                        foreach ($selection_salles as $key => $salle) {
                            $p_id = $temp_profs[$key];
                            $ins = $pdo->prepare("INSERT INTO examens (module_id, date_heure, salle_id, prof_id, duree_minute) VALUES (?, ?, ?, ?, 90)");
                            $ins->execute([$mod['id'], $ts, $salle['id'], $p_id]);
                            $salle_occupee[$ts][$salle['id']] = true;
                            $prof_occupe[$ts][$p_id] = true;
                        }
                        echo "✅ Module <b>{$mod['nom']}</b> planifié ($total_a_placer étudiants).<br>";
                        $planifie = true; break;
                    }
                }
            }
            if ($planifie) break;
        }
        if (!$planifie) echo "❌ Impossible de planifier <b>{$mod['nom']}</b> (pas assez de profs ou salles libres).<br>";
    }

    echo "<br><a href='admin.php'>Retour au planning</a>";

} catch (Exception $e) {
    die("Erreur : " . $e->getMessage());
}
