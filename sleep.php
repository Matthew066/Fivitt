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
    WHERE user_id = ? AND sleep_date = ?
");
$check->execute([$user_id, $today]);
$todayLog = $check->fetch(PDO::FETCH_ASSOC);

if (!$todayLog) {
    $insert = $pdo->prepare("
        INSERT INTO sleep_logs (user_id, sleep_date, sleep_start, sleep_end)
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

    $update = $pdo->prepare("
        UPDATE sleep_logs
        SET sleep_start = ?, sleep_end = ?
        WHERE user_id = ? AND sleep_date = ?
    ");
    $update->execute([$start, $end, $user_id, $today]);

    header("Location: sleep.php");
    exit;
}

/* ================= WEEKLY CALCULATION ================= */

$hours   = [];   // ini sekarang berisi SKOR
$dates   = [];
$totalScore = 0;
$count   = 0;

$stmt = $pdo->prepare("
    SELECT sleep_date, sleep_start, sleep_end
    FROM sleep_logs
    WHERE user_id = ?
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

    /* ===== SMART SCORE (OPTIMAL 8 JAM) ===== */
    $dailyScore = 10 - (abs($duration - 8) * 1.5);
    $dailyScore = max(0, min(10, $dailyScore));
    $dailyScore = round($dailyScore, 1);

    $hours[] = $dailyScore;
    $dates[] = date("d M", strtotime($date));

    $totalScore += $dailyScore;
    $count++;
}

$averageScore = $count ? round($totalScore / $count, 1) : 0;

$user_id = $_SESSION['user_id'] ?? 1;

$stmt = $pdo->prepare("
    SELECT sleep_date, sleep_start, sleep_end
    FROM sleep_logs
    WHERE user_id = ?
    AND sleep_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
");
$stmt->execute([$user_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total = 0;
$count = 0;

foreach ($rows as $row) {

    $start = strtotime($row['sleep_date'] . ' ' . $row['sleep_start']);
    $end   = strtotime($row['sleep_date'] . ' ' . $row['sleep_end']);

    if ($end <= $start) {
        $end = strtotime("+1 day", $end);
    }

    $total += ($end - $start) / 3600;
    $count++;
}

$average = $count ? round($total / $count, 2) : 0;

function formatAverage($avg) {
    $m = round($avg * 60);
    $jam = floor($m / 60);
    $menit = $m % 60;

    return $jam . " jam " . $menit . " menit";
}

/* ================= STATUS ================= */

if ($averageScore < 6) {
    $status = "Kurang";
} elseif ($averageScore < 8) {
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

        <button class="btn-primary" type="submit">
            Update Tidur
        </button>
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
</section>


<!-- ================= SCORE GAUGE ================= -->
<section class="card score-card">
    <div class="score-header">Skor Total Tidur</div>

    <div class="score-wrapper">
        <div class="score-number">
            <?= number_format($averageScore,1) ?> <small>/10.0</small>
        </div>

        <?php 
            $rotation = 180 + ($averageScore / 10) * 180; 
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
