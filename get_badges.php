<?php
session_start();
require 'includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT b.name, b.icon
    FROM user_badges ub
    JOIN badges b ON ub.badge_id = b.id_badges
    WHERE ub.user_id = ?
");
$stmt->execute([$userId]);

echo json_encode($stmt->fetchAll());
