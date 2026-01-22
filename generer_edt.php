<?php
session_start();
require_once 'db.php';
set_time_limit(600); 

ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0; TRUNCATE TABLE examens; SET FOREIGN_KEY_CHECKS = 1;");
    $pdo->exec("UPDATE inscriptions SET salle_id = NULL; UPDATE departements SET etat_planning = 'en_attente';");
    
    $pdo->beginTransaction();

    $modules = $pdo->query("SELECT * FROM modules")->fetchAll(PDO::FETCH_ASSOC);
    $amphis = $pdo->query("SELECT * FROM lieu_examen WHERE nom LIKE '%Amphi%' ORDER BY capacite DESC")->fetchAll(PDO::FETCH_ASSOC);
    $salles_normales = $pdo->query("SELECT * FROM lieu_examen WHERE nom NOT LIKE '%Amphi%' ORDER BY capacite DESC")->fetchAll(PDO::FETCH_ASSOC);
    $profs = $pdo->query("SELECT id FROM professeurs")->fetchAll(PDO::FETCH_ASSOC);

    $suivi_missions = [];
    foreach ($profs as $p) { $suivi_missions[$p['id']] = 0; }

    $jours = ['2026-06-15', '2026-06-16', '2026-06-17', '2026-06-18', '2026-06-19', '2026-06-20'];
    $creneaux_base = ['08:00:00', '10:00:00', '12:00:00', '14:00:00', '16:00:00'];

    $salle_occupee = [];
    $prof_occupe_slot = [];
    $prof_count_jour = [];
    $etudiant_occupe_jour = [];
    $examens_crees = 0;

    foreach ($modules as $mod) {
        $stmtEtu = $pdo->prepare("SELECT etudiant_id FROM inscriptions WHERE module_id = ?");
        $stmtEtu->execute([$mod['id']]);
        $etudiants = $stmtEtu->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($etudiants)) continue;
        $nb_etu = count($etudiants);
        $place = false;
        
        shuffle($jours);

        foreach ($jours as $j) {
            $conflit_etu = false;
            foreach ($etudiants as $id_etu) {
                if (isset($etudiant_occupe_jour[$j][$id_etu])) { $conflit_etu = true; break; }
            }
            if ($conflit_etu) continue;

            $creneaux_test = $creneaux_base;
            shuffle($creneaux_test); 

            foreach ($creneaux_test as $h) {
                $ts = "$j $h";
                $salles_selectionnees = [];
                $capacite_cumulee = 0;

                // 1. Sélectionner un Amphi
                foreach ($amphis as $amp) {
                    if (!isset($salle_occupee[$ts][$amp['id']])) {
                        $salles_selectionnees[] = $amp;
                        $capacite_cumulee += $amp['capacite'];
                        break; 
                    }
                }

                if (empty($salles_selectionnees)) continue;

                // 2. Compléter avec des salles si nécessaire
                if ($capacite_cumulee < $nb_etu) {
                    foreach ($salles_normales as $sn) {
                        if (!isset($salle_occupee[$ts][$sn['id']])) {
                            $salles_selectionnees[] = $sn;
                            $capacite_cumulee += $sn['capacite'];
                            if ($capacite_cumulee >= $nb_etu) break;
                        }
                    }
                }

                // 3. Vérifier si assez de place ET assez de profs différents
                if ($capacite_cumulee >= $nb_etu) {
                    $temp_profs_disponibles = [];
                    asort($suivi_missions);
                    
                    // On cherche autant de profs que de salles sélectionnées
                    foreach ($suivi_missions as $id_p => $nb) {
                        if (($prof_count_jour[$j][$id_p] ?? 0) < 3 && !isset($prof_occupe_slot[$ts][$id_p])) {
                            $temp_profs_disponibles[] = $id_p;
                            if (count($temp_profs_disponibles) == count($salles_selectionnees)) break;
                        }
                    }

                    // Si on a assez de profs différents pour chaque lieu
                    if (count($temp_profs_disponibles) == count($salles_selectionnees)) {
                        $temp_etu = $etudiants;
                        
                        foreach ($salles_selectionnees as $index => $sc) {
                            $current_p_id = $temp_profs_disponibles[$index]; // Prof différent pour chaque index de salle
                            
                            $groupe = array_splice($temp_etu, 0, $sc['capacite']);
                            if (!empty($groupe)) {
                                $placeholders = implode(',', array_fill(0, count($groupe), '?'));
                                $upd = $pdo->prepare("UPDATE inscriptions SET salle_id = ? WHERE module_id = ? AND etudiant_id IN ($placeholders)");
                                $upd->execute(array_merge([$sc['id'], $mod['id']], $groupe));
                                foreach ($groupe as $id_etu) { $etudiant_occupe_jour[$j][$id_etu] = true; }
                            }

                            // Insertion avec le prof spécifique à cette salle
                            $ins = $pdo->prepare("INSERT INTO examens (module_id, date_heure, salle_id, prof_id, duree_minute) VALUES (?, ?, ?, ?, 90)");
                            $ins->execute([$mod['id'], $ts, $sc['id'], $current_p_id]);
                            
                            // Marquer salle et prof comme occupés
                            $salle_occupee[$ts][$sc['id']] = true;
                            $prof_occupe_slot[$ts][$current_p_id] = true;
                            $prof_count_jour[$j][$current_p_id] = ($prof_count_jour[$j][$current_p_id] ?? 0) + 1;
                            $suivi_missions[$current_p_id]++;
                            
                            $examens_crees++;
                        }
                        $place = true; 
                        break;
                    }
                }
            }
            if ($place) break;
        }
    }

    $pdo->commit();
    echo "Succès : " . $examens_crees . " examens générés avec un surveillant par salle.";

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "Erreur Fatale : " . $e->getMessage();
}
