<?php
session_start();
require_once 'db.php';

// Augmenter le temps d'exécution pour les gros volumes
set_time_limit(300); 

try {
    echo "<h2>🚀 Optimisation en cours...</h2>";

    // --- 1. NETTOYAGE (DOIT ÊTRE HORS TRANSACTION) ---
    // On désactive les clés étrangères pour vider proprement les tables
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $pdo->exec("TRUNCATE TABLE examens;");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

    // On réinitialise l'état pour que les chefs de dept voient le bouton "Publier"
    $pdo->exec("UPDATE departements SET etat_planning = 'en_attente'");
    $pdo->exec("UPDATE inscriptions SET salle_id = NULL");

    // --- 2. DÉMARRAGE DE LA TRANSACTION POUR LES CALCULS ---
    $pdo->beginTransaction();

    // Chargement des données en mémoire (ton code original)
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
            // Vérification de conflit (ton seuil de 10%)
            $conflit_count = 0;
            foreach ($etudiants_a_placer as $id_etu) {
                if (isset($etudiant_occupe_jour[$j][$id_etu])) $conflit_count++;
            }
            if ($conflit_count > ($total_a_placer * 0.1)) continue; 

            foreach ($creneaux_base as $h) {
                $ts = "$j $h";
                $selection_salles = [];
                $cap_cumulee = 0;

                // Sélection des salles disponibles
                foreach ($salles_prioritaires as $s) {
                    if (!isset($salle_occupee_slot[$ts][$s['id']])) {
                        $selection_salles[] = $s;
                        $cap_cumulee += $s['capacite'];
                        if ($cap_cumulee >= $total_a_placer) break;
                    }
                }

                if ($cap_cumulee >= $total_a_placer) {
                    $copie_etudiants = $etudiants_a_placer;
                    
                    foreach ($selection_salles as $salle_choisie) {
                        // Attribution Professeur
                        $p_id = null;
                        foreach ($profs as $p) {
                            if (!isset($prof_occupe_slot[$ts][$p['id']])) {
                                $p_id = $p['id']; break;
                            }
                        }
                        if (!$p_id) $p_id = $profs[array_rand($profs)]['id'];

                        // Extraction du groupe pour cette salle
                        $groupe_salle = array_splice($copie_etudiants, 0, $salle_choisie['capacite']);
                        
                        if (!empty($groupe_salle)) {
                            // UPDATE PAR LOT (BATCH)
                            $placeholders = implode(',', array_fill(0, count($groupe_salle), '?'));
                            $sqlUpd = "UPDATE inscriptions SET salle_id = ? WHERE module_id = ? AND etudiant_id IN ($placeholders)";
                            $updStmt = $pdo->prepare($sqlUpd);
                            
                            $params = array_merge([$salle_choisie['id'], $mod['id']], $groupe_salle);
                            $updStmt->execute($params);

                            // Marquer les étudiants comme occupés
                            foreach ($groupe_salle as $id_etu) {
                                $etudiant_occupe_jour[$j][$id_etu] = true;
                            }
                        }

                        // Création de l'examen
                        $ins = $pdo->prepare("INSERT INTO examens (module_id, date_heure, salle_id, prof_id, duree_minute) VALUES (?, ?, ?, ?, 90)");
                        $ins->execute([$mod['id'], $ts, $salle_choisie['id'], $p_id]);

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
        if (!$planifie) echo "<span style='color:red;'>❌ {$mod['nom']} : Échec (Pas de ressources)</span><br>";
    }

    // --- 3. VALIDATION FINALE ---
    $pdo->commit();
    echo "<h3>🎉 Optimisation terminée avec succès !</h3>";
    echo "<a href='admin.php' style='padding:10px; background:#3b82f6; color:white; text-decoration:none; border-radius:5px;'>Retour au Dashboard</a>";

} catch (Exception $e) {
    // Annuler uniquement si une transaction est en cours
    if ($pdo->inTransaction()) $pdo->rollBack();
    die("<h2 style='color:red;'>Erreur : " . $e->getMessage() . "</h2>");
}
