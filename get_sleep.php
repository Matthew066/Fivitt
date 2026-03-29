<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once "includes/db.php";

$user_id = (int) $_SESSION['user_id'];

$today = date('Y-m-d');
$weekAgo = date('Y-m-d', strtotime('-6 days'));
$prevWeekStart = date('Y-m-d', strtotime('-13 days'));
$prevWeekEnd = date('Y-m-d', strtotime('-7 days'));

function sleepHours(string $date, ?string $start, ?string $end): float
{
    if ($start === null || $end === null || $start === '' || $end === '') {
        return 0.0;
    }

    $startTs = strtotime($date . ' ' . $start);
    $endTs = strtotime($date . ' ' . $end);
    if ($startTs === false || $endTs === false) {
        return 0.0;
    }

    if ($endTs <= $startTs) {
        $endTs = strtotime('+1 day', $endTs);
    }

    return max(0.0, ($endTs - $startTs) / 3600);
}

$weekStmt = $pdo->prepare("
    SELECT sleep_date, sleep_start, sleep_end
    FROM sleep_logs
    WHERE id_users = ?
      AND sleep_date BETWEEN ? AND ?
    ORDER BY sleep_date ASC
");
$weekStmt->execute([$user_id, $weekAgo, $today]);
$weekRows = $weekStmt->fetchAll(PDO::FETCH_ASSOC);

$dates = [];
$hours = [];
$total = 0.0;
$count = 0;

foreach ($weekRows as $row) {
    $date = (string) ($row['sleep_date'] ?? '');
    $jam = sleepHours($date, $row['sleep_start'] ?? null, $row['sleep_end'] ?? null);

    $dates[] = $date;
    $hours[] = round($jam, 2);
    $total += $jam;
    $count++;
}

$average = $count ? round($total / $count, 1) : 0.0;
$score = round(min(10, ($average / 8) * 10), 1);

$prevStmt = $pdo->prepare("
    SELECT sleep_date, sleep_start, sleep_end
    FROM sleep_logs
    WHERE id_users = ?
      AND sleep_date BETWEEN ? AND ?
");
$prevStmt->execute([$user_id, $prevWeekStart, $prevWeekEnd]);
$prevRows = $prevStmt->fetchAll(PDO::FETCH_ASSOC);

$totalPrev = 0.0;
$countPrev = 0;
foreach ($prevRows as $row) {
    $date = (string) ($row['sleep_date'] ?? '');
    $totalPrev += sleepHours($date, $row['sleep_start'] ?? null, $row['sleep_end'] ?? null);
    $countPrev++;
}

$prev_average = $countPrev ? round($totalPrev / $countPrev, 1) : 0.0;
$prev_score = round(min(10, ($prev_average / 8) * 10), 1);

$status = "Kurang Tidur";
if ($average >= 8) {
    $status = "Tidur Optimal";
} elseif ($average >= 6) {
    $status = "Tidur Cukup";
}

$streakStmt = $pdo->prepare("
    SELECT DISTINCT sleep_date
    FROM sleep_logs
    WHERE id_users = ?
    ORDER BY sleep_date DESC
");
$streakStmt->execute([$user_id]);
$streakRows = $streakStmt->fetchAll(PDO::FETCH_ASSOC);

$streak = 0;
$prevDate = null;
foreach ($streakRows as $row) {
    $currentDate = (string) ($row['sleep_date'] ?? '');
    if ($currentDate === '') {
        continue;
    }

    if ($prevDate === null) {
        $streak++;
    } else {
        $diff = (strtotime($prevDate) - strtotime($currentDate)) / 86400;
        if ($diff == 1) {
            $streak++;
        } else {
            break;
        }
    }

    $prevDate = $currentDate;
}

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
