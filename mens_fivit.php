<?php
session_start();
require_once 'includes/db.php';

$pageTitle = 'Lunar Harmony Insight';
include 'includes/header.php';

$user_id = $_SESSION['user_id'] ?? 1;
$today = date('Y-m-d');

// Localhost-friendly API config (no virtual host required).
$apiConfig = [];
$apiConfigPath = __DIR__ . '/includes/api_config.php';
if (is_file($apiConfigPath)) {
    $loaded = include $apiConfigPath;
    if (is_array($loaded)) {
        $apiConfig = $loaded;
    }
}

/* ================= SCHEMA GUARD ================= */
$pdo->exec("
    CREATE TABLE IF NOT EXISTS mens_cycle_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        period_start_date DATE NOT NULL,
        period_end_date DATE NULL,
        mood_type VARCHAR(20) DEFAULT 'calm',
        symptom_tags VARCHAR(255) DEFAULT '',
        reported_cycle_length INT DEFAULT 28,
        period_length INT DEFAULT 5,
        symptom_score INT DEFAULT 5,
        mood_score INT DEFAULT 5,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_user_period (user_id, period_start_date)
    )
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS mens_user_profiles (
        user_id INT PRIMARY KEY,
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

safeAlter($pdo, "ALTER TABLE mens_cycle_logs ADD COLUMN period_end_date DATE NULL");
safeAlter($pdo, "ALTER TABLE mens_cycle_logs ADD COLUMN mood_type VARCHAR(20) DEFAULT 'calm'");
safeAlter($pdo, "ALTER TABLE mens_cycle_logs ADD COLUMN symptom_tags VARCHAR(255) DEFAULT ''");

/* ================= HELPERS ================= */
function clampNumber($value, $min, $max): int
{
    return max($min, min($max, (int)$value));
}

function cfg(string $key, string $default = ''): string
{
    global $apiConfig;

    $fromEnv = getenv($key);
    if ($fromEnv !== false && trim((string)$fromEnv) !== '') {
        return trim((string)$fromEnv);
    }

    if (isset($_SERVER[$key]) && trim((string)$_SERVER[$key]) !== '') {
        return trim((string)$_SERVER[$key]);
    }

    if (isset($apiConfig[$key]) && trim((string)$apiConfig[$key]) !== '') {
        return trim((string)$apiConfig[$key]);
    }

    return $default;
}

function apiHeadersFromEnv(string $keyEnv, string $hostEnv): array
{
    $headers = ['Accept: application/json'];
    $apiKey = cfg($keyEnv);
    $apiHost = cfg($hostEnv);

    if ($apiKey !== '') {
        $headers[] = 'X-RapidAPI-Key: ' . $apiKey;
        $headers[] = 'Authorization: Bearer ' . $apiKey;
        $headers[] = 'apikey: ' . $apiKey;
        $headers[] = 'x-api-key: ' . $apiKey;
    }
    if ($apiHost !== '') {
        $headers[] = 'X-RapidAPI-Host: ' . $apiHost;
    }

    return $headers;
}

function requestJson(string $url, array $headers = [], int $timeoutSec = 3): ?array
{
    if ($url === '') return null;

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => $timeoutSec,
            'ignore_errors' => true,
            'header' => implode("\r\n", $headers)
        ]
    ]);

    $raw = @file_get_contents($url, false, $context);
    if (!$raw) return null;

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

function requestJsonPost(string $url, array $payload, array $headers = [], int $timeoutSec = 3): ?array
{
    if ($url === '') return null;

    $mergedHeaders = array_merge(['Content-Type: application/json', 'Accept: application/json'], $headers);
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'timeout' => $timeoutSec,
            'ignore_errors' => true,
            'header' => implode("\r\n", $mergedHeaders),
            'content' => json_encode($payload)
        ]
    ]);

    $raw = @file_get_contents($url, false, $context);
    if (!$raw) return null;

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

function arrGet(array $data, array $paths, $default = null)
{
    foreach ($paths as $path) {
        $cursor = $data;
        $ok = true;
        foreach (explode('.', $path) as $key) {
            if (!is_array($cursor) || !array_key_exists($key, $cursor)) {
                $ok = false;
                break;
            }
            $cursor = $cursor[$key];
        }
        if ($ok && $cursor !== null && $cursor !== '') {
            return $cursor;
        }
    }
    return $default;
}

function valueToString($value): string
{
    if (is_string($value)) return trim($value);
    if (is_int($value) || is_float($value)) return (string)$value;
    if (is_bool($value)) return $value ? '1' : '0';

    if (is_array($value)) {
        foreach (['name', 'phase_name', 'phase', 'value', 'text'] as $key) {
            if (isset($value[$key])) {
                $text = valueToString($value[$key]);
                if ($text !== '') return $text;
            }
        }
        foreach ($value as $item) {
            $text = valueToString($item);
            if ($text !== '') return $text;
        }
    }

    return '';
}

function valueToFloat($value, ?float $default = null): ?float
{
    if (is_int($value) || is_float($value)) return (float)$value;
    if (is_string($value) && trim($value) !== '' && is_numeric($value)) return (float)$value;

    if (is_array($value)) {
        foreach (['value', 'illumination', 'age', 'days'] as $key) {
            if (isset($value[$key])) {
                $num = valueToFloat($value[$key], null);
                if ($num !== null) return $num;
            }
        }
        foreach ($value as $item) {
            $num = valueToFloat($item, null);
            if ($num !== null) return $num;
        }
    }

    return $default;
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
    static $cache = [];
    if (isset($cache[$date])) {
        return $cache[$date];
    }

    $fallback = moonPhaseDataLocal($date);
    $ts = strtotime($date . ' 12:00:00 UTC');
    if (!$ts) {
        $cache[$date] = $fallback;
        return $cache[$date];
    }

    // 1) Try lunar-mcp-server bridge first.
    // Expected bridge: HTTP endpoint that accepts JSON {date:"YYYY-MM-DD"} or MCP-like payload.
    $mcpUrl = cfg('LUNAR_MCP_URL');
    if ($mcpUrl !== '') {
        $mcpHeaders = apiHeadersFromEnv('LUNAR_MCP_API_KEY', 'LUNAR_MCP_API_HOST');

        $mcpJson = requestJsonPost($mcpUrl, ['date' => $date], $mcpHeaders, 3);
        if (!$mcpJson) {
            // JSON-RPC style fallback if bridge expects MCP tool call envelope.
            $mcpJson = requestJsonPost($mcpUrl, [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'tools/call',
                'params' => [
                    'name' => 'get_moon_phase',
                    'arguments' => ['date' => $date]
                ]
            ], $mcpHeaders, 3);
        }

        if ($mcpJson) {
            $root = $mcpJson;
            if (isset($mcpJson['result']) && is_array($mcpJson['result'])) {
                $root = $mcpJson['result'];
            }
            if (isset($root['content']) && is_array($root['content']) && isset($root['content'][0]['text'])) {
                $decodedText = json_decode((string)$root['content'][0]['text'], true);
                if (is_array($decodedText)) $root = $decodedText;
            }

            $phase = valueToString(arrGet($root, ['phase_name', 'phase', 'moon.phase', 'moon_phase.phase_name'], ''));
            $illumRaw = arrGet($root, ['illumination', 'moon.illumination', 'moon_phase.illumination'], null);
            $ageRaw = arrGet($root, ['age', 'moon_age', 'moon.age'], null);

            if ($phase !== '') {
                $illum = $fallback['illumination'];
                $illumValue = valueToFloat($illumRaw, null);
                if ($illumValue !== null) {
                    $illum = $illumValue;
                    if ($illum > 1) $illum /= 100;
                }
                $cache[$date] = [
                    'phase' => $phase,
                    'age' => valueToFloat($ageRaw, $fallback['age']),
                    'illumination' => max(0.0, min(1.0, $illum)),
                    'source' => 'lunar_mcp'
                ];
                return $cache[$date];
            }
        }
    }

    // 2) Try configured Moon Phase API.
    $moonApiTpl = cfg('MOON_PHASE_API_URL');
    if ($moonApiTpl !== '') {
        $apiUrl = str_replace('{date}', $date, $moonApiTpl);
        $apiUrl = str_replace('{timestamp}', (string)$ts, $apiUrl);
        $apiJson = requestJson(
            $apiUrl,
            apiHeadersFromEnv('MOON_PHASE_API_KEY', 'MOON_PHASE_API_HOST')
        );
        if ($apiJson) {
            $root = isset($apiJson[0]) && is_array($apiJson[0]) ? $apiJson[0] : $apiJson;
            $phase = valueToString(arrGet($root, ['phase_name', 'phase', 'phase.name', 'moon.phase', 'moon_phase.phase_name'], ''));
            $illumRaw = arrGet($root, ['illumination', 'phase.illumination', 'moon.illumination', 'moon_phase.illumination'], null);
            $ageRaw = arrGet($root, ['age', 'moon_age', 'phase.age_days', 'moon.age'], null);

            if ($phase !== '') {
                $illum = $fallback['illumination'];
                $illumValue = valueToFloat($illumRaw, null);
                if ($illumValue !== null) {
                    $illum = $illumValue;
                    if ($illum > 1) $illum /= 100;
                }
                $cache[$date] = [
                    'phase' => $phase,
                    'age' => valueToFloat($ageRaw, $fallback['age']),
                    'illumination' => max(0.0, min(1.0, $illum)),
                    'source' => 'moon_api'
                ];
                return $cache[$date];
            }
        }

        // API mode is enabled but response is invalid / quota exhausted.
        $cache[$date] = [
            'phase' => 'Unavailable',
            'age' => 0.0,
            'illumination' => 0.0,
            'source' => 'moon_api_limit'
        ];
        return $cache[$date];
    }

    // 3) Fallback public moon API.
    $url = 'https://api.farmsense.net/v1/moonphases/?d=' . $ts;
    $json = requestJson($url, ['Accept: application/json'], 2);

    if (is_array($json) && !empty($json[0]) && is_array($json[0])) {
        $phase = $json[0]['Phase'] ?? $fallback['phase'];
        $age = isset($json[0]['Age']) ? (float)$json[0]['Age'] : $fallback['age'];
        $illum = isset($json[0]['Illumination']) ? ((float)$json[0]['Illumination'] / 100) : $fallback['illumination'];

        $cache[$date] = [
            'phase' => $phase ?: $fallback['phase'],
            'age' => $age,
            'illumination' => max(0.0, min(1.0, $illum)),
            'source' => 'api'
        ];
        return $cache[$date];
    }

    $cache[$date] = $fallback;
    return $cache[$date];
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

function getZodiacData(?int $birthYear): array
{
    if (!$birthYear) {
        return [
            'zodiac' => 'Unknown',
            'element' => 'Unknown',
            'bazi' => '',
            'source' => 'none',
            'insight' => 'Tambahkan tahun lahir untuk mendapatkan insight zodiac personal.'
        ];
    }

    $referenceDate = date('Y-m-d');

    // 1) Try configured Astrology + BaZi / Chinese Calendar API.
    $astroTpl = cfg('ASTRO_BAZI_API_URL');
    if ($astroTpl !== '') {
        $apiUrl = str_replace('{birth_year}', (string)$birthYear, $astroTpl);
        $apiUrl = str_replace('{date}', $referenceDate, $apiUrl);

        $apiMethod = strtoupper(cfg('ASTRO_BAZI_API_METHOD', 'GET'));
        $apiHeaders = apiHeadersFromEnv('ASTRO_BAZI_API_KEY', 'ASTRO_BAZI_API_HOST');

        if ($apiMethod === 'POST' || str_contains($apiUrl, '/natal/calculate')) {
            $city = cfg('ASTRO_BAZI_CITY', 'Jakarta, Indonesia');
            $lat = (float)cfg('ASTRO_BAZI_LAT', '-6.2088');
            $lng = (float)cfg('ASTRO_BAZI_LNG', '106.8456');
            $tz = cfg('ASTRO_BAZI_TZ', 'AUTO');
            $month = (int)cfg('ASTRO_BAZI_BIRTH_MONTH', '6');
            $day = (int)cfg('ASTRO_BAZI_BIRTH_DAY', '15');
            $hour = (int)cfg('ASTRO_BAZI_BIRTH_HOUR', '12');
            $minute = (int)cfg('ASTRO_BAZI_BIRTH_MINUTE', '0');

            $month = max(1, min(12, $month));
            $day = max(1, min(28, $day));
            $hour = max(0, min(23, $hour));
            $minute = max(0, min(59, $minute));

            $payload = [
                'name' => 'FIVIT User',
                'year' => $birthYear,
                'month' => $month,
                'day' => $day,
                'hour' => $hour,
                'minute' => $minute,
                'city' => $city,
                'lat' => $lat,
                'lng' => $lng,
                'tz_str' => $tz
            ];
            $apiJson = requestJsonPost($apiUrl, $payload, $apiHeaders, 4);
        } else {
            $apiJson = requestJson($apiUrl, $apiHeaders, 4);
        }

        if ($apiJson) {
            $root = isset($apiJson['data']) && is_array($apiJson['data']) ? $apiJson['data'] : $apiJson;
            $zodiacApi = valueToString(arrGet($root, [
                'chinese_zodiac', 'zodiac', 'animal_sign', 'bazi.zodiac', 'calendar.chinese_zodiac',
                'sun_sign', 'sun.sign', 'sun.zodiac_sign', 'planets.sun.sign'
            ], ''));
            $elementApi = valueToString(arrGet($root, [
                'element', 'chinese_element', 'bazi.element', 'bazi.day_master_element', 'calendar.element', 'day_master.element',
                'sun.element', 'planets.sun.element'
            ], ''));
            $baziText = valueToString(arrGet($root, [
                'bazi.day_master', 'bazi.pillars', 'bazi.summary', 'bazi.description', 'natal.chart_summary'
            ], ''));

            if ($zodiacApi !== '' || $elementApi !== '') {
                $zodiacFinal = $zodiacApi !== '' ? $zodiacApi : 'Unknown';
                $elementFinal = $elementApi !== '' ? $elementApi : 'Unknown';
                $insightApi = valueToString(arrGet($root, ['insight', 'guidance', 'wellness_note', 'summary', 'natal.interpretation'], ''));
                if ($insightApi === '') {
                    $insightApi = 'Insight diambil dari Astrology + BaZi API dan dipakai sebagai wellness guidance.';
                }
                return [
                    'zodiac' => $zodiacFinal,
                    'element' => $elementFinal,
                    'bazi' => $baziText,
                    'source' => 'astro_api',
                    'insight' => $insightApi
                ];
            }
        }
    }

    // 2) Local fallback mapping.
    $zodiacs = [
        'Rat', 'Ox', 'Tiger', 'Rabbit', 'Dragon', 'Snake',
        'Horse', 'Goat', 'Monkey', 'Rooster', 'Dog', 'Pig'
    ];
    $elements = ['Wood', 'Wood', 'Fire', 'Fire', 'Earth', 'Earth', 'Metal', 'Metal', 'Water', 'Water'];

    $zodiacIndex = (($birthYear - 4) % 12 + 12) % 12;
    $stemIndex = (($birthYear - 4) % 10 + 10) % 10;

    $zodiac = $zodiacs[$zodiacIndex];
    $element = $elements[$stemIndex];

    $elementInsights = [
        'Wood' => 'Sebagai elemen Wood, ritme kamu cenderung kuat saat memulai kebiasaan baru dan growth phase.',
        'Fire' => 'Sebagai elemen Fire, energi kamu biasanya naik saat fase sosial dan aktivitas intens.',
        'Earth' => 'Sebagai elemen Earth, kestabilan rutinitas tidur dan nutrisi memberi dampak paling konsisten.',
        'Metal' => 'Sebagai elemen Metal, fokus dan struktur harian sering membantu menstabilkan perubahan mood.',
        'Water' => 'Sebagai elemen Water, sensitivitas emosi bisa meningkat saat transisi musim atau fase siklus.'
    ];

    return [
        'zodiac' => $zodiac,
        'element' => $element,
        'bazi' => '',
        'source' => 'local',
        'insight' => $elementInsights[$element] ?? ''
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
    $periodStart = $_POST['period_start_date'] ?? $today;
    $periodEnd = $_POST['period_end_date'] ?? '';
    $moodType = $_POST['mood_type'] ?? 'calm';
    $moodType = array_key_exists($moodType, $moodOptions) ? $moodType : 'calm';

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
        INSERT INTO mens_user_profiles (user_id, birth_year)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE birth_year = VALUES(birth_year)
    ");
    $profileStmt->execute([$user_id, $birthYear]);

    $logStmt = $pdo->prepare("
        INSERT INTO mens_cycle_logs
            (user_id, period_start_date, period_end_date, mood_type, symptom_tags)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            period_end_date = VALUES(period_end_date),
            mood_type = VALUES(mood_type),
            symptom_tags = VALUES(symptom_tags)
    ");
    $logStmt->execute([$user_id, $periodStart, $periodEnd, $moodType, $symptomTags]);

    header("Location: mens_fivit.php");
    exit;
}

/* ================= LOAD DATA ================= */
$profile = $pdo->prepare("SELECT birth_year FROM mens_user_profiles WHERE user_id = ? LIMIT 1");
$profile->execute([$user_id]);
$profileRow = $profile->fetch(PDO::FETCH_ASSOC) ?: [];
$birthYear = !empty($profileRow['birth_year']) ? (int)$profileRow['birth_year'] : null;

$cyclesStmt = $pdo->prepare("
    SELECT period_start_date, period_end_date, mood_type, symptom_tags
    FROM mens_cycle_logs
    WHERE user_id = ?
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

$predictedCycleLength = (int)round(max(21, min(40, $wma + $symptomAdjust + ($moodAdjust[$latestMood] ?? 0))));
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
$moonApiStrictBlocked = (($periodMoon['source'] ?? '') === 'moon_api_limit') && (($ovulationMoon['source'] ?? '') === 'moon_api_limit');
if ($moonApiStrictBlocked) {
    $periodEnergyTitle = 'Unavailable';
    $periodEnergyDesc = 'Lunar insight unavailable because Moon API daily limit is reached.';
    $ovuEnergyTitle = 'Unavailable';
    $ovuEnergyDesc = 'Upgrade API plan to re-enable lunar-based insights.';
} else {
    [$periodEnergyTitle, $periodEnergyDesc] = lunarEnergyInsight($periodPhase);
    [$ovuEnergyTitle, $ovuEnergyDesc] = lunarEnergyInsight($ovulationPhase);
}
$zodiac = getZodiacData($birthYear);

/* ================= FAVORABLE DAY ENGINE ================= */
$energyDays = [];
for ($i = 0; $i < 14; $i++) {
    $date = date('Y-m-d', strtotime($today . " +{$i} days"));
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

    // Deterministic score: aligned with API lunar phase + illumination.
    $score = 40 + $ovulationBoost + $phaseBoost + $illuminationBoost + $elementBoost + $moodBase - $symptomPenalty;
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

    $energyDays[] = [
        'date' => $date,
        'label' => $label,
        'badge' => $badge,
        'tip' => $tip,
        'phase' => $phase,
        'element' => $dayElement,
        'score' => $score,
        'illumination_pct' => round($illuminationPct, 1),
        'source' => $moon['source']
    ];
}
$moonSourceCounts = [];
foreach ($energyDays as $row) {
    $src = (string)($row['source'] ?? 'unknown');
    $moonSourceCounts[$src] = ($moonSourceCounts[$src] ?? 0) + 1;
}
$successfulApiCount =
    ($moonSourceCounts['moon_api'] ?? 0) +
    ($moonSourceCounts['lunar_mcp'] ?? 0) +
    ($moonSourceCounts['api'] ?? 0);
$limitCount = $moonSourceCounts['moon_api_limit'] ?? 0;
$moonApiLimitReached = $limitCount > 0 && $successfulApiCount === 0;
$primaryMoonSource = $energyDays[0]['source'] ?? 'unknown';
$canRenderFavorableList = !$moonApiLimitReached;

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
                    AI-adaptive cycle prediction + cultural wellness layer
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
                <label>Mood Tracker (4 mood)</label>
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

            <div class="input-row">
                <div class="input-group">
                    <label>Tahun lahir (Chinese Zodiac)</label>
                    <input type="number" name="birth_year" min="1940" max="<?= date('Y') ?>" value="<?= htmlspecialchars((string)($birthYear ?? '')) ?>" placeholder="contoh: 2002">
                </div>
            </div>

            <button class="btn-primary" type="submit">Update Lunar Harmony Data</button>
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
        </div>
        <div class="notice-box" style="margin-top:10px;"><?= htmlspecialchars($stabilityNote) ?></div>
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
                                if (!$cell['date']) {
                                    $class[] = 'empty';
                                } else {
                                    if ($cell['date'] === $today) $class[] = 'today';
                                    if ($cell['date'] >= $lastStart && $cell['date'] <= $lastEnd) $class[] = 'period';
                                    if ($cell['date'] >= $fertileStart && $cell['date'] <= $fertileEnd) $class[] = 'fertile';
                                    if ($cell['date'] === $ovulationDate) $class[] = 'ovulation';
                                }
                            ?>
                                <td class="<?= htmlspecialchars(implode(' ', $class)) ?>"><?= htmlspecialchars((string)$cell['day']) ?></td>
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
        <div class="summary-title">Zodiac Personalization</div>
        <div class="prediction-grid">
            <div class="prediction-item">
                <div class="k">Chinese Zodiac</div>
                <div class="v"><?= htmlspecialchars($zodiac['zodiac']) ?></div>
            </div>
            <div class="prediction-item">
                <div class="k">Five Element</div>
                <div class="v"><?= htmlspecialchars($zodiac['element']) ?></div>
            </div>
            <div class="prediction-item">
                <div class="k">Astrology Source</div>
                <div class="v"><?= htmlspecialchars($zodiac['source']) ?></div>
            </div>
        </div>
        <div class="notice-box" style="margin-top:10px;">
            <?= htmlspecialchars($zodiac['insight']) ?>
        </div>
        <?php if (!empty($zodiac['bazi'])): ?>
            <div class="small-muted" style="margin-top:8px;">
                BaZi: <?= htmlspecialchars((string)$zodiac['bazi']) ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="card">
        <div class="summary-title">Favorable Day Energy Indicator (14 Hari)</div>
        <?php if ($canRenderFavorableList): ?>
            <div class="energy-list">
                <?php foreach ($energyDays as $energy): ?>
                    <div class="energy-item">
                        <div class="energy-head">
                            <strong><?= date('d F', strtotime($energy['date'])) ?></strong>
                            <span class="badge-energy <?= htmlspecialchars($energy['badge']) ?>">
                                <?= htmlspecialchars($energy['label']) ?>
                            </span>
                        </div>
                        <div class="small-muted">
                            <?= htmlspecialchars($energy['phase']) ?> (illum <?= number_format((float)$energy['illumination_pct'], 1) ?>%) | Element Day: <?= htmlspecialchars($energy['element']) ?> | Score: <?= (int)$energy['score'] ?>
                        </div>
                        <div style="margin-top:6px;"><?= htmlspecialchars($energy['tip']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if ($moonApiLimitReached): ?>
            <div class="notice-box" style="margin-top:10px; border-color:#fecaca; background:#fff1f2; color:#9f1239;">
                Moon API daily limit reached. Upgrade your API plan to continue real-time lunar insights.
            </div>
        <?php else: ?>
            <div class="small-muted" style="margin-top:8px;">
                Lunar source: <?= htmlspecialchars($primaryMoonSource) ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="card">
        <div class="notice-box">
            <strong>Catatan penting:</strong> Fitur ini adalah wellness & cultural insight untuk engagement dan self-awareness. Tidak menggantikan konsultasi medis dan tidak bersifat deterministik.
        </div>
    </section>
</main>

<script>
new Chart(document.getElementById('cycleChart'), {
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
</script>

<?php include 'includes/footer.php'; ?>
