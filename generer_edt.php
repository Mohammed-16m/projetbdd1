<?php
require_once 'db.php';
set_time_limit(180); 

try {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0; TRUNCATE TABLE examens; SET FOREIGN_KEY_CHECKS = 1;");

    $modules = $pdo->query("SELECT * FROM modules")->fetchAll(PDO::FETCH_ASSOC);
    $salles_prioritaires = $pdo->query("SELECT * FROM lieu_examen ORDER BY capacite DESC")->fetchAll(PDO::FETCH_ASSOC);
    $profs = $pdo->query("SELECT * FROM professeurs")->fetchAll(PDO::FETCH_ASSOC);

    $jours = ['2026-06-15', '2026-06-16', '2026-06-17', '2026-06-18', '2026-06-19', '2026-06-20'];
    $creneaux_base = ['09:00:00', '14:00:00'];

    $salle_occupee_slot = [];
    $prof_occupe_slot = [];
    $etudiant_occupe_jour = [];

    echo "<h2>Debug de l'Optimisation</h2>";

    foreach ($modules as $mod) {
        // 1. On récupère les étudiants
        $stmtEtu = $pdo->prepare("SELECT etudiant_id FROM inscriptions WHERE module_id = ?");
        $stmtEtu->execute([$mod['id']]);
        $etudiants_a_placer = $stmtEtu->fetchAll(PDO::FETCH_COLUMN);
        
        // --- FALLBACK : Si la table est vide, on simule 1 étudiant pour que l'examen soit créé ---
        if (empty($etudiants_a_placer)) {
            $etudiants_a_placer = [999]; // ID fictif
        }
        $total_a_placer = count($etudiants_a_placer);

        $planifie = false;
        shuffle($jours); 

        foreach ($jours as $j) {
            // 2. Vérification Conflit Étudiant (Un seul exam par jour)
            $conflit_etudiant = false;
            foreach ($etudiants_a_placer as $id_etu) {
                if (isset($etudiant_occupe_jour[$j][$id_etu])) {
                    $conflit_etudiant = true;
                    break;
                }
            }
            if ($conflit_etudiant) continue; // Trop d'étudiants occupés ce jour, jour suivant

            foreach ($creneaux_base as $h) {
                $ts = "$j $h";
                $selection_salles = [];
                $cap_cumulee = 0;

                // 3. Sélection des salles
                foreach ($salles_prioritaires as $s) {
                    if (!isset($salle_occupee_slot[$ts][$s['id']])) {
                        $selection_salles[] = $s;
                        $cap_cumulee += $s['capacite'];
                        if ($cap_cumulee >= $total_a_placer) break;
                    }
                }

                if ($cap_cumulee >= $total_a_placer) {
                    $copie_etudiants = $etudiants_a_placer;
                    
                    foreach ($selection_salles as $salle_choisie) {
                        // 4. Choix du prof (si pas de prof libre, on en force un au hasard)
                        $p_id = null;
                        foreach ($profs as $p) {
                            if (!isset($prof_occupe_slot[$ts][$p['id']])) {
                                $p_id = $p['id']; break;
                            }
                        }
                        if (!$p_id) $p_id = $profs[array_rand($profs)]['id'];

                        $groupe = array_splice($copie_etudiants, 0, $salle_choisie['capacite']);
                        
                        // 5. INSERTION
                        $ins = $pdo->prepare("INSERT INTO examens (module_id, date_heure, salle_id, prof_id, duree_minute) VALUES (?, ?, ?, ?, 90)");
                        $ins->execute([$mod['id'], $ts, $salle_choisie['id'], $p_id]);

                        // 6. TRACKING
                        foreach ($groupe as $id_etu) {
                            $etudiant_occupe_jour[$j][$id_etu] = true;
                        }
                        $salle_occupee_slot[$ts][$salle_choisie['id']] = true;
                        $prof_occupe_slot[$ts][$p_id] = true;
                    }
                    
                    echo "✅ Module <b>{$mod['nom']}</b> planifié le $j à $h<br>";
                    $planifie = true;
                    break; 
                }
            }
            if ($planifie) break;
        }
        if (!$planifie) echo "❌ Module <b>{$mod['nom']}</b> : Impossible de trouver un créneau sans conflit.<br>";
    }

    echo "<p><a href='admin.php'>Retour au planning</a></p>";

} catch (Exception $e) {
    die("Erreur fatale : " . $e->getMessage());
}
