<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/auth_guard.php';
require_login();

$pageTitle = 'Health Overview';
$bodyClass = 'health-page';
include 'includes/header.php';

$user_id = $_SESSION['user_id'] ?? 1;
$today = date('Y-m-d');

/* ================= AUTO CREATE CHECKIN ================= */

$check = $pdo->prepare("
    SELECT * FROM daily_checkins
    WHERE id_users = ? AND checkin_date = ?
");
$check->execute([$user_id, $today]);
$todayData = $check->fetch(PDO::FETCH_ASSOC);

if (!$todayData) {
    $insert = $pdo->prepare("
        INSERT INTO daily_checkins (id_users, checkin_date, activity_minutes, water_intake_ml)
        VALUES (?, ?, 0, 0)
    ");
    $insert->execute([$user_id, $today]);

    $todayData = [
        'activity_minutes' => 0,
        'water_intake_ml'  => 0
    ];
}

/* ================= HANDLE SUBMIT ================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $activity = $_POST['activity_minutes'];
    $water    = $_POST['water_intake_ml'];

    // UPDATE CHECKIN
    $update = $pdo->prepare("
        UPDATE daily_checkins
        SET activity_minutes = ?, water_intake_ml = ?
        WHERE id_users = ? AND checkin_date = ?
    ");
    $update->execute([$activity, $water, $user_id, $today]);

    // HANDLE BMI
    if (!empty($_POST['height_cm']) && !empty($_POST['weight_kg'])) {

        $height = $_POST['height_cm'];
        $weight = $_POST['weight_kg'];

        $bmi = $weight / pow(($height/100),2);
        $bmi = round($bmi,2);

        $insertBMI = $pdo->prepare("
            INSERT INTO bmi_records 
            (id_users, height_cm, weight_kg, bmi_value, recorded_at)
            VALUES (?, ?, ?, ?, CURDATE())
        ");
        $insertBMI->execute([$user_id, $height, $weight, $bmi]);
    }

    header("Location: health.php");
    exit;
}

$activity = $todayData['activity_minutes'];
$water_ml = (int) ($todayData['water_intake_ml'] ?? 0);
$glassSizeMl = 250;
$waterMinKidneyMl = 1100;
$waterTargetMl = 1500;
$waterUpperCautionMl = 4000;

$water_glass = (int) round($water_ml / $glassSizeMl);
$water_glass = max(0, min(8, $water_glass));

if ($water_ml <= 0) {
    $waterStatus = "Belum Ada Data";
    $waterClass = "water-none";
    $waterAdvice = "Target dasar air putih 1,1-1,5 L/hari (sekitar 5-6 gelas @250 ml).";
} elseif ($water_ml < $waterMinKidneyMl) {
    $waterStatus = "Kurang (< 1,1 L/hari)";
    $waterClass = "water-low";
    $extraGlass = (int) ceil(($waterMinKidneyMl - $water_ml) / $glassSizeMl);
    $waterAdvice = "Tambahkan sekitar {$extraGlass} gelas lagi hari ini untuk mencapai batas minimum.";
} elseif ($water_ml <= $waterTargetMl) {
    $waterStatus = "Cukup Minimum (1,1-1,5 L/hari)";
    $waterClass = "water-ok";
    $waterAdvice = "Pertahankan pola minum, tambah bila aktivitas tinggi atau cuaca panas.";
} elseif ($water_ml <= 3000) {
    $waterStatus = "Baik (Perkiraan Euhidrasi)";
    $waterClass = "water-good";
    $waterAdvice = "Pantau warna urine tetap kuning pucat/jernih untuk indikator hidrasi harian.";
} elseif ($water_ml <= $waterUpperCautionMl) {
    $waterStatus = "Tinggi";
    $waterClass = "water-high";
    $waterAdvice = "Pastikan asupan tersebar sepanjang hari dan sesuaikan rasa haus/aktivitas.";
} else {
    $waterStatus = "Berlebih (Waspada Over-hydration)";
    $waterClass = "water-over";
    $waterAdvice = "Kurangi laju minum air sekaligus dalam jumlah besar, terutama tanpa elektrolit.";
}


/* ================= GET BMI TERBARU ================= */

$bmiStmt = $pdo->prepare("
    SELECT bmi_value, height_cm, weight_kg
    FROM bmi_records
    WHERE id_users = ?
    ORDER BY recorded_at DESC
    LIMIT 1
");
$bmiStmt->execute([$user_id]);
$bmiRow = $bmiStmt->fetch(PDO::FETCH_ASSOC) ?: [];
$bmi = $bmiRow['bmi_value'] ?? 0;
$lastHeight = $bmiRow['height_cm'] ?? '';
$lastWeight = $bmiRow['weight_kg'] ?? '';

/* ================= BMI/IMT STATUS (ASIA) ================= */

if ($bmi == 0) {
    $bmiStatus = "Belum Ada Data";
} elseif ($bmi < 18.5) {
    $bmiStatus = "Berat Badan Kurang (Underweight)";
} elseif ($bmi < 23) {
    $bmiStatus = "Berat Badan Normal";
} elseif ($bmi < 30) {
    $bmiStatus = "Berat Badan Berlebih (Overweight)";
} else {
    $bmiStatus = "Obesitas";
}

/* ================= BMI GAUGE ================= */
$bmiMin = 10;
$bmiMax = 40;
$bmiClamp = max($bmiMin, min($bmiMax, (float) $bmi));
$bmiPercent = $bmi > 0 ? (($bmiClamp - $bmiMin) / ($bmiMax - $bmiMin)) * 100 : 0;

if ($bmi == 0) {
    $bmiGaugeClass = "bmi-none";
} elseif ($bmi < 18.5) {
    $bmiGaugeClass = "bmi-low";
} elseif ($bmi < 23) {
    $bmiGaugeClass = "bmi-normal";
} elseif ($bmi < 30) {
    $bmiGaugeClass = "bmi-high";
} else {
    $bmiGaugeClass = "bmi-obese";
}

/* ================= WEEKLY SCORE ================= */

$stmt = $pdo->prepare("
    SELECT activity_minutes, water_intake_ml
    FROM daily_checkins
    WHERE id_users = ?
    AND checkin_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
");
$stmt->execute([$user_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$weeklyActivityMinutes = 0;
$weeklyWaterMl = 0;
$checkinCount = 0;

foreach ($rows as $row) {
    $weeklyActivityMinutes += (int) ($row['activity_minutes'] ?? 0);
    $weeklyWaterMl += (int) ($row['water_intake_ml'] ?? 0);
    $checkinCount++;
}

$waterDailyAvgMl = $checkinCount ? ($weeklyWaterMl / $checkinCount) : 0;

/* ================= SLEEP WEEKLY (PILLAR) ================= */
$sleepStmt = $pdo->prepare("
    SELECT sleep_date, sleep_start, sleep_end
    FROM sleep_logs
    WHERE id_users = ?
    AND sleep_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
");
$sleepStmt->execute([$user_id]);
$sleepRows = $sleepStmt->fetchAll(PDO::FETCH_ASSOC);

$sleepTotalHours = 0.0;
$sleepCount = 0;

foreach ($sleepRows as $sleepRow) {
    $start = strtotime($sleepRow['sleep_date'] . ' ' . $sleepRow['sleep_start']);
    $end = strtotime($sleepRow['sleep_date'] . ' ' . $sleepRow['sleep_end']);
    if ($end <= $start) {
        $end = strtotime("+1 day", $end);
    }
    $sleepTotalHours += (($end - $start) / 3600);
    $sleepCount++;
}

$weeklySleepAvgHours = $sleepCount ? ($sleepTotalHours / $sleepCount) : 0;

/* ================= HEALTH PILLAR SCORE ================= */
$bmiPillarMet = ($bmi >= 18.5 && $bmi <= 22.9);
if ($bmi <= 0) {
    $bmiPillarScore = 0;
} elseif ($bmiPillarMet) {
    $bmiPillarScore = 10;
} else {
    $bmiPillarScore = max(2, 10 - (abs($bmi - 21.7) * 0.8));
}
$bmiPillarScore = round($bmiPillarScore, 1);

$waterPillarMet = ($waterDailyAvgMl >= 1100 && $waterDailyAvgMl <= 1500);
if ($waterDailyAvgMl <= 0) {
    $waterPillarScore = 0;
} elseif ($waterDailyAvgMl < 1100) {
    $waterPillarScore = max(1, ($waterDailyAvgMl / 1100) * 8);
} elseif ($waterDailyAvgMl <= 1500) {
    $waterPillarScore = 10;
} elseif ($waterDailyAvgMl <= 3000) {
    $waterPillarScore = 9;
} elseif ($waterDailyAvgMl <= 4000) {
    $waterPillarScore = 7;
} else {
    $waterPillarScore = 4;
}
$waterPillarScore = round($waterPillarScore, 1);

$workoutPillarMet = ($weeklyActivityMinutes >= 150 && $weeklyActivityMinutes <= 300);
if ($weeklyActivityMinutes <= 0) {
    $workoutPillarScore = 0;
} elseif ($weeklyActivityMinutes < 150) {
    $workoutPillarScore = max(1, ($weeklyActivityMinutes / 150) * 10);
} elseif ($weeklyActivityMinutes <= 300) {
    $workoutPillarScore = 10;
} else {
    $workoutPillarScore = 9;
}
$workoutPillarScore = round($workoutPillarScore, 1);

$sleepPillarMet = ($weeklySleepAvgHours >= 7 && $weeklySleepAvgHours <= 9);
if ($weeklySleepAvgHours <= 0) {
    $sleepPillarScore = 0;
} elseif ($sleepPillarMet) {
    $sleepPillarScore = 10;
} elseif ($weeklySleepAvgHours >= 6 && $weeklySleepAvgHours <= 10) {
    $sleepPillarScore = 7;
} elseif ($weeklySleepAvgHours >= 5 && $weeklySleepAvgHours <= 11) {
    $sleepPillarScore = 4;
} else {
    $sleepPillarScore = 2;
}
$sleepPillarScore = round($sleepPillarScore, 1);

$healthyPillarCount = 0;
if ($bmiPillarMet) { $healthyPillarCount++; }
if ($waterPillarMet) { $healthyPillarCount++; }
if ($workoutPillarMet) { $healthyPillarCount++; }
if ($sleepPillarMet) { $healthyPillarCount++; }

$averageScore = round(
    ($bmiPillarScore * 0.35) +
    ($waterPillarScore * 0.15) +
    ($workoutPillarScore * 0.25) +
    ($sleepPillarScore * 0.25),
1);

$activityMinTarget = 150;
$activityMaxTarget = 300;
$activityLeft = max(0, $activityMinTarget - $weeklyActivityMinutes);

if ($weeklyActivityMinutes <= 0) {
    $activityWeeklyStatus = "Belum ada aktivitas tercatat minggu ini";
    $activityWeeklyClass = "act-none";
} elseif ($weeklyActivityMinutes < $activityMinTarget) {
    $activityWeeklyStatus = "Kurang Aktif";
    $activityWeeklyClass = "act-low";
} elseif ($weeklyActivityMinutes <= $activityMaxTarget) {
    $activityWeeklyStatus = "Sesuai Target Sehat";
    $activityWeeklyClass = "act-good";
} else {
    $activityWeeklyStatus = "Sangat Aktif";
    $activityWeeklyClass = "act-high";
}

if ($healthyPillarCount >= 3 && $averageScore >= 7.5) {
    $status = "Sangat Sehat";
} elseif ($averageScore < 5) {
    $status = "Perlu Peningkatan";
} elseif ($averageScore < 8) {
    $status = "Baik";
} else {
    $status = "Optimal";
}

$weeklyTrendStmt = $pdo->prepare("
    SELECT checkin_date, activity_minutes, water_intake_ml
    FROM daily_checkins
    WHERE id_users = ?
      AND checkin_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    ORDER BY checkin_date ASC
");
$weeklyTrendStmt->execute([$user_id]);
$weeklyTrendRows = $weeklyTrendStmt->fetchAll(PDO::FETCH_ASSOC);

$sleepTrendStmt = $pdo->prepare("
    SELECT sleep_date, sleep_start, sleep_end
    FROM sleep_logs
    WHERE id_users = ?
      AND sleep_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    ORDER BY sleep_date ASC
");
$sleepTrendStmt->execute([$user_id]);
$sleepTrendRows = $sleepTrendStmt->fetchAll(PDO::FETCH_ASSOC);

$sleepTrendMap = [];
foreach ($sleepTrendRows as $row) {
    $date = (string) ($row['sleep_date'] ?? '');
    $start = strtotime($date . ' ' . $row['sleep_start']);
    $end = strtotime($date . ' ' . $row['sleep_end']);
    if ($end <= $start) {
        $end = strtotime('+1 day', $end);
    }
    $sleepTrendMap[$date] = round(($end - $start) / 3600, 1);
}

$trendLabels = [];
$trendActivityData = [];
$trendWaterData = [];
$trendSleepData = [];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} day"));
    $trendLabels[] = date('d M', strtotime($date));

    $checkinRow = null;
    foreach ($weeklyTrendRows as $row) {
        if (($row['checkin_date'] ?? '') === $date) {
            $checkinRow = $row;
            break;
        }
    }

    $trendActivityData[] = (int) ($checkinRow['activity_minutes'] ?? 0);
    $trendWaterData[] = round(((int) ($checkinRow['water_intake_ml'] ?? 0)) / 1000, 2);
    $trendSleepData[] = $sleepTrendMap[$date] ?? 0;
}

$waterAvgLiters = round($waterDailyAvgMl / 1000, 1);
$sleepAvgHoursFormatted = number_format($weeklySleepAvgHours, 1);
$activityCompletionPercent = max(0, min(100, round(($weeklyActivityMinutes / $activityMinTarget) * 100)));
$waterCompletionPercent = max(0, min(100, round(($waterDailyAvgMl / $waterTargetMl) * 100)));
$sleepCompletionPercent = max(0, min(100, round(($weeklySleepAvgHours / 8) * 100)));

?>

<style>
body.health-page {
    background:
        radial-gradient(circle at top left, rgba(245, 158, 11, 0.10), transparent 18%),
        radial-gradient(circle at top right, rgba(20, 184, 166, 0.16), transparent 22%),
        linear-gradient(180deg, #faf7f1 0%, #f4f8f6 48%, #eef5fb 100%);
}

.health-page .app {
    width: min(420px, 100% - 24px);
    margin: 14px auto 0;
    display: grid;
    grid-template-columns: 1fr;
    gap: 14px;
    padding-bottom: 88px;
}

.health-page .card {
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: 22px;
    background: linear-gradient(145deg, #ffffff, #fbfdfa 58%, #f4fbf8 100%);
    box-shadow: 0 18px 38px rgba(15, 23, 42, 0.08);
    min-width: 0;
    overflow: hidden;
}

.health-page .sleep-hero {
    grid-column: 1 / -1;
    padding: 18px;
    background:
        radial-gradient(circle at top right, rgba(255,255,255,.32), transparent 24%),
        linear-gradient(145deg, #1f766e 0%, #2b8b82 48%, #d6b36f 118%);
    color: #fffdf8;
    border-color: rgba(255,255,255,.16);
    box-shadow: 0 22px 46px rgba(23, 63, 68, 0.18);
}

.health-page .sleep-hero-inner {
    display: grid;
    grid-template-columns: 1fr;
    gap: 14px;
}

.health-page .emoji-bubble {
    width: 58px;
    height: 58px;
    border-radius: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 26px;
    background: rgba(255,255,255,.14);
    border: 1px solid rgba(255,255,255,.16);
}

.health-page .hero-main {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    min-width: 0;
}

.health-page .hero-copy,
.health-page .sleep-form-head,
.health-page .health-summary-score,
.health-page .health-summary-metric,
.health-page .health-pillar-tile,
.health-page .input-group,
.health-page .score-wrapper {
    min-width: 0;
}

.health-page .hero-kicker {
    display: inline-flex;
    margin-bottom: 8px;
    padding: 7px 12px;
    border-radius: 999px;
    background: rgba(255,255,255,.14);
    border: 1px solid rgba(255,255,255,.16);
    color: #fff8ef;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: .08em;
    text-transform: uppercase;
}

.health-page .sleep-title,
.health-page .summary-title,
.health-page .score-header {
    color: #16323b;
}

.health-page .sleep-hero .sleep-title {
    color: #fffdf8;
    font-size: clamp(24px, 8vw, 32px);
    line-height: 1.08;
    overflow-wrap: anywhere;
}

.health-page .sleep-sub {
    color: #607077;
    overflow-wrap: anywhere;
}

.health-page .sleep-hero .sleep-sub {
    color: rgba(255, 250, 240, 0.86);
}

.health-page .summary-pill,
.health-page .activity-status,
.health-page .water-status {
    border-radius: 999px;
    padding: 8px 12px;
    font-size: 12px;
    font-weight: 800;
}

.health-page .health-pillars-card,
.health-page .chart-card {
    grid-column: 1 / -1;
    padding: 16px;
}

.health-page .health-pillars-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 12px;
}

.health-page .health-pillar-tile {
    padding: 16px;
    border-radius: 20px;
    background: #f8fafc;
    border: 1px solid rgba(148, 163, 184, 0.14);
}

.health-page .health-pillar-label {
    display: block;
    margin-bottom: 8px;
    font-size: 13px;
    font-weight: 800;
    letter-spacing: .05em;
    text-transform: uppercase;
    color: #64748b;
}

.health-page .health-pillar-tile strong {
    display: block;
    margin-top: 6px;
    font-size: 22px;
    line-height: 1.15;
    color: #0f172a;
    overflow-wrap: anywhere;
}

.health-page .health-pillar-tile small {
    display: block;
    margin-top: 8px;
    color: #475569;
    font-size: 13px;
    line-height: 1.5;
    overflow-wrap: anywhere;
}

.health-page .health-checkin-card,
.health-page .health-bmi-card,
.health-page .health-summary-card,
.health-page .score-card {
    padding: 16px;
}

.health-page .health-checkin-card {
    grid-column: 1 / -1;
}

.health-page .input-row,
.health-page .health-body-metrics {
    display: grid;
    grid-template-columns: 1fr;
    gap: 12px;
}

.health-page .input-card,
.health-page .water-card {
    background: linear-gradient(145deg, #fffdf8, #f8fbfb);
    border: 1px solid rgba(226,232,240,.8);
    border-radius: 18px;
}

.health-page .water-card {
    margin: 6px 0 12px;
    padding: 14px;
}

.health-page .glass-wrapper {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 10px;
}

.health-page .glass-label,
.health-page .water-title,
.health-page .water-subtitle,
.health-page .water-advice,
.health-page .bmi-status,
.health-page .bmi-labels,
.health-page .summary-pill {
    overflow-wrap: anywhere;
}

.health-page .progress {
    background: #e6f1ef;
}

.health-page .progress-fill {
    background: linear-gradient(90deg, #1f766e, #d6b36f);
}

.health-page .chart-card canvas {
    margin-top: 10px;
    height: 220px !important;
}

.health-page .score-number {
    color: #16323b;
    font-size: clamp(30px, 10vw, 48px);
    line-height: 1;
    overflow-wrap: anywhere;
}

.health-page .health-summary-card {
    background: linear-gradient(145deg, #17353d, #214752 58%, #2d6e6a 100%);
    color: #f8fafc;
}

.health-page .health-summary-top {
    display: grid;
    grid-template-columns: 1fr;
    gap: 12px;
    margin-top: 12px;
}

.health-page .health-summary-score {
    padding: 14px;
    border-radius: 18px;
    background: rgba(255,255,255,.10);
    border: 1px solid rgba(255,255,255,.12);
}

.health-page .health-summary-score-label {
    display: block;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: rgba(248,250,252,.74);
}

.health-page .health-summary-score strong {
    display: block;
    margin-top: 8px;
    font-size: 30px;
    line-height: 1;
    color: #fff;
    overflow-wrap: anywhere;
}

.health-page .health-summary-score small {
    display: block;
    margin-top: 8px;
    color: rgba(248,250,252,.82);
    line-height: 1.5;
}

.health-page .health-summary-metrics {
    display: grid;
    grid-template-columns: 1fr;
    gap: 10px;
}

.health-page .health-summary-metric {
    padding: 12px 14px;
    border-radius: 16px;
    background: rgba(255,255,255,.08);
    border: 1px solid rgba(255,255,255,.10);
}

.health-page .health-summary-metric span {
    display: block;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: rgba(248,250,252,.72);
}

.health-page .health-summary-metric strong {
    display: block;
    margin-top: 6px;
    color: #fff;
    font-size: 18px;
    overflow-wrap: anywhere;
}

.health-page .health-summary-card .summary-title,
.health-page .health-summary-card .sleep-sub,
.health-page .health-summary-card .summary-pill {
    color: #f8fafc;
}

.health-page .health-summary-card .summary-pill {
    background: rgba(255,255,255,.12);
    display: inline-flex;
    margin-top: 12px;
}

.health-page .health-summary-card .progress {
    margin-top: 14px;
    height: 12px;
    border-radius: 999px;
    overflow: hidden;
    background: rgba(255,255,255,.14);
}

.health-page .health-summary-card .progress-fill {
    border-radius: inherit;
    min-width: 8%;
    background: linear-gradient(90deg, #fde68a, #86efac, #67e8f9);
}

.health-page .activity-status.act-good,
.health-page .water-status.water-good,
.health-page .water-status.water-ok {
    background: #ecfdf5;
    color: #166534;
}

.health-page .activity-status.act-low,
.health-page .water-status.water-low {
    background: #fff7ed;
    color: #9a3412;
}

.health-page .activity-status.act-high,
.health-page .water-status.water-high {
    background: #eff6ff;
    color: #1d4ed8;
}

.health-page .activity-status.act-none,
.health-page .water-status.water-none,
.health-page .water-status.water-over {
    background: #fef2f2;
    color: #991b1b;
}

.health-page .sleep-form-footer {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 12px;
}

.health-page .sleep-submit-btn {
    width: 100%;
    min-width: 0;
    order: 1;
}

.health-page .sleep-form-note {
    order: 2;
    overflow-wrap: anywhere;
}

.health-page input,
.health-page textarea,
.health-page button,
.health-page canvas {
    max-width: 100%;
}

.health-page .bmi-labels {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 6px;
    font-size: 10px;
}

@media (max-width: 480px) {
    .health-page .app {
        width: min(100%, calc(100vw - 12px));
        gap: 12px;
        padding-bottom: 80px;
    }

    .health-page .card,
    .health-page .sleep-hero,
    .health-page .health-pillars-card,
    .health-page .chart-card,
    .health-page .health-checkin-card,
    .health-page .health-bmi-card,
    .health-page .health-summary-card,
    .health-page .score-card {
        padding: 14px;
        border-radius: 18px;
    }

    .health-page .hero-kicker {
        max-width: 100%;
        white-space: normal;
    }

    .health-page .summary-title {
        font-size: 18px;
        line-height: 1.2;
    }

    .health-page .health-pillar-tile strong {
        font-size: 20px;
    }

    .health-page .health-summary-score strong {
        font-size: 26px;
    }

    .health-page .chart-card canvas {
        height: 200px !important;
    }
}

@media (min-width: 900px) {
    .health-page .app {
        width: min(1160px, calc(100% - 40px));
        margin: 18px auto 0;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px;
        padding-bottom: 90px;
    }

    .health-page .card {
        border-radius: 28px;
    }

    .health-page .sleep-hero {
        padding: 28px;
    }

    .health-page .sleep-hero-inner {
        grid-template-columns: 1fr;
        gap: 18px;
    }

    .health-page .emoji-bubble {
        width: 72px;
        height: 72px;
        border-radius: 22px;
        font-size: 32px;
    }

    .health-page .hero-main {
        gap: 18px;
    }

    .health-page .sleep-hero .sleep-title {
        font-size: clamp(28px, 3.6vw, 38px);
    }

    .health-page .health-pillars-card,
    .health-page .chart-card {
        padding: 18px;
    }

    .health-page .health-pillars-grid {
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
    }

    .health-page .health-pillar-tile {
        padding: 16px;
        border-radius: 22px;
    }

    .health-page .health-pillar-tile strong {
        font-size: 24px;
    }

    .health-page .health-checkin-card,
    .health-page .health-bmi-card,
    .health-page .health-summary-card,
    .health-page .health-workout-card,
    .health-page .score-card {
        padding: 18px;
    }

    .health-page .health-bmi-card {
        grid-column: 1 / -1;
    }

    .health-page .input-card,
    .health-page .water-card {
        border-radius: 22px;
    }

    .health-page .water-card {
        padding: 18px;
    }

    .health-page .chart-card canvas {
        height: 320px !important;
    }

    .health-page .health-summary-top,
    .health-page .health-summary-metrics {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .health-page .input-row,
    .health-page .health-body-metrics {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .health-page .workout-meta-grid,
    .health-page .workout-guidance {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .health-page .workout-icon {
        width: 58px;
        height: 58px;
        border-radius: 18px;
        font-size: 26px;
    }
}
</style>

<main class="app">

<!-- ================= HERO ================= -->
<section class="card sleep-hero">
    <div class="sleep-hero-inner">
        <div class="hero-main">
            <div class="emoji-bubble">&#128170;</div>
            <div class="hero-copy">
                <span class="hero-kicker">Weekly Health Reading</span>
                <div class="sleep-title"><?= $status ?></div>
                <div class="sleep-sub">
                    Ringkasan kondisi health kamu minggu ini dari check-in otomatis, hidrasi, aktivitas, dan BMI.
                </div>
            </div>
        </div>
    </div>
</section>

<section class="card health-pillars-card">
    <div class="health-pillars-grid">
        <div class="health-pillar-tile">
            <span class="health-pillar-label">Aktivitas</span>
            <strong><?= (int) $weeklyActivityMinutes ?> menit</strong>
            <small><?= $activityCompletionPercent ?>% dari target minimum mingguan</small>
        </div>
        <div class="health-pillar-tile">
            <span class="health-pillar-label">Hidrasi</span>
            <strong><?= number_format($waterAvgLiters, 1) ?> L/hari</strong>
            <small><?= $waterCompletionPercent ?>% dari target harian</small>
        </div>
        <div class="health-pillar-tile">
            <span class="health-pillar-label">Tidur</span>
            <strong><?= $sleepAvgHoursFormatted ?> jam</strong>
            <small><?= $sleepCompletionPercent ?>% menuju ritme ideal</small>
        </div>
        <div class="health-pillar-tile">
            <span class="health-pillar-label">BMI</span>
            <strong><?= $bmi > 0 ? number_format((float) $bmi, 1) : '--' ?></strong>
            <small><?= htmlspecialchars($bmiStatus) ?></small>
        </div>
    </div>
</section>


<!-- ================= CHECKIN ================= -->
<section class="card sleep-form-card health-checkin-card">
    <div class="sleep-form-head">
        <div>
            <div class="summary-title">Daily Check-In</div>
            <div class="sleep-sub">Isi aktivitas, hidrasi, dan data tubuh harian dalam satu panel yang lebih ringkas.</div>
        </div>
    </div>

    <form method="POST" class="sleep-entry-form">

        <div class="input-row">
            <div class="input-group input-card">
                <label>Aktivitas Fisik (menit)</label>
                <input type="number"
                       name="activity_minutes"
                       min="0"
                       value="<?= htmlspecialchars((string) $activity) ?>"
                       placeholder="Contoh: 30">
            </div>
            <div class="input-group input-card health-quick-note">
                <span class="sleep-date-summary-label">Status mingguan</span>
                <strong><?= htmlspecialchars($activityWeeklyStatus) ?></strong>
                <small><?= (int) $healthyPillarCount ?>/4 pilar sehat sudah tercapai minggu ini.</small>
            </div>
        </div>

        <div class="water-card">

            <div class="water-title">&#128167; Asupan Air Harian</div>
            <div class="water-subtitle">Pilih jumlah gelas untuk memperbarui estimasi hidrasi harian.</div>

            <div class="glass-wrapper">
                <?php for ($i = 1; $i <= 8; $i++): ?>
                    <div class="glass <?= $water_glass >= $i ? 'active' : '' ?>"
                         onclick="setGlass(<?= $i ?>)">
                        <div class="water"></div>
                    </div>
                <?php endfor; ?>
            </div>

            <div class="glass-label">
                <strong id="glassCount"><?= $water_glass ?></strong> gelas
                <small>(1 gelas = 250 ml)</small>
            </div>
            <div class="water-status <?= $waterClass ?>"><?= $waterStatus ?></div>
            <div class="water-advice"><?= htmlspecialchars($waterAdvice) ?></div>

            <input type="hidden"
                   name="water_intake_ml"
                   id="waterInput"
                   value="<?= $water_ml ?>">

        </div>

        <div class="input-row health-body-metrics">
            <div class="input-group input-card">
                <label>Tinggi Badan (cm)</label>
                <input type="number"
                       name="height_cm"
                       min="1"
                       value="<?= htmlspecialchars((string) $lastHeight) ?>"
                       placeholder="Contoh: 170">
            </div>

            <div class="input-group input-card">
                <label>Berat Badan (kg)</label>
                <input type="number"
                       step="0.1"
                       min="1"
                       name="weight_kg"
                       value="<?= htmlspecialchars((string) $lastWeight) ?>"
                       placeholder="Contoh: 65">
            </div>
        </div>

        <div class="sleep-form-footer">
            <button class="btn-primary sleep-submit-btn" type="submit">
                Simpan Semua Data
            </button>
            <div class="sleep-form-note">
                <strong>Tip:</strong> data hari ini otomatis jadi dasar ringkasan mingguan, jadi cukup update sekali tiap hari.
            </div>
        </div>

    </form>
</section>


<!-- ================= BMI RESULT ================= -->
<section class="card health-bmi-card">
    <div class="summary-title">BMI/IMT Terakhir</div>

    <div class="score-number">
        <?= $bmi > 0 ? $bmi : '--' ?>
    </div>

    <div class="sleep-sub bmi-status <?= $bmiGaugeClass ?>">
        <?= $bmiStatus ?>
    </div>

    <div class="bmi-gauge <?= $bmiGaugeClass ?>">
        <div class="bmi-track">
            <div class="bmi-marker" style="left: <?= $bmiPercent ?>%;"></div>
        </div>
        <div class="bmi-labels">
            <span>&lt;18,5</span>
            <span>18,5-22,9</span>
            <span>23,0-29,9</span>
            <span>&ge;30,0</span>
        </div>
    </div>
</section>

<section class="card chart-card">
    <div class="summary-title">Tren 7 Hari Terakhir</div>
    <canvas id="healthTrendChart" height="320"></canvas>
</section>


<!-- ================= WEEKLY PROGRESS ================= -->
<section class="card health-summary-card">
    <div class="summary-title">Ringkasan Mingguan</div>
    <div class="sleep-sub">Snapshot mingguan supaya kamu cepat lihat posisi health tanpa baca semua detail.</div>

    <div class="health-summary-top">
        <div class="health-summary-score">
            <span class="health-summary-score-label">Weekly Score</span>
            <strong><?= number_format($averageScore, 1) ?>/10</strong>
            <small><?= (int) $healthyPillarCount ?>/4 pilar sehat berhasil tercapai minggu ini.</small>
        </div>

        <div class="health-summary-metrics">
            <div class="health-summary-metric">
                <span>Aktivitas</span>
                <strong><?= (int) $weeklyActivityMinutes ?> menit</strong>
            </div>
            <div class="health-summary-metric">
                <span>Air Harian</span>
                <strong><?= number_format($waterAvgLiters, 1) ?> L</strong>
            </div>
            <div class="health-summary-metric">
                <span>Tidur</span>
                <strong><?= $sleepAvgHoursFormatted ?> jam</strong>
            </div>
            <div class="health-summary-metric">
                <span>BMI</span>
                <strong><?= $bmi > 0 ? number_format((float) $bmi, 1) : '--' ?></strong>
            </div>
        </div>
    </div>

    <div class="progress">
        <div class="progress-fill" style="width: <?= $averageScore * 10 ?>%;"></div>
    </div>

    <div class="summary-pill">
        Skor Mingguan <?= $averageScore ?>/10
    </div>
    <div class="sleep-sub">
        Skor Pilar: BMI <?= number_format($bmiPillarScore,1) ?>, Water <?= number_format($waterPillarScore,1) ?>,
        Workout <?= number_format($workoutPillarScore,1) ?>, Sleep <?= number_format($sleepPillarScore,1) ?>
    </div>
</section>


<!-- ================= GAUGE ================= -->
<section class="card score-card">

    <div class="score-header">Skor Total Health</div>

    <div class="score-wrapper">

        <div class="score-number">
            <?= number_format($averageScore,1) ?>
            <small>/10.0</small>
        </div>

        <?php $rotation = 180 + ($averageScore / 10) * 180; ?>

        <div class="gauge">
            <div class="gauge-fill"
                 style="transform: scaleX(-1) rotate(<?= $rotation ?>deg);">
            </div>
        </div>

    </div>

</section>

</main>
<script src="assets/js/chart.js"></script>
<script>
function setGlass(count) {
    const glasses = document.querySelectorAll('.glass');
    const glassCount = document.getElementById('glassCount');
    const waterInput = document.getElementById('waterInput');

    glasses.forEach((glass, index) => {
        if (index < count) {
            glass.classList.add('active');
        } else {
            glass.classList.remove('active');
        }
    });

    glassCount.innerText = count;
    waterInput.value = count * 250;
}

(() => {
    const canvas = document.getElementById('healthTrendChart');
    if (!canvas || typeof Chart === 'undefined') {
        return;
    }

    new Chart(canvas, {
        data: {
            labels: <?= json_encode($trendLabels) ?>,
            datasets: [
                {
                    type: 'bar',
                    label: 'Aktivitas (menit)',
                    data: <?= json_encode($trendActivityData) ?>,
                    backgroundColor: 'rgba(34, 197, 94, 0.75)',
                    borderRadius: 10,
                    yAxisID: 'y'
                },
                {
                    type: 'line',
                    label: 'Air (L)',
                    data: <?= json_encode($trendWaterData) ?>,
                    borderColor: '#0ea5e9',
                    backgroundColor: 'rgba(14, 165, 233, 0.16)',
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#0ea5e9',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    tension: 0.35,
                    fill: false,
                    yAxisID: 'y1'
                },
                {
                    type: 'line',
                    label: 'Tidur (jam)',
                    data: <?= json_encode($trendSleepData) ?>,
                    borderColor: '#8b5cf6',
                    backgroundColor: 'rgba(139, 92, 246, 0.12)',
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#8b5cf6',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    tension: 0.35,
                    fill: false,
                    yAxisID: 'y2'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                intersect: false,
                mode: 'index'
            },
            plugins: {
                legend: {
                    position: 'bottom'
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    }
                },
                y: {
                    beginAtZero: true,
                    position: 'left',
                    suggestedMax: 60,
                    ticks: {
                        color: '#475569'
                    },
                    grid: {
                        color: 'rgba(148, 163, 184, 0.15)'
                    }
                },
                y1: {
                    beginAtZero: true,
                    position: 'right',
                    suggestedMax: 3,
                    grid: {
                        drawOnChartArea: false
                    },
                    ticks: {
                        color: '#0284c7'
                    }
                },
                y2: {
                    beginAtZero: true,
                    position: 'right',
                    display: false,
                    suggestedMax: 10
                }
            }
        }
    });
})();
</script>

<?php include 'includes/footer.php'; ?>
