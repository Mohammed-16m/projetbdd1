<?php
session_start();
require_once 'db.php';

// 1. SÉCURITÉ : Uniquement l'admin peut lancer l'optimisation
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Augmenter le temps d'exécution (5 minutes) pour les gros volumes de données
set_time_limit(300); 

try {
    echo "<h2 style='font-family: sans-serif; color: #1e293b;'>🚀 Optimisation de l'EDT en cours...</h2>";
    echo "<div style='font-family: monospace; background: #f8fafc; padding: 20px; border-radius: 10px; border: 1px solid #e2e8f0;'>";

    // --- ÉTAPE 0 : NETTOYAGE PRÉLIMINAIRE (Hors Transaction) ---
    // Note : TRUNCATE est une commande DDL qui force un commit, on le fait donc AVANT de commencer la transaction.
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $pdo->exec("TRUNCATE TABLE examens;");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

    // On remet tous les départements en 'en_attente' pour forcer une nouvelle validation par les chefs
    $pdo->exec("UPDATE departements SET etat_planning = 'en_attente'");
    
    // On vide les salles attribuées aux étudiants
    $pdo->exec("UPDATE inscriptions SET salle_id = NULL");

    // --- ÉTAPE 1 : DÉBUT DE LA TRANSACTION POUR LES DONNÉES ---
    $pdo->beginTransaction();

    // Chargement des ressources en mémoire pour limiter les requêtes SQL dans les boucles
    $modules = $pdo->query("SELECT * FROM modules")->fetchAll(PDO::FETCH_ASSOC);
    $salles_prioritaires = $pdo->query("SELECT * FROM lieu_examen ORDER BY capacite DESC")->fetchAll(PDO::FETCH_ASSOC);
    $profs = $pdo->query("SELECT * FROM professeurs")->fetchAll(PDO::FETCH_ASSOC);

    // Paramètres de l'algorithme
    $jours = ['2026-06-15', '2026-06-16', '2026-06-17', '2026-06-18', '2026-06-19', '2026-06-20'];
    $creneaux_base = ['09:00:00', '14:00:00'];

    // Tableaux de suivi pour éviter les doublons (Contraintes fortes)
    $salle_occupee_slot = []; // [timestamp][salle_id]
    $prof_occupe_slot = [];  // [timestamp][prof_id]
    $etudiant_occupe_jour = []; // [jour][etudiant_id]

    foreach ($modules as $mod) {
        // Récupérer la liste des étudiants inscrits à ce module
        $stmtEtu = $pdo->prepare("SELECT etudiant_id FROM inscriptions WHERE module_id = ?");
        $stmtEtu->execute([$mod['id']]);
        $etudiants_a_placer = $stmtEtu->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($etudiants_a_placer)) {
            echo "<span style='color: #64748b;'>ℹ️ {$mod['nom']} : Aucun inscrit, ignoré.</span><br>";
            continue;
        }

        $total_a_placer = count($etudiants_a_placer);
        $planifie = false;
        shuffle($jours); // Aléatoire pour mieux répartir sur la semaine

        foreach ($jours as $j) {
            // Vérification de conflit : on compte combien d'étudiants ont déjà un examen ce jour-là
            $conflit_count = 0;
            foreach ($etudiants_a_placer as $id_etu) {
                if (isset($etudiant_occupe_jour[$j][$id_etu])) $conflit_count++;
            }
            
            // Si plus de 10% des étudiants ont déjà un examen ce jour, on cherche un autre jour
            if ($conflit_count > ($total_a_placer * 0.1)) continue; 

            foreach ($creneaux_base as $h) {
                $ts = "$j $h";
                $selection_salles = [];
                $cap_cumulee = 0;

                // Trouver des salles libres pour ce créneau
                foreach ($salles_prioritaires as $s) {
                    if (!isset($salle_occupee_slot[$ts][$s['id']])) {
                        $selection_salles[] = $s;
                        $cap_cumulee += $s['capacite'];
                        if ($cap_cumulee >= $total_a_placer) break;
                    }
                }

                // Si on a assez de places cumulées dans les salles libres
                if ($cap_cumulee >= $total_a_placer) {
                    $copie_etudiants = $etudiants_a_placer;
                    
                    foreach ($selection_salles as $salle_choisie) {
                        // Attribution d'un professeur (Surveillant)
                        $p_id = null;
                        foreach ($profs as $p) {
                            if (!isset($prof_occupe_slot[$ts][$p['id']])) {
                                $p_id = $p['id']; 
                                break;
                            }
                        }
                        // Sécurité : si aucun prof de libre, on en prend un au hasard
                        if (!$p_id) $p_id = $profs[array_rand($profs)]['id'];

                        // Extraire le nombre d'étudiants correspondant à la capacité de la salle
                        $groupe_salle = array_splice($copie_etudiants, 0, $salle_choisie['capacite']);
                        
                        if (!empty($groupe_salle)) {
                            // Mise à jour des places dans la table inscriptions
                            $placeholders = implode(',', array_fill(0, count($groupe_salle), '?'));
                            $sqlUpd = "UPDATE inscriptions SET salle_id = ? WHERE module_id = ? AND etudiant_id IN ($placeholders)";
                            $updStmt = $pdo->prepare($sqlUpd);
                            $params = array_merge([$salle_choisie['id'], $mod['id']], $groupe_salle);
                            $updStmt->execute($params);

                            // Marquer les étudiants comme occupés pour ce jour
                            foreach ($groupe_salle as $id_etu) {
                                $etudiant_occupe_jour[$j][$id_etu] = true;
                            }
                        }

                        // Insérer l'examen
                        $ins = $pdo->prepare("INSERT INTO examens (module_id, date_heure, salle_id, prof_id, duree_minute) VALUES (?, ?, ?, ?, 90)");
                        $ins->execute([$mod['id'], $ts, $salle_choisie['id'], $p_id]);

                        // Verrouiller la salle et le prof pour ce créneau
                        $salle_occupee_slot[$ts][$salle_choisie['id']] = true;
                        $prof_occupe_slot[$ts][$p_id] = true;
                    }
                    
                    echo "<span style='color: #10b981;'>✅ <b>{$mod['nom']}</b></span> : Planifié le " . date('d/m', strtotime($j)) . " à $h<br>";
                    $planifie = true;
                    break; 
                }
            }
            if ($planifie) break;
        }
        if (!$planifie) {
            echo "<span style='color: #ef4444;'>❌ <b>{$mod['nom']}</b></span> : Échec (Pas de ressources disponibles)<br>";
        }
    }

    // --- ÉTAPE 2 : VALIDATION FINALE ---
    if ($pdo->inTransaction()) {
        $pdo->commit();
    }

    echo "</div>";
    echo "<div style='margin-top: 20px; font-family: sans-serif;'>";
    echo "<h3 style='color: #10b981;'>🎉 Optimisation terminée !</h3>";
    echo "<p>Tous les plannings ont été réinitialisés à l'état <b>'En attente'</b>.</p>";
    echo "<a href='admin.php' style='display: inline-block; padding: 12px 25px; background: #4361ee; color: white; text-decoration: none; border-radius: 8px; font-weight: bold;'>Retour au Dashboard</a>";
    echo "</div>";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("<h2 style='color: #ef4444;'>Erreur critique :</h2><p>" . $e->getMessage() . "</p>");
}
