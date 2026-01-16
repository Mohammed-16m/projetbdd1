<?php
require_once 'db.php';
// On augmente le temps d'exécution car l'optimisation est un calcul lourd
set_time_limit(300); 

try {
    // =================================================================
    // 1. REMISE À ZÉRO ET PRÉPARATION
    // =================================================================
    
    // On met tous les départements en "brouillon" (en_attente)
    // Tant que le chef ne valide pas, les étudiants ne verront rien.
    $pdo->exec("UPDATE departements SET etat_planning = 'en_attente'");

    // On vide la table des examens et on détache les étudiants des salles
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $pdo->exec("TRUNCATE TABLE examens;");
    $pdo->exec("UPDATE inscriptions SET salle_id = NULL;");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

    // =================================================================
    // 2. RÉCUPÉRATION DES RESSOURCES
    // =================================================================
    
    // On récupère les modules avec le nombre d'inscrits pour gagner du temps
    // (Note: on suppose que tu as une table inscriptions)
    $modules = $pdo->query("SELECT m.*, d.nom as dept_nom 
                            FROM modules m 
                            JOIN departements d ON m.departement_id = d.id
                            ORDER BY m.id ASC")->fetchAll(PDO::FETCH_ASSOC);

    // IMPORTANT : On trie les salles par CAPACITÉ DÉCROISSANTE
    // L'algo prendra donc automatiquement : Amphi (200) -> Puis Salles (20) -> Puis Salles (20)...
    $salles_prioritaires = $pdo->query("SELECT * FROM lieu_examen ORDER BY capacite DESC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
    
    $profs = $pdo->query("SELECT * FROM professeurs")->fetchAll(PDO::FETCH_ASSOC);

    // Paramètres temporels
    $jours = ['2026-06-15', '2026-06-16', '2026-06-17', '2026-06-18', '2026-06-19'];
    $creneaux_base = ['09:00:00', '14:00:00']; // Matin et Après-midi

    // Trackers (Tableaux pour suivre qui est occupé quand)
    // Structure : $array['YYYY-MM-DD HH:MM:SS'][id] = true;
    $formation_jour_pris = []; 
    $salle_occupee_slot = [];
    $prof_occupe_slot = [];

    // =================================================================
    // 3. ALGORITHME DE PLACEMENT (TETRIS)
    // =================================================================

    foreach ($modules as $mod) {
        // A. Récupérer les étudiants inscrits à ce module
        $stmtEtu = $pdo->prepare("SELECT etudiant_id FROM inscriptions WHERE module_id = ?");
        $stmtEtu->execute([$mod['id']]);
        $etudiants_a_placer = $stmtEtu->fetchAll(PDO::FETCH_COLUMN);
        $total_a_placer = count($etudiants_a_placer);

        if ($total_a_placer == 0) continue; // Pas d'étudiants, on passe

        $planifie = false;
        shuffle($jours); // On mélange pour éviter que tout le monde passe le Lundi

        // --- Recherche d'un créneau (Jour + Heure) ---
        foreach ($jours as $j) {
            $creneaux_test = $creneaux_base;
            shuffle($creneaux_test);

            foreach ($creneaux_test as $h) {
                $ts = "$j $h"; // Timestamp complet

                // Règle 1 : Pas deux examens le même jour pour la même formation (Departement)
                // (Ou formation_id si tu as une colonne plus précise)
                if (isset($formation_jour_pris[$j][$mod['departement_id']])) continue;

                // --- Tentative de trouver assez de salles et de profs ---
                $salles_candidates = [];
                $profs_candidats = [];
                $capacite_trouvee = 0;
                
                // 1. On cherche les salles (Amphis d'abord grâce au tri SQL)
                foreach ($salles_prioritaires as $s) {
                    if (!isset($salle_occupee_slot[$ts][$s['id']])) {
                        // On prend la salle
                        $salles_candidates[] = $s;
                        $capacite_trouvee += $s['capacite'];

                        // On s'arrête dès qu'on a assez de place
                        if ($capacite_trouvee >= $total_a_placer) break;
                    }
                }

                // 2. Si on a assez de salles, on cherche les profs (1 par salle)
                if ($capacite_trouvee >= $total_a_placer) {
                    $besoin_profs = count($salles_candidates);
                    
                    foreach ($profs as $p) {
                        if (!isset($prof_occupe_slot[$ts][$p['id']])) {
                            $profs_candidats[] = $p['id'];
                            if (count($profs_candidats) == $besoin_profs) break;
                        }
                    }

                    // 3. A-t-on assez de profs pour surveiller toutes ces salles ?
                    if (count($profs_candidats) == $besoin_profs) {
                        
                        // === SUCCÈS : ON INSERT TOUT ===
                        $etudiants_restants = $etudiants_a_placer;
                        
                        // On parcourt les couples (Salle / Prof) trouvés
                        for ($i = 0; $i < count($salles_candidates); $i++) {
                            $salle = $salles_candidates[$i];
                            $prof_id = $profs_candidats[$i];

                            // On découpe le groupe d'étudiants pour cette salle
                            $nb_places = $salle['capacite'];
                            $groupe_etudiants = array_splice($etudiants_restants, 0, $nb_places);

                            // A. Création de l'examen (AVEC duree_minute)
                            $stmtIns = $pdo->prepare("INSERT INTO examens (module_id, date_heure, salle_id, prof_id, duree_minute) VALUES (?, ?, ?, ?, ?)");
                            $stmtIns->execute([$mod['id'], $ts, $salle['id'], $prof_id, 90]); // 90 min par défaut

                            // B. Mise à jour des inscriptions (Pour savoir qui passe où)
                            $stmtUpd = $pdo->prepare("UPDATE inscriptions SET salle_id = ? WHERE module_id = ? AND etudiant_id = ?");
                            foreach ($groupe_etudiants as $id_etu) {
                                $stmtUpd->execute([$salle['id'], $mod['id'], $id_etu]);
                            }

                            // C. Marquer les ressources comme occupées
                            $salle_occupee_slot[$ts][$salle['id']] = true;
                            $prof_occupe_slot[$ts][$prof_id] = true;
                        }

                        // Marquer la formation comme occupée ce jour-là
                        $formation_jour_pris[$j][$mod['departement_id']] = true;
                        
                        $planifie = true;
                        break; // Sortie boucle créneaux
                    }
                }
            }
            if ($planifie) break; // Sortie boucle jours
        }
    }

    // Redirection avec message de succès
    header("Location: admin.php?msg=optimisation_ok");
    exit();

} catch (Exception $e) {
    die("<h3>Erreur fatale lors de l'optimisation :</h3>" . $e->getMessage());
}
?>
