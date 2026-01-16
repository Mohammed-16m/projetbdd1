<?php
require_once 'db.php';
set_time_limit(300); 

try {
    // 1. NETTOYAGE TOTAL (On enlève le chaos de l'aléatoire)
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0; TRUNCATE TABLE examens; UPDATE inscriptions SET salle_id = NULL; SET FOREIGN_KEY_CHECKS = 1;");
    $pdo->exec("UPDATE departements SET etat_planning = 'en_attente'");

    // 2. RÉCUPÉRATION DES DONNÉES
    $modules = $pdo->query("SELECT * FROM modules")->fetchAll(PDO::FETCH_ASSOC);
    // On trie : Amphis (200+) en premier, Salles (20) après
    $salles_prioritaires = $pdo->query("SELECT * FROM lieu_examen ORDER BY capacite DESC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $profs = $pdo->query("SELECT * FROM professeurs")->fetchAll(PDO::FETCH_ASSOC);

    // Paramètres (On augmente les jours pour éviter les conflits)
    $jours = ['2026-06-15', '2026-06-16', '2026-06-17', '2026-06-18', '2026-06-19'];
    $creneaux = ['09:00:00', '14:00:00'];

    $salle_occupee_slot = [];
    $prof_occupe_slot = [];
    $formation_jour_pris = [];

    foreach ($modules as $mod) {
        // A. Compter les vrais inscrits
        $stmtEtu = $pdo->prepare("SELECT etudiant_id FROM inscriptions WHERE module_id = ?");
        $stmtEtu->execute([$mod['id']]);
        $etudiants_a_placer = $stmtEtu->fetchAll(PDO::FETCH_COLUMN);
        $total_a_placer = count($etudiants_a_placer);

        if ($total_a_placer == 0) continue; 

        $planifie = false;
        shuffle($jours); // Pour répartir équitablement

        foreach ($jours as $j) {
            foreach ($creneaux as $h) {
                $ts = "$j $h";
                
                // Règle : Un seul exam par jour pour cette formation
                $dept_id = $mod['departement_id'];
                if (isset($formation_jour_pris[$j][$dept_id])) continue;

                // --- LOGIQUE DE RÉPARTITION (AMPHI + SALLES) ---
                $salles_candidates = [];
                $cap_trouvee = 0;

                foreach ($salles_prioritaires as $s) {
                    if (!isset($salle_occupee_slot[$ts][$s['id']])) {
                        $salles_candidates[] = $s;
                        $cap_trouvee += $s['capacite'];
                        if ($cap_trouvee >= $total_a_placer) break;
                    }
                }

                // Si on a trouvé assez de place SANS CONFLIT
                if ($cap_trouvee >= $total_a_placer) {
                    $copie_etudiants = $etudiants_a_placer;
                    $success_profs = true;
                    $temp_profs = [];

                    // Vérifier si on a assez de profs DISPONIBLES
                    foreach ($salles_candidates as $salle) {
                        $p_id = null;
                        foreach ($profs as $p) {
                            if (!isset($prof_occupe_slot[$ts][$p['id']]) && !in_array($p['id'], $temp_profs)) {
                                $p_id = $p['id'];
                                break;
                            }
                        }
                        if ($p_id) {
                            $temp_profs[$salle['id']] = $p_id;
                        } else {
                            $success_profs = false; break; 
                        }
                    }

                    if ($success_profs) {
                        // ON VALIDE L'INSERTION
                        foreach ($salles_candidates as $salle) {
                            $prof_id = $temp_profs[$salle['id']];
                            $groupe = array_splice($copie_etudiants, 0, $salle['capacite']);
                            
                            // 1. Insertion Examen
                            $ins = $pdo->prepare("INSERT INTO examens (module_id, date_heure, salle_id, prof_id, duree_minute) VALUES (?, ?, ?, ?, 90)");
                            $ins->execute([$mod['id'], $ts, $salle['id'], $prof_id]);

                            // 2. Mise à jour Inscriptions (Pour le placement individuel)
                            $upd = $pdo->prepare("UPDATE inscriptions SET salle_id = ? WHERE module_id = ? AND etudiant_id = ?");
                            foreach ($groupe as $id_etu) {
                                $upd->execute([$salle['id'], $mod['id'], $id_etu]);
                            }

                            // 3. Marquer comme occupé (Empêche les conflits)
                            $salle_occupee_slot[$ts][$salle['id']] = true;
                            $prof_occupe_slot[$ts][$prof_id] = true;
                        }
                        $formation_jour_pris[$j][$dept_id] = true;
                        $planifie = true;
                        break;
                    }
                }
            }
            if ($planifie) break;
        }
    }
    header("Location: admin.php?msg=Optimisation_reussie_sans_conflits");
    exit();
} catch (Exception $e) {
    die("Erreur : " . $e->getMessage());
}
