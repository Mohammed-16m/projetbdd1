<?php
session_start();
require_once 'db.php';

// Augmenter le temps max d'exécution pour les calculs complexes
set_time_limit(600); 

// Affichage des erreurs pour le débogage
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    // --- 1. NETTOYAGE ET PRÉPARATION ---
    // On désactive les contraintes de clés étrangères temporairement pour vider proprement
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $pdo->exec("TRUNCATE TABLE examens;");
    $pdo->exec("UPDATE inscriptions SET salle_id = NULL;");
    $pdo->exec("UPDATE departements SET etat_planning = 'en_attente';");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

    // Début de la transaction
    $pdo->beginTransaction();

    // --- 2. CHARGEMENT DES DONNÉES ---
    // On récupère aussi le departement_id pour la priorité
    $modules = $pdo->query("SELECT m.*, d.id as dept_id FROM modules m LEFT JOIN departements d ON m.departement_id = d.id")->fetchAll(PDO::FETCH_ASSOC);
    $salles = $pdo->query("SELECT * FROM lieu_examen ORDER BY capacite DESC")->fetchAll(PDO::FETCH_ASSOC);
    $profs_data = $pdo->query("SELECT id, departement_id FROM professeurs")->fetchAll(PDO::FETCH_ASSOC);

    if (empty($profs_data) || empty($salles)) {
        throw new Exception("Erreur critique : Aucune salle ou aucun professeur dans la base.");
    }

    // Initialisation du compteur de missions pour l'équité
    $suivi_missions = [];
    foreach ($profs_data as $p) { 
        $suivi_missions[$p['id']] = 0; 
    }

    // Paramètres du calendrier
    $jours = ['2026-06-15', '2026-06-16', '2026-06-17', '2026-06-18', '2026-06-19', '2026-06-20'];
    $creneaux_base = ['08:00:00', '10:00:00', '12:00:00', '14:00:00'];

    // Tableaux de suivi des contraintes
    $salle_occupee = [];        // [date_heure][salle_id]
    $prof_occupe_slot = [];     // [date_heure][prof_id]
    $prof_count_jour = [];      // [date][prof_id] -> Max 3
    $etudiant_occupe_jour = []; // [date][etu_id] -> Max 1

    $examens_crees = 0;

    // --- 3. ALGORITHME ---
    foreach ($modules as $mod) {
        // Récupérer les étudiants inscrits
        $stmtEtu = $pdo->prepare("SELECT etudiant_id FROM inscriptions WHERE module_id = ?");
        $stmtEtu->execute([$mod['id']]);
        $etudiants = $stmtEtu->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($etudiants)) continue;

        $nb_etu = count($etudiants);
        $place = false;
        
        // On mélange les jours pour éviter de toujours remplir le lundi en premier
        shuffle($jours);

        foreach ($jours as $j) {
            
            // A. CONTRAINTE ÉTUDIANT (Max 1 exam / jour)
            // Si UN SEUL étudiant du groupe a déjà un examen ce jour-là, on saute le jour
            $conflit_etu = false;
            foreach ($etudiants as $id_etu) {
                if (isset($etudiant_occupe_jour[$j][$id_etu])) {
                    $conflit_etu = true; 
                    break;
                }
            }
            if ($conflit_etu) continue; // On tente le jour suivant

            // B. MÉLANGE DES CRÉNEAUX (Solution pour ne pas tout avoir à 08h)
            $creneaux = $creneaux_base;
            shuffle($creneaux); 

            foreach ($creneaux as $h) {
                $ts = "$j $h";
                
                // C. RECHERCHE DE SALLES
                $salles_candidates = []; 
                $cap_actuelle = 0;

                foreach ($salles as $s) {
                    if (!isset($salle_occupee[$ts][$s['id']])) {
                        $salles_candidates[] = $s; 
                        $cap_actuelle += $s['capacite'];
                        if ($cap_actuelle >= $nb_etu) break;
                    }
                }

                // D. SI SALLES SUFFISANTES -> CHOIX DU PROF
                if ($cap_actuelle >= $nb_etu) {
                    
                    // 1. Filtrer les profs disponibles (Libre ce créneau + < 3 exams ce jour)
                    $profs_eligibles = [];
                    foreach ($profs_data as $p) {
                        $c_jour = $prof_count_jour[$j][$p['id']] ?? 0;
                        if ($c_jour < 3 && !isset($prof_occupe_slot[$ts][$p['id']])) {
                            $profs_eligibles[] = $p;
                        }
                    }

                    if (!empty($profs_eligibles)) {
                        // 2. TRI INTELLIGENT (Équité > Priorité Dept > Aléatoire)
                        usort($profs_eligibles, function($a, $b) use ($suivi_missions, $mod) {
                            // Critère 1 : Celui qui a le MOINS travaillé passe d'abord
                            if ($suivi_missions[$a['id']] !== $suivi_missions[$b['id']]) {
                                return $suivi_missions[$a['id']] - $suivi_missions[$b['id']];
                            }
                            
                            // Critère 2 : Si égalité, priorité au prof du MEME département
                            $a_dept = ($a['departement_id'] == $mod['dept_id']) ? 1 : 0;
                            $b_dept = ($b['departement_id'] == $mod['dept_id']) ? 1 : 0;
                            if ($a_dept !== $b_dept) {
                                return $b_dept - $a_dept; // 1 (prioritaire) avant 0
                            }

                            // Critère 3 : Si toujours égalité, hasard (pour éviter de figer l'ordre)
                            return rand(-1, 1);
                        });

                        // Le gagnant est le premier de la liste triée
                        $p_id = $profs_eligibles[0]['id'];

                        // E. ENREGISTREMENT ET MISES À JOUR
                        $temp_etu = $etudiants;
                        foreach ($salles_candidates as $sc) {
                            $groupe = array_splice($temp_etu, 0, $sc['capacite']);
                            
                            if (!empty($groupe)) {
                                // Mise à jour des étudiants (salle + occupation jour)
                                $placeholders = implode(',', array_fill(0, count($groupe), '?'));
                                $upd = $pdo->prepare("UPDATE inscriptions SET salle_id = ? WHERE module_id = ? AND etudiant_id IN ($placeholders)");
                                $upd->execute(array_merge([$sc['id'], $mod['id']], $groupe));
                                
                                foreach ($groupe as $id_etu) { 
                                    $etudiant_occupe_jour[$j][$id_etu] = true; 
                                }
                            }
                            
                            // Création de l'examen
                            $ins = $pdo->prepare("INSERT INTO examens (module_id, date_heure, salle_id, prof_id, duree_minute) VALUES (?, ?, ?, ?, 90)");
                            $ins->execute([$mod['id'], $ts, $sc['id'], $p_id]);
                            
                            // Salle occupée
                            $salle_occupee[$ts][$sc['id']] = true;
                            $examens_crees++;
                        }

                        // Prof occupé + Incrément des compteurs
                        $prof_occupe_slot[$ts][$p_id] = true;
                        $prof_count_jour[$j][$p_id] = ($prof_count_jour[$j][$p_id] ?? 0) + 1;
                        $suivi_missions[$p_id]++; // +1 surveillance au total pour ce prof

                        $place = true; 
                        break; // On a placé ce module, on sort de la boucle créneaux
                    }
                }
            }
            if ($place) break; // On a placé ce module, on sort de la boucle jours
        }
    }

    $pdo->commit();
    echo "Succès : " . $examens_crees . " examens générés avec succès.";

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "Erreur Fatale : " . $e->getMessage();
}
