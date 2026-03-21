<?php
session_start();
require_once 'includes/db.php';

$pageTitle = 'Sleep & Recovery';
include 'includes/header.php';

$user_id = $_SESSION['user_id'] ?? 1;
$today = date('Y-m-d');

/* ================= AUTO CREATE TODAY ================= */

$check = $pdo->prepare("
    SELECT * FROM sleep_logs
    WHERE id_users = ? AND sleep_date = ?
");
$check->execute([$user_id, $today]);
$todayLog = $check->fetch(PDO::FETCH_ASSOC);

if (!$todayLog) {
    $insert = $pdo->prepare("
        INSERT INTO sleep_logs (id_users, sleep_date, sleep_start, sleep_end)
        VALUES (?, ?, '22:00', '06:00')
    ");
    $insert->execute([$user_id, $today]);

    $todayLog = [
        'sleep_start' => '22:00',
        'sleep_end'   => '06:00'
    ];
}

/* ================= HANDLE UPDATE ================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $start = $_POST['sleep_start'];
    $end   = $_POST['sleep_end'];
    $ageGroup = $_POST['age_group'] ?? 'adult';
    $sleepLatency = isset($_POST['sleep_latency_min']) ? (int) $_POST['sleep_latency_min'] : 20;
    $nightAwakenings = isset($_POST['night_awakenings']) ? (int) $_POST['night_awakenings'] : 0;

    $_SESSION['sleep_age_group'] = in_array($ageGroup, ['teen_12_14', 'adult'], true) ? $ageGroup : 'adult';
    $_SESSION['sleep_latency_min'] = max(0, min(180, $sleepLatency));
    $_SESSION['night_awakenings'] = max(0, min(10, $nightAwakenings));

    $update = $pdo->prepare("
        UPDATE sleep_logs
        SET sleep_start = ?, sleep_end = ?
        WHERE id_users = ? AND sleep_date = ?
    ");
    $update->execute([$start, $end, $user_id, $today]);

    header("Location: sleep.php");
    exit;
}

/* ================= SLEEP TARGET PROFILE ================= */
$ageGroup = $_SESSION['sleep_age_group'] ?? 'adult';
$sleepLatency = (int) ($_SESSION['sleep_latency_min'] ?? 20);
$nightAwakenings = (int) ($_SESSION['night_awakenings'] ?? 0);

if ($ageGroup === 'teen_12_14') {
    $sleepTargetMin = 8.0;
    $sleepTargetMax = 10.0;
    $sleepTargetLabel = '8-10 jam (usia 12-14)';
} else {
    $sleepTargetMin = 7.0;
    $sleepTargetMax = 9.0;
    $sleepTargetLabel = '7-9 jam (dewasa)';
}

$sleepTargetMid = ($sleepTargetMin + $sleepTargetMax) / 2;

/* ================= WEEKLY CALCULATION ================= */

$hours   = [];   // ini sekarang berisi SKOR
$dates   = [];
$totalScore = 0;
$count   = 0;
$totalDuration = 0;

$stmt = $pdo->prepare("
    SELECT sleep_date, sleep_start, sleep_end
    FROM sleep_logs
    WHERE id_users = ?
    AND sleep_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ORDER BY sleep_date ASC
");
$stmt->execute([$user_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $row) {

    $date = $row['sleep_date'];

    $startTime = strtotime("$date {$row['sleep_start']}");
    $endTime   = strtotime("$date {$row['sleep_end']}");

    if ($endTime <= $startTime) {
        $endTime = strtotime("+1 day", $endTime);
    }

    $duration = ($endTime - $startTime) / 3600;

    /* ===== SMART SCORE BERBASIS TARGET USIA ===== */
    $dailyScore = 10 - (abs($duration - $sleepTargetMid) * 1.6);
    $dailyScore = max(0, min(10, $dailyScore));
    $dailyScore = round($dailyScore, 1);

    $hours[] = $dailyScore;
    $dates[] = date("d M", strtotime($date));

    $totalDuration += $duration;
    $totalScore += $dailyScore;
    $count++;
}

$averageScore = $count ? round($totalScore / $count, 1) : 0;
$average = $count ? round($totalDuration / $count, 2) : 0;

/* ================= QUALITY PROXY (PSQI RINGAN) ================= */
$qualityScore = 0;

if ($sleepLatency < 30) {
    $qualityScore += 4;
} elseif ($sleepLatency <= 45) {
    $qualityScore += 2;
}

if ($nightAwakenings <= 1) {
    $qualityScore += 3;
} elseif ($nightAwakenings === 2) {
    $qualityScore += 2;
} elseif ($nightAwakenings === 3) {
    $qualityScore += 1;
}

if ($average >= $sleepTargetMin && $average <= $sleepTargetMax) {
    $qualityScore += 3;
} elseif ($average >= ($sleepTargetMin - 1) && $average <= ($sleepTargetMax + 1)) {
    $qualityScore += 1;
}

$qualityScore = max(0, min(10, $qualityScore));
$combinedSleepScore = round(($averageScore * 0.7) + ($qualityScore * 0.3), 1);

if ($sleepLatency < 30 && $nightAwakenings <= 1) {
    $qualityStatus = "Kualitas Baik";
} elseif ($sleepLatency <= 45 && $nightAwakenings <= 2) {
    $qualityStatus = "Kualitas Cukup";
} else {
    $qualityStatus = "Kualitas Perlu Perbaikan";
}

if ($average < $sleepTargetMin) {
    $durationStatus = "Durasi Kurang";
} elseif ($average > $sleepTargetMax) {
    $durationStatus = "Durasi Berlebih";
} else {
    $durationStatus = "Durasi Sesuai Target";
}

function formatAverage($avg) {
    $m = round($avg * 60);
    $jam = floor($m / 60);
    $menit = $m % 60;

    return $jam . " jam " . $menit . " menit";
}

/* ================= STATUS ================= */

if ($combinedSleepScore < 6) {
    $status = "Perlu Perbaikan";
} elseif ($combinedSleepScore < 8) {
    $status = "Cukup";
} else {
    $status = "Optimal";
}

?>

<main class="app">

<!-- ================= INPUT ================= -->
<section class="card">
    <div class="summary-title">Input Tidur Hari Ini</div>

    <form method="POST">
        <div class="input-row">

            <div class="input-group">
                <label>Mulai</label>
                <input type="time" name="sleep_start"
                       value="<?= $todayLog['sleep_start'] ?>" required>
            </div>

            <div class="input-group">
                <label>Selesai</label>
                <input type="time" name="sleep_end"
                       value="<?= $todayLog['sleep_end'] ?>" required>
            </div>

        </div>
        <div class="input-row">
            <div class="input-group">
                <label>Kelompok Usia</label>
                <select name="age_group" required>
                    <option value="adult" <?= $ageGroup === 'adult' ? 'selected' : '' ?>>Dewasa (7-9 jam)</option>
                    <option value="teen_12_14" <?= $ageGroup === 'teen_12_14' ? 'selected' : '' ?>>Remaja 12-14 (8-10 jam)</option>
                </select>
            </div>
            <div class="input-group">
                <label>Latensi Tidur (menit)</label>
                <input type="number" name="sleep_latency_min" min="0" max="180"
                       value="<?= (int) $sleepLatency ?>" placeholder="Contoh: 20">
            </div>
            <div class="input-group">
                <label>Terbangun Malam (kali)</label>
                <input type="number" name="night_awakenings" min="0" max="10"
                       value="<?= (int) $nightAwakenings ?>" placeholder="Contoh: 1">
            </div>
        </div>

        <button class="btn-primary" type="submit">
            Update Tidur
        </button>
        <div class="sleep-sub" style="margin-top: 10px;">
            Catatan: indikator kualitas ini adalah skrining harian ringan (bukan PSQI lengkap).
        </div>
    </form>
</section>


<!-- ================= HERO ================= -->
<section class="card sleep-hero">
    <div class="sleep-hero-inner">
        <div class="emoji-bubble">🌙</div>
        <div>
            <div class="sleep-title"><?= $status ?></div>
            <div class="sleep-sub">
                Tidur rata-rata <?= formatAverage($average) ?>
            </div>
            <div class="sleep-sub">
                Target aktif: <?= htmlspecialchars($sleepTargetLabel) ?>
            </div>
        </div>
    </div>
</section>



<!-- ================= PROGRESS ================= -->
<section class="card">
    <div class="summary-title">Ringkasan Mingguan</div>

    <div class="progress">
        <div class="progress-fill"
             style="width: <?= $averageScore * 10 ?>%;">
        </div>
    </div>

    <div class="summary-pill">
        Skor Mingguan <?= $averageScore ?>/10
    </div>
    <div class="sleep-sub" style="margin-top: 10px;">
        <?= htmlspecialchars($durationStatus) ?> | <?= htmlspecialchars($qualityStatus) ?>
    </div>
    <div class="sleep-sub">
        Latensi <?= (int) $sleepLatency ?> menit, terbangun malam <?= (int) $nightAwakenings ?> kali.
    </div>
</section>


<!-- ================= SCORE GAUGE ================= -->
<section class="card score-card">
    <div class="score-header">Skor Total Tidur</div>

    <div class="score-wrapper">
        <div class="score-number">
            <?= number_format($combinedSleepScore,1) ?> <small>/10.0</small>
        </div>

        <?php 
            $rotation = 180 + ($combinedSleepScore / 10) * 180; 
        ?>

        <div class="gauge">
            <div class="gauge-fill"
                 style="transform: scaleX(-1) rotate(<?= $rotation ?>deg);">
            </div>
        </div>
    </div>
</section>



<!-- ================= CHART (SKOR HARIAN) ================= -->
<section class="card chart-card">
    <canvas id="sleepChart"></canvas>
</section>

</main>


<script>
new Chart(document.getElementById('sleepChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($dates) ?>,
        datasets: [{
            label: 'Skor Harian',
            data: <?= json_encode($hours) ?>,
            borderColor: '#2ec4cc',
            backgroundColor: 'rgba(46,196,204,0.2)',
            tension: 0.4,
            fill: true,
            pointRadius: 4
        }]
    },
    options: {
        plugins: { legend: { display: false }},
        scales: {
            y: {
                min: 0,
                max: 10,
                ticks: { stepSize: 2 }
            }
        }
    }
});
</script>

<?php include 'includes/footer.php'; ?>
