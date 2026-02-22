<?php
include 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["status" => "unauthorized"]);
    exit;
}

$user_id = $_SESSION['user_id'];
$date = date("Y-m-d");

$query = mysqli_query($conn, "
    SELECT activity_minutes, water_intake_ml 
    FROM daily_checkins
    WHERE user_id='$user_id' 
    AND checkin_date='$date'
");

$data = mysqli_fetch_assoc($query);

echo json_encode([
    "status" => "success",
    "data" => $data
]);
?>
