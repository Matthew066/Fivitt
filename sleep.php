<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/auth_guard.php';
require_once 'includes/user_profile.php';
require_login();

ensure_users_profile_schema($pdo);

$pageTitle = 'Sleep & Recovery';
$bodyClass = 'sleep-page';
include 'includes/header.php';

$user_id = $_SESSION['user_id'] ?? 1;
$today = date('Y-m-d');
$selectedDate = $_GET['date'] ?? $today;

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate) || strtotime($selectedDate) === false || $selectedDate > $today) {
    $selectedDate = $today;
}

/* ================= LOAD SELECTED DATE ================= */

$check = $pdo->prepare("
    SELECT * FROM sleep_logs
    WHERE id_users = ? AND sleep_date = ?
");
$check->execute([$user_id, $selectedDate]);
$selectedLog = $check->fetch(PDO::FETCH_ASSOC);

if (!$selectedLog) {
    $selectedLog = [
        'sleep_date'  => $selectedDate,
        'sleep_start' => '22:00',
        'sleep_end'   => '06:00'
    ];
}

/* ================= HANDLE UPDATE ================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $sleepDate = $_POST['sleep_date'] ?? $today;
    $start = $_POST['sleep_start'];
    $end   = $_POST['sleep_end'];
    $sleepLatency = isset($_POST['sleep_latency_min']) ? (int) $_POST['sleep_latency_min'] : 20;
    $nightAwakenings = isset($_POST['night_awakenings']) ? (int) $_POST['night_awakenings'] : 0;

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $sleepDate) || strtotime($sleepDate) === false || $sleepDate > $today) {
        $sleepDate = $today;
    }

    $_SESSION['sleep_latency_min'] = max(0, min(180, $sleepLatency));
    $_SESSION['night_awakenings'] = max(0, min(10, $nightAwakenings));

    $existingLog = $pdo->prepare("
        SELECT id_users
        FROM sleep_logs
        WHERE id_users = ? AND sleep_date = ?
    ");
    $existingLog->execute([$user_id, $sleepDate]);

    if ($existingLog->fetchColumn()) {
        $update = $pdo->prepare("
            UPDATE sleep_logs
            SET sleep_start = ?, sleep_end = ?
            WHERE id_users = ? AND sleep_date = ?
        ");
        $update->execute([$start, $end, $user_id, $sleepDate]);
    } else {
        $insert = $pdo->prepare("
            INSERT INTO sleep_logs (id_users, sleep_date, sleep_start, sleep_end)
            VALUES (?, ?, ?, ?)
        ");
        $insert->execute([$user_id, $sleepDate, $start, $end]);
    }

    header("Location: sleep.php?date=" . urlencode($sleepDate));
    exit;
}

/* ================= SLEEP TARGET PROFILE ================= */
$sleepLatency = (int) ($_SESSION['sleep_latency_min'] ?? 20);
$nightAwakenings = (int) ($_SESSION['night_awakenings'] ?? 0);
$profileSettings = get_user_profile_settings($pdo, (int) $user_id);
$sleepTarget = get_sleep_target_by_age_group($profileSettings['effective_age_group']);
$sleepTargetMin = $sleepTarget['min'];
$sleepTargetMax = $sleepTarget['max'];
$sleepTargetLabel = $sleepTarget['label'];
$sleepProfileLabel = $sleepTarget['profile_label'];

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

/* ================= WEEKLY CHART ================= */
$weeklyChartMap = [];
$weeklyChartLabels = [];
$weeklyChartScores = [];

$weeklyChartStmt = $pdo->prepare("
    SELECT sleep_date, sleep_start, sleep_end
    FROM sleep_logs
    WHERE id_users = ?
      AND sleep_date >= DATE_SUB(CURDATE(), INTERVAL 41 DAY)
    ORDER BY sleep_date ASC
");
$weeklyChartStmt->execute([$user_id]);
$weeklyChartRows = $weeklyChartStmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($weeklyChartRows as $row) {
    $date = (string) ($row['sleep_date'] ?? '');
    if ($date === '') {
        continue;
    }

    $startTime = strtotime($date . ' ' . $row['sleep_start']);
    $endTime = strtotime($date . ' ' . $row['sleep_end']);

    if ($endTime <= $startTime) {
        $endTime = strtotime('+1 day', $endTime);
    }

    $duration = ($endTime - $startTime) / 3600;
    $dailyScore = 10 - (abs($duration - $sleepTargetMid) * 1.6);
    $dailyScore = max(0, min(10, $dailyScore));

    $weekStart = date('Y-m-d', strtotime('monday this week', strtotime($date)));
    if (!isset($weeklyChartMap[$weekStart])) {
        $weeklyChartMap[$weekStart] = ['total' => 0, 'count' => 0];
    }

    $weeklyChartMap[$weekStart]['total'] += $dailyScore;
    $weeklyChartMap[$weekStart]['count']++;
}

for ($i = 5; $i >= 0; $i--) {
    $weekStart = date('Y-m-d', strtotime('monday this week -' . $i . ' week'));
    $weekEnd = date('d M', strtotime($weekStart . ' +6 day'));
    $weeklyChartLabels[] = date('d M', strtotime($weekStart)) . ' - ' . $weekEnd;

    if (isset($weeklyChartMap[$weekStart]) && $weeklyChartMap[$weekStart]['count'] > 0) {
        $weeklyChartScores[] = round(
            $weeklyChartMap[$weekStart]['total'] / $weeklyChartMap[$weekStart]['count'],
            1
        );
    } else {
        $weeklyChartScores[] = 0;
    }
}

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

<!-- ================= HERO ================= -->
<section class="card sleep-hero">
    <div class="sleep-hero-inner">
        <div class="emoji-bubble">🌙</div>
        <div class="hero-copy">
            <div class="sleep-title"><?= $status ?></div>
            <div class="sleep-sub">
                Ringkasan kualitas tidur dan recovery kamu minggu ini berdasarkan durasi, latensi tidur, dan frekuensi terbangun malam.
            </div>
            <div class="hero-badges">
                <span class="hero-badge">Rata-rata <?= formatAverage($average) ?></span>
                <span class="hero-badge">Target <?= htmlspecialchars($sleepTargetLabel) ?></span>
                <span class="hero-badge">Profil <?= htmlspecialchars($sleepProfileLabel) ?></span>
                <span class="hero-badge"><?= htmlspecialchars($qualityStatus) ?></span>
            </div>
        </div>
    </div>
</section>

<!-- ================= INPUT ================= -->
<section class="card sleep-form-card">
    <div class="sleep-form-head">
        <div>
            <div class="summary-title">Input Tidur</div>
            <div class="sleep-sub">
                Catat jam tidur, pilih tanggal, lalu perbarui kalau kemarin sempat terlewat.
                Target tidur mengikuti profil usia kamu.
                <a href="profile_settings.php">Ubah profil usia</a>
            </div>
        </div>
    </div>

    <form method="POST" class="sleep-entry-form">
        <div class="input-row sleep-primary-row">
            <div class="input-group input-card input-card-accent">
                <label>Tanggal</label>
                <input type="date" name="sleep_date" max="<?= htmlspecialchars($today) ?>"
                       value="<?= htmlspecialchars($selectedDate) ?>" required>
                <small class="input-hint">Bisa dipakai untuk update tidur kemarin.</small>
            </div>
            <div class="input-group input-card sleep-date-summary">
                <span class="sleep-date-summary-label">Tanggal terpilih</span>
                <strong><?= htmlspecialchars(date('d M Y', strtotime($selectedDate))) ?></strong>
                <small>Data akan diperbarui jika log sudah ada.</small>
            </div>
        </div>

        <div class="input-row">
            <div class="input-group input-card">
                <label>Mulai Tidur</label>
                <input type="time" name="sleep_start"
                       value="<?= htmlspecialchars($selectedLog['sleep_start']) ?>" required>
            </div>

            <div class="input-group input-card">
                <label>Bangun</label>
                <input type="time" name="sleep_end"
                       value="<?= htmlspecialchars($selectedLog['sleep_end']) ?>" required>
            </div>
        </div>

        <div class="input-row sleep-metrics-row">
            <div class="input-group input-card">
                <label>Latensi Tidur</label>
                <input type="number" name="sleep_latency_min" min="0" max="180"
                       value="<?= (int) $sleepLatency ?>" placeholder="Contoh: 20">
                <small class="input-hint">Berapa menit sampai benar-benar tertidur.</small>
            </div>
            <div class="input-group input-card">
                <label>Terbangun Malam</label>
                <input type="number" name="night_awakenings" min="0" max="10"
                       value="<?= (int) $nightAwakenings ?>" placeholder="Contoh: 1">
                <small class="input-hint">Jumlah bangun di tengah malam.</small>
            </div>
        </div>

        <div class="sleep-form-footer">
            <div class="sleep-form-note">
                <strong>Catatan:</strong> indikator kualitas ini adalah skrining harian ringan, bukan PSQI lengkap.
            </div>
            <button class="btn-primary sleep-submit-btn" type="submit">
                Simpan Update Tidur
            </button>
        </div>
    </form>
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



<!-- ================= CHART (SKOR MINGGUAN) ================= -->
<section class="card chart-card">
    <div class="summary-title">Grafik Skor Mingguan</div>
    <canvas id="sleepChart" height="320"></canvas>
</section>

</main>


<script src="assets/js/chart.js"></script>
<script>
(() => {
    const canvas = document.getElementById('sleepChart');
    if (!canvas || typeof Chart === 'undefined') {
        return;
    }

    new Chart(canvas, {
        type: 'line',
        data: {
            labels: <?= json_encode($weeklyChartLabels) ?>,
            datasets: [{
                label: 'Skor Mingguan',
                data: <?= json_encode($weeklyChartScores) ?>,
                borderColor: '#2ec4cc',
                backgroundColor: 'rgba(46, 196, 204, 0.18)',
                pointBackgroundColor: '#4facfe',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6,
                borderWidth: 3,
                tension: 0.35,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                intersect: false,
                mode: 'index'
            },
            plugins: {
                legend: { display: false }
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        color: '#64748b'
                    }
                },
                y: {
                    min: 0,
                    max: 10,
                    ticks: {
                        stepSize: 2,
                        color: '#64748b'
                    },
                    grid: {
                        color: 'rgba(148, 163, 184, 0.18)'
                    }
                }
            }
        }
    });
})();
</script>

<?php include 'includes/footer.php'; ?>
