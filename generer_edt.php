<?php
session_start();
require_once 'db.php';

// Augmenter le temps d'exécution
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

    $salle_occupee = [];
    $prof_occupe = [];

    foreach ($modules as $mod) {
        $stmtEtu = $pdo->prepare("SELECT etudiant_id FROM inscriptions WHERE module_id = ?");
        $stmtEtu->execute([$mod['id']]);
        $etudiants = $stmtEtu->fetchAll(PDO::FETCH_COLUMN);
        $nb_etu = count($etudiants);

        if ($nb_etu == 0) continue;

        $place = false;
        shuffle($jours);

        foreach ($jours as $j) {
            foreach ($creneaux as $h) {
                $ts = "$j $h";
                $salles_candidates = [];
                $cap_totale = 0;

                foreach ($salles as $s) {
                    if (!isset($salle_occupee[$ts][$s['id']])) {
                        $salles_candidates[] = $s;
                        $cap_totale += $s['capacite'];
                        if ($cap_totale >= $nb_etu) break;
                    }
                }

                if ($cap_totale >= $nb_etu) {
                    // Trouver un prof libre
                    $p_id = $profs[array_rand($profs)]['id']; 
                    foreach($profs as $p) {
                        if(!isset($prof_occupe[$ts][$p['id']])) { $p_id = $p['id']; break; }
                    }

                    $temp_etu = $etudiants;
                    foreach ($salles_candidates as $sc) {
                        $groupe = array_splice($temp_etu, 0, $sc['capacite']);
                        if (!empty($groupe)) {
                            $placeholders = implode(',', array_fill(0, count($groupe), '?'));
                            $upd = $pdo->prepare("UPDATE inscriptions SET salle_id = ? WHERE module_id = ? AND etudiant_id IN ($placeholders)");
                            $upd->execute(array_merge([$sc['id'], $mod['id']], $groupe));
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
