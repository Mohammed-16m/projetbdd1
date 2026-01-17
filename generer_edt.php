<?php
session_start();
require_once 'db.php';
set_time_limit(600); 

try {
    // --- 1. NETTOYAGE ---
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0; TRUNCATE TABLE examens; SET FOREIGN_KEY_CHECKS = 1;");
    $pdo->exec("UPDATE inscriptions SET salle_id = NULL;");
    $pdo->exec("UPDATE departements SET etat_planning = 'en_attente';");

    $pdo->beginTransaction();

    // --- 2. CHARGEMENT DES DONNÉES ---
    // On récupère les modules avec l'ID de leur département pour la priorité prof
    $modules = $pdo->query("SELECT m.*, d.id as dept_id FROM modules m JOIN departements d ON m.departement_id = d.id")->fetchAll(PDO::FETCH_ASSOC);
    $salles = $pdo->query("SELECT * FROM lieu_examen ORDER BY capacite DESC")->fetchAll(PDO::FETCH_ASSOC);
    $profs = $pdo->query("SELECT * FROM professeurs")->fetchAll(PDO::FETCH_ASSOC);

    $jours = ['2026-06-15', '2026-06-16', '2026-06-17', '2026-06-18', '2026-06-19', '2026-06-20'];
    $creneaux = ['09:00:00', '14:00:00'];

    // --- 3. TABLEAUX DE SUIVI DES CONTRAINTES ---
    $salle_occupee = [];       // [timestamp][salle_id]
    $prof_occupe_slot = [];    // [timestamp][prof_id]
    $prof_count_jour = [];     // [jour][prof_id] -> max 3
    $prof_total_missions = []; // [prof_id] -> pour l'équité
    $etudiant_occupe_jour = [];// [jour][etudiant_id] -> max 1/jour

    // Initialiser le compteur de missions pour chaque prof
    foreach ($profs as $p) { $prof_total_missions[$p['id']] = 0; }

    foreach ($modules as $mod) {
        $stmtEtu = $pdo->prepare("SELECT etudiant_id FROM inscriptions WHERE module_id = ?");
        $stmtEtu->execute([$mod['id']]);
        $etudiants = $stmtEtu->fetchAll(PDO::FETCH_COLUMN);
        $nb_etu = count($etudiants);
        if ($nb_etu == 0) continue;

        $place = false;
        shuffle($jours); 

        foreach ($jours as $j) {
            // --- CONTRAINTE : 1 EXAMEN / JOUR / ETUDIANT ---
            $conflit_etu = false;
            foreach ($etudiants as $id_etu) {
                if (isset($etudiant_occupe_jour[$j][$id_etu])) { $conflit_etu = true; break; }
            }
            if ($conflit_etu) continue;

            foreach ($creneaux as $h) {
                $ts = "$j $h";
                $salles_candidates = [];
                $cap_totale = 0;

                // --- CONTRAINTE : CAPACITÉ RÉELLE DES SALLES ---
                foreach ($salles as $s) {
                    if (!isset($salle_occupee[$ts][$s['id']])) {
                        $salles_candidates[] = $s;
                        $cap_totale += $s['capacite'];
                        if ($cap_totale >= $nb_etu) break;
                    }
                }

                if ($cap_totale >= $nb_etu) {
                    // --- PRIORITÉ & ÉQUITÉ PROFESSEURS ---
                    // Trier les profs : 1. Ceux du même département d'abord, 2. Ceux qui ont le moins de missions
                    usort($profs, function($a, $b) use ($mod, $prof_total_missions) {
                        $a_meme_dept = ($a['departement_id'] == $mod['dept_id']) ? 0 : 1;
                        $b_meme_dept = ($b['departement_id'] == $mod['dept_id']) ? 0 : 1;
                        if ($a_meme_dept !== $b_meme_dept) return $a_meme_dept - $b_meme_dept;
                        return $prof_total_missions[$a['id']] - $prof_total_missions[$b['id']];
                    });

                    $p_id = null;
                    foreach ($profs as $p) {
                        $c_jour = $prof_count_jour[$j][$p['id']] ?? 0;
                        // --- CONTRAINTE : MAX 3 / JOUR & LIBRE AU SLOT ---
                        if ($c_jour < 3 && !isset($prof_occupe_slot[$ts][$p['id']])) {
                            $p_id = $p['id'];
                            break;
                        }
                    }

                    if ($p_id) {
                        // Attribution
                        $temp_etu = $etudiants;
                        foreach ($salles_candidates as $sc) {
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
                        $prof_total_missions[$p_id]++; // Pour l'équité globale
                        $place = true;
                        break;
                    }
                }
            }
            if ($place) break;
        }
    }
    $pdo->commit();
    echo "Succès";
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500); echo "Erreur : " . $e->getMessage();
}
