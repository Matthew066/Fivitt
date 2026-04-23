<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require_once 'includes/db.php';
require_once 'includes/profile_image.php';

ensure_users_profile_image_schema($pdo);

$userId = (int) ($_SESSION['user_id'] ?? 0);
$userRole = strtolower(trim((string)($_SESSION['user_role'] ?? 'user')));
$canteenLink = 'foodselection.php';
if ($userRole === 'cooker') {
    $canteenLink = 'healthy_canteen.php';
} elseif ($userRole === 'admin') {
    $canteenLink = 'admin/healthy_canteen.php';
}

$profileImage = null;
if ($userId > 0) {
    $stmt = $pdo->prepare("SELECT profile_image FROM users WHERE id_users = ? LIMIT 1");
    $stmt->execute([$userId]);
    $profileImage = $stmt->fetchColumn() ?: null;
}

$profileSettings = get_user_profile_settings($pdo, $userId);
$sleepTarget = get_sleep_target_by_age_group($profileSettings['effective_age_group']);

$checkinStmt = $pdo->prepare("
    SELECT checkin_date, activity_minutes, water_intake_ml
    FROM daily_checkins
    WHERE id_users = ?
      AND checkin_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    ORDER BY checkin_date ASC
");
$checkinStmt->execute([$userId]);
$checkinRows = $checkinStmt->fetchAll(PDO::FETCH_ASSOC);

$sleepStmt = $pdo->prepare("
    SELECT sleep_date, sleep_start, sleep_end
    FROM sleep_logs
    WHERE id_users = ?
      AND sleep_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    ORDER BY sleep_date ASC
");
$sleepStmt->execute([$userId]);
$sleepRows = $sleepStmt->fetchAll(PDO::FETCH_ASSOC);

$waterTotal = 0;
$activityTotal = 0;
$checkinCount = count($checkinRows);
foreach ($checkinRows as $row) {
    $waterTotal += (int) ($row['water_intake_ml'] ?? 0);
    $activityTotal += (int) ($row['activity_minutes'] ?? 0);
}

$sleepMap = [];
$sleepTotalHours = 0.0;
$sleepCount = 0;
foreach ($sleepRows as $row) {
    $date = (string) ($row['sleep_date'] ?? '');
    $start = strtotime($date . ' ' . $row['sleep_start']);
    $end = strtotime($date . ' ' . $row['sleep_end']);
    if ($end <= $start) {
        $end = strtotime('+1 day', $end);
    }
    $duration = round(($end - $start) / 3600, 1);
    $sleepMap[$date] = $duration;
    $sleepTotalHours += $duration;
    $sleepCount++;
}

$avgWaterLiters = $checkinCount ? round(($waterTotal / $checkinCount) / 1000, 1) : 0;
$avgSleepHours = $sleepCount ? round($sleepTotalHours / $sleepCount, 1) : 0;
$weeklyActivity = (int) $activityTotal;
$checkinStatus = $checkinCount >= 5 ? 'Konsisten' : ($checkinCount >= 3 ? 'Lumayan' : 'Baru mulai');
$goalProgress = max(18, min(100, (int) round((($weeklyActivity / 150) * 45) + (($avgSleepHours / 8) * 35) + (($avgWaterLiters / 1.5) * 20))));
$checkinMissing = max(0, 7 - $checkinCount);
$todayPulse = $goalProgress >= 78 ? 'Aligned Day' : ($goalProgress >= 52 ? 'Balanced Day' : 'Recovery Day');
$todayPulseText = $goalProgress >= 78
    ? 'Ritme minggu ini lagi bagus. Kamu tinggal jaga konsistensi kecilnya.'
    : ($goalProgress >= 52
        ? 'Pola kamu sudah mulai kebentuk. Sedikit dorongan lagi bakal terasa beda.'
        : 'Tubuhmu butuh ritme yang lebih lembut. Fokus ke tidur, air, dan check-in sederhana.');
$consistencyText = $checkinMissing === 0
    ? 'Check-in lengkap 7/7 hari.'
    : 'Masih kurang ' . $checkinMissing . ' hari check-in untuk menutup minggu ini.';
$sleepMood = $avgSleepHours >= 7 ? 'Sleep window stabil' : 'Sleep debt perlu dikejar';
$hydrationMood = $avgWaterLiters >= 1.1 ? 'Hidrasi aman' : 'Hidrasi masih rendah';
$heroMessage = $goalProgress >= 70
    ? 'Home base untuk jagain momentum sehatmu.'
    : 'Home base untuk balikin ritme sehatmu pelan-pelan.';

$chartLabels = [];
$chartActivity = [];
$chartWater = [];
$chartSleep = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} day"));
    $chartLabels[] = date('D', strtotime($date));

    $checkin = null;
    foreach ($checkinRows as $row) {
        if (($row['checkin_date'] ?? '') === $date) {
            $checkin = $row;
            break;
        }
    }

    $chartActivity[] = (int) ($checkin['activity_minutes'] ?? 0);
    $chartWater[] = round(((int) ($checkin['water_intake_ml'] ?? 0)) / 1000, 2);
    $chartSleep[] = $sleepMap[$date] ?? 0;
}

$pageTitle = 'Home';
$bodyClass = 'home-page';
$extraStyles = [
    'assets/css/all.min.css',
    'assets/css/homsescreen.css'
];
include 'includes/header.php';
?>

<div class="preloader">
    <img src="assets/images/splashscreen/logofivit.png" alt="Loading Fivit">
</div>

<div class="homescreen-wrapper">
    <main class="app home-main">
        <section class="card home-hero upgraded-hero">
            <div class="sleep-hero-inner home-hero-top">
                <form class="avatar-upload-form" method="post" action="update_profile_image.php" enctype="multipart/form-data">
                    <input type="file" name="profile_image" id="userProfileImageInput" hidden>

                    <div class="avatar-shell">
                        <div class="avatar-circle">
                            <?php if (!empty($profileImage)): ?>
                                <img class="avatar-image" src="<?= htmlspecialchars($profileImage) ?>">
                            <?php else: ?>
                                <i class="fa-solid fa-seedling"></i>
                            <?php endif; ?>
                        </div>

                        <button type="button" class="avatar-upload-btn"
                                onclick="document.getElementById('userProfileImageInput').click()">
                            <i class="fa-solid fa-camera"></i>
                        </button>
                    </div>
                </form>

                <div class="hero-copy">
                    <div class="sleep-title">
                        Hi, <?= htmlspecialchars($_SESSION['user_name']) ?>!
                    </div>

                    <div class="sleep-sub"><?= htmlspecialchars($heroMessage) ?></div>
                </div>
            </div>

            <div class="hero-progress-block">
                <div class="hero-progress-label">
                    <span>Weekly rhythm</span>
                    <strong><?= $goalProgress ?>%</strong>
                </div>

                <div class="progress-track">
                    <div class="progress-fill" style="width: <?= $goalProgress ?>%;"></div>
                </div>

                <p class="hero-note"><?= htmlspecialchars($todayPulseText) ?></p>
            </div>
        </section>

        <section class="card home-actions-card">
            <div class="section-head">
                <div>
                    <h2>Quick Access</h2>
                    <p>Fitur utama yang sering dipakai.</p>
                </div>
                <a href="profile_settings.php" class="section-link action-profile-link">Profil</a>
            </div>

            <div class="home-actions-grid">

                <a class="action-card action-health" href="health.php">
                    <div class="action-icon icon-yellow"><i class="fa-solid fa-heart-pulse"></i></div>
                    <div class="action-copy">
                        <strong>Health</strong>
                        <span><?= $checkinStatus ?></span>
                    </div>
                </a>

                <a class="action-card action-sleep" href="sleep.php">
                    <div class="action-icon icon-mint"><i class="fa-solid fa-bed"></i></div>
                    <div class="action-copy">
                        <strong>Sleep</strong>
                        <span><?= number_format($avgSleepHours,1) ?>h</span>
                    </div>
                </a>

                <a class="action-card action-gym" href="gym_booking.php">
                    <div class="action-icon icon-purple"><i class="fa-solid fa-dumbbell"></i></div>
                    <div class="action-copy">
                        <strong>Gym</strong>
                        <span><?= $weeklyActivity ?> min</span>
                    </div>
                </a>

                <a class="action-card action-nutrition" href="<?= htmlspecialchars($canteenLink) ?>">
                    <div class="action-icon icon-cream"><i class="fa-solid fa-utensils"></i></div>
                    <div class="action-copy">
                        <strong>Nutrition</strong>
                        <span>Meal plan</span>
                    </div>
                </a>

                <a class="action-card action-community" href="community.php">
                    <div class="action-icon icon-sky"><i class="fa-solid fa-users"></i></div>
                    <div class="action-copy">
                        <strong>Community</strong>
                        <span>Support circle</span>
                    </div>
                </a>

                <a class="action-card action-education" href="education.php">
                    <div class="action-icon icon-lime"><i class="fa-solid fa-book-open"></i></div>
                    <div class="action-copy">
                        <strong>Education</strong>
                        <span>Healthy tips</span>
                    </div>
                </a>
            </div>
        </section>

        <section class="card chart-card">
            <div class="section-head">
                <div>
                    <h3>Weekly Rhythm</h3>
                    <p>Aktivitas, hidrasi, dan tidur 7 hari terakhir.</p>
                </div>
                <a href="health.php" class="section-link">Detail</a>
            </div>

            <div class="home-chart-shell">
                <canvas id="homeWeeklyChart" height="240"></canvas>
            </div>

            <div class="legend" id="homeChartLegend">
                <span class="legend-item legend-activity is-active" data-dataset-index="0"><i class="dot teal"></i>Aktivitas</span>
                <span class="legend-item legend-water is-active" data-dataset-index="1"><i class="dot sand"></i>Air</span>
                <span class="legend-item legend-sleep is-active" data-dataset-index="2"><i class="dot green"></i>Tidur</span>
            </div>
        </section>

        <section class="card home-summary-card">
            <div class="section-head">
                <h2>Ringkasan</h2>
            </div>

            <div class="home-summary-grid">

                <div class="summary-tile">
                    <span>Aktivitas</span>
                    <strong><?= $weeklyActivity ?> min</strong>
                </div>

                <div class="summary-tile">
                    <span>Tidur</span>
                    <strong><?= number_format($avgSleepHours,1) ?> jam</strong>
                </div>

                <div class="summary-tile">
                    <span>Air</span>
                    <strong><?= number_format($avgWaterLiters,1) ?> L</strong>
                </div>

                <div class="summary-tile">
                    <span>Check-in</span>
                    <strong><?= $checkinCount ?>/7</strong>
                </div>

            </div>
        </section>

        <section class="card other-section">
            <div class="section-head">
                <h3>Explore More</h3>
                <p>Fitur pendukung yang tetap satu tema dengan dashboard health kamu.</p>
            </div>
            <div class="other-grid">
                <a class="other-card other-events" href="sportevent.php">
                    <div class="other-icon"><i class="fa-solid fa-calendar-days"></i></div>
                    <p>Events</p>
                </a>
                <a class="other-card other-education" href="education.php">
                    <div class="other-icon"><i class="fa-solid fa-book-open"></i></div>
                    <p>Education</p>
                </a>
                <a class="other-card other-community" href="community.php">
                    <div class="other-icon"><i class="fa-solid fa-users"></i></div>
                    <p>Community</p>
                </a>
                <a class="other-card other-leaderboard" href="leaderboard.php">
                    <div class="other-icon"><i class="fa-solid fa-trophy"></i></div>
                    <p>Leaderboard</p>
                </a>
            </div>
        </section>
    </main>
</div>

<?php include 'includes/footer.php'; ?>

<script src="assets/js/chart.js"></script>
<script>
window.addEventListener("load", function () {
    const loader = document.querySelector(".preloader");
    if (loader) {
        loader.style.transition = "opacity 1s ease";

        setTimeout(() => {
            loader.style.opacity = "0";
            setTimeout(() => {
                loader.style.display = "none";
            }, 1000);
        }, 2000);
    }
});

document.getElementById('userProfileImageInput')?.addEventListener('change', function () {
    if (this.files && this.files.length > 0) {
        this.form.submit();
    }
});

(() => {
    const canvas = document.getElementById('homeWeeklyChart');
    const legend = document.getElementById('homeChartLegend');
    if (!canvas || !legend || typeof Chart === 'undefined') {
        return;
    }

    const chart = new Chart(canvas, {
        data: {
            labels: <?php echo json_encode($chartLabels); ?>,
            datasets: [
                {
                    type: 'bar',
                    label: 'Aktivitas',
                    data: <?php echo json_encode($chartActivity); ?>,
                    backgroundColor: 'rgba(26, 188, 156, 0.78)',
                    borderRadius: 12,
                    yAxisID: 'y'
                },
                {
                    type: 'line',
                    label: 'Air (L)',
                    data: <?php echo json_encode($chartWater); ?>,
                    borderColor: '#c08457',
                    backgroundColor: 'rgba(192, 132, 87, 0.12)',
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#c08457',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    tension: 0.35,
                    yAxisID: 'y1'
                },
                {
                    type: 'line',
                    label: 'Tidur (jam)',
                    data: <?php echo json_encode($chartSleep); ?>,
                    borderColor: '#7cb342',
                    backgroundColor: 'rgba(124, 179, 66, 0.12)',
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#7cb342',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    tension: 0.35,
                    yAxisID: 'y2'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                intersect: false,
                mode: 'index'
            },
            plugins: {
                legend: {
                    display: false
                }
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
                    beginAtZero: true,
                    suggestedMax: 60,
                    grid: {
                        color: 'rgba(148, 163, 184, 0.14)'
                    },
                    ticks: {
                        color: '#0f766e'
                    }
                },
                y1: {
                    beginAtZero: true,
                    position: 'right',
                    suggestedMax: 3,
                    grid: {
                        drawOnChartArea: false
                    },
                    ticks: {
                        color: '#a16207'
                    }
                },
                y2: {
                    beginAtZero: true,
                    position: 'right',
                    display: false,
                    suggestedMax: 10
                }
            }
        }
    });

    legend.querySelectorAll('[data-dataset-index]').forEach((item) => {
        item.addEventListener('click', () => {
            const datasetIndex = Number(item.dataset.datasetIndex);
            const isVisible = chart.isDatasetVisible(datasetIndex);

            chart.setDatasetVisibility(datasetIndex, !isVisible);
            item.classList.toggle('is-active', !isVisible);
            item.classList.toggle('is-muted', isVisible);
            chart.update();
        });
    });
})();
</script>
