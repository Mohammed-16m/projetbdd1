<?php
session_start();
require_once 'db.php';
set_time_limit(600); 

// On active l'affichage des erreurs pour comprendre le blocage
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    // 1. Nettoyage initial (HORS TRANSACTION pour éviter les verrous)
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $pdo->exec("TRUNCATE TABLE examens;");
    $pdo->exec("UPDATE inscriptions SET salle_id = NULL;");
    $pdo->exec("UPDATE departements SET etat_planning = 'en_attente';");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

    // 2. Début de la transaction
    $pdo->beginTransaction();

    $modules = $pdo->query("SELECT * FROM modules")->fetchAll(PDO::FETCH_ASSOC);
    $salles = $pdo->query("SELECT * FROM lieu_examen ORDER BY capacite DESC")->fetchAll(PDO::FETCH_ASSOC);
    $profs = $pdo->query("SELECT id FROM professeurs")->fetchAll(PDO::FETCH_ASSOC);

    if (empty($profs) || empty($salles)) {
        throw new Exception("Erreur : Table professeurs ou lieu_examen vide !");
    }

    // Compteur pour l'équité des profs
    $suivi_missions = [];
    foreach ($profs as $p) { $suivi_missions[$p['id']] = 0; }

    $jours = ['2026-06-15', '2026-06-16', '2026-06-17', '2026-06-18', ];
    $creneaux = ['08:00:00', '10:00:00','12:00:00','14:00:00'];

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
            // Contrainte 1 exam/jour/etudiant
            $conflit_etu = false;
            foreach ($etudiants as $id_etu) {
                if (isset($etudiant_occupe_jour[$j][$id_etu])) {
                    $conflit_etu = true; break;
                }
            }
            if ($conflit_etu) continue;

            foreach ($creneaux as $h) {
                $ts = "$j $h";
                $salles_c = []; $cap = 0;

                foreach ($salles as $s) {
                    if (!isset($salle_occupee[$ts][$s['id']])) {
                        $salles_c[] = $s; $cap += $s['capacite'];
                        if ($cap >= $nb_etu) break;
                    }
                }

                if ($cap >= $nb_etu) {
                    // Équité des profs : on trie par nombre de missions
                    asort($suivi_missions);
                    $p_id = null;
                    foreach ($suivi_missions as $id_prof => $nb) {
                        if (($prof_count_jour[$j][$id_prof] ?? 0) < 3 && !isset($prof_occupe_slot[$ts][$id_prof])) {
                            $p_id = $id_prof; break;
                        }
                    }

                    if ($p_id) {
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
    // On renvoie un message clair pour le debug
    echo "Succès : " . $examens_crees . " lignes créées.";

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "Erreur Fatale : " . $e->getMessage();
}
