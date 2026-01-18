<?php
session_start();
require_once 'db.php';
set_time_limit(600); 

ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    // ... (Partie Nettoyage inchangée) ...
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0; TRUNCATE TABLE examens; SET FOREIGN_KEY_CHECKS = 1;");
    $pdo->exec("UPDATE inscriptions SET salle_id = NULL; UPDATE departements SET etat_planning = 'en_attente';");
    $pdo->beginTransaction();

    $modules = $pdo->query("SELECT * FROM modules")->fetchAll(PDO::FETCH_ASSOC);
    $salles = $pdo->query("SELECT * FROM lieu_examen ORDER BY capacite DESC")->fetchAll(PDO::FETCH_ASSOC);
    $profs = $pdo->query("SELECT id FROM professeurs")->fetchAll(PDO::FETCH_ASSOC);

    // ... (Vérifications inchangées) ...

    $suivi_missions = [];
    foreach ($profs as $p) { $suivi_missions[$p['id']] = 0; }

    $jours = ['2026-06-15', '2026-06-16', '2026-06-17', '2026-06-18', '2026-06-19', '2026-06-20'];
    
    // --- MODIFICATION 1 : AJOUT DES CRÉNEAUX ICI ---
    $creneaux_base = ['08:00:00', '10:00:00', '12:00:00', '14:00:00', '16:00:00'];

    $salle_occupee = [];
    $prof_occupe_slot = [];
    $prof_count_jour = [];
    $etudiant_occupe_jour = [];
    $examens_crees = 0;

    foreach ($modules as $mod) {
        // ... (Récupération étudiants inchangée) ...
        $stmtEtu = $pdo->prepare("SELECT etudiant_id FROM inscriptions WHERE module_id = ?");
        $stmtEtu->execute([$mod['id']]);
        $etudiants = $stmtEtu->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($etudiants)) continue;
        $nb_etu = count($etudiants);
        $place = false;
        
        shuffle($jours);

        foreach ($jours as $j) {
            // Vérification conflit étudiant
            $conflit_etu = false;
            foreach ($etudiants as $id_etu) {
                if (isset($etudiant_occupe_jour[$j][$id_etu])) { $conflit_etu = true; break; }
            }
            if ($conflit_etu) continue;

            // --- MODIFICATION 2 : MÉLANGE DES CRÉNEAUX ---
            // On copie le tableau de base pour ne pas le modifier définitivement, et on le mélange
            $creneaux_test = $creneaux_base;
            shuffle($creneaux_test); 

            // On boucle sur la version mélangée ($creneaux_test)
            foreach ($creneaux_test as $h) {
                $ts = "$j $h";
                
                // ... (Reste du code inchangé : recherche salle, prof, etc.) ...
                $salles_c = []; $cap = 0;
                foreach ($salles as $s) {
                    if (!isset($salle_occupee[$ts][$s['id']])) {
                        $salles_c[] = $s; $cap += $s['capacite'];
                        if ($cap >= $nb_etu) break;
                    }
                }

                if ($cap >= $nb_etu) {
                    asort($suivi_missions);
                    $p_id = null;
                    foreach ($suivi_missions as $id_prof => $nb) {
                        if (($prof_count_jour[$j][$id_prof] ?? 0) < 3 && !isset($prof_occupe_slot[$ts][$id_prof])) {
                            $p_id = $id_prof; break;
                        }
                    }

                    if ($p_id) {
                        // ... (Logique d'insertion inchangée) ...
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
                            $examens_crees++;
                        }
                        $prof_occupe_slot[$ts][$p_id] = true;
                        $prof_count_jour[$j][$p_id] = ($prof_count_jour[$j][$p_id] ?? 0) + 1;
                        $suivi_missions[$p_id]++;
                        $place = true; break;
                    }
                }
            }
            if ($place) break;
        }
    }

    $pdo->commit();
    echo "Succès : " . $examens_crees . " lignes créées.";

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "Erreur Fatale : " . $e->getMessage();
}
