<?php
include 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    exit("Unauthorized");
}

$user_id = $_SESSION['user_id'];
$date = date("Y-m-d");

$activity = $_POST['activity_minutes'];
$water = $_POST['water_intake_ml'];

// cek apakah sudah ada checkin hari ini
$check = mysqli_query($conn, "
    SELECT * FROM daily_checkins 
    WHERE user_id='$user_id' AND checkin_date='$date'
");

if (mysqli_num_rows($check) > 0) {

    // UPDATE
    mysqli_query($conn, "
        UPDATE daily_checkins 
        SET activity_minutes='$activity',
            water_intake_ml='$water'
        WHERE user_id='$user_id' 
        AND checkin_date='$date'
    ");

} else {

    // INSERT
    mysqli_query($conn, "
        INSERT INTO daily_checkins 
        (user_id, activity_minutes, water_intake_ml, checkin_date)
        VALUES 
        ('$user_id','$activity','$water','$date')
    ");
}

header("Location: health.php");
exit;
?>
