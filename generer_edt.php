<?php
require_once 'db.php';
set_time_limit(300); 

try {
    // 1. Reset
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0; TRUNCATE TABLE examens; UPDATE inscriptions SET salle_id = NULL; SET FOREIGN_KEY_CHECKS = 1;");

    // 2. Chargement et Vérification
    $modules = $pdo->query("SELECT * FROM modules")->fetchAll(PDO::FETCH_ASSOC);
    $salles = $pdo->query("SELECT * FROM lieu_examen ORDER BY capacite DESC")->fetchAll(PDO::FETCH_ASSOC);
    $profs = $pdo->query("SELECT * FROM professeurs")->fetchAll(PDO::FETCH_ASSOC);

    // VERIFICATION DE BASE
    if (empty($modules)) die("❌ Erreur : La table 'modules' est vide.");
    if (empty($salles)) die("❌ Erreur : La table 'lieu_examen' est vide.");
    if (empty($profs)) die("❌ Erreur : La table 'professeurs' est vide.");

    $jours = ['2026-06-15', '2026-06-16', '2026-06-17', '2026-06-18', '2026-06-19', '2026-06-20'];
    $creneaux = ['09:00:00', '14:00:00'];

    $salle_occupee_slot = [];
    $prof_occupe_slot = [];
    $etudiant_occupe_jour = [];

    echo "<h2>Rapport de l'Optimiseur</h2>";

    foreach ($modules as $mod) {
        // Récupérer les étudiants
        $stmtEtu = $pdo->prepare("SELECT etudiant_id FROM inscriptions WHERE module_id = ?");
        $stmtEtu->execute([$mod['id']]);
        $etudiants_a_placer = $stmtEtu->fetchAll(PDO::FETCH_COLUMN);
        $total_a_placer = count($etudiants_a_placer);

        if ($total_a_placer == 0) {
            echo "⚠️ Module <b>{$mod['nom']}</b> ignoré : 0 étudiant inscrit.<br>";
            continue; 
        }

        $planifie = false;

        foreach ($jours as $j) {
            // Vérification conflit étudiant (Un seul examen par jour)
            $conflit_etu = false;
            foreach ($etudiants_a_placer as $id_etu) {
                if (isset($etudiant_occupe_jour[$j][$id_etu])) {
                    $conflit_etu = true; break;
                }
            }
            if ($conflit_etu) continue;

            foreach ($creneaux as $h) {
                $ts = "$j $h";
                $selection_salles = [];
                $cap_trouvee = 0;

                // Trouver des salles
                foreach ($salles as $s) {
                    if (!isset($salle_occupee_slot[$ts][$s['id']])) {
                        $selection_salles[] = $s;
                        $cap_trouvee += $s['capacite'];
                        if ($cap_trouvee >= $total_a_placer) break;
                    }
                }

                if ($cap_trouvee >= $total_a_placer) {
                    // Trouver des profs
                    $temp_profs = [];
                    foreach ($selection_salles as $salle) {
                        foreach ($profs as $p) {
                            if (!isset($prof_occupe_slot[$ts][$p['id']]) && !in_array($p['id'], $temp_profs)) {
                                $temp_profs[] = $p['id'];
                                break;
                            }
                        }
                    }

                    if (count($temp_profs) == count($selection_salles)) {
                        // INSERTION RÉELLE
                        $copie_etu = $etudiants_a_placer;
                        foreach ($selection_salles as $index => $salle) {
                            $p_id = $temp_profs[$index];
                            $groupe = array_splice($copie_etu, 0, $salle['capacite']);

                            $stmt = $pdo->prepare("INSERT INTO examens (module_id, date_heure, salle_id, prof_id, duree_minute) VALUES (?, ?, ?, ?, 90)");
                            $stmt->execute([$mod['id'], $ts, $salle['id'], $p_id]);

                            foreach ($groupe as $id_etu) {
                                $etudiant_occupe_jour[$j][$id_etu] = true;
                                $upd = $pdo->prepare("UPDATE inscriptions SET salle_id = ? WHERE module_id = ? AND etudiant_id = ?");
                                $upd->execute([$salle['id'], $mod['id'], $id_etu]);
                            }
                            $salle_occupee_slot[$ts][$salle['id']] = true;
                            $prof_occupe_slot[$ts][$p_id] = true;
                        }
                        echo "✅ Module <b>{$mod['nom']}</b> placé le $ts.<br>";
                        $planifie = true; break;
                    }
                }
            }
            if ($planifie) break;
        }
        if (!$planifie) echo "❌ <b>{$mod['nom']}</b> impossible à placer (Manque de salles ou profs libres).<br>";
    }

    echo "<br><a href='admin.php'>Voir le planning final</a>";

} catch (Exception $e) {
    die("💥 Erreur Critique : " . $e->getMessage());
}
