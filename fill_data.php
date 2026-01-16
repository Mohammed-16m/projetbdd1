<?php
require_once 'db.php';
set_time_limit(600); 

try {
    echo "<h2>🚀 Remplissage par Département (500 étu / 5 mod par Dept)</h2>";

    // 1. AJOUT DES 30 SALLES TD (si pas déjà fait en SQL)
    for ($i = 1; $i <= 30; $i++) {
        $pdo->prepare("INSERT IGNORE INTO lieu_examen (nom, capacite) VALUES (?, 40)")->execute(["Salle TD " . $i]);
    }

    // 2. AJOUT DES 40 PROFESSEURS
    for ($i = 1; $i <= 40; $i++) {
        $username = "prof" . $i;
        $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, 'prof123', 'professeur')")->execute([$username]);
        $last_id = $pdo->lastInsertId();
        $dept = ($i % 3) + 1;
        $pdo->prepare("INSERT INTO professeurs (id, nom_affichage, dept_id, specialite) VALUES (?, ?, ?, 'Enseignant')")->execute([$last_id, "Dr. " . ucfirst($username), $dept]);
    }

    // 3. AJOUT DES 1500 ÉTUDIANTS (500 par département)
    $pdo->beginTransaction();
    
    for ($i = 1; $i <= 1500; $i++) {
        $usernameEtu = "etudiant" . $i;
        $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, 'etu123', 'etudiant')")->execute([$usernameEtu]);
        $id_etu = $pdo->lastInsertId();

        // Logique de séparation :
        // 1-500 -> Dept 1 | 501-1000 -> Dept 2 | 1001-1500 -> Dept 3
        if ($i <= 500) { $dept_id = 1; $start_mod = 1; }
        elseif ($i <= 1000) { $dept_id = 2; $start_mod = 6; }
        else { $dept_id = 3; $start_mod = 11; }

        $pdo->prepare("INSERT INTO etudiants (id, nom_affichage, formation_id, promo, email) VALUES (?, ?, ?, '2026', ?)")
            ->execute([$id_etu, "Etudiant_" . $i, $dept_id, "etu$i@univ.dz"]);

        // INSCRIPTION AUX 5 MODULES DU DÉPARTEMENT
        $stmtI = $pdo->prepare("INSERT INTO inscriptions (etudiant_id, module_id) VALUES (?, ?)");
        for ($m = 0; $m < 5; $m++) {
            $stmtI->execute([$id_etu, $start_mod + $m]);
        }
    }
    
    $pdo->commit();
    echo "✅ Terminé : 1500 étudiants créés (500 par Dept) avec 5 modules chacun !";

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    die("Erreur : " . $e->getMessage());
}
?>