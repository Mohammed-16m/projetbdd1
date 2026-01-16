<?php
// ... ton début de code ...
$stmt = $pdo->prepare($query);
$stmt->execute([$user_id]);
$mes_examens = $stmt->fetchAll();

// AJOUTE ÇA POUR VOIR LE PROBLÈME :
if (count($mes_examens) == 0) {
    echo "<div style='background:black; color:lime; padding:10px; font-family:monospace;'>";
    echo "--- DEBUG MODE ---<br>";
    echo "ID Étudiant connecté : " . $user_id . "<br>";
    
    $checkInsc = $pdo->prepare("SELECT COUNT(*) FROM inscriptions WHERE etudiant_id = ?");
    $checkInsc->execute([$user_id]);
    echo "Nombre d'inscriptions trouvées : " . $checkInsc->fetchColumn() . "<br>";

    $checkEx = $pdo->query("SELECT COUNT(*) FROM examens")->fetchColumn();
    echo "Nombre total d'examens en base : " . $checkEx . "<br>";
    echo "------------------</div>";
}
?>
