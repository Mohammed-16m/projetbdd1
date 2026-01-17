<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

try {
    // 1. Nettoyage (Ces commandes ferment souvent les transactions automatiquement)
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $pdo->exec("TRUNCATE TABLE examens");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    // 2. Mises à jour (DML - supportent bien les transactions)
    $pdo->beginTransaction();
    
    $pdo->exec("UPDATE inscriptions SET salle_id = NULL");
    $pdo->exec("UPDATE departements SET etat_planning = 'en_attente'");

    $pdo->commit();
    
    header("Location: admin.php?msg=supprime");
    exit();

} catch (Exception $e) {
    // On ne fait le rollback que si une transaction est vraiment active
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Erreur : " . $e->getMessage());
}
