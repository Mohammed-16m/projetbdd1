<?php
require_once 'db.php';

// On donne plus de temps au serveur pour traiter les 1500 lignes
set_time_limit(600); 

try {
    echo "<h2>🚀 Initialisation et Remplissage de la Base de Données</h2>";

    // --- 1. NETTOYAGE DES DONNÉES EXISTANTES ---
    echo "Nettoyage en cours... ";
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $pdo->exec("TRUNCATE TABLE inscriptions;");
    $pdo->exec("TRUNCATE TABLE etudiants;");
    $pdo->exec("TRUNCATE TABLE professeurs;");
    $pdo->exec("TRUNCATE TABLE users;");
    $pdo->exec("TRUNCATE TABLE lieu_examen;");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    echo "✅ OK<br>";

    // --- 2. AJOUT DES SALLES ---
    echo "Création des salles... ";
    for ($i = 1; $i <= 30; $i++) {
        $pdo->prepare("INSERT INTO lieu_examen (nom, capacite) VALUES (?, 40)")
            ->execute(["Salle TD " . $i]);
    }
    echo "✅ OK<br>";

    // --- 3. AJOUT DES PROFESSEURS ---
    echo "Création des 40 professeurs... ";
    for ($i = 1; $i <= 40; $i++) {
        $username = "prof" . $i;
        $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, 'prof123', 'professeur')")
            ->execute([$username]);
        $last_id = $pdo->lastInsertId();
        
        $dept = ($i % 3) + 1; // Répartit sur Dept 1, 2 et 3
        $pdo->prepare("INSERT INTO professeurs (id, nom_affichage, dept_id, specialite) VALUES (?, ?, ?, 'Enseignant')")
            ->execute([$last_id, "Dr. " . ucfirst($username), $dept]);
    }
    echo "✅ OK<br>";

    // --- 4. AJOUT DES 1500 ÉTUDIANTS (AVEC TRANSACTION) ---
    echo "Insertion massive des 1500 étudiants (soyez patient)... ";
    
    // Début de la transaction pour la vitesse et éviter les erreurs 502
    $pdo->beginTransaction();

    for ($i = 1; $i <= 1500; $i++) {
        $usernameEtu = "etudiant" . $i;
        
        // Créer l'utilisateur système
        $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, 'etu123', 'etudiant')")
            ->execute([$usernameEtu]);
        $id_etu = $pdo->lastInsertId();

        // Logique de séparation par Département
        if ($i <= 500) { 
            $dept_id = 1; $start_mod = 1; 
        } elseif ($i <= 1000) { 
            $dept_id = 2; $start_mod = 6; 
        } else { 
            $dept_id = 3; $start_mod = 11; 
        }

        // Créer le profil étudiant
        $pdo->prepare("INSERT INTO etudiants (id, nom_affichage, formation_id, promo, email) VALUES (?, ?, ?, '2026', ?)")
            ->execute([$id_etu, "Etudiant_" . $i, $dept_id, "etu$i@univ.dz"]);

        // Inscription aux 5 modules du département
        $stmtI = $pdo->prepare("INSERT INTO inscriptions (etudiant_id, module_id) VALUES (?, ?)");
        for ($m = 0; $m < 5; $m++) {
            $stmtI->execute([$id_etu, $start_mod + $m]);
        }
    }

    // On valide tout d'un coup
    $pdo->commit();
    echo "✅ OK<br>";

    // --- 5. AJOUT DE L'ADMIN ET DU DOYEN ---
    $pdo->prepare("INSERT INTO users (username, password, role) VALUES ('admin', 'admin123', 'admin')")->execute();
    $pdo->prepare("INSERT INTO users (username, password, role) VALUES ('doyen', 'doyen123', 'doyen')")->execute();

    echo "<br><h2>🎉 TOUT EST PRÊT !</h2>";
    echo "Vous pouvez maintenant vous connecter avec :<br>";
    echo "- Étudiant : <b>etudiant1</b> / <b>etu123</b><br>";
    echo "- Professeur : <b>prof1</b> / <b>prof123</b><br>";
    echo "- Admin : <b>admin</b> / <b>admin123</b>";

} catch (Exception $e) {
    // Si une erreur survient, on annule tout ce qui a été fait dans la transaction
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("<br>❌ <b>Erreur fatale :</b> " . $e->getMessage());
}
?>
