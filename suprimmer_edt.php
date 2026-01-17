<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

try {
    $pdo->beginTransaction();

    // 1. Vider les examens
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0; TRUNCATE TABLE examens; SET FOREIGN_KEY_CHECKS = 1;");

    // 2. Réinitialiser les places étudiants
    $pdo->exec("UPDATE inscriptions SET salle_id = NULL");

    // 3. Remettre les départements en attente de validation
    $pdo->exec("UPDATE departements SET etat_planning = 'en_attente'");

    $pdo->commit();
    header("Location: admin.php?msg=supprime");
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    die("Erreur : " . $e->getMessage());
}
