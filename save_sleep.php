<?php
session_start();
require_once 'include.php';

$pageTitle = 'Sleep & Recovery';
include 'includes/header.php';

$user_id = $_SESSION['user_id'] ?? 1;
$today = date('Y-m-d');

/* ================= AUTO CREATE TODAY LOG ================= */

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

/* ================= WEEKLY DATA ================= */

$average = 0;
$score   = 0;
$status  = "Belum Ada Data";
$hours   = [];
$dates   = [];

$stmt = $pdo->prepare("
    SELECT sleep_date, sleep_start, sleep_end
    FROM sleep_logs
    WHERE user_id = ?
    AND sleep_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ORDER BY sleep_date ASC
");
$stmt->execute([$user_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($rows) {

    $total = 0;
    $count = 0;

    foreach ($rows as $row) {

        $date = $row['sleep_date'];
        $startTime = strtotime("$date {$row['sleep_start']}");
        $endTime   = strtotime("$date {$row['sleep_end']}");

        if ($endTime <= $startTime) {
            $endTime = strtotime("+1 day", $endTime);
        }

        $duration = ($endTime - $startTime) / 3600;

        $total += $duration;
        $count++;

        $hours[] = round($duration,2);
        $dates[] = date("d M", strtotime($date));
    }

    $average = round($total / $count, 2);
    $score   = round(min(10, ($average / 8) * 10), 1);

    if ($average < 6) $status = "Kurang Tidur";
    elseif ($average <= 8) $status = "Tidur Cukup";
    else $status = "Terlalu Lama Tidur";
}

function formatAverage($avg) {
    $m = round($avg * 60);
    return floor($m/60)." jam ".($m%60)." menit";
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
                Estimasi <?= formatAverage($average) ?>
            </div>
        </div>
    </div>
</section>


<!-- ================= WEEKLY ================= -->
<section class="card">
    <div class="summary-title">Ringkasan Mingguan</div>

    <div class="progress">
        <div class="progress-fill"
            style="width: <?= min(100, round(($average/8)*100)) ?>%;">
        </div>
    </div>

    <div class="summary-pill">
        <?= number_format($average,1,',','.') ?> Jam Rata-rata
    </div>
</section>


<!-- ================= SCORE ================= -->
<section class="card score-card">
    <div class="score-header">Skor Total Tidur</div>

    <div class="score-wrapper">
        <div class="score-number">
            <?= $score ?> <small>/10.0</small>
        </div>

        <div class="gauge">
            <div class="gauge-fill"
                style="transform: rotate(<?= ($score/10)*180 - 90 ?>deg);">
            </div>
        </div>
    </div>
</section>


<!-- ================= CHART ================= -->
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
            data: <?= json_encode($hours) ?>,
            borderColor: '#2ec4cc',
            backgroundColor: 'rgba(46,196,204,0.2)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        plugins: { legend: { display: false }},
        scales: { y: { min: 0, max: 10 }}
    }
});
</script>

<?php include 'includes/footer.php'; ?>
