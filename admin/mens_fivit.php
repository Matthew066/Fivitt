<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_login('../login.php');

$pageTitle = 'Admin - Mens Fivit';

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function clampInt($value, int $min, int $max, int $fallback): int
{
    if (!is_numeric($value)) return $fallback;
    $value = (int) $value;
    return max($min, min($max, $value));
}

$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'delete_profile') {
        $userId = (int) ($_POST['id_users'] ?? 0);
        if ($userId > 0) {
            $stmt = $pdo->prepare("DELETE FROM mens_user_profiles WHERE id_users = ?");
            $stmt->execute([$userId]);
        }
        header('Location: mens_fivit.php');
        exit;
    }

    if ($action === 'delete_cycle') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("DELETE FROM mens_cycle_logs WHERE id_mens_cycle_logs = ?");
            $stmt->execute([$id]);
        }
        header('Location: mens_fivit.php');
        exit;
    }
}

$profiles = $pdo->query("
    SELECT p.id_users, p.birth_year, p.birth_month, p.birth_day, p.birth_hour, p.updated_at, u.name, u.email
    FROM mens_user_profiles p
    LEFT JOIN users u ON u.id_users = p.id_users
    ORDER BY p.updated_at DESC
")->fetchAll();

$cycles = $pdo->query("
    SELECT c.id_mens_cycle_logs, c.id_users, c.period_start_date, c.period_end_date, c.reported_cycle_length,
           c.period_length, c.symptom_score, c.mood_score, c.mood_type, c.symptom_tags, c.flow_level, c.created_at,
           u.name, u.email
    FROM mens_cycle_logs c
    LEFT JOIN users u ON u.id_users = c.id_users
    ORDER BY c.period_start_date DESC, c.id_mens_cycle_logs DESC
")->fetchAll();

$userNames = $pdo->query("SELECT id_users, name, email FROM users ORDER BY name ASC")->fetchAll();
$userMap = [];
foreach ($userNames as $u) {
    $label = trim((string)($u['name'] ?? ''));
    if ($label === '') $label = 'User';
    $email = trim((string)($u['email'] ?? ''));
    $userMap[(int)$u['id_users']] = $label . ($email !== '' ? " ($email)" : '');
}

$cyclesByUser = [];
$moodCounts = [];
$flowCounts = [];
foreach ($cycles as $row) {
    $uid = (int) $row['id_users'];
    if (!isset($cyclesByUser[$uid])) {
        $cyclesByUser[$uid] = [];
    }
    $cyclesByUser[$uid][] = $row;

    $mood = trim((string)($row['mood_type'] ?? ''));
    if ($mood !== '') {
        $moodCounts[$mood] = ($moodCounts[$mood] ?? 0) + 1;
    }
    $flow = (int)($row['flow_level'] ?? 0);
    if ($flow > 0) {
        $flowCounts[$flow] = ($flowCounts[$flow] ?? 0) + 1;
    }
}

$cycleSeries = [];
$avgByUser = [];
 $predictionByUser = [];
foreach ($cyclesByUser as $uid => $rows) {
    $rowsAsc = $rows;
    usort($rowsAsc, function ($a, $b) {
        return strcmp((string)$a['period_start_date'], (string)$b['period_start_date']);
    });
    $points = [];
    $prevStart = null;
    foreach ($rowsAsc as $row) {
        $start = (string)($row['period_start_date'] ?? '');
        if ($start === '') continue;
        $len = $row['reported_cycle_length'] ?? null;
        if ($len === null && $prevStart) {
            $prevTs = strtotime($prevStart);
            $currTs = strtotime($start);
            if ($prevTs && $currTs && $currTs > $prevTs) {
                $len = (int) round(($currTs - $prevTs) / 86400);
            }
        }
        $prevStart = $start;
        if ($len !== null) {
            $points[] = ['x' => $start, 'y' => (int)$len];
        }
    }
    if (!$points) continue;

    $points = array_slice($points, -12);
    $cycleSeries[$uid] = $points;

    $sum = 0;
    foreach ($points as $p) $sum += $p['y'];
    $avgByUser[$uid] = round($sum / count($points), 1);

    $lastCycle = end($rowsAsc);
    $lastStart = $lastCycle['period_start_date'] ?? null;
    $avgLen = $avgByUser[$uid];
    if ($lastStart && $avgLen) {
        $predictionByUser[$uid] = date('Y-m-d', strtotime($lastStart . ' +' . (int)round($avgLen) . ' days'));
    }
}

ksort($flowCounts);
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($pageTitle) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <style>
        body { font-family: 'Poppins', sans-serif; background: #f8fafc; }
        .page-wrapper { padding: 24px; }
        .card { border-radius: 14px; border: 1px solid #e2e8f0; }
        .card h6 { font-weight: 700; }
        .badge-soft { background: #eef2ff; color: #4338ca; }
        .table td, .table th { vertical-align: middle; }
        .chart-wrap { min-height: 280px; }
        .chart-toolbar { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; }
    </style>
</head>
<body>
<div class="page-wrapper">
    <div class="mb-3">
        <h4 class="mb-0">Admin - Mens Fivit</h4>
        <div class="text-muted">Kelola profil & catatan siklus.</div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0">Analitik Siklus</h6>
                        <div class="chart-toolbar">
                            <label class="small text-muted mb-0">Filter user:</label>
                            <select class="form-select form-select-sm" id="userFilter" style="min-width: 220px;">
                                <option value="all">Semua User</option>
                                <?php foreach ($cycleSeries as $uid => $_): ?>
                                    <option value="<?= (int)$uid ?>"><?= h($userMap[$uid] ?? ('User #' . $uid)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="chart-wrap">
                        <canvas id="cycleChart"></canvas>
                    </div>
                    <div class="small text-muted mt-2">Grafik panjang siklus per user (hari vs tanggal mulai).</div>
                </div>
            </div>

            <div class="row">
                <div class="col-12 col-lg-6">
                    <div class="card mb-3">
                        <div class="card-body">
                            <h6 class="mb-3">Rata-rata Siklus per User</h6>
                            <div class="chart-wrap">
                                <canvas id="avgChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-lg-6">
                    <div class="card mb-3">
                        <div class="card-body">
                            <h6 class="mb-3">Distribusi Mood & Flow</h6>
                            <div class="chart-wrap mb-3">
                                <canvas id="moodChart"></canvas>
                            </div>
                            <div class="chart-wrap">
                                <canvas id="flowChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-body">
                    <h6 class="mb-3">Prediksi Next Period (berdasarkan rata-rata)</h6>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Last Period Start</th>
                                    <th>Avg Cycle (hari)</th>
                                    <th>Prediksi Next</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (!$predictionByUser): ?>
                                <tr><td colspan="4" class="text-center">Belum ada data prediksi.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($predictionByUser as $uid => $nextDate): ?>
                                <?php
                                    $userLabel = $userMap[$uid] ?? ('User #' . $uid);
                                    $lastRow = $cyclesByUser[$uid] ?? [];
                                    $lastRow = end($lastRow);
                                    $lastStart = $lastRow['period_start_date'] ?? '-';
                                ?>
                                <tr>
                                    <td><?= h($userLabel) ?></td>
                                    <td><?= h($lastStart) ?></td>
                                    <td><?= h((string)($avgByUser[$uid] ?? '-')) ?></td>
                                    <td><?= h($nextDate) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-body">
                    <h6 class="mb-3">Daftar Profil</h6>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Tanggal Lahir</th>
                                    <th>Jam</th>
                                    <th>Update</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (!$profiles): ?>
                                <tr><td colspan="5" class="text-center">Belum ada profil.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($profiles as $profile): ?>
                                <tr>
                                    <td>
                                        <?= h($profile['name'] ?: 'User') ?>
                                        <div class="text-muted small"><?= h($profile['email'] ?: '-') ?></div>
                                    </td>
                                    <td>
                                        <?= h(sprintf('%02d/%02d/%04d',
                                            (int)($profile['birth_day'] ?? 0),
                                            (int)($profile['birth_month'] ?? 0),
                                            (int)($profile['birth_year'] ?? 0)
                                        )) ?>
                                    </td>
                                    <td><?= h((string)($profile['birth_hour'] ?? '-')) ?></td>
                                    <td class="text-muted small"><?= h((string)($profile['updated_at'] ?? '-')) ?></td>
                                    <td>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Hapus profil ini?');">
                                            <input type="hidden" name="action" value="delete_profile">
                                            <input type="hidden" name="id_users" value="<?= (int)$profile['id_users'] ?>">
                                            <button class="btn btn-sm btn-outline-danger" type="submit">Hapus</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h6 class="mb-3">Daftar Siklus</h6>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Mulai</th>
                                    <th>Selesai</th>
                                    <th>Mood</th>
                                    <th>Flow</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (!$cycles): ?>
                                <tr><td colspan="6" class="text-center">Belum ada data siklus.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($cycles as $cycle): ?>
                                <tr>
                                    <td>
                                        <?= h($cycle['name'] ?: 'User') ?>
                                        <div class="text-muted small"><?= h($cycle['email'] ?: '-') ?></div>
                                    </td>
                                    <td><?= h($cycle['period_start_date'] ?? '-') ?></td>
                                    <td><?= h($cycle['period_end_date'] ?? '-') ?></td>
                                    <td><?= h($cycle['mood_type'] ?? '-') ?></td>
                                    <td><span class="badge badge-soft"><?= h((string)($cycle['flow_level'] ?? '-')) ?></span></td>
                                    <td>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Hapus data siklus ini?');">
                                            <input type="hidden" name="action" value="delete_cycle">
                                            <input type="hidden" name="id" value="<?= (int)$cycle['id_mens_cycle_logs'] ?>">
                                            <button class="btn btn-sm btn-outline-danger" type="submit">Hapus</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3"></script>
<script>
const cycleSeries = <?= json_encode($cycleSeries, JSON_UNESCAPED_SLASHES) ?>;
const userMap = <?= json_encode($userMap, JSON_UNESCAPED_SLASHES) ?>;
const avgByUser = <?= json_encode($avgByUser, JSON_UNESCAPED_SLASHES) ?>;
const moodCounts = <?= json_encode($moodCounts, JSON_UNESCAPED_SLASHES) ?>;
const flowCounts = <?= json_encode($flowCounts, JSON_UNESCAPED_SLASHES) ?>;

const palette = [
    '#ec4899', '#0ea5e9', '#22c55e', '#f59e0b', '#8b5cf6',
    '#14b8a6', '#ef4444', '#64748b', '#e11d48', '#3b82f6'
];

function buildCycleDatasets() {
    const datasets = [];
    let idx = 0;
    Object.keys(cycleSeries).forEach((uid) => {
        datasets.push({
            label: userMap[uid] || `User #${uid}`,
            data: cycleSeries[uid],
            parsing: false,
            borderColor: palette[idx % palette.length],
            backgroundColor: palette[idx % palette.length] + '22',
            pointRadius: 3,
            tension: 0.3
        });
        idx++;
    });
    return datasets;
}

let cycleChart = null;
function initCycleChart() {
    const canvas = document.getElementById('cycleChart');
    if (!canvas || !window.Chart) return;
    cycleChart = new Chart(canvas, {
        type: 'line',
        data: { datasets: buildCycleDatasets() },
        options: {
            responsive: true,
            plugins: { legend: { display: true } },
            scales: {
                x: { type: 'time', time: { unit: 'day' } },
                y: { beginAtZero: false, suggestedMin: 20, suggestedMax: 40 }
            }
        }
    });
}

function applyUserFilter() {
    const select = document.getElementById('userFilter');
    if (!select || !cycleChart) return;
    const uid = select.value;
    cycleChart.data.datasets.forEach((ds) => {
        if (uid === 'all') ds.hidden = false;
        else ds.hidden = ds.label !== (userMap[uid] || `User #${uid}`);
    });
    cycleChart.update();
}

function initAvgChart() {
    const canvas = document.getElementById('avgChart');
    if (!canvas || !window.Chart) return;
    const labels = Object.keys(avgByUser).map((uid) => userMap[uid] || `User #${uid}`);
    const data = Object.keys(avgByUser).map((uid) => avgByUser[uid]);
    new Chart(canvas, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'Rata-rata Panjang Siklus (hari)',
                data,
                backgroundColor: '#0ea5e9'
            }]
        },
        options: {
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: false, suggestedMin: 20, suggestedMax: 40 } }
        }
    });
}

function initMoodChart() {
    const canvas = document.getElementById('moodChart');
    if (!canvas || !window.Chart) return;
    const labels = Object.keys(moodCounts);
    const data = Object.keys(moodCounts).map((k) => moodCounts[k]);
    new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels,
            datasets: [{ data, backgroundColor: palette }]
        },
        options: { plugins: { legend: { position: 'bottom' } } }
    });
}

function initFlowChart() {
    const canvas = document.getElementById('flowChart');
    if (!canvas || !window.Chart) return;
    const labels = Object.keys(flowCounts).map((k) => `Level ${k}`);
    const data = Object.keys(flowCounts).map((k) => flowCounts[k]);
    new Chart(canvas, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'Jumlah',
                data,
                backgroundColor: '#22c55e'
            }]
        },
        options: { plugins: { legend: { display: false } } }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initCycleChart();
    initAvgChart();
    initMoodChart();
    initFlowChart();
    applyUserFilter();
    const select = document.getElementById('userFilter');
    if (select) select.addEventListener('change', applyUserFilter);
});
</script>
</body>
</html>
