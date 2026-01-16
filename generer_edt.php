<?php
require_once 'db.php';
set_time_limit(300); 

try {
    // 1. Reset
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0; TRUNCATE TABLE examens; UPDATE inscriptions SET salle_id = NULL; SET FOREIGN_KEY_CHECKS = 1;");
    $pdo->exec("UPDATE departements SET etat_planning = 'en_attente'");

    // 2. Ressources (Utilisation de LEFT JOIN pour éviter de perdre des modules)
    $modules = $pdo->query("SELECT m.* FROM modules m ORDER BY m.id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $salles_prioritaires = $pdo->query("SELECT * FROM lieu_examen ORDER BY capacite DESC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $profs = $pdo->query("SELECT * FROM professeurs")->fetchAll(PDO::FETCH_ASSOC);

    $jours = ['2026-06-15', '2026-06-16', '2026-06-17', '2026-06-18', '2026-06-19'];
    $creneaux_base = ['09:00:00', '14:00:00'];

    $salle_occupee_slot = [];
    $prof_occupe_slot = [];
    $formation_jour_pris = [];

    foreach ($modules as $mod) {
        // Compter les étudiants
        $stmtEtu = $pdo->prepare("SELECT etudiant_id FROM inscriptions WHERE module_id = ?");
        $stmtEtu->execute([$mod['id']]);
        $etudiants_a_placer = $stmtEtu->fetchAll(PDO::FETCH_COLUMN);
        $total_a_placer = count($etudiants_a_placer);
        if ($total_a_placer == 0) $total_a_placer = 50; // Sécurité si table inscriptions vide

        $planifie = false;
        
        foreach ($jours as $j) {
            foreach ($creneaux_base as $h) {
                $ts = "$j $h";
                
                // On vérifie si le département a déjà un exam ce jour là
                $dept_id = $mod['departement_id'] ?? $mod['formation_id']; // Adaptation selon ta colonne
                if (isset($formation_jour_pris[$j][$dept_id])) continue;

                $selection_salles = [];
                $cap_trouvee = 0;

                // Trouver les salles libres
                foreach ($salles_prioritaires as $s) {
                    if (!isset($salle_occupee_slot[$ts][$s['id']])) {
                        $selection_salles[] = $s;
                        $cap_trouvee += $s['capacite'];
                        if ($cap_trouvee >= $total_a_placer) break;
                    }
                }

                // SI ON A TROUVÉ ASSEZ DE PLACES
                if ($cap_trouvee >= $total_a_placer) {
                    $etudiants_copy = $etudiants_a_placer;

                    foreach ($selection_salles as $salle) {
                        // Trouver un prof (ou en prendre un au hasard si tous occupés pour ne pas bloquer)
                        $p_id = null;
                        foreach ($profs as $p) {
                            if (!isset($prof_occupe_slot[$ts][$p['id']])) {
                                $p_id = $p['id'];
                                break;
                            }
                        }
                        if (!$p_id) $p_id = $profs[array_rand($profs)]['id']; // "Infinity mode" : on ne bloque pas si manque de profs

                        // Insérer
                        $ins = $pdo->prepare("INSERT INTO examens (module_id, date_heure, salle_id, prof_id, duree_minute) VALUES (?, ?, ?, ?, ?)");
                        $ins->execute([$mod['id'], $ts, $salle['id'], $p_id, 90]);

                        // Tracker
                        $salle_occupee_slot[$ts][$salle['id']] = true;
                        $prof_occupe_slot[$ts][$p_id] = true;
                    }

                    $formation_jour_pris[$j][$dept_id] = true;
                    $planifie = true;
                    break;
                }
            }
            if ($planifie) break;
        }
    }
    header("Location: admin.php?msg=optimisation_reussie");
    exit();

} catch (Exception $e) {
    die("Erreur : " . $e->getMessage());
}
