<?php
session_start();
require_once 'includes/db.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$pageTitle = 'Workout Plan';
include 'includes/header.php';

$user_id = $_SESSION['user_id'] ?? 1;
$success = '';
$errors = [];

function apiGetJson(string $url): ?array {
    $response = null;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        $raw = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($raw !== false && $code >= 200 && $code < 300) {
            $response = json_decode($raw, true);
        }
    }

    if (!$response) {
        $context = stream_context_create([
            'http' => [
                'timeout' => 8,
                'header' => "User-Agent: FIVIT\r\n"
            ]
        ]);
        $raw = @file_get_contents($url, false, $context);
        if ($raw !== false) {
            $response = json_decode($raw, true);
        }
    }

    return is_array($response) ? $response : null;
}

function getLanguageId(string $name): int {
    $data = apiGetJson('https://wger.de/api/v2/language/?limit=200');
    if (!empty($data['results'])) {
        foreach ($data['results'] as $lang) {
            $langName = isset($lang['name']) ? (string) $lang['name'] : '';
            if ($langName !== '' && strcasecmp($langName, $name) === 0) {
                return (int) ($lang['id'] ?? 0);
            }
        }
    }
    return 2;
}

function cleanText(string $html): string {
    $text = strip_tags($html);
    $text = preg_replace('/\s+/', ' ', $text);
    return trim($text);
}

function buildFallbackPlan(string $goal, string $fitness): array {
    $goalLower = strtolower($goal);
    $fitnessLower = strtolower($fitness);

    $baseBodyweight = [
        'Jumping Jacks',
        'High Knees',
        'Mountain Climbers',
        'Burpees',
        'Squats',
        'Lunges',
        'Push Ups',
        'Plank',
        'Glute Bridge',
        'Bicycle Crunches',
        'Jump Rope',
        'Jog in Place'
    ];

    if (str_contains($goalLower, 'fat') || str_contains($goalLower, 'loss') || str_contains($goalLower, 'cardio')) {
        return [
            'Jump Rope',
            'High Knees',
            'Mountain Climbers',
            'Burpees',
            'Jumping Jacks',
            'Jog in Place',
            'Plank',
            'Bicycle Crunches'
        ];
    }

    if (str_contains($goalLower, 'muscle') || str_contains($goalLower, 'gain') || str_contains($goalLower, 'strength')) {
        return [
            'Squats',
            'Lunges',
            'Push Ups',
            'Glute Bridge',
            'Plank',
            'Step Ups',
            'Triceps Dips',
            'Calf Raises'
        ];
    }

    if (str_contains($goalLower, 'endurance') || str_contains($goalLower, 'stamina')) {
        return [
            'Jog in Place',
            'Jump Rope',
            'Mountain Climbers',
            'High Knees',
            'Jumping Jacks',
            'Burpees',
            'Plank',
            'Bicycle Crunches'
        ];
    }

    if (str_contains($fitnessLower, 'beginner')) {
        return [
            'March in Place',
            'Bodyweight Squats',
            'Wall Push Ups',
            'Glute Bridge',
            'Standing Calf Raises',
            'Dead Bug',
            'Modified Plank',
            'Side Steps'
        ];
    }

    return $baseBodyweight;
}

function pickExercises(array $exerciseList, string $goal, string $fitness, int $limit = 8): array {
    $goalLower = strtolower($goal);
    $fitnessLower = strtolower($fitness);
    $filtered = [];

    $cardioKeywords = ['run', 'jog', 'cycle', 'bike', 'cardio', 'swim', 'row', 'walk', 'jump'];
    $strengthKeywords = ['press', 'squat', 'deadlift', 'row', 'curl', 'lunge', 'pull', 'push', 'bench', 'dip'];
    $bodyweightKeywords = ['bodyweight', 'push up', 'push-up', 'plank', 'squat', 'lunge', 'burpee'];

    foreach ($exerciseList as $ex) {
        $name = isset($ex['name']) ? trim((string) $ex['name']) : '';
        if ($name === '') {
            continue;
        }
        $lower = strtolower($name);
        $hasBodyweight = (bool) array_filter($bodyweightKeywords, fn($kw) => str_contains($lower, $kw));

        if (
            (str_contains($goalLower, 'fat') || str_contains($goalLower, 'loss') || str_contains($goalLower, 'cardio')) &&
            array_filter($cardioKeywords, fn($kw) => str_contains($lower, $kw))
        ) {
            $filtered[] = $ex;
            continue;
        }

        if (
            (str_contains($goalLower, 'muscle') || str_contains($goalLower, 'gain') || str_contains($goalLower, 'strength')) &&
            array_filter($strengthKeywords, fn($kw) => str_contains($lower, $kw))
        ) {
            $filtered[] = $ex;
            continue;
        }

        if (str_contains($fitnessLower, 'beginner')) {
            $filtered[] = $ex;
        }
    }

    if (!$filtered) {
        $filtered = $exerciseList;
    }

    shuffle($filtered);
    return array_slice($filtered, 0, $limit);
}

function getPlanText(string $name): string {
    if ($name === '') {
        return '3 set x 10 repetisi';
    }
    $lower = strtolower($name);
    $cardioKeywords = ['run', 'jog', 'cycle', 'bike', 'cardio', 'swim', 'row', 'walk'];
    foreach ($cardioKeywords as $kw) {
        if (str_contains($lower, $kw)) {
            $minutes = rand(10, 20);
            return $minutes . ' menit';
        }
    }
    $reps = rand(8, 15);
    return '3 set x ' . $reps . ' repetisi';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save_workout') {
        $goal = trim($_POST['goal'] ?? '');
        $fitness_level = trim($_POST['fitness_level'] ?? '');
        $detail_workout = trim($_POST['detail_workout'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if ($detail_workout === '') {
            $errors[] = 'Detail workout belum ada.';
        }

        if (!$errors) {
            $insertWorkout = $pdo->prepare("
                INSERT INTO workout_personalizations
                (id_users, goal, fitness_level, detail_workout, notes)
                VALUES (?, ?, ?, ?, ?)
            ");
            $insertWorkout->execute([
                $user_id, $goal, $fitness_level, $detail_workout, $notes
            ]);

            header("Location: workout.php?saved=1");
            exit;
        }
    }
}

if (isset($_GET['saved'])) {
    $success = 'Rencana workout tersimpan.';
}

$workoutStmt = $pdo->prepare("
    SELECT *
    FROM workout_personalizations
    WHERE id_users = ?
    ORDER BY created_at DESC
    LIMIT 1
");
$workoutStmt->execute([$user_id]);
$workoutRow = $workoutStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$lastGoal = $workoutRow['goal'] ?? '';
$lastFitness = $workoutRow['fitness_level'] ?? '';
$lastNotes = $workoutRow['notes'] ?? '';

$goalContext = $lastGoal;
$fitnessContext = $lastFitness;
$workoutLimit = 8;

$langId = getLanguageId('English');
$exerciseData = apiGetJson("https://wger.de/api/v2/exercise/?language={$langId}&status=2&limit=80");
$exerciseList = $exerciseData['results'] ?? [];

if (!$exerciseList) {
    $exerciseData = apiGetJson("https://wger.de/api/v2/exercise/?language={$langId}&limit=80");
    $exerciseList = $exerciseData['results'] ?? [];
}

$exerciseList = pickExercises($exerciseList, $goalContext, $fitnessContext, $workoutLimit);

$planLines = [];
foreach ($exerciseList as $ex) {
    $name = isset($ex['name']) ? trim((string) $ex['name']) : '';
    if ($name === '') {
        continue;
    }
    $planLines[] = $name . ' - ' . getPlanText($name);
}

if (!$planLines) {
    $fallback = buildFallbackPlan($goalContext, $fitnessContext);
    if ($workoutLimit > 0) {
        $fallback = array_slice($fallback, 0, $workoutLimit);
    }
    foreach ($fallback as $name) {
        $planLines[] = $name . ' - ' . getPlanText($name);
    }
}
$planText = implode("\n", $planLines);
?>

<style>
.app {
    max-width: 420px;
    margin: 0 auto;
    padding: 18px 18px 80px;
    min-width: 0;
}

.card {
    background: #ffffff;
    border-radius: 20px;
    padding: 18px;
    box-shadow: 0 12px 26px rgba(22, 64, 94, 0.12);
    margin-bottom: 16px;
    min-width: 0;
    overflow: hidden;
}

.hero {
    background: linear-gradient(135deg, #0f766e 0%, #22c55e 52%, #67c6f5 100%);
    color: #ffffff;
    border-radius: 22px;
    padding: 26px;
    border: 1px solid rgba(255, 255, 255, 0.18);
    box-shadow: 0 20px 38px rgba(16, 122, 110, 0.18);
}

.hero .sleep-sub {
    color: rgba(255, 255, 255, 0.95);
}

.sleep-hero-inner {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.hero-copy {
    min-width: 0;
}

.emoji-bubble {
    width: 56px;
    height: 56px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 18px;
    background: rgba(255, 255, 255, 0.14);
    border: 1px solid rgba(255, 255, 255, 0.22);
    font-size: 28px;
}

.sleep-title {
    font-size: clamp(24px, 7vw, 30px);
    font-weight: 800;
    line-height: 1.1;
}

.summary-title {
    font-size: 20px;
    font-weight: 800;
    color: #16323b;
    margin-bottom: 10px;
    line-height: 1.2;
    overflow-wrap: anywhere;
}

.hero-badges {
    margin-top: 14px;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.hero-badge {
    display: inline-flex;
    align-items: center;
    min-height: 34px;
    padding: 7px 11px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.16);
    border: 1px solid rgba(255, 255, 255, 0.22);
    font-size: 12px;
    font-weight: 700;
    line-height: 1.3;
}

.tab-row {
    display: flex;
    gap: 8px;
    margin: 4px 0 14px;
    font-weight: 700;
    font-size: 12px;
    flex-wrap: nowrap;
}

.tab-row a,
.tab-row span {
    text-decoration: none;
    color: #5b6b77;
    padding: 10px 12px;
    border-radius: 999px;
    border: 1px solid #d7e6ef;
    background: #ffffff;
    box-shadow: 0 10px 20px rgba(15, 23, 42, 0.05);
    line-height: 1.3;
    flex: 1 1 0;
    min-width: 0;
    text-align: center;
    overflow-wrap: anywhere;
}

.tab-row .active {
    color: #0f766e;
    background: linear-gradient(145deg, #ecfeff, #f0fdfa);
    border-color: #8be4dc;
}

.plan-item {
    background: #f7fbfd;
    border-radius: 14px;
    padding: 12px;
    margin-bottom: 10px;
    border: 1px solid #e1edf4;
    min-width: 0;
}

.plan-name {
    font-weight: 700;
    font-size: 14px;
    margin-bottom: 4px;
    overflow-wrap: anywhere;
}

.plan-desc {
    font-size: 12px;
    color: #60707e;
    overflow-wrap: anywhere;
}

.plan-meta {
    margin-top: 6px;
    font-size: 12px;
    font-weight: 700;
    color: #1c8a6a;
    overflow-wrap: anywhere;
}

.input-group {
    margin-bottom: 12px;
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-width: 0;
}

.input-group label {
    font-size: 12px;
    font-weight: 600;
    color: #24343f;
}

.input-group input,
.input-group textarea {
    width: 100%;
    max-width: 100%;
    padding: 10px 12px;
    border-radius: 12px;
    border: 1px solid #d7e1ea;
    font-size: 13px;
    min-width: 0;
}

.btn-primary {
    width: 100%;
    background: #3ad26f;
    color: #ffffff;
    border: none;
    border-radius: 999px;
    padding: 10px 16px;
    font-weight: 700;
    cursor: pointer;
}

.btn-secondary {
    width: 100%;
    background: #ffffff;
    color: #1c3a4f;
    border: 1px solid #cfe1ee;
    border-radius: 999px;
    padding: 10px 16px;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    text-align: center;
}

.message {
    background: #e8fff1;
    border: 1px solid #b7f1cd;
    color: #1b6c3d;
    padding: 10px 12px;
    border-radius: 12px;
    font-size: 12px;
    margin-bottom: 12px;
}

.error {
    background: #ffecec;
    border: 1px solid #f7bcbc;
    color: #8a2f2f;
}

.btn-primary,
.btn-secondary {
    max-width: 100%;
}

@media (max-width: 480px) {
    .app {
        max-width: 100%;
        padding: 10px 10px 76px;
    }

    .card,
    .hero {
        padding: 12px;
        border-radius: 18px;
    }

    .hero {
        margin-bottom: 10px;
    }

    .sleep-hero-inner {
        gap: 10px;
    }

    .sleep-title {
        font-size: clamp(22px, 6vw, 26px);
    }

    .hero .sleep-sub {
        font-size: 12px;
        line-height: 1.45;
    }

    .tab-row {
        gap: 6px;
        margin: 2px 0 12px;
        font-size: 11px;
    }

    .tab-row a,
    .tab-row span {
        padding: 9px 8px;
    }

    .hero-badges {
        margin-top: 8px;
        gap: 6px;
    }

    .hero-badge {
        min-height: 30px;
        padding: 5px 8px;
        font-size: 11px;
    }

    .summary-title {
        margin-bottom: 8px;
    }
}

@media (min-width: 900px) {
    .app {
        max-width: 1100px;
        padding: 20px 22px 92px;
    }

    .sleep-hero-inner {
        flex-direction: row;
        align-items: flex-start;
    }

    .hero {
        padding: 28px;
        margin-bottom: 12px;
    }

    .sleep-title {
        font-size: 34px;
    }

    .tab-row {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
        margin: 4px 0 18px;
    }

    .tab-row a,
    .tab-row span {
        min-height: 56px;
        padding: 14px 18px;
        border-radius: 20px;
        font-size: 15px;
        font-weight: 800;
        box-shadow: 0 14px 28px rgba(15, 23, 42, 0.08);
    }
}
</style>

<main class="app">
    <section class="card hero">
        <div class="sleep-hero-inner">
            <div class="emoji-bubble">&#127947;</div>
            <div class="hero-copy">
                <div class="sleep-title">Workout Personalization</div>
                <div class="sleep-sub">Rekomendasi latihan otomatis untuk hari ini yang menyesuaikan goal, level fitness, dan plan yang tersedia.</div>
                <div class="hero-badges">
                    <span class="hero-badge"><?= $lastGoal !== '' ? htmlspecialchars($lastGoal) : 'Goal belum diisi' ?></span>
                    <span class="hero-badge"><?= $lastFitness !== '' ? htmlspecialchars($lastFitness) : 'Level belum diisi' ?></span>
                    <span class="hero-badge"><?= count($planLines) ?> rekomendasi latihan</span>
                </div>
            </div>
        </div>
    </section>

    <div class="tab-row">
        <a href="gym_booking.php">Available &amp; Pesan alat</a>
        <span class="active">Workout Recommend</span>
    </div>

    <?php if ($errors): ?>
        <section class="card">
            <div class="message error">
                <?= htmlspecialchars(implode(' ', $errors)) ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($success): ?>
        <section class="card">
            <div class="message">
                <?= htmlspecialchars($success) ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="card">
        <div class="summary-title">Rencana Latihan Hari Ini</div>

        <?php if (!$planLines): ?>
            <div class="plan-desc">Belum ada rekomendasi workout.</div>
        <?php else: ?>
            <?php foreach ($planLines as $line): ?>
                <?php
                    [$name, $plan] = array_pad(explode(' - ', $line, 2), 2, '');
                ?>
                <div class="plan-item">
                    <div class="plan-name"><?= htmlspecialchars($name) ?></div>
                    <div class="plan-desc">Latihan sesuai goal & fitness level.</div>
                    <div class="plan-meta"><?= htmlspecialchars($plan) ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <a class="btn-secondary" href="workout.php?refresh=1">Generate Ulang</a>
    </section>

    <section class="card">
        <div class="summary-title">Simpan Rencana Workout</div>

        <form method="POST">
            <input type="hidden" name="action" value="save_workout">
            <input type="hidden" name="detail_workout" value="<?= htmlspecialchars($planText) ?>">

            <div class="input-group">
                <label>Goal</label>
                <input type="text" name="goal" value="<?= htmlspecialchars((string) $lastGoal) ?>" placeholder="Contoh: Fat loss, Muscle gain">
            </div>

            <div class="input-group">
                <label>Fitness Level</label>
                <input type="text" name="fitness_level" value="<?= htmlspecialchars((string) $lastFitness) ?>" placeholder="Beginner / Intermediate / Advanced">
            </div>

            <div class="input-group">
                <label>Notes</label>
                <textarea name="notes" rows="3" placeholder="Catatan tambahan"><?= htmlspecialchars((string) $lastNotes) ?></textarea>
            </div>

            <button class="btn-primary" type="submit">Simpan Rencana</button>
        </form>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
