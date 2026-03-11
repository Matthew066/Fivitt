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
    WHERE user_id = ?
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
    WHERE user_id = ?
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
    WHERE user_id = ?
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

if ($bmi == 0) {
    $workoutPlan = "Isi data IMT agar rekomendasi latihan lebih personal. Mulai dari jalan cepat 20-30 menit, 5 hari per minggu.";
} elseif ($bmi < 18.5) {
    $workoutPlan = "Prioritaskan latihan kekuatan 3-4x/minggu dengan progresif load, tambahkan kardio ringan 2-3x/minggu.";
} elseif ($bmi < 23) {
    $workoutPlan = "Pola seimbang: 3x latihan kekuatan + 2-3x kardio sedang per minggu untuk menjaga kebugaran dan komposisi tubuh.";
} elseif ($bmi < 30) {
    $workoutPlan = "Fokus fat-loss aman: brisk walk/circuit training 30-45 menit, 5-6 hari per minggu + kekuatan 2-3x/minggu.";
} else {
    $workoutPlan = "Mulai low-impact cardio (jalan, sepeda statis) 20-40 menit bertahap, plus kekuatan seluruh tubuh 2-3x/minggu.";
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
    margin-bottom: 8px;
}

.water-subtitle {
    font-size: 13px;
    color: #5f6b77;
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

.water-status {
    margin-top: 12px;
    font-size: 14px;
    font-weight: 600;
}

.water-advice {
    margin-top: 6px;
    font-size: 13px;
    color: #4b5563;
}

.water-low { color: #b23a2f; }
.water-ok { color: #1f7a6c; }
.water-good { color: #157347; }
.water-high { color: #8a6d1d; }
.water-over { color: #8b1e3f; }
.water-none { color: #666; }

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
        #2a9d8f 43%,
        #e9c46a 43%,
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

/* ===== PERSONALIZED WORKOUT ===== */
.activity-meta {
    margin-top: 8px;
    font-size: 14px;
}

.activity-status {
    margin-top: 10px;
    font-size: 15px;
    font-weight: 700;
}

.activity-advice {
    margin-top: 8px;
    font-size: 14px;
    color: #4b5563;
    line-height: 1.5;
}

.act-low { color: #b23a2f; }
.act-good { color: #157347; }
.act-high { color: #8a6d1d; }
.act-none { color: #666; }


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
            <div class="sleep-sub">
                Pilar sehat tercapai: <?= (int) $healthyPillarCount ?>/4 (target minimal 3/4)
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
            <small class="water-subtitle" style="display:block; margin-top:8px; margin-bottom:0;">
                Patokan umum: 150-300 menit/minggu aktivitas intensitas sedang.
            </small>
        </div>

        <!-- WATER -->
        <div class="water-card">

            <div class="water-title">&#128167; Asupan Air Harian</div>
            <div class="water-subtitle">
                Target dasar air putih: 1,1-1,5 L/hari (5-6 gelas @250 ml)
            </div>

            <div class="glass-wrapper">
                <?php for ($i = 1; $i <= 12; $i++): ?>
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
            <div class="water-advice">
                Rumus berbasis energi: Total Air (mL) = Total Kalori Harian x 1,05.
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
    <div class="summary-title">BMI/IMT Terakhir</div>

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
            <span>&lt;18,5</span>
            <span>18,5-22,9</span>
            <span>23,0-29,9</span>
            <span>&ge;30,0</span>
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
    <div class="sleep-sub" style="margin-top: 10px;">
        Bobot Health Score: BMI 35% | Water 15% | Workout 25% | Sleep 25%
    </div>
    <div class="sleep-sub">
        Skor Pilar: BMI <?= number_format($bmiPillarScore,1) ?>, Water <?= number_format($waterPillarScore,1) ?>,
        Workout <?= number_format($workoutPillarScore,1) ?>, Sleep <?= number_format($sleepPillarScore,1) ?>
    </div>
</section>

<!-- ================= PERSONALIZED WORKOUT ================= -->
<section class="card">
    <div class="summary-title">Workout Personal (Mingguan)</div>

    <div class="activity-meta">
        Total aktivitas 7 hari: <strong><?= (int) $weeklyActivityMinutes ?> menit</strong>
        (target sehat: 150-300 menit/minggu)
    </div>

    <div class="activity-status <?= $activityWeeklyClass ?>">
        <?= $activityWeeklyStatus ?>
    </div>

    <div class="activity-advice">
        <?php if ($activityLeft > 0): ?>
            Tambah sekitar <strong><?= (int) $activityLeft ?> menit</strong> lagi minggu ini agar mencapai target minimum.
        <?php else: ?>
            Target minimum minggu ini sudah tercapai. Pertahankan ritme dan pastikan hari pemulihan tetap cukup.
        <?php endif; ?>
    </div>

    <div class="activity-advice">
        Rekomendasi personal: <?= htmlspecialchars($workoutPlan) ?>
    </div>
    <div class="activity-advice">
        Pantau denyut jantung saat latihan agar intensitas tetap aman dan efektif sesuai kondisi tubuh.
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
