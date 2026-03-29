<?php
session_start();
require_once 'includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
       echo json_encode(["status" => "unauthorized"]);
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$date = date("Y-m-d");

$stmt = $pdo->prepare("
    SELECT activity_minutes, water_intake_ml
    FROM daily_checkins
    WHERE id_users = ?
    AND checkin_date = ?
    LIMIT 1
");
$stmt->execute([$user_id, $date]);
$data = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['activity_minutes' => 0, 'water_intake_ml' => 0];

echo json_encode([
    "status" => "success",
    "data" => $data
]);
?>
