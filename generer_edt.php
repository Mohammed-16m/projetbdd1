<?php
session_start();
require_once 'db.php';

// Augmenter le temps d'exécution pour les gros volumes
set_time_limit(300); 

try {
    echo "<h2>🚀 Optimisation en cours...</h2>";

    // --- ÉTAPE 0 : RÉINITIALISATION CRITIQUE (Hors Transaction pour plus de sécurité) ---
    // On repasse TOUS les départements en 'en_attente' dès le début
    // Cela force le Chef de Département à valider le nouveau planning
    $pdo->exec("UPDATE departements SET etat_planning = 'en_attente'");
    
    // On vide les attributions de salles des étudiants
    $pdo->exec("UPDATE inscriptions SET salle_id = NULL");

    // --- ÉTAPE 1 : DÉBUT DU TRAITEMENT DES DONNÉES ---
    $pdo->beginTransaction();

    // Nettoyage de la table des examens (Truncate est plus rapide que Delete)
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0; TRUNCATE TABLE examens; SET FOREIGN_KEY_CHECKS = 1;");

    // Chargement des données en mémoire
    $modules = $pdo->query("SELECT * FROM modules")->fetchAll(PDO::FETCH_ASSOC);
    $salles_prioritaires = $pdo->query("SELECT * FROM lieu_examen ORDER BY capacite DESC")->fetchAll(PDO::FETCH_ASSOC);
    $profs = $pdo->query("SELECT * FROM professeurs")->fetchAll(PDO::FETCH_ASSOC);

    $jours = ['2026-06-15', '2026-06-16', '2026-06-17', '2026-06-18', '2026-06-19', '2026-06-20'];
    $creneaux_base = ['09:00:00', '14:00:00'];

    $salle_occupee_slot = [];
    $prof_occupe_slot = [];
    $etudiant_occupe_jour = [];

    foreach ($modules as $mod) {
        // Récupérer les étudiants inscrits au module
        $stmtEtu = $pdo->prepare("SELECT etudiant_id FROM inscriptions WHERE module_id = ?");
        $stmtEtu->execute([$mod['id']]);
        $etudiants_a_placer = $stmtEtu->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($etudiants_a_placer)) continue;

        $total_a_placer = count($etudiants_a_placer);
        $planifie = false;
        shuffle($jours); 

        foreach ($jours as $j) {
            // Vérification de conflit étudiant (seuil de 10% de chevauchement max)
            $conflit_count = 0;
            foreach ($etudiants_a_placer as $id_etu) {
                if (isset($etudiant_occupe_jour[$j][$id_etu])) $conflit_count++;
            }
            if ($conflit_count > ($total_a_placer * 0.1)) continue; 

            foreach ($creneaux_base as $h) {
                $ts = "$j $h";
                $selection_salles = [];
                $cap_cumulee = 0;

                // Trouver les salles disponibles pour ce créneau
                foreach ($salles_prioritaires as $s) {
                    if (!isset($salle_occupee_slot[$ts][$s['id']])) {
                        $selection_salles[] = $s;
                        $cap_cumulee += $s['capacite'];
                        if ($cap_cumulee >= $total_a_placer) break;
                    }
                }

                // Si on a assez de place, on place l'examen
                if ($cap_cumulee >= $total_a_placer) {
                    $copie_etudiants = $etudiants_a_placer;
                    
                    foreach ($selection_salles as $salle_choisie) {
                        // Attribution d'un professeur disponible
                        $p_id = null;
                        foreach ($profs as $p) {
                            if (!isset($prof_occupe_slot[$ts][$p['id']])) {
                                $p_id = $p['id']; break;
                            }
                        }
                        // Si aucun prof n'est libre, on en prend un au hasard (sécurité)
                        if (!$p_id) $p_id = $profs[array_rand($profs)]['id'];

                        // Extraction des étudiants pour remplir cette salle
                        $groupe_salle = array_splice($copie_etudiants, 0, $salle_choisie['capacite']);
                        
                        if (!empty($groupe_salle)) {
                            // UPDATE PAR LOT (BATCH) pour les inscriptions
                            $placeholders = implode(',', array_fill(0, count($groupe_salle), '?'));
                            $sqlUpd = "UPDATE inscriptions SET salle_id = ? WHERE module_id = ? AND etudiant_id IN ($placeholders)";
                            $updStmt = $pdo->prepare($sqlUpd);
                            
                            $params = array_merge([$salle_choisie['id'], $mod['id']], $groupe_salle);
                            $updStmt->execute($params);

                            // Marquer les étudiants comme occupés ce jour-là
                            foreach ($groupe_salle as $id_etu) {
                                $etudiant_occupe_jour[$j][$id_etu] = true;
                            }
                        }

                        // Création de l'examen en base
                        $ins = $pdo->prepare("INSERT INTO examens (module_id, date_heure, salle_id, prof_id, duree_minute) VALUES (?, ?, ?, ?, 90)");
                        $ins->execute([$mod['id'], $ts, $salle_choisie['id'], $p_id]);

                        // Marquer salle et prof comme occupés pour ce créneau
                        $salle_occupee_slot[$ts][$salle_choisie['id']] = true;
                        $prof_occupe_slot[$ts][$p_id] = true;
                    }
                    
                    echo "✅ <b>{$mod['nom']}</b> : Planifié le $j à $h<br>";
                    $planifie = true;
                    break; 
                }
            }
            if ($planifie) break;
        }
        if (!$planifie) echo "<span style='color:red;'>❌ {$mod['nom']} : Échec (Pas de ressources disponibles)</span><br>";
    }

    // --- ÉTAPE 2 : VALIDATION FINALE ---
    $pdo->commit();
    echo "<hr><h3>🎉 Optimisation terminée avec succès !</h3>";
    echo "<p>L'état de tous les départements a été réinitialisé. Les chefs doivent valider l'EDT.</p>";
    echo "<a href='admin.php' style='display:inline-block; padding:12px 25px; background:#4361ee; color:white; text-decoration:none; border-radius:10px; font-weight:bold;'>Retour au Dashboard</a>";

} catch (Exception $e) {
    // En cas d'erreur, on annule tout ce qui était dans la transaction
    if ($pdo->inTransaction()) $pdo->rollBack();
    die("<h2 style='color:red;'>Erreur critique : " . $e->getMessage() . "</h2>");
}
