<?php
session_start();
require_once 'db.php';

// Augmenter les limites pour éviter les arrêts brutaux
ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(600); 

try {
    echo "<h2>🛠️ Diagnostic de l'Optimisation</h2>";

    // 1. NETTOYAGE SÉCURISÉ
    echo "Nettoyage des anciennes données... ";
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $pdo->exec("TRUNCATE TABLE examens;");
    $pdo->exec("UPDATE inscriptions SET salle_id = NULL;");
    $pdo->exec("UPDATE departements SET etat_planning = 'en_attente';");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    echo "✅ OK<br>";

    // 2. CHARGEMENT ET VÉRIFICATION
    $modules = $pdo->query("SELECT * FROM modules")->fetchAll(PDO::FETCH_ASSOC);
    $salles = $pdo->query("SELECT * FROM lieu_examen ORDER BY capacite DESC")->fetchAll(PDO::FETCH_ASSOC);
    $profs = $pdo->query("SELECT * FROM professeurs")->fetchAll(PDO::FETCH_ASSOC);

    echo "📊 Stats : ".count($modules)." modules, ".count($salles)." salles, ".count($profs)." profs chargés.<br><hr>";

    if (empty($salles)) die("<b style='color:red;'>ERREUR : Aucune salle trouvée dans la table lieu_examen !</b>");

    // 3. DÉBUT DU CALCUL
    $pdo->beginTransaction();

    $jours = ['2026-06-15', '2026-06-16', '2026-06-17', '2026-06-18', '2026-06-19', '2026-06-20'];
    $creneaux = ['09:00:00', '14:00:00'];

    $salle_occupee_slot = [];
    $prof_occupe_slot = [];
    $etudiant_occupe_jour = [];

    foreach ($modules as $mod) {
        $stmtEtu = $pdo->prepare("SELECT etudiant_id FROM inscriptions WHERE module_id = ?");
        $stmtEtu->execute([$mod['id']]);
        $etudiants = $stmtEtu->fetchAll(PDO::FETCH_COLUMN);
        
        $nb_etudiants = count($etudiants);
        if ($nb_etudiants == 0) continue;

        $planifie = false;
        shuffle($jours); 

        foreach ($jours as $j) {
            foreach ($creneaux as $h) {
                $ts = "$j $h";
                $selection_salles = [];
                $cap_cumulee = 0;

                // Recherche de salles
                foreach ($salles as $s) {
                    if (!isset($salle_occupee_slot[$ts][$s['id']])) {
                        $selection_salles[] = $s;
                        $cap_cumulee += $s['capacite'];
                        if ($cap_cumulee >= $nb_etudiants) break;
                    }
                }

                if ($cap_cumulee >= $nb_etudiants) {
                    // Attribution du prof
                    $p_id = null;
                    foreach ($profs as $p) {
                        if (!isset($prof_occupe_slot[$ts][$p['id']])) {
                            $p_id = $p['id']; break;
                        }
                    }
                    if (!$p_id) $p_id = $profs[array_rand($profs)]['id'];

                    // Attribution effective
                    $temp_etudiants = $etudiants;
                    foreach ($selection_salles as $s_choisie) {
                        $groupe = array_splice($temp_etudiants, 0, $s_choisie['capacite']);
                        if (!empty($groupe)) {
                            $placeholders = implode(',', array_fill(0, count($groupe), '?'));
                            $upd = $pdo->prepare("UPDATE inscriptions SET salle_id = ? WHERE module_id = ? AND etudiant_id IN ($placeholders)");
                            $upd->execute(array_merge([$s_choisie['id'], $mod['id']], $groupe));
                        }
                        
                        $ins = $pdo->prepare("INSERT INTO examens (module_id, date_heure, salle_id, prof_id, duree_minute) VALUES (?, ?, ?, ?, 90)");
                        $ins->execute([$mod['id'], $ts, $s_choisie['id'], $p_id]);
                        
                        $salle_occupee_slot[$ts][$s_choisie['id']] = true;
                    }
                    
                    $prof_occupe_slot[$ts][$p_id] = true;
                    echo "✅ <b>{$mod['nom']}</b> ($nb_etudiants étu.) -> $ts<br>";
                    $planifie = true;
                    break;
                }
            }
            if ($planifie) break;
        }
        if (!$planifie) echo "<span style='color:red;'>⚠️ {$mod['nom']} : Impossible à placer (Capacité insuffisante)</span><br>";
    }

    $pdo->commit();
    echo "<hr><h3>🎉 Terminé !</h3><a href='admin.php'>Retour au Dashboard</a>";

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "<h2 style='color:red;'>ERREUR : " . $e->getMessage() . "</h2>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
