<?php
session_start();
require_once 'db.php';
set_time_limit(600); 

try {
    // 1. Nettoyage complet
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0; TRUNCATE TABLE examens; SET FOREIGN_KEY_CHECKS = 1;");
    $pdo->exec("UPDATE inscriptions SET salle_id = NULL;");
    $pdo->exec("UPDATE departements SET etat_planning = 'en_attente';");

    $pdo->beginTransaction();

    // 2. Chargement des données
    $modules = $pdo->query("SELECT m.*, d.id as dept_id FROM modules m JOIN departements d ON m.departement_id = d.id")->fetchAll(PDO::FETCH_ASSOC);
    $salles = $pdo->query("SELECT * FROM lieu_examen ORDER BY capacite DESC")->fetchAll(PDO::FETCH_ASSOC);
    $profs_data = $pdo->query("SELECT id, departement_id FROM professeurs")->fetchAll(PDO::FETCH_ASSOC);

    // Initialisation des compteurs pour l'équité
    $suivi_missions = [];
    foreach ($profs_data as $p) { $suivi_missions[$p['id']] = 0; }

    // Calendrier (Vérifie que ces dates couvrent assez de jours)
    $jours = ['2026-06-15', '2026-06-16', '2026-06-17', '2026-06-18', '2026-06-19', '2026-06-20', '2026-06-21', '2026-06-22'];
    $creneaux = ['09:00:00', '14:00:00'];

    $salle_occupee = [];
    $prof_occupe_slot = [];
    $prof_count_jour = [];
    $etudiant_occupe_jour = [];
    
    $exam_count = 0;

    foreach ($modules as $mod) {
        $stmtEtu = $pdo->prepare("SELECT etudiant_id FROM inscriptions WHERE module_id = ?");
        $stmtEtu->execute([$mod['id']]);
        $etudiants = $stmtEtu->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($etudiants)) continue;

        $nb_etu = count($etudiants);
        $place = false;
        shuffle($jours);

        foreach ($jours as $j) {
            // --- VERIFICATION CONTRAINTE ETUDIANT ---
            $conflit_etu = false;
            foreach ($etudiants as $id_etu) {
                if (isset($etudiant_occupe_jour[$j][$id_etu])) {
                    $conflit_etu = true; 
                    break;
                }
            }
            
            // Si trop de conflits ce jour-là, on tente un autre jour
            if ($conflit_etu) continue; 

            foreach ($creneaux as $h) {
                $ts = "$j $h";
                $salles_c = []; $cap = 0;

                // --- TROUVER SALLES LIBRES ---
                foreach ($salles as $s) {
                    if (!isset($salle_occupee[$ts][$s['id']])) {
                        $salles_c[] = $s; 
                        $cap += $s['capacite'];
                        if ($cap >= $nb_etu) break;
                    }
                }

                if ($cap >= $nb_etu) {
                    // --- TROUVER PROF LIBRE (EQUITE + DEPT) ---
                    $profs_eligibles = [];
                    foreach ($profs_data as $p) {
                        $c_jour = $prof_count_jour[$j][$p['id']] ?? 0;
                        if ($c_jour < 3 && !isset($prof_occupe_slot[$ts][$p['id']])) {
                            $profs_eligibles[] = $p;
                        }
                    }

                    if (!empty($profs_eligibles)) {
                        // Tri : Missions d'abord, Département ensuite
                        usort($profs_eligibles, function($a, $b) use ($suivi_missions, $mod) {
                            if ($suivi_missions[$a['id']] != $suivi_missions[$b['id']]) {
                                return $suivi_missions[$a['id']] - $suivi_missions[$b['id']];
                            }
                            $a_dept = ($a['departement_id'] == $mod['dept_id']) ? 0 : 1;
                            $b_dept = ($b['departement_id'] == $mod['dept_id']) ? 0 : 1;
                            return $a_dept - $b_dept;
                        });

                        $p_id = $profs_eligibles[0]['id'];

                        // --- ENREGISTREMENT ---
                        $temp_etu = $etudiants;
                        foreach ($salles_c as $sc) {
                            $groupe = array_splice($temp_etu, 0, $sc['capacite']);
                            if (!empty($groupe)) {
                                $placeholders = implode(',', array_fill(0, count($groupe), '?'));
                                $upd = $pdo->prepare("UPDATE inscriptions SET salle_id = ? WHERE module_id = ? AND etudiant_id IN ($placeholders)");
                                $upd->execute(array_merge([$sc['id'], $mod['id']], $groupe));
                                foreach ($groupe as $id_etu) { $etudiant_occupe_jour[$j][$id_etu] = true; }
                            }
                            $ins = $pdo->prepare("INSERT INTO examens (module_id, date_heure, salle_id, prof_id, duree_minute) VALUES (?, ?, ?, ?, 90)");
                            $ins->execute([$mod['id'], $ts, $sc['id'], $p_id]);
                            $salle_occupee[$ts][$sc['id']] = true;
                        }

                        $prof_occupe_slot[$ts][$p_id] = true;
                        $prof_count_jour[$j][$p_id] = ($prof_count_jour[$j][$p_id] ?? 0) + 1;
                        $suivi_missions[$p_id]++;
                        $exam_count++;
                        $place = true; break;
                    }
                }
            }
            if ($place) break;
        }
    }

    $pdo->commit();
    echo "Succès : " . $exam_count . " examens générés.";

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "Erreur : " . $e->getMessage();
}
