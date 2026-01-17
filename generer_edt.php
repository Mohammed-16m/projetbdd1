<?php
session_start();
// AJOUTE CES LIGNES ICI :
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'db.php';
set_time_limit(600); 

try {
    // 1. Nettoyage
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0; TRUNCATE TABLE examens; SET FOREIGN_KEY_CHECKS = 1;");
    $pdo->exec("UPDATE inscriptions SET salle_id = NULL; UPDATE departements SET etat_planning = 'en_attente';");

    $pdo->beginTransaction();

    // 2. Chargement des ressources
    $modules = $pdo->query("SELECT * FROM modules")->fetchAll(PDO::FETCH_ASSOC);
    $salles = $pdo->query("SELECT * FROM lieu_examen ORDER BY capacite DESC")->fetchAll(PDO::FETCH_ASSOC);
    $profs_data = $pdo->query("SELECT id, departement_id FROM professeurs")->fetchAll(PDO::FETCH_ASSOC);

    // Initialisation des compteurs de surveillance (tous à 0 au début)
    $suivi_missions = [];
    foreach ($profs_data as $p) {
        $suivi_missions[$p['id']] = 0;
    }

    $jours = ['2026-06-15', '2026-06-16', '2026-06-17', '2026-06-18', '2026-06-19', '2026-06-20'];
    $creneaux = ['09:00:00', '14:00:00'];

    $salle_occupee = [];
    $prof_occupe_slot = [];
    $prof_count_jour = [];
    $etudiant_occupe_jour = [];

    foreach ($modules as $mod) {
        $stmtEtu = $pdo->prepare("SELECT etudiant_id FROM inscriptions WHERE module_id = ?");
        $stmtEtu->execute([$mod['id']]);
        $etudiants = $stmtEtu->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($etudiants)) continue;

        $nb_etu = count($etudiants);
        $place = false;
        shuffle($jours);

        foreach ($jours as $j) {
            // Contrainte Etudiant (1/jour)
            $deja_pris = array_intersect($etudiants, array_keys($etudiant_occupe_jour[$j] ?? []));
            if (!empty($deja_pris)) continue;

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
                    
                    // --- LOGIQUE D'ÉQUITÉ DES PROFS ---
                    // On trie les profs pour prendre celui qui a le MOINS de missions au total
                    asort($suivi_missions); 
                    
                    $p_id = null;
                    foreach ($suivi_missions as $id_prof => $nb_missions) {
                        $c_jour = $prof_count_jour[$j][$id_prof] ?? 0;
                        
                        // Vérifier : Max 3/jour ET Libre sur ce créneau
                        if ($c_jour < 3 && !isset($prof_occupe_slot[$ts][$id_prof])) {
                            $p_id = $id_prof;
                            break;
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
                        }
                        
                        // Mise à jour des compteurs pour l'équité
                        $prof_occupe_slot[$ts][$p_id] = true;
                        $prof_count_jour[$j][$p_id] = ($prof_count_jour[$j][$p_id] ?? 0) + 1;
                        $suivi_missions[$p_id]++; // On incrémente le nombre total de missions
                        
                        $place = true; break;
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
    echo "Erreur : " . $e->getMessage();
}
