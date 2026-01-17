<?php
session_start();
require_once 'db.php';

// Augmenter le temps d'exécution car les vérifications sont plus lourdes
set_time_limit(300); 

try {
    // --- 1. NETTOYAGE (HORS TRANSACTION) ---
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $pdo->exec("TRUNCATE TABLE examens;");
    $pdo->exec("UPDATE inscriptions SET salle_id = NULL;");
    $pdo->exec("UPDATE departements SET etat_planning = 'en_attente';");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

    // --- 2. DÉBUT DU CALCUL ---
    $pdo->beginTransaction();

    $modules = $pdo->query("SELECT * FROM modules")->fetchAll(PDO::FETCH_ASSOC);
    $salles = $pdo->query("SELECT * FROM lieu_examen ORDER BY capacite DESC")->fetchAll(PDO::FETCH_ASSOC);
    $profs = $pdo->query("SELECT * FROM professeurs")->fetchAll(PDO::FETCH_ASSOC);

    $jours = ['2026-06-15', '2026-06-16', '2026-06-17', '2026-06-18', '2026-06-19'];
    $creneaux = ['09:00:00', '14:00:00'];

    $salle_occupee = []; // [timestamp][salle_id]
    $prof_occupe = [];  // [timestamp][prof_id]
    $etudiant_occupe_jour = []; // [jour][etudiant_id] <-- Pour la contrainte 1 exam/jour

    foreach ($modules as $mod) {
        // Récupérer les étudiants inscrits
        $stmtEtu = $pdo->prepare("SELECT etudiant_id FROM inscriptions WHERE module_id = ?");
        $stmtEtu->execute([$mod['id']]);
        $etudiants = $stmtEtu->fetchAll(PDO::FETCH_COLUMN);
        $nb_etu = count($etudiants);

        if ($nb_etu == 0) continue;

        $place = false;
        shuffle($jours); // Aléatoire pour répartir la charge

        foreach ($jours as $j) {
            
            // --- VERIFICATION CONTRAINTE ETUDIANT ---
            // On vérifie si UN SEUL étudiant du module a déjà un examen ce jour-là
            $conflit_detecte = false;
            foreach ($etudiants as $id_etu) {
                if (isset($etudiant_occupe_jour[$j][$id_etu])) {
                    $conflit_detecte = true;
                    break; // Un seul conflit suffit à rejeter le jour pour tout le groupe
                }
            }
            if ($conflit_detecte) continue; // On passe au jour suivant

            foreach ($creneaux as $h) {
                $ts = "$j $h";
                $salles_candidates = [];
                $cap_totale = 0;

                // 1. Trouver les salles libres
                foreach ($salles as $s) {
                    if (!isset($salle_occupee[$ts][$s['id']])) {
                        $salles_candidates[] = $s;
                        $cap_totale += $s['capacite'];
                        if ($cap_totale >= $nb_etu) break;
                    }
                }

                // 2. Si assez de place et jour sans conflit
                if ($cap_totale >= $nb_etu) {
                    
                    // Trouver un prof libre (rotation avec shuffle)
                    $p_id = null;
                    shuffle($profs);
                    foreach($profs as $p) {
                        if(!isset($prof_occupe[$ts][$p['id']])) { 
                            $p_id = $p['id']; 
                            break; 
                        }
                    }
                    if (!$p_id) $p_id = $profs[0]['id'];

                    // 3. Attribution
                    $temp_etu = $etudiants;
                    foreach ($salles_candidates as $sc) {
                        $groupe = array_splice($temp_etu, 0, $sc['capacite']);
                        
                        if (!empty($groupe)) {
                            $placeholders = implode(',', array_fill(0, count($groupe), '?'));
                            $upd = $pdo->prepare("UPDATE inscriptions SET salle_id = ? WHERE module_id = ? AND etudiant_id IN ($placeholders)");
                            $upd->execute(array_merge([$sc['id'], $mod['id']], $groupe));
                            
                            // Marquer chaque étudiant comme occupé pour TOUTE LA JOURNÉE
                            foreach ($groupe as $id_etu) {
                                $etudiant_occupe_jour[$j][$id_etu] = true;
                            }
                        }

                        $ins = $pdo->prepare("INSERT INTO examens (module_id, date_heure, salle_id, prof_id, duree_minute) VALUES (?, ?, ?, ?, 90)");
                        $ins->execute([$mod['id'], $ts, $sc['id'], $p_id]);
                        $salle_occupee[$ts][$sc['id']] = true;
                    }
                    
                    $prof_occupe[$ts][$p_id] = true;
                    $place = true;
                    break;
                }
            }
            if ($place) break;
        }
    }

    $pdo->commit();
    echo "Succès";

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo "Erreur : " . $e->getMessage();
}
