<?php
require_once 'db.php';
set_time_limit(300); 

try {
    // 1. Reset des données
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0; TRUNCATE TABLE examens; UPDATE inscriptions SET salle_id = NULL; SET FOREIGN_KEY_CHECKS = 1;");
    $pdo->exec("UPDATE departements SET etat_planning = 'en_attente'");

    // 2. Chargement des ressources
    $modules = $pdo->query("SELECT * FROM modules")->fetchAll(PDO::FETCH_ASSOC);
    $salles_prioritaires = $pdo->query("SELECT * FROM lieu_examen ORDER BY capacite DESC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $profs = $pdo->query("SELECT * FROM professeurs")->fetchAll(PDO::FETCH_ASSOC);

    $jours = ['2026-06-15', '2026-06-16', '2026-06-17', '2026-06-18', '2026-06-19'];
    $creneaux = ['09:00:00', '14:00:00'];

    // Trackers pour éviter les conflits
    $salle_occupee_slot = [];
    $prof_occupe_slot = [];
    $etudiant_occupe_jour = []; // [Date][Etudiant_ID] = true

    foreach ($modules as $mod) {
        // Récupérer la liste des étudiants pour ce module
        $stmtEtu = $pdo->prepare("SELECT etudiant_id FROM inscriptions WHERE module_id = ?");
        $stmtEtu->execute([$mod['id']]);
        $etudiants_a_placer = $stmtEtu->fetchAll(PDO::FETCH_COLUMN);
        $total_a_placer = count($etudiants_a_placer);

        if ($total_a_placer == 0) continue; 

        $planifie = false;
        shuffle($jours); 

        foreach ($jours as $j) {
            // --- NOUVELLE CONTRAINTE STRICTE ÉTUDIANT ---
            // On vérifie si UN SEUL des étudiants du module a déjà un examen ce jour-là
            $conflit_etudiant = false;
            foreach ($etudiants_a_placer as $id_etu) {
                if (isset($etudiant_occupe_jour[$j][$id_etu])) {
                    $conflit_etudiant = true;
                    break;
                }
            }
            if ($conflit_etudiant) continue; // Si conflit, on change de jour immédiatement

            foreach ($creneaux as $h) {
                $ts = "$j $h";
                
                $salles_candidates = [];
                $cap_trouvee = 0;

                // 1. Trouver des salles libres
                foreach ($salles_prioritaires as $s) {
                    if (!isset($salle_occupee_slot[$ts][$s['id']])) {
                        $salles_candidates[] = $s;
                        $cap_trouvee += $s['capacite'];
                        if ($cap_trouvee >= $total_a_placer) break;
                    }
                }

                if ($cap_trouvee >= $total_a_placer) {
                    // 2. Trouver des profs libres
                    $temp_profs = [];
                    $success_profs = true;
                    foreach ($salles_candidates as $salle) {
                        $p_id = null;
                        foreach ($profs as $p) {
                            if (!isset($prof_occupe_slot[$ts][$p['id']]) && !in_array($p['id'], $temp_profs)) {
                                $p_id = $p['id'];
                                break;
                            }
                        }
                        if ($p_id) $temp_profs[$salle['id']] = $p_id;
                        else { $success_profs = false; break; }
                    }

                    if ($success_profs) {
                        // 3. ENREGISTREMENT
                        $copie_etudiants = $etudiants_a_placer;
                        foreach ($salles_candidates as $salle) {
                            $prof_id = $temp_profs[$salle['id']];
                            $groupe = array_splice($copie_etudiants, 0, $salle['capacite']);
                            
                            // Insertion examen
                            $ins = $pdo->prepare("INSERT INTO examens (module_id, date_heure, salle_id, prof_id, duree_minute) VALUES (?, ?, ?, ?, 90)");
                            $ins->execute([$mod['id'], $ts, $salle['id'], $prof_id]);

                            // Update inscriptions & Tracker Étudiant
                            $upd = $pdo->prepare("UPDATE inscriptions SET salle_id = ? WHERE module_id = ? AND etudiant_id = ?");
                            foreach ($groupe as $id_etu) {
                                $upd->execute([$salle['id'], $mod['id'], $id_etu]);
                                // On marque l'étudiant comme pris pour TOUTE la journée $j
                                $etudiant_occupe_jour[$j][$id_etu] = true;
                            }

                            // Tracker Ressources
                            $salle_occupee_slot[$ts][$salle['id']] = true;
                            $prof_occupe_slot[$ts][$prof_id] = true;
                        }
                        $planifie = true;
                        break; 
                    }
                }
            }
            if ($planifie) break;
        }
    }
    header("Location: admin.php?msg=Optimisation_Etudiante_Parfaite");
    exit();
} catch (Exception $e) {
    die("Erreur : " . $e->getMessage());
}
