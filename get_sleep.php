<?php
session_start();
header('Content-Type: application/json');

if(!isset($_SESSION['user_id'])){
    echo json_encode(["error"=>"Unauthorized"]);
    exit;
}

include "includes/db.php";

$user_id = intval($_SESSION['user_id']);

$today = date('Y-m-d');
$weekAgo = date('Y-m-d', strtotime('-6 days'));
$prevWeekStart = date('Y-m-d', strtotime('-13 days'));
$prevWeekEnd = date('Y-m-d', strtotime('-7 days'));


// =========================
// AMBIL DATA MINGGU INI
// =========================
$query = mysqli_query($conn,"
    SELECT tanggal, jam_tidur
    FROM sleep
    WHERE user_id = $user_id
    AND tanggal BETWEEN '$weekAgo' AND '$today'
    ORDER BY tanggal ASC
");

$dates = [];
$hours = [];
$total = 0;
$count = 0;

while($row = mysqli_fetch_assoc($query)){
    $dates[] = $row['tanggal'];
    $jam = floatval($row['jam_tidur']);
    $hours[] = $jam;
    $total += $jam;
    $count++;
}

$average = $count ? round($total / $count,1) : 0;
$score = round(min(10, ($average/8)*10),1);


// =========================
// AMBIL DATA MINGGU LALU
// =========================
$queryPrev = mysqli_query($conn,"
    SELECT jam_tidur
    FROM sleep
    WHERE user_id = $user_id
    AND tanggal BETWEEN '$prevWeekStart' AND '$prevWeekEnd'
");

$totalPrev = 0;
$countPrev = 0;

while($row = mysqli_fetch_assoc($queryPrev)){
    $totalPrev += floatval($row['jam_tidur']);
    $countPrev++;
}

$prev_average = $countPrev ? round($totalPrev / $countPrev,1) : 0;
$prev_score = round(min(10, ($prev_average/8)*10),1);


// =========================
// STATUS
// =========================
$status = "Kurang Tidur";
if($average >= 8){
    $status = "Tidur Optimal";
} elseif($average >= 6){
    $status = "Tidur Cukup";
}


// =========================
// HITUNG STREAK
// =========================
$streak = 0;
$streakQuery = mysqli_query($conn,"
    SELECT tanggal
    FROM sleep
    WHERE user_id = $user_id
    ORDER BY tanggal DESC
");

$prevDate = null;

while($row = mysqli_fetch_assoc($streakQuery)){
    $currentDate = $row['tanggal'];

    if($prevDate === null){
        $streak++;
    } else {
        $diff = (strtotime($prevDate) - strtotime($currentDate)) / 86400;
        if($diff == 1){
            $streak++;
        } else {
            break;
        }
    }

    $prevDate = $currentDate;
}


// =========================
// RESPONSE
// =========================
echo json_encode([
    "dates" => $dates,
    "hours" => $hours,
    "average" => $average,
    "prev_average" => $prev_average,
    "score" => $score,
    "prev_score" => $prev_score,
    "status" => $status,
    "streak" => $streak
]);
