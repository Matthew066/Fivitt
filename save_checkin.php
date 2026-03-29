<?php
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    exit("Unauthorized");
}

$user_id = (int) $_SESSION['user_id'];
$date = date("Y-m-d");

$activity = (int) ($_POST['activity_minutes'] ?? 0);
$water = (int) ($_POST['water_intake_ml'] ?? 0);

$check = $pdo->prepare("
    SELECT id_daily_checkins
    FROM daily_checkins
    WHERE id_users = ? AND checkin_date = ?
    LIMIT 1
");
$check->execute([$user_id, $date]);

if ($check->fetch()) {
    $update = $pdo->prepare("
        UPDATE daily_checkins
        SET activity_minutes = ?, water_intake_ml = ?
        WHERE id_users = ? AND checkin_date = ?
    ");
    $update->execute([$activity, $water, $user_id, $date]);
} else {
    $insert = $pdo->prepare("
        INSERT INTO daily_checkins
        (id_users, activity_minutes, water_intake_ml, checkin_date)
        VALUES
        (?, ?, ?, ?)
    ");
    $insert->execute([$user_id, $activity, $water, $date]);
}

header("Location: health.php");
exit;
?>
