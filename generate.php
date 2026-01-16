<?php
// Fichier : generate.php
require_once 'db.php';
header('Content-Type: application/json');

try {
    // 1. On vide la table actuelle des examens
    $pdo->exec("DELETE FROM examens");

    // 2. On récupère les modules et les salles
    $modules = $pdo->query("SELECT * FROM modules")->fetchAll(PDO::FETCH_ASSOC);
    $salles = $pdo->query("SELECT * FROM lieu_examen ORDER BY capacite DESC")->fetchAll(PDO::FETCH_ASSOC);

    $count = 0;
    $date_start = new DateTime('2026-06-01 08:30:00');

    // 3. Boucle d'optimisation simplifiée (Heuristique Gloutonne)
    foreach ($modules as $mod) {
        $placed = false;
        
        // Simuler le nombre d'étudiants inscrits (pour l'exemple)
        // Dans le vrai cas : SELECT COUNT(*) FROM inscriptions WHERE module_id = ...
        $nb_inscrits = rand(15, 150); 

        foreach ($salles as $salle) {
            // Règle 1 : Capacité
            if ($salle['capacite'] >= $nb_inscrits) {
                
                // On insère l'examen
                $sql = "INSERT INTO examens (module_id, salle_id, date_heure, duree_minutes, prof_id) 
                        VALUES (?, ?, ?, 120, 1)"; // On met prof_id=1 par défaut pour l'exemple
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$mod['id'], $salle['id'], $date_start->format('Y-m-d H:i:s')]);
                
                // On décale la date pour le prochain examen (simule l'absence de conflit)
                $date_start->modify('+4 hours'); 
                
                $placed = true;
                $count++;
                break; // Salle trouvée, on passe au module suivant
            }
        }
    }

    echo json_encode([
        "status" => "success", 
        "message" => "Optimisation terminée : $count examens planifiés sans conflits."
    ]);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>