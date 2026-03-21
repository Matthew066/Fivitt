<?php
session_start();
require_once 'includes/db.php';

$pageTitle = 'Lunar Harmony Insight';
include 'includes/header.php';

$user_id = $_SESSION['user_id'] ?? 1;
$today = date('Y-m-d');

/* ================= SCHEMA GUARD ================= */
$pdo->exec("
    CREATE TABLE IF NOT EXISTS mens_cycle_logs (
        id_mens_cycle_logs INT AUTO_INCREMENT PRIMARY KEY,
        id_users INT NOT NULL,
        period_start_date DATE NOT NULL,
        period_end_date DATE NULL,
        mood_type VARCHAR(20) DEFAULT 'calm',
        symptom_tags VARCHAR(255) DEFAULT '',
        flow_level INT DEFAULT 3,
        reported_cycle_length INT DEFAULT 28,
        period_length INT DEFAULT 5,
        symptom_score INT DEFAULT 5,
        mood_score INT DEFAULT 5,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_user_period (id_users, period_start_date)
    )
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS mens_user_profiles (
        id_users INT PRIMARY KEY,
        birth_year INT DEFAULT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )
");

function safeAlter(PDO $pdo, string $sql): void
{
    try {
        $pdo->exec($sql);
    } catch (Throwable $e) {
        // Ignore duplicate column and legacy schema alter errors.
    }
}

function columnExists(PDO $pdo, string $tableName, string $columnName): bool
{
    static $dbName = null;
    if ($dbName === null) {
        $dbName = (string)$pdo->query("SELECT DATABASE()")->fetchColumn();
    }
    if ($dbName === '') return false;

    $stmt = $pdo->prepare("
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = ?
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
        LIMIT 1
    ");
    $stmt->execute([$dbName, $tableName, $columnName]);
    return (bool)$stmt->fetchColumn();
}

function addColumnIfMissing(PDO $pdo, string $tableName, string $columnName, string $ddl): void
{
    if (!columnExists($pdo, $tableName, $columnName)) {
        safeAlter($pdo, "ALTER TABLE {$tableName} ADD COLUMN {$columnName} {$ddl}");
    }
}

// Avoid long ALTER TABLE waits on every request.
try {
    $pdo->exec("SET SESSION innodb_lock_wait_timeout = 3");
} catch (Throwable $e) {
    // Ignore if not supported.
}

addColumnIfMissing($pdo, 'mens_cycle_logs', 'period_end_date', 'DATE NULL');
addColumnIfMissing($pdo, 'mens_cycle_logs', 'mood_type', "VARCHAR(20) DEFAULT 'calm'");
addColumnIfMissing($pdo, 'mens_cycle_logs', 'symptom_tags', "VARCHAR(255) DEFAULT ''");
addColumnIfMissing($pdo, 'mens_cycle_logs', 'flow_level', 'INT DEFAULT 3');
addColumnIfMissing($pdo, 'mens_user_profiles', 'birth_month', 'INT DEFAULT 6');
addColumnIfMissing($pdo, 'mens_user_profiles', 'birth_day', 'INT DEFAULT 15');
addColumnIfMissing($pdo, 'mens_user_profiles', 'birth_hour', 'INT DEFAULT 12');

/* ================= HELPERS ================= */
function clampNumber($value, $min, $max): int
{
    return max($min, min($max, (int)$value));
}

function moonPhaseName(string $date): string
{
    $synodicMonth = 29.53058867;
    $knownNewMoon = strtotime('2000-01-06 18:14:00 UTC');
    $target = strtotime($date . ' 12:00:00 UTC');

    if ($target === false || $knownNewMoon === false) {
        return 'Unknown';
    }

    $daysSince = ($target - $knownNewMoon) / 86400;
    $age = fmod($daysSince, $synodicMonth);
    if ($age < 0) {
        $age += $synodicMonth;
    }

    if ($age < 1.84566) return 'New Moon';
    if ($age < 5.53699) return 'Waxing Crescent';
    if ($age < 9.22831) return 'First Quarter';
    if ($age < 12.91963) return 'Waxing Gibbous';
    if ($age < 16.61096) return 'Full Moon';
    if ($age < 20.30228) return 'Waning Gibbous';
    if ($age < 23.99361) return 'Last Quarter';
    if ($age < 27.68493) return 'Waning Crescent';
    return 'New Moon';
}

function moonPhaseDataLocal(string $date): array
{
    $synodicMonth = 29.53058867;
    $knownNewMoon = strtotime('2000-01-06 18:14:00 UTC');
    $target = strtotime($date . ' 12:00:00 UTC');

    if ($target === false || $knownNewMoon === false) {
        return ['phase' => 'Unknown', 'age' => 0.0, 'illumination' => 0.0, 'source' => 'local'];
    }

    $daysSince = ($target - $knownNewMoon) / 86400;
    $age = fmod($daysSince, $synodicMonth);
    if ($age < 0) $age += $synodicMonth;

    $phase = moonPhaseName($date);
    $illumination = (1 - cos(2 * M_PI * $age / $synodicMonth)) / 2;

    return [
        'phase' => $phase,
        'age' => $age,
        'illumination' => $illumination,
        'source' => 'local'
    ];
}

function moonPhaseData(string $date): array
{
    return moonPhaseDataLocal($date);
}

function lunarEnergyInsight(string $phase): array
{
    if ($phase === 'New Moon') {
        return ['Reflection & Rest', 'Ideal untuk refleksi, journaling, dan pemulihan energi.'];
    }
    if (str_contains($phase, 'Waxing') || $phase === 'First Quarter') {
        return ['Growth & Planning', 'Bagus untuk menyusun rencana, belajar, dan membangun rutinitas.'];
    }
    if ($phase === 'Full Moon') {
        return ['High Vitality Window', 'Cocok untuk aktivitas sosial, presentasi, dan momen produktif.'];
    }
    return ['Recovery & Reset', 'Prioritaskan tidur, hidrasi, dan ritme aktivitas yang lebih tenang.'];
}

function normalizeCycleIndex(int $value, int $mod): int
{
    return (($value % $mod) + $mod) % $mod;
}

function ganzhiFromIndex(int $index): array
{
    $stems = ['Jia', 'Yi', 'Bing', 'Ding', 'Wu', 'Ji', 'Geng', 'Xin', 'Ren', 'Gui'];
    $branches = ['Zi', 'Chou', 'Yin', 'Mao', 'Chen', 'Si', 'Wu', 'Wei', 'Shen', 'You', 'Xu', 'Hai'];
    $stemElements = ['Wood', 'Wood', 'Fire', 'Fire', 'Earth', 'Earth', 'Metal', 'Metal', 'Water', 'Water'];
    $animals = ['Rat', 'Ox', 'Tiger', 'Rabbit', 'Dragon', 'Snake', 'Horse', 'Goat', 'Monkey', 'Rooster', 'Dog', 'Pig'];

    $i = normalizeCycleIndex($index, 60);
    $stemIndex = $i % 10;
    $branchIndex = $i % 12;

    return [
        'index60' => $i,
        'stem' => $stems[$stemIndex],
        'branch' => $branches[$branchIndex],
        'element' => $stemElements[$stemIndex],
        'animal' => $animals[$branchIndex],
        'text' => $stems[$stemIndex] . $branches[$branchIndex]
    ];
}

function branchElementByIndex(int $branchIndex): string
{
    $branchElements = ['Water', 'Earth', 'Wood', 'Wood', 'Earth', 'Fire', 'Fire', 'Earth', 'Metal', 'Metal', 'Earth', 'Water'];
    return $branchElements[normalizeCycleIndex($branchIndex, 12)];
}

function baziElementInsight(string $dominant, string $weak): string
{
    $guidance = [
        'Wood' => 'Elemen dominan Wood: jaga ritme growth bertahap, tidur stabil, dan aktivitas mobilitas rutin.',
        'Fire' => 'Elemen dominan Fire: energi cenderung tinggi, tetap jaga hidrasi dan pemulihan agar tidak overdrive.',
        'Earth' => 'Elemen dominan Earth: struktur rutinitas harian biasanya membantu kestabilan mood dan siklus.',
        'Metal' => 'Elemen dominan Metal: fokus pada konsistensi, manajemen stres, dan jadwal yang terukur.',
        'Water' => 'Elemen dominan Water: sensitivitas tubuh meningkat saat transisi fase, prioritaskan recovery dan kualitas tidur.'
    ];
    $base = $guidance[$dominant] ?? 'Pertahankan pola hidup seimbang antar aktivitas, hidrasi, dan tidur.';
    return $base . ' Elemen terendah: ' . $weak . ' dapat diperkuat lewat rutinitas yang konsisten.';
}

function getBaziData(?int $birthYear, int $birthMonth = 6, int $birthDay = 15, int $birthHour = 12): array
{
    if (!$birthYear) {
        return [
            'zodiac' => 'Unknown',
            'element' => 'Unknown',
            'bazi' => 'Unknown',
            'source' => 'local_bazi',
            'insight' => 'Tambahkan data kelahiran untuk menghitung BaZi lokal (4 pilar).',
            'pillars' => []
        ];
    }

    $birthMonth = max(1, min(12, $birthMonth));
    $birthDay = max(1, min(28, $birthDay));
    $birthHour = max(0, min(23, $birthHour));

    $stems = ['Jia', 'Yi', 'Bing', 'Ding', 'Wu', 'Ji', 'Geng', 'Xin', 'Ren', 'Gui'];
    $branches = ['Zi', 'Chou', 'Yin', 'Mao', 'Chen', 'Si', 'Wu', 'Wei', 'Shen', 'You', 'Xu', 'Hai'];
    $stemElements = ['Wood', 'Wood', 'Fire', 'Fire', 'Earth', 'Earth', 'Metal', 'Metal', 'Water', 'Water'];

    // Approximation baseline: 1984 is JiaZi in sexagenary cycle.
    $yearPillar = ganzhiFromIndex($birthYear - 1984);
    $yearStemIndex = $yearPillar['index60'] % 10;

    // Gregorian month mapped to branch cycle: Jan=Chou ... Dec=Zi.
    $monthBranchIndex = $birthMonth % 12;
    $tigerMonthOrder = ($birthMonth >= 2) ? ($birthMonth - 1) : 12; // Feb=1 ... Jan=12
    $monthStemStart = normalizeCycleIndex(($yearStemIndex * 2) + 2, 10);
    $monthStemIndex = normalizeCycleIndex($monthStemStart + ($tigerMonthOrder - 1), 10);
    $monthPillar = [
        'text' => $stems[$monthStemIndex] . $branches[$monthBranchIndex],
        'element' => $stemElements[$monthStemIndex]
    ];

    $birthDate = sprintf('%04d-%02d-%02d', $birthYear, $birthMonth, $birthDay);
    $baseDate = strtotime('1984-02-02 00:00:00');
    $targetDate = strtotime($birthDate . ' 00:00:00');
    $daysDiff = (int)floor((($targetDate ?: $baseDate) - $baseDate) / 86400);
    $dayPillar = ganzhiFromIndex($daysDiff);
    $dayStemIndex = $dayPillar['index60'] % 10;

    $hourBranchIndex = (int)floor((($birthHour + 1) % 24) / 2);
    $hourStemStart = normalizeCycleIndex(($dayStemIndex % 5) * 2, 10);
    $hourStemIndex = normalizeCycleIndex($hourStemStart + $hourBranchIndex, 10);
    $hourPillar = [
        'text' => $stems[$hourStemIndex] . $branches[$hourBranchIndex],
        'element' => $stemElements[$hourStemIndex]
    ];

    $elementCount = ['Wood' => 0, 'Fire' => 0, 'Earth' => 0, 'Metal' => 0, 'Water' => 0];
    foreach ([$yearPillar['element'], $monthPillar['element'], $dayPillar['element'], $hourPillar['element']] as $el) {
        $elementCount[$el]++;
    }
    foreach ([
        branchElementByIndex($yearPillar['index60'] % 12),
        branchElementByIndex($monthBranchIndex),
        branchElementByIndex($dayPillar['index60'] % 12),
        branchElementByIndex($hourBranchIndex)
    ] as $el) {
        $elementCount[$el]++;
    }
    arsort($elementCount);
    $dominantElement = array_key_first($elementCount);
    $weakElement = array_key_last($elementCount);

    return [
        'zodiac' => $yearPillar['animal'],
        'element' => $dayPillar['element'],
        'bazi' => $yearPillar['text'] . ' ' . $monthPillar['text'] . ' ' . $dayPillar['text'] . ' ' . $hourPillar['text'],
        'source' => 'local_bazi',
        'insight' => baziElementInsight((string)$dominantElement, (string)$weakElement),
        'pillars' => [
            'year' => $yearPillar['text'],
            'month' => $monthPillar['text'],
            'day' => $dayPillar['text'],
            'hour' => $hourPillar['text']
        ],
        'dominant' => $dominantElement,
        'weak' => $weakElement
    ];
}

function elementOfDay(string $date): string
{
    $elements = ['Wood', 'Fire', 'Earth', 'Metal', 'Water'];
    $dayNumber = (int)date('z', strtotime($date));
    return $elements[$dayNumber % 5];
}

function calculateStdDev(array $values): float
{
    $count = count($values);
    if ($count <= 1) return 0.0;

    $avg = array_sum($values) / $count;
    $sum = 0.0;
    foreach ($values as $value) {
        $sum += pow(($value - $avg), 2);
    }

    return sqrt($sum / ($count - 1));
}

/* ================= CONSTANTS ================= */
$moodOptions = [
    'very_low' => ['label' => 'Very Low', 'emoji' => '😞'],
    'moody' => ['label' => 'Moody', 'emoji' => '😕'],
    'calm' => ['label' => 'Calm', 'emoji' => '🙂'],
    'energetic' => ['label' => 'Energetic', 'emoji' => '😄']
];

$symptomOptions = [
    'cramps' => 'Kram',
    'bloating' => 'Kembung',
    'headache' => 'Sakit Kepala',
    'nausea' => 'Mual',
    'fatigue' => 'Lelah',
    'back_pain' => 'Nyeri Pinggang',
    'acne' => 'Jerawat',
    'mood_swing' => 'Mood Swing'
];

/* ================= HANDLE SUBMIT ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $birthYear = !empty($_POST['birth_year']) ? clampNumber($_POST['birth_year'], 1940, (int)date('Y')) : null;
    $birthMonth = !empty($_POST['birth_month']) ? clampNumber($_POST['birth_month'], 1, 12) : 6;
    $birthDay = !empty($_POST['birth_day']) ? clampNumber($_POST['birth_day'], 1, 28) : 15;
    $birthHour = isset($_POST['birth_hour']) ? clampNumber($_POST['birth_hour'], 0, 23) : 12;
    $periodStart = $_POST['period_start_date'] ?? $today;
    $periodEnd = $_POST['period_end_date'] ?? '';
    $moodType = $_POST['mood_type'] ?? 'calm';
    $moodType = array_key_exists($moodType, $moodOptions) ? $moodType : 'calm';
    $flowLevel = isset($_POST['flow_level']) ? clampNumber($_POST['flow_level'], 1, 5) : 3;

    $symptomsRaw = $_POST['symptoms'] ?? [];
    $symptoms = [];
    if (is_array($symptomsRaw)) {
        foreach ($symptomsRaw as $key) {
            if (array_key_exists($key, $symptomOptions)) {
                $symptoms[] = $key;
            }
        }
    }
    $symptomTags = implode(',', $symptoms);

    $startTs = strtotime($periodStart);
    $endTs = $periodEnd ? strtotime($periodEnd) : false;
    if (!$endTs || ($startTs && $endTs < $startTs)) {
        $periodEnd = date('Y-m-d', strtotime($periodStart . ' +4 days'));
    }

    $profileStmt = $pdo->prepare("
        INSERT INTO mens_user_profiles (id_users, birth_year, birth_month, birth_day, birth_hour)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            birth_year = VALUES(birth_year),
            birth_month = VALUES(birth_month),
            birth_day = VALUES(birth_day),
            birth_hour = VALUES(birth_hour)
    ");
    $profileStmt->execute([$user_id, $birthYear, $birthMonth, $birthDay, $birthHour]);

    $logStmt = $pdo->prepare("
        INSERT INTO mens_cycle_logs
            (id_users, period_start_date, period_end_date, mood_type, symptom_tags, flow_level)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            period_end_date = VALUES(period_end_date),
            mood_type = VALUES(mood_type),
            symptom_tags = VALUES(symptom_tags),
            flow_level = VALUES(flow_level)
    ");
    $logStmt->execute([$user_id, $periodStart, $periodEnd, $moodType, $symptomTags, $flowLevel]);

    header("Location: mens_fivit.php");
    exit;
}

/* ================= LOAD DATA ================= */
$profile = $pdo->prepare("SELECT birth_year, birth_month, birth_day, birth_hour FROM mens_user_profiles WHERE id_users = ? LIMIT 1");
$profile->execute([$user_id]);
$profileRow = $profile->fetch(PDO::FETCH_ASSOC) ?: [];
$birthYear = !empty($profileRow['birth_year']) ? (int)$profileRow['birth_year'] : null;
$birthMonth = !empty($profileRow['birth_month']) ? (int)$profileRow['birth_month'] : 6;
$birthDay = !empty($profileRow['birth_day']) ? (int)$profileRow['birth_day'] : 15;
$birthHour = isset($profileRow['birth_hour']) ? (int)$profileRow['birth_hour'] : 12;

$cyclesStmt = $pdo->prepare("
    SELECT period_start_date, period_end_date, mood_type, symptom_tags, flow_level
    FROM mens_cycle_logs
    WHERE id_users = ?
    ORDER BY period_start_date ASC
");
$cyclesStmt->execute([$user_id]);
$cycles = $cyclesStmt->fetchAll(PDO::FETCH_ASSOC);

$latest = end($cycles) ?: null;
if ($latest !== false) {
    reset($cycles);
}

$latestMood = $latest['mood_type'] ?? 'calm';
$selectedSymptoms = !empty($latest['symptom_tags']) ? explode(',', $latest['symptom_tags']) : [];
$latestFlow = isset($latest['flow_level']) ? clampNumber($latest['flow_level'], 1, 5) : 3;
$flowLabelMap = [
    1 => 'Sangat Ringan',
    2 => 'Ringan',
    3 => 'Normal',
    4 => 'Deras',
    5 => 'Sangat Deras'
];
$latestFlowLabel = $flowLabelMap[$latestFlow] ?? 'Normal';

$cycleLengthsActual = [];
$intervalLabels = [];
for ($i = 1; $i < count($cycles); $i++) {
    $prev = strtotime($cycles[$i - 1]['period_start_date']);
    $curr = strtotime($cycles[$i]['period_start_date']);
    if ($prev && $curr && $curr > $prev) {
        $days = (int)round(($curr - $prev) / 86400);
        if ($days >= 18 && $days <= 50) {
            $cycleLengthsActual[] = $days;
            $intervalLabels[] = date('d M', $curr);
        }
    }
}

if (empty($cycleLengthsActual)) {
    $cycleLengthsActual = [28];
    $intervalLabels = ['Baseline'];
}

$relevant = array_slice($cycleLengthsActual, -6);
$weightedSum = 0.0;
$weightTotal = 0.0;
foreach ($relevant as $idx => $len) {
    $weight = $idx + 1;
    $weightedSum += $len * $weight;
    $weightTotal += $weight;
}
$wma = $weightTotal ? ($weightedSum / $weightTotal) : 28.0;

$symptomCount = count($selectedSymptoms);
$symptomAdjust = 0.0;
if ($symptomCount >= 4) $symptomAdjust = 1.0;
if ($symptomCount <= 1) $symptomAdjust = -0.4;

$moodAdjust = [
    'very_low' => 0.8,
    'moody' => 0.4,
    'calm' => -0.2,
    'energetic' => -0.4
];

$flowPredictAdjust = [
    1 => -0.4,
    2 => -0.2,
    3 => 0.0,
    4 => 0.4,
    5 => 0.8
];

$predictedCycleLength = (int)round(max(21, min(40,
    $wma +
    $symptomAdjust +
    ($moodAdjust[$latestMood] ?? 0) +
    ($flowPredictAdjust[$latestFlow] ?? 0)
)));
$averageCycleLength = (float)round(array_sum($cycleLengthsActual) / count($cycleLengthsActual), 1);
$cycleStdDev = round(calculateStdDev($cycleLengthsActual), 2);
$cycleRange = max($cycleLengthsActual) - min($cycleLengthsActual);
$irregularEnoughData = count($cycleLengthsActual) >= 3;
$isCycleIrregular = $irregularEnoughData && ($cycleStdDev >= 3.5 || $cycleRange >= 8);
$cycleStability = $isCycleIrregular ? 'Kurang Stabil' : 'Relatif Stabil';
$stabilityNote = $isCycleIrregular
    ? 'Variasi siklus cukup besar. Disarankan lanjut tracking 2-3 bulan untuk pola yang lebih akurat.'
    : 'Pola siklus terlihat cukup konsisten dari data terbaru.';

$lastStart = $latest['period_start_date'] ?? $today;
$lastEnd = $latest['period_end_date'] ?? date('Y-m-d', strtotime($lastStart . ' +4 days'));
$nextPeriodDate = date('Y-m-d', strtotime($lastStart . " +{$predictedCycleLength} days"));
$ovulationDate = date('Y-m-d', strtotime($nextPeriodDate . ' -14 days'));
$fertileStart = date('Y-m-d', strtotime($ovulationDate . ' -5 days'));
$fertileEnd = date('Y-m-d', strtotime($ovulationDate . ' +1 day'));

$periodMoon = moonPhaseData($nextPeriodDate);
$ovulationMoon = moonPhaseData($ovulationDate);
$periodPhase = $periodMoon['phase'];
$ovulationPhase = $ovulationMoon['phase'];
[$periodEnergyTitle, $periodEnergyDesc] = lunarEnergyInsight($periodPhase);
[$ovuEnergyTitle, $ovuEnergyDesc] = lunarEnergyInsight($ovulationPhase);
$zodiac = getBaziData($birthYear, $birthMonth, $birthDay, $birthHour);

/* ================= FAVORABLE DAY ENGINE ================= */
$calculateEnergy = function (string $date) use ($ovulationDate, $zodiac, $latestMood, $symptomCount, $latestFlow): array {
    $moon = moonPhaseData($date);
    $phase = $moon['phase'];
    $dayElement = elementOfDay($date);
    $illuminationPct = (float)$moon['illumination'] * 100;

    $daysToOvulation = abs((int)((strtotime($date) - strtotime($ovulationDate)) / 86400));
    $ovulationBoost = max(0, 20 - ($daysToOvulation * 4));

    $phaseBoostMap = [
        'New Moon' => -6,
        'Waxing Crescent' => 4,
        'First Quarter' => 8,
        'Waxing Gibbous' => 10,
        'Full Moon' => 14,
        'Waning Gibbous' => 5,
        'Last Quarter' => -2,
        'Waning Crescent' => -5
    ];
    $phaseBoost = $phaseBoostMap[$phase] ?? 0;
    $illuminationBoost = (int)round(($illuminationPct / 100) * 14);

    $elementBoost = ($zodiac['element'] !== 'Unknown' && $dayElement === $zodiac['element']) ? 7 : -1;
    $moodBase = ['very_low' => -7, 'moody' => -3, 'calm' => 2, 'energetic' => 4][$latestMood] ?? 0;
    $symptomPenalty = min(10, $symptomCount * 2);
    $flowAdjust = [1 => 1, 2 => 0, 3 => 0, 4 => -2, 5 => -4][$latestFlow] ?? 0;

    // Deterministic score: aligned with API lunar phase + illumination.
    $score = 40 + $ovulationBoost + $phaseBoost + $illuminationBoost + $elementBoost + $moodBase + $flowAdjust - $symptomPenalty;
    $score = max(20, min(95, $score));

    if ($score >= 70) {
        $label = 'High Energy Day';
        $badge = 'high';
        $tip = 'Lunar + cycle alignment sedang tinggi. Cocok untuk produktivitas dan social activity.';
    } elseif ($score >= 50) {
        $label = 'Balanced Day';
        $badge = 'balanced';
        $tip = 'Energi cenderung stabil. Cocok untuk aktivitas harian dan olahraga ringan-moderat.';
    } else {
        $label = 'Recovery Day';
        $badge = 'recovery';
        $tip = 'Lunar/biological alignment rendah. Prioritaskan recovery, hidrasi, dan tidur.';
    }

    return [
        'date' => $date,
        'label' => $label,
        'badge' => $badge,
        'tip' => $tip,
        'phase' => $phase,
        'element' => $dayElement,
        'score' => $score,
        'illumination_pct' => round($illuminationPct, 1)
    ];
};

/* ================= MONTH CALENDAR ================= */
$monthStart = strtotime(date('Y-m-01'));
$daysInMonth = (int)date('t', $monthStart);
$startWeekday = (int)date('N', $monthStart); // 1 (Mon) ... 7 (Sun)

$calendarCells = [];
for ($i = 1; $i < $startWeekday; $i++) {
    $calendarCells[] = ['date' => null, 'day' => ''];
}
for ($day = 1; $day <= $daysInMonth; $day++) {
    $date = date('Y-m-', $monthStart) . str_pad((string)$day, 2, '0', STR_PAD_LEFT);
    $calendarCells[] = ['date' => $date, 'day' => $day];
}
while (count($calendarCells) % 7 !== 0) {
    $calendarCells[] = ['date' => null, 'day' => ''];
}

$energyByDate = [];
foreach ($calendarCells as $cell) {
    if (!empty($cell['date'])) {
        $energyByDate[$cell['date']] = $calculateEnergy($cell['date']);
    }
}

$avgSeries = array_fill(0, count($cycleLengthsActual), $averageCycleLength);

?>

<style>
.mens-hero {
    background: linear-gradient(135deg, #f472b6, #fb7185 50%, #f59e0b);
    color: #fff;
}

.prediction-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 10px;
}

.prediction-item {
    padding: 12px;
    border-radius: 12px;
    background: #f8fafc;
    border: 1px solid #e5e7eb;
}

.prediction-item .k {
    font-size: 12px;
    color: #64748b;
    margin-bottom: 3px;
}

.prediction-item .v {
    font-weight: 700;
    color: #0f172a;
}

.period-dates-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 12px;
}

.period-date-field label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    font-size: 13px;
    color: #0f766e;
    letter-spacing: 0.02em;
}

.period-date-wrap {
    display: flex;
    align-items: center;
    gap: 10px;
    min-height: 50px;
    border-radius: 14px;
    border: 1px solid #d7e3ef;
    background: linear-gradient(180deg, #ffffff 0%, #f5fbff 100%);
    padding: 0 12px;
    transition: border-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
}

.period-date-wrap:focus-within {
    border-color: #4facfe;
    box-shadow: 0 0 0 3px rgba(79, 172, 254, 0.2);
    transform: translateY(-1px);
}

.period-date-icon {
    font-size: 16px;
    line-height: 1;
}

.period-date-input {
    width: 100%;
    min-width: 0;
    border: none;
    background: transparent;
    color: #1e293b;
    font-size: 16px;
    outline: none;
    padding: 0;
}

.period-date-input::-webkit-calendar-picker-indicator {
    cursor: pointer;
    filter: hue-rotate(135deg) saturate(1.2);
}

.stability-good {
    color: #166534;
}

.stability-bad {
    color: #b91c1c;
}

.mood-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
}

.mood-option input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.mood-option span {
    display: block;
    text-align: center;
    padding: 10px;
    border-radius: 12px;
    border: 1px solid #d7e3ef;
    background: linear-gradient(180deg, #ffffff 0%, #f5fbff 100%);
    font-weight: 600;
    color: #334155;
    cursor: pointer;
    transition: 0.2s ease;
}

.mood-option input:checked + span {
    border-color: #4facfe;
    box-shadow: 0 0 0 3px rgba(79, 172, 254, 0.2);
    color: #0f766e;
}

.symptom-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px;
}

.symptom-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 10px;
    border: 1px solid #d7e3ef;
    border-radius: 12px;
    background: #fff;
}

.symptom-item input {
    accent-color: #2ec4cc;
}

.flow-card {
    margin-top: 8px;
    background: #fff7fb;
    border: 1px solid #fbcfe8;
    border-radius: 16px;
    padding: 14px 12px;
}

.flow-title {
    font-size: 14px;
    font-weight: 700;
    color: #be185d;
    margin-bottom: 10px;
}

.flow-drops {
    display: flex;
    gap: 10px;
    align-items: flex-end;
}

.flow-drop {
    width: 24px;
    height: 34px;
    border-radius: 50% 50% 60% 60%;
    border: 2px solid #ec4899;
    background: #fff;
    position: relative;
    overflow: hidden;
    cursor: pointer;
    transition: transform 0.2s ease;
}

.flow-drop::before {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    height: 0;
    background: linear-gradient(to top, #ec4899, #f472b6);
    transition: height 0.25s ease;
}

.flow-drop.active::before {
    height: 88%;
}

.flow-drop:hover {
    transform: translateY(-3px);
}

.flow-status {
    margin-top: 10px;
    font-size: 13px;
    color: #6b2146;
}

.calendar-wrap {
    overflow-x: auto;
}

.cycle-calendar {
    width: 100%;
    min-width: 560px;
    border-collapse: separate;
    border-spacing: 6px;
}

.cycle-calendar th {
    font-size: 12px;
    color: #64748b;
    font-weight: 700;
}

.cycle-calendar td {
    height: 64px;
    text-align: center;
    border-radius: 12px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    font-weight: 600;
    color: #1e293b;
}

.cycle-calendar td.has-energy {
    cursor: pointer;
    transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.cycle-calendar td.has-energy:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.12);
}

.cycle-calendar td.energy-high {
    box-shadow: inset 0 -3px 0 #16a34a;
}

.cycle-calendar td.energy-balanced {
    box-shadow: inset 0 -3px 0 #d97706;
}

.cycle-calendar td.energy-recovery {
    box-shadow: inset 0 -3px 0 #2563eb;
}

.cycle-calendar td.empty {
    background: transparent;
    border-color: transparent;
}

.cycle-calendar td.today {
    border-color: #4facfe;
    box-shadow: inset 0 0 0 1px #4facfe;
}

.cycle-calendar td.period {
    background: #fee2e2;
    border-color: #fca5a5;
}

.cycle-calendar td.fertile {
    background: #dcfce7;
    border-color: #86efac;
}

.cycle-calendar td.ovulation {
    background: #fde68a;
    border-color: #fcd34d;
}

.legend-row {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 10px;
}

.legend-item {
    font-size: 12px;
    padding: 6px 10px;
    border-radius: 999px;
    border: 1px solid #e2e8f0;
}

.legend-period { background: #fee2e2; }
.legend-fertile { background: #dcfce7; }
.legend-ovulation { background: #fde68a; }
.legend-today { background: #dbeafe; }
.legend-energy-high { background: #dcfce7; }
.legend-energy-balanced { background: #fef3c7; }
.legend-energy-recovery { background: #dbeafe; }

.insight-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 14px;
}

.insight-mini {
    padding: 14px;
    border-radius: 14px;
    border: 1px solid #f3d3e2;
    background: linear-gradient(180deg, #fff, #fff7fb);
}

.insight-mini h4 {
    margin: 0 0 6px;
    font-size: 14px;
    color: #9d174d;
}

.insight-mini p {
    margin: 0;
    font-size: 13px;
    color: #334155;
}

.energy-list {
    display: grid;
    gap: 10px;
}

.energy-item {
    border-radius: 14px;
    padding: 12px;
    border: 1px solid #e2e8f0;
    background: #fff;
}

.energy-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    margin-bottom: 6px;
}

.badge-energy {
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
}

.badge-energy.high { background: #dcfce7; color: #166534; }
.badge-energy.balanced { background: #fef3c7; color: #92400e; }
.badge-energy.recovery { background: #dbeafe; color: #1e40af; }

.small-muted {
    font-size: 12px;
    color: #64748b;
}

.notice-box {
    padding: 12px;
    border-radius: 12px;
    background: #f8fafc;
    border: 1px solid #dbeafe;
    font-size: 13px;
    color: #334155;
}

.energy-detail {
    margin-top: 10px;
    padding: 10px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: #f8fafc;
    font-size: 13px;
    color: #334155;
}

@media (min-width: 760px) {
    .period-dates-grid {
        grid-template-columns: 1fr 1fr;
    }

    .prediction-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .insight-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .mood-grid {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }

    .symptom-grid {
        grid-template-columns: repeat(4, minmax(0, 1fr));
    }
}
</style>

<main class="app">
    <section class="card mens-hero">
        <div class="sleep-hero-inner">
            <div class="emoji-bubble">&#127769;</div>
            <div>
                <div class="sleep-title">Lunar Harmony Insight</div>
                <div class="sleep-sub">
                    Local cycle prediction + BaZi and lunar rhythm layer
                </div>
            </div>
        </div>
    </section>

    <section class="card">
        <div class="summary-title">Input Siklus & Profil Energi</div>
        <form method="POST">
            <div class="period-dates-grid">
                <div class="period-date-field">
                    <label>Tanggal mulai haid</label>
                    <div class="period-date-wrap">
                        <span class="period-date-icon">&#128197;</span>
                        <input class="period-date-input" type="date" name="period_start_date" value="<?= htmlspecialchars($latest['period_start_date'] ?? $today) ?>" required>
                    </div>
                </div>

                <div class="period-date-field">
                    <label>Tanggal selesai haid</label>
                    <div class="period-date-wrap">
                        <span class="period-date-icon">&#128197;</span>
                        <input class="period-date-input" type="date" name="period_end_date" value="<?= htmlspecialchars($latest['period_end_date'] ?? date('Y-m-d', strtotime(($latest['period_start_date'] ?? $today) . ' +4 days'))) ?>" required>
                    </div>
                </div>
            </div>

            <div class="input-group">
                <label style="display:block;">Mood Tracker<br>(4 mood)</label>
                <div class="mood-grid">
                    <?php foreach ($moodOptions as $key => $mood): ?>
                        <label class="mood-option">
                            <input type="radio" name="mood_type" value="<?= htmlspecialchars($key) ?>" <?= $latestMood === $key ? 'checked' : '' ?>>
                            <span><?= htmlspecialchars($mood['emoji'] . ' ' . $mood['label']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="input-group">
                <label>Gejala yang dirasakan</label>
                <div class="symptom-grid">
                    <?php foreach ($symptomOptions as $key => $label): ?>
                        <label class="symptom-item">
                            <input type="checkbox" name="symptoms[]" value="<?= htmlspecialchars($key) ?>" <?= in_array($key, $selectedSymptoms, true) ? 'checked' : '' ?>>
                            <span><?= htmlspecialchars($label) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="flow-card">
                <div class="flow-title">Flow Menstruasi (deras atau tidak)</div>
                <div class="flow-drops">
                    <?php for ($f = 1; $f <= 5; $f++): ?>
                        <div
                            class="flow-drop <?= $latestFlow >= $f ? 'active' : '' ?>"
                            onclick="setFlowLevel(<?= $f ?>)"
                        ></div>
                    <?php endfor; ?>
                </div>
                <div class="flow-status">
                    Level: <strong id="flowLabel"><?= htmlspecialchars($latestFlowLabel) ?></strong>
                </div>
                <input type="hidden" name="flow_level" id="flowLevelInput" value="<?= (int)$latestFlow ?>">
            </div>

            <div class="input-row">
                <div class="input-group">
                    <label>Tahun lahir (BaZi)</label>
                    <input type="number" name="birth_year" min="1940" max="<?= date('Y') ?>" value="<?= htmlspecialchars((string)($birthYear ?? '')) ?>" placeholder="contoh: 2002">
                </div>
                <div class="input-group">
                    <label>Bulan lahir</label>
                    <input type="number" name="birth_month" min="1" max="12" value="<?= (int)$birthMonth ?>" placeholder="1-12">
                </div>
                <div class="input-group">
                    <label>Tanggal lahir</label>
                    <input type="number" name="birth_day" min="1" max="28" value="<?= (int)$birthDay ?>" placeholder="1-28">
                </div>
                <div class="input-group">
                    <label>Jam lahir (0-23)</label>
                    <input type="number" name="birth_hour" min="0" max="23" value="<?= (int)$birthHour ?>" placeholder="contoh: 14">
                </div>
            </div>

            <button class="btn-primary" type="submit">Update Cycle & BaZi Data</button>
        </form>
    </section>

    <section class="card">
        <div class="summary-title">AI Adaptive Cycle Engine</div>
        <div class="prediction-grid">
            <div class="prediction-item">
                <div class="k">Predicted Next Period</div>
                <div class="v"><?= date('d F Y', strtotime($nextPeriodDate)) ?></div>
            </div>
            <div class="prediction-item">
                <div class="k">Predicted Ovulation</div>
                <div class="v"><?= date('d F Y', strtotime($ovulationDate)) ?></div>
            </div>
            <div class="prediction-item">
                <div class="k">Fertile Window</div>
                <div class="v"><?= date('d M', strtotime($fertileStart)) ?> - <?= date('d M Y', strtotime($fertileEnd)) ?></div>
            </div>
            <div class="prediction-item">
                <div class="k">Siklus Rata-rata (otomatis)</div>
                <div class="v"><?= number_format($averageCycleLength, 1) ?> hari</div>
            </div>
            <div class="prediction-item">
                <div class="k">Stabilitas Siklus</div>
                <div class="v <?= $isCycleIrregular ? 'stability-bad' : 'stability-good' ?>">
                    <?= htmlspecialchars($cycleStability) ?>
                </div>
                <div class="small-muted">SD <?= number_format($cycleStdDev, 2) ?> | Range <?= (int)$cycleRange ?> hari</div>
            </div>
            <div class="prediction-item">
                <div class="k">Flow Saat Ini</div>
                <div class="v"><?= htmlspecialchars($latestFlowLabel) ?></div>
            </div>
        </div>
        <div class="notice-box" style="margin-top:10px;">
            <?= htmlspecialchars($stabilityNote) ?><br>
            Prediksi saat ini mempertimbangkan: histori siklus, mood, gejala, dan flow menstruasi terakhir.
        </div>
    </section>

    <section class="card chart-card">
        <div class="summary-title">Grafik Siklus Otomatis</div>
        <div class="small-muted">Rata-rata dihitung otomatis dari selisih tanggal mulai haid antar siklus.</div>
        <canvas id="cycleChart"></canvas>
    </section>

    <section class="card">
        <div class="summary-title">Kalender Siklus Bulan Ini</div>
        <div class="small-muted" style="margin-bottom: 8px;">
            <?= date('F Y', $monthStart) ?> | Last period: <?= date('d M', strtotime($lastStart)) ?> - <?= date('d M', strtotime($lastEnd)) ?>
        </div>
        <div class="calendar-wrap">
            <table class="cycle-calendar">
                <thead>
                    <tr>
                        <th>Mon</th><th>Tue</th><th>Wed</th><th>Thu</th><th>Fri</th><th>Sat</th><th>Sun</th>
                    </tr>
                </thead>
                <tbody>
                    <?php for ($i = 0; $i < count($calendarCells); $i += 7): ?>
                        <tr>
                            <?php for ($j = 0; $j < 7; $j++):
                                $cell = $calendarCells[$i + $j];
                                $class = [];
                                $energyJson = '';
                                $energyTitle = '';
                                if (!$cell['date']) {
                                    $class[] = 'empty';
                                } else {
                                    $energy = $energyByDate[$cell['date']] ?? null;
                                    if ($cell['date'] === $today) $class[] = 'today';
                                    if ($cell['date'] >= $lastStart && $cell['date'] <= $lastEnd) $class[] = 'period';
                                    if ($cell['date'] >= $fertileStart && $cell['date'] <= $fertileEnd) $class[] = 'fertile';
                                    if ($cell['date'] === $ovulationDate) $class[] = 'ovulation';
                                    if ($energy) {
                                        $class[] = 'has-energy';
                                        if ($energy['badge'] === 'high') $class[] = 'energy-high';
                                        if ($energy['badge'] === 'balanced') $class[] = 'energy-balanced';
                                        if ($energy['badge'] === 'recovery') $class[] = 'energy-recovery';

                                        $energyJson = htmlspecialchars(json_encode([
                                            'date' => $energy['date'],
                                            'label' => $energy['label'],
                                            'score' => (int)$energy['score'],
                                            'phase' => $energy['phase'],
                                            'illumination_pct' => (float)$energy['illumination_pct'],
                                            'element' => $energy['element'],
                                            'tip' => $energy['tip']
                                        ]), ENT_QUOTES, 'UTF-8');
                                        $energyTitle = htmlspecialchars(
                                            $energy['label'] . ' | Score ' . (int)$energy['score'] . ' | ' . $energy['phase'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                    }
                                }
                            ?>
                                <td
                                    class="<?= htmlspecialchars(implode(' ', $class)) ?>"
                                    <?= $energyJson !== '' ? 'data-energy="' . $energyJson . '"' : '' ?>
                                    <?= $energyTitle !== '' ? 'title="' . $energyTitle . '"' : '' ?>
                                ><?= htmlspecialchars((string)$cell['day']) ?></td>
                            <?php endfor; ?>
                        </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>
        <div class="legend-row">
            <div class="legend-item legend-period">Period</div>
            <div class="legend-item legend-fertile">Fertile Window</div>
            <div class="legend-item legend-ovulation">Ovulation</div>
            <div class="legend-item legend-today">Today</div>
            <div class="legend-item legend-energy-high">Energy High</div>
            <div class="legend-item legend-energy-balanced">Energy Balanced</div>
            <div class="legend-item legend-energy-recovery">Energy Recovery</div>
        </div>
        <div id="energyDetail" class="energy-detail">
            Klik tanggal pada kalender untuk melihat detail energy day. Di desktop bisa hover untuk ringkasan cepat.
        </div>
        <div class="small-muted" style="margin-top:8px;">
            Formula: 40 + ovulation proximity + moon phase + illumination + element match + mood - symptom penalty.
        </div>
    </section>

    <section class="card">
        <div class="summary-title">Lunar Synchronization Insight</div>
        <div class="insight-grid">
            <div class="insight-mini">
                <h4>Menstruation Phase: <?= htmlspecialchars($periodPhase) ?></h4>
                <p><strong><?= htmlspecialchars($periodEnergyTitle) ?>:</strong> <?= htmlspecialchars($periodEnergyDesc) ?></p>
            </div>
            <div class="insight-mini">
                <h4>Ovulation Phase: <?= htmlspecialchars($ovulationPhase) ?></h4>
                <p><strong><?= htmlspecialchars($ovuEnergyTitle) ?>:</strong> <?= htmlspecialchars($ovuEnergyDesc) ?></p>
            </div>
        </div>
    </section>

    <section class="card">
        <div class="summary-title">BaZi Personalization (4 Pilar)</div>
        <div class="prediction-grid">
            <div class="prediction-item">
                <div class="k">Chinese Zodiac</div>
                <div class="v"><?= htmlspecialchars($zodiac['zodiac']) ?></div>
            </div>
            <div class="prediction-item">
                <div class="k">Day Master Element</div>
                <div class="v"><?= htmlspecialchars($zodiac['element']) ?></div>
            </div>
            <?php if (!empty($zodiac['dominant'])): ?>
                <div class="prediction-item">
                    <div class="k">Dominant Element</div>
                    <div class="v"><?= htmlspecialchars((string)$zodiac['dominant']) ?></div>
                </div>
            <?php endif; ?>
        </div>
        <div class="notice-box" style="margin-top:10px;">
            <?= htmlspecialchars($zodiac['insight']) ?>
        </div>
        <?php if (!empty($zodiac['bazi'])): ?>
            <div class="small-muted" style="margin-top:8px;">
                BaZi: <?= htmlspecialchars((string)$zodiac['bazi']) ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($zodiac['pillars'])): ?>
            <div class="small-muted" style="margin-top:8px;">
                Year: <?= htmlspecialchars((string)$zodiac['pillars']['year']) ?> |
                Month: <?= htmlspecialchars((string)$zodiac['pillars']['month']) ?> |
                Day: <?= htmlspecialchars((string)$zodiac['pillars']['day']) ?> |
                Hour: <?= htmlspecialchars((string)$zodiac['pillars']['hour']) ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="card">
        <div class="notice-box">
            <strong>Catatan penting:</strong> Fitur ini adalah wellness & cultural insight untuk engagement dan self-awareness. Tidak menggantikan konsultasi medis dan tidak bersifat deterministik.
        </div>
    </section>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
function setFlowLevel(level) {
    const drops = document.querySelectorAll('.flow-drop');
    const input = document.getElementById('flowLevelInput');
    const label = document.getElementById('flowLabel');
    const labels = {
        1: 'Sangat Ringan',
        2: 'Ringan',
        3: 'Normal',
        4: 'Deras',
        5: 'Sangat Deras'
    };

    drops.forEach((drop, idx) => {
        if (idx < level) drop.classList.add('active');
        else drop.classList.remove('active');
    });

    if (input) input.value = level;
    if (label) label.textContent = labels[level] || 'Normal';
}

function initCycleChart() {
    const canvas = document.getElementById('cycleChart');
    if (!canvas) return;
    if (!window.Chart) {
        console.warn('Chart.js not loaded; cycle chart skipped.');
        return;
    }

    new Chart(canvas, {
        type: 'line',
        data: {
            labels: <?= json_encode($intervalLabels) ?>,
            datasets: [{
                label: 'Cycle Length (days)',
                data: <?= json_encode($cycleLengthsActual) ?>,
                borderColor: '#ec4899',
                backgroundColor: 'rgba(236,72,153,0.16)',
                fill: true,
                tension: 0.35,
                pointRadius: 4
            }, {
                label: 'Rata-rata',
                data: <?= json_encode($avgSeries) ?>,
                borderColor: '#0ea5e9',
                borderDash: [6, 6],
                pointRadius: 0,
                fill: false,
                tension: 0
            }]
        },
        options: {
            plugins: { legend: { display: true } },
            scales: {
                y: {
                    min: 20,
                    max: 42,
                    ticks: { stepSize: 2 }
                }
            }
        }
    });
}

const energyDetail = document.getElementById('energyDetail');
document.querySelectorAll('.cycle-calendar td[data-energy]').forEach((cell) => {
    cell.addEventListener('click', () => {
        try {
            const data = JSON.parse(cell.getAttribute('data-energy'));
            energyDetail.textContent =
                `${data.date} | ${data.label} | Score ${data.score} | ${data.phase} (${data.illumination_pct}% illum) | Element: ${data.element}. ${data.tip}`;
        } catch (e) {
            energyDetail.textContent = 'Detail energy tidak tersedia untuk tanggal ini.';
        }
    });
});

const defaultFlowInput = document.getElementById('flowLevelInput');
if (defaultFlowInput) {
    const initialLevel = parseInt(defaultFlowInput.value || '3', 10);
    setFlowLevel(isNaN(initialLevel) ? 3 : initialLevel);
}

document.addEventListener('DOMContentLoaded', initCycleChart, { once: true });
if (document.readyState !== 'loading') initCycleChart();
</script>

<?php include 'includes/footer.php'; ?>
