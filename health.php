<?php
session_start();
require_once 'includes/db.php';

$pageTitle = 'Health Overview';
include 'includes/header.php';

$user_id = $_SESSION['user_id'] ?? 1;
$today = date('Y-m-d');

/* ================= AUTO CREATE CHECKIN ================= */

$check = $pdo->prepare("
    SELECT * FROM daily_checkins
    WHERE user_id = ? AND checkin_date = ?
");
$check->execute([$user_id, $today]);
$todayData = $check->fetch(PDO::FETCH_ASSOC);

if (!$todayData) {
    $insert = $pdo->prepare("
        INSERT INTO daily_checkins (user_id, checkin_date, activity_minutes, water_intake_ml)
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
        WHERE user_id = ? AND checkin_date = ?
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
            (user_id, height_cm, weight_kg, bmi_value, recorded_at)
            VALUES (?, ?, ?, ?, CURDATE())
        ");
        $insertBMI->execute([$user_id, $height, $weight, $bmi]);
    }

    header("Location: health.php");
    exit;
}

$activity = $todayData['activity_minutes'];
$water_ml = (int) ($todayData['water_intake_ml'] ?? 0);
$water_glass = (int) round($water_ml / 250);
$water_glass = max(0, min(8, $water_glass));


/* ================= GET BMI TERBARU ================= */

$bmiStmt = $pdo->prepare("
    SELECT bmi_value, height_cm, weight_kg
    FROM bmi_records
    WHERE user_id = ?
    ORDER BY recorded_at DESC
    LIMIT 1
");
$bmiStmt->execute([$user_id]);
$bmiRow = $bmiStmt->fetch(PDO::FETCH_ASSOC) ?: [];
$bmi = $bmiRow['bmi_value'] ?? 0;
$lastHeight = $bmiRow['height_cm'] ?? '';
$lastWeight = $bmiRow['weight_kg'] ?? '';

/* ================= BMI STATUS ================= */

if ($bmi == 0) {
    $bmiStatus = "Belum Ada Data";
} elseif ($bmi < 18.5) {
    $bmiStatus = "Kurus";
} elseif ($bmi < 25) {
    $bmiStatus = "Normal";
} elseif ($bmi < 30) {
    $bmiStatus = "Overweight";
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
} elseif ($bmi < 25) {
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
    WHERE user_id = ?
    AND checkin_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
");
$stmt->execute([$user_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalScore = 0;
$count = 0;

foreach ($rows as $row) {

    $dailyScore = min(10,
        ($row['activity_minutes']/30)*4 +
        ($row['water_intake_ml']/2000)*4 +
        ($bmi > 18 && $bmi < 25 ? 2 : 1)
    );

    $totalScore += $dailyScore;
    $count++;
}

$averageScore = $count ? round($totalScore/$count,1) : 0;

if ($averageScore < 5) {
    $status = "Perlu Peningkatan";
} elseif ($averageScore < 8) {
    $status = "Baik";
} else {
    $status = "Optimal";
}

?>

<style>

/* ===== WATER CARD ===== */
.water-card {
    background: #ffffff;
    padding: 24px;
    border-radius: 20px;
    box-shadow: 0 12px 24px rgba(0,150,255,0.08);
    text-align: center;
}

.water-title {
    font-size: 18px;
    font-weight: 600;
    color: #0077b6;
    margin-bottom: 18px;
}

/* ===== GLASS WRAPPER ===== */
.glass-wrapper {
    display: flex;
    justify-content: center;
    gap: 10px;
    flex-wrap: nowrap;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    padding-bottom: 4px;
}

/* ===== GLASS ===== */
.glass {
    width: 36px;
    height: 60px;
    border: 2px solid #00b4d8;
    border-radius: 8px 8px 15px 15px;
    position: relative;
    overflow: hidden;
    cursor: pointer;
    transition: 0.3s ease;
    background: rgba(255,255,255,0.7);
}

.glass:hover {
    transform: translateY(-6px);
}

@media (max-width: 520px) {
    .glass-wrapper {
        justify-content: flex-start;
    }
}

/* ===== WATER ===== */
.water {
    position: absolute;
    bottom: 0;
    width: 100%;
    height: 0%;
    background: linear-gradient(to top,#00b4d8,#90e0ef);
    transition: height 0.4s ease;
}

/* ACTIVE GLASS */
.glass.active .water {
    height: 85%;
}

/* ===== LABEL ===== */
.glass-label {
    margin-top: 25px;
    font-size: 16px;
}

.glass-label strong {
    font-size: 24px;
    color: #0077b6;
}

.glass-label small {
    display: block;
    font-size: 13px;
    color: #666;
    margin-top: 5px;
}

/* ===== BMI GAUGE ===== */
.bmi-gauge {
    margin-top: 16px;
}

.bmi-track {
    position: relative;
    height: 12px;
    border-radius: 999px;
    background: linear-gradient(90deg,
        #f4a261 0%,
        #f4a261 28%,
        #2a9d8f 28%,
        #2a9d8f 50%,
        #e9c46a 50%,
        #e9c46a 67%,
        #e76f51 67%,
        #e76f51 100%
    );
    box-shadow: inset 0 0 0 1px rgba(0,0,0,0.08);
}

.bmi-marker {
    position: absolute;
    top: -6px;
    width: 3px;
    height: 24px;
    background: #1d3557;
    border-radius: 2px;
}

.bmi-labels {
    display: flex;
    justify-content: space-between;
    margin-top: 10px;
    font-size: 12px;
    color: #556;
}

.bmi-status {
    margin-top: 8px;
    font-weight: 600;
}

.bmi-gauge.bmi-none {
    opacity: 0.6;
}

.bmi-gauge.bmi-none .bmi-marker {
    display: none;
}

.bmi-low { color: #b65734; }
.bmi-normal { color: #1f7a6c; }
.bmi-high { color: #a37c15; }
.bmi-obese { color: #b23a2f; }
.bmi-none { color: #666; }


</style>

<main class="app">

<!-- ================= HERO ================= -->
<section class="card sleep-hero">
    <div class="sleep-hero-inner">
        <div class="emoji-bubble">&#128170;</div>
        <div>
            <div class="sleep-title"><?= $status ?></div>
            <div class="sleep-sub">
                Skor rata-rata <?= $averageScore ?>/10 minggu ini
            </div>
        </div>
    </div>
</section>


<!-- ================= CHECKIN ================= -->
<section class="card">

    <div class="summary-title">Your health and Check-In (auto)</div>

    <form method="POST">

        <!-- ACTIVITY -->
        <div class="input-group">
            <label>Aktivitas Fisik (menit)</label>
            <input type="number"
                   name="activity_minutes"
                   min="0"
                   value="<?= htmlspecialchars((string) $activity) ?>"
                   placeholder="Contoh: 30">
        </div>

        <!-- WATER -->
        <div class="water-card">

            <div class="water-title">&#128167; Asupan Air Harian</div>

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
                <small>(1 gelas = 250ml)</small>
            </div>

            <input type="hidden"
                   name="water_intake_ml"
                   id="waterInput"
                   value="<?= $water_ml ?>">

        </div>

        <!-- BMI INPUT -->
        <div class="input-group">
            <label>Tinggi Badan (cm)</label>
            <input type="number"
                   name="height_cm"
                   min="1"
                   value="<?= htmlspecialchars((string) $lastHeight) ?>"
                   placeholder="Contoh: 170">
        </div>

        <div class="input-group">
            <label>Berat Badan (kg)</label>
            <input type="number"
                   step="0.1"
                   min="1"
                   name="weight_kg"
                   value="<?= htmlspecialchars((string) $lastWeight) ?>"
                   placeholder="Contoh: 65">
        </div>

        <button class="btn-primary" type="submit">
            Simpan Semua Data
        </button>

    </form>
</section>


<!-- ================= BMI RESULT ================= -->
<section class="card">
    <div class="summary-title">BMI Terakhir</div>

    <div class="score-number">
        <?= $bmi ?>
    </div>

    <div class="sleep-sub bmi-status <?= $bmiGaugeClass ?>">
        <?= $bmiStatus ?>
    </div>

    <div class="bmi-gauge <?= $bmiGaugeClass ?>">
        <div class="bmi-track">
            <div class="bmi-marker" style="left: <?= $bmiPercent ?>%;"></div>
        </div>
        <div class="bmi-labels">
            <span>Kurus</span>
            <span>Normal</span>
            <span>Overweight</span>
            <span>Obesitas</span>
        </div>
    </div>
</section>


<!-- ================= WEEKLY PROGRESS ================= -->
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
</script>

<?php include 'includes/footer.php'; ?>
