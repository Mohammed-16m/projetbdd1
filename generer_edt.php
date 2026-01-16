<?php
require_once 'db.php';
set_time_limit(300); 

try {
    // 1. Nettoyage
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0; TRUNCATE TABLE examens; UPDATE inscriptions SET salle_id = NULL; SET FOREIGN_KEY_CHECKS = 1;");

    // 2. Ressources
    $modules = $pdo->query("SELECT * FROM modules")->fetchAll(PDO::FETCH_ASSOC);
    $salles = $pdo->query("SELECT * FROM lieu_examen ORDER BY capacite DESC")->fetchAll(PDO::FETCH_ASSOC);
    $profs = $pdo->query("SELECT * FROM professeurs")->fetchAll(PDO::FETCH_ASSOC);

    // Trackers
    $salle_occupee = [];
    $prof_occupe = [];
    $etudiant_deja_pris_ce_jour = []; // [Jour][Etudiant_ID]

    $jours = ['2026-06-15', '2026-06-16', '2026-06-17', '2026-06-18', '2026-06-19', '2026-06-20'];
    $creneaux = ['09:00:00', '14:00:00'];

    foreach ($modules as $mod) {
        // Récupérer les étudiants inscrits
        $stmtEtu = $pdo->prepare("SELECT etudiant_id FROM inscriptions WHERE module_id = ?");
        $stmtEtu->execute([$mod['id']]);
        $etudiants = $stmtEtu->fetchAll(PDO::FETCH_COLUMN);
        
        $total_inscrits = count($etudiants);
        if ($total_inscrits == 0) continue; // On passe si personne n'est inscrit

        $place = false;

        foreach ($jours as $j) {
            // --- VERIFICATION CONFLIT JOURNEE ---
            // On regarde si au moins un étudiant du groupe a déjà un exam ce jour-là
            $conflit = false;
            foreach ($etudiants as $id_etu) {
                if (isset($etudiant_deja_pris_ce_jour[$j][$id_etu])) {
                    $conflit = true;
                    break;
                }
            }
            if ($conflit) continue; // Trop risqué, on teste le jour suivant

            foreach ($creneaux as $h) {
                $ts = "$j $h";
                $salles_trouvees = [];
                $cap_cumulee = 0;

                // Trouver des salles libres à ce créneau
                foreach ($salles as $s) {
                    if (!isset($salle_occupee[$ts][$s['id']])) {
                        $salles_trouvees[] = $s;
                        $cap_cumulee += $s['capacite'];
                        if ($cap_cumulee >= $total_inscrits) break;
                    }
                }

                // Si on a assez de place
                if ($cap_cumulee >= $total_inscrits) {
                    $copie_etudiants = $etudiants;
                    
                    foreach ($salles_trouvees as $salle) {
                        // On prend un prof au hasard (pour ne pas bloquer si manque de profs)
                        $p_id = $profs[array_rand($profs)]['id'];

                        // On prend le nombre d'étudiants pour la salle
                        $groupe = array_splice($copie_etudiants, 0, $salle['capacite']);

                        // INSERTION
                        $ins = $pdo->prepare("INSERT INTO examens (module_id, date_heure, salle_id, prof_id, duree_minute) VALUES (?, ?, ?, ?, 90)");
                        $ins->execute([$mod['id'], $ts, $salle['id'], $p_id]);

                        // MARQUAGE DES ÉTUDIANTS (1 exam par jour)
                        foreach ($groupe as $id_etu) {
                            $etudiant_deja_pris_ce_jour[$j][$id_etu] = true;
                            // Optionnel : mettre à jour la salle dans inscriptions
                            $upd = $pdo->prepare("UPDATE inscriptions SET salle_id = ? WHERE module_id = ? AND etudiant_id = ?");
                            $upd->execute([$salle['id'], $mod['id'], $id_etu]);
                        }

                        $salle_occupee[$ts][$salle['id']] = true;
                    }
                    $place = true;
                    break;
                }
            }
            if ($place) break;
        }
    }

    header("Location: admin.php?msg=Optimisation_Terminee");
    exit();

} catch (Exception $e) {
    die("Erreur : " . $e->getMessage());
}
