<?php
require_once 'db.php';
set_time_limit(120); 

try {
    // 1. Reset des données précédentes
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0; TRUNCATE TABLE examens; UPDATE inscriptions SET salle_id = NULL; SET FOREIGN_KEY_CHECKS = 1;");
    $pdo->exec("UPDATE departements SET etat_planning = 'en_attente'");

    // 2. Chargement des ressources
    $modules = $pdo->query("SELECT * FROM modules")->fetchAll(PDO::FETCH_ASSOC);
    // On récupère les salles : Amphis (Gros) d'abord
    $salles_prioritaires = $pdo->query("SELECT * FROM lieu_examen ORDER BY capacite DESC")->fetchAll(PDO::FETCH_ASSOC);
    $profs = $pdo->query("SELECT * FROM professeurs")->fetchAll(PDO::FETCH_ASSOC);

    $jours = ['2026-06-15', '2026-06-16', '2026-06-17', '2026-06-18', '2026-06-19'];
    $creneaux_base = ['09:00:00', '14:00:00'];

    // --- TRACKERS ---
    $formation_jour_pris = [];
    $salle_occupee_slot = [];
    $prof_occupe_slot = [];
    $etudiant_occupe_jour = []; // Nouveau : Suit les étudiants par jour

    foreach ($modules as $mod) {
        $stmtEtu = $pdo->prepare("SELECT etudiant_id FROM inscriptions WHERE module_id = ?");
        $stmtEtu->execute([$mod['id']]);
        $etudiants_a_placer = $stmtEtu->fetchAll(PDO::FETCH_COLUMN);
        $total_a_placer = count($etudiants_a_placer);

        if ($total_a_placer == 0) continue;

        $planifie = false;
        shuffle($jours); 

        foreach ($jours as $j) {
            // --- SÉCURITÉ : CONFLIT ÉTUDIANT ---
            // On vérifie si UN SEUL des étudiants a déjà un examen ce jour-là
            $conflit_etudiant = false;
            foreach ($etudiants_a_placer as $id_etu) {
                if (isset($etudiant_occupe_jour[$j][$id_etu])) {
                    $conflit_etudiant = true;
                    break;
                }
            }
            if ($conflit_etudiant) continue; // Si conflit, on passe au jour suivant

            $creneaux_test = $creneaux_base;
            shuffle($creneaux_test);

            foreach ($creneaux_test as $h) {
                $ts = "$j $h";
                
                // Contrainte formation (département)
                if (isset($formation_jour_pris[$j][$mod['formation_id']])) continue;

                $selection_salles = [];
                $cap_cumulee = 0;

                // Logique Amphi Prioritaire
                foreach ($salles_prioritaires as $s) {
                    if (!isset($salle_occupee_slot[$ts][$s['id']]) && $cap_cumulee < $total_a_placer) {
                        $selection_salles[] = $s;
                        $cap_cumulee += $s['capacite'];
                    }
                }

                if ($cap_cumulee >= $total_a_placer) {
                    $copie_etudiants = $etudiants_a_placer;
                    $success_profs = true;
                    $temp_inserts = [];

                    foreach ($selection_salles as $salle_choisie) {
                        $p_id = null;
                        foreach ($profs as $p) {
                            if (!isset($prof_occupe_slot[$ts][$p['id']])) {
                                $p_id = $p['id'];
                                break;
                            }
                        }

                        if ($p_id) {
                            $groupe = array_splice($copie_etudiants, 0, $salle_choisie['capacite']);
                            $temp_inserts[] = [
                                'salle' => $salle_choisie['id'],
                                'prof' => $p_id,
                                'liste_etu' => $groupe
                            ];
                        } else {
                            $success_profs = false;
                            break;
                        }
                    }

                    // Validation finale du créneau
                    if ($success_profs && !empty($temp_inserts)) {
                        foreach ($temp_inserts as $data) {
                            // Insertion (Ajout de duree_minute si nécessaire)
                            $ins = $pdo->prepare("INSERT INTO examens (module_id, date_heure, salle_id, prof_id, duree_minute) VALUES (?, ?, ?, ?, 90)");
                            $ins->execute([$mod['id'], $ts, $data['salle'], $data['prof']]);

                            foreach ($data['liste_etu'] as $id_etu) {
                                // Affectation salle
                                $upd = $pdo->prepare("UPDATE inscriptions SET salle_id = ? WHERE etudiant_id = ? AND module_id = ?");
                                $upd->execute([$data['salle'], $id_etu, $mod['id']]);
                                
                                // MARQUER L'ÉTUDIANT COMME OCCUPÉ CE JOUR
                                $etudiant_occupe_jour[$j][$id_etu] = true;
                            }

                            $salle_occupee_slot[$ts][$data['salle']] = true;
                            $prof_occupe_slot[$ts][$data['prof']] = true;
                        }
                        
                        $formation_jour_pris[$j][$mod['formation_id']] = true;
                        $planifie = true;
                        break; 
                    }
                }
            }
            if ($planifie) break;
        }
    }
    header("Location: admin.php?msg=Optimisation Reussie - 1 Exam par Jour");
    exit();

} catch (Exception $e) {
    die("Erreur : " . $e->getMessage());
}
