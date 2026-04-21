<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/auth_guard.php';
require_once 'includes/profile_image.php';

require_login();
ensure_users_profile_image_schema($pdo);

function table_has_column(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];
    static $dbName = null;
    $key = $table . '.' . $column;

    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    if ($dbName === null) {
        $dbName = (string) $pdo->query("SELECT DATABASE()")->fetchColumn();
    }

    if ($dbName === '') {
        $cache[$key] = false;
        return false;
    }

    $stmt = $pdo->prepare("
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = ?
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
        LIMIT 1
    ");
    $stmt->execute([$dbName, $table, $column]);
    $cache[$key] = (bool) $stmt->fetch(PDO::FETCH_ASSOC);

    return $cache[$key];
}

function pick_column(PDO $pdo, string $table, array $candidates, string $fallback): string
{
    foreach ($candidates as $candidate) {
        if (table_has_column($pdo, $table, $candidate)) {
            return $candidate;
        }
    }

    return $fallback;
}

function avatar_label(string $name, string $email): string
{
    $source = trim($name) !== '' ? $name : $email;
    $parts = preg_split('/\s+/', trim($source)) ?: [];
    $initials = '';

    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }

        $initials .= strtoupper(function_exists('mb_substr') ? mb_substr($part, 0, 1) : substr($part, 0, 1));
        if (strlen($initials) >= 2) {
            break;
        }
    }

    return $initials !== '' ? $initials : 'FV';
}

$usersIdColumn = pick_column($pdo, 'users', ['id_users', 'user_id'], 'id_users');
$dailyUserColumn = pick_column($pdo, 'daily_checkins', ['id_users', 'user_id'], 'user_id');
$sleepUserColumn = pick_column($pdo, 'sleep_logs', ['id_users', 'user_id'], 'user_id');
$pointsUserColumn = pick_column($pdo, 'user_points', ['user_id', 'id_users'], 'user_id');
$streakUserColumn = pick_column($pdo, 'user_streaks', ['user_id', 'id_users'], 'user_id');
$badgeUserColumn = pick_column($pdo, 'user_badges', ['user_id', 'id_users'], 'user_id');
$eventUserColumn = pick_column($pdo, 'event_participants', ['user_id', 'id_users'], 'user_id');

$leaderboardSql = "
    SELECT
        u.`{$usersIdColumn}` AS user_id,
        COALESCE(u.name, '') AS name,
        COALESCE(u.email, '') AS email,
        COALESCE(u.role, 'user') AS role,
        COALESCE(u.profile_image, '') AS profile_image,
        COALESCE(up.total_points, 0) AS total_points,
        COALESCE(st.current_streak, 0) AS current_streak,
        COALESCE(st.longest_streak, 0) AS longest_streak,
        COALESCE(bg.badge_count, 0) AS badge_count,
        COALESCE(ch.weekly_checkins, 0) AS weekly_checkins,
        COALESCE(ch.weekly_activity, 0) AS weekly_activity,
        COALESCE(ch.weekly_water, 0) AS weekly_water,
        COALESCE(sl.avg_sleep_hours, 0) AS avg_sleep_hours,
        COALESCE(ev.event_count, 0) AS event_count
    FROM users u
    LEFT JOIN (
        SELECT `{$pointsUserColumn}` AS user_id, MAX(COALESCE(total_points, 0)) AS total_points
        FROM user_points
        GROUP BY `{$pointsUserColumn}`
    ) up ON up.user_id = u.`{$usersIdColumn}`
    LEFT JOIN (
        SELECT
            `{$streakUserColumn}` AS user_id,
            MAX(COALESCE(current_streak, 0)) AS current_streak,
            MAX(COALESCE(longest_streak, 0)) AS longest_streak
        FROM user_streaks
        GROUP BY `{$streakUserColumn}`
    ) st ON st.user_id = u.`{$usersIdColumn}`
    LEFT JOIN (
        SELECT `{$badgeUserColumn}` AS user_id, COUNT(*) AS badge_count
        FROM user_badges
        GROUP BY `{$badgeUserColumn}`
    ) bg ON bg.user_id = u.`{$usersIdColumn}`
    LEFT JOIN (
        SELECT
            `{$dailyUserColumn}` AS user_id,
            COUNT(DISTINCT checkin_date) AS weekly_checkins,
            SUM(COALESCE(activity_minutes, 0)) AS weekly_activity,
            SUM(COALESCE(water_intake_ml, 0)) AS weekly_water
        FROM daily_checkins
        WHERE checkin_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY `{$dailyUserColumn}`
    ) ch ON ch.user_id = u.`{$usersIdColumn}`
    LEFT JOIN (
        SELECT
            `{$sleepUserColumn}` AS user_id,
            AVG(
                CASE
                    WHEN sleep_start IS NULL OR sleep_end IS NULL THEN COALESCE(total_sleep_hours, 0)
                    WHEN TIME_TO_SEC(sleep_end) <= TIME_TO_SEC(sleep_start)
                        THEN (TIME_TO_SEC(sleep_end) + 86400 - TIME_TO_SEC(sleep_start)) / 3600
                    ELSE (TIME_TO_SEC(sleep_end) - TIME_TO_SEC(sleep_start)) / 3600
                END
            ) AS avg_sleep_hours
        FROM sleep_logs
        WHERE sleep_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY `{$sleepUserColumn}`
    ) sl ON sl.user_id = u.`{$usersIdColumn}`
    LEFT JOIN (
        SELECT `{$eventUserColumn}` AS user_id, COUNT(*) AS event_count
        FROM event_participants
        GROUP BY `{$eventUserColumn}`
    ) ev ON ev.user_id = u.`{$usersIdColumn}`
    WHERE COALESCE(u.is_active, 1) = 1
";

$leaderboardStmt = $pdo->query($leaderboardSql);
$leaderboardRows = $leaderboardStmt->fetchAll(PDO::FETCH_ASSOC);

$rankedUsers = [];
$currentUserId = (int) ($_SESSION['user_id'] ?? 0);

foreach ($leaderboardRows as $row) {
    $totalPoints = (int) ($row['total_points'] ?? 0);
    $currentStreak = (int) ($row['current_streak'] ?? 0);
    $longestStreak = (int) ($row['longest_streak'] ?? 0);
    $badgeCount = (int) ($row['badge_count'] ?? 0);
    $weeklyCheckins = (int) ($row['weekly_checkins'] ?? 0);
    $weeklyActivity = (int) ($row['weekly_activity'] ?? 0);
    $weeklyWater = (int) ($row['weekly_water'] ?? 0);
    $avgSleepHours = round((float) ($row['avg_sleep_hours'] ?? 0), 1);
    $eventCount = (int) ($row['event_count'] ?? 0);

    $consistencyBonus = min($weeklyCheckins, 7) * 6;
    $activityBonus = (int) round(min($weeklyActivity, 320) * 0.22);
    $hydrationBonus = (int) round(min($weeklyWater / 250, 14) * 2);
    $sleepBonus = (int) round(min(max($avgSleepHours, 0), 10) * 7);
    $streakBonus = ($currentStreak * 12) + ($longestStreak * 3);
    $badgeBonus = $badgeCount * 20;
    $eventBonus = $eventCount * 10;

    $leaderboardScore = $totalPoints + $consistencyBonus + $activityBonus + $hydrationBonus + $sleepBonus + $streakBonus + $badgeBonus + $eventBonus;

    $rankedUsers[] = [
        'user_id' => (int) ($row['user_id'] ?? 0),
        'name' => (string) ($row['name'] ?? ''),
        'email' => (string) ($row['email'] ?? ''),
        'role' => strtolower(trim((string) ($row['role'] ?? 'user'))),
        'profile_image' => (string) ($row['profile_image'] ?? ''),
        'total_points' => $totalPoints,
        'current_streak' => $currentStreak,
        'longest_streak' => $longestStreak,
        'badge_count' => $badgeCount,
        'weekly_checkins' => $weeklyCheckins,
        'weekly_activity' => $weeklyActivity,
        'weekly_water' => $weeklyWater,
        'avg_sleep_hours' => $avgSleepHours,
        'event_count' => $eventCount,
        'leaderboard_score' => $leaderboardScore,
        'hydration_liters' => round($weeklyWater / 1000, 1),
        'avatar_label' => avatar_label((string) ($row['name'] ?? ''), (string) ($row['email'] ?? '')),
        'is_me' => (int) ($row['user_id'] ?? 0) === $currentUserId,
    ];
}

usort($rankedUsers, static function (array $left, array $right): int {
    return [$right['leaderboard_score'], $right['total_points'], $right['badge_count'], $right['current_streak']]
        <=> [$left['leaderboard_score'], $left['total_points'], $left['badge_count'], $left['current_streak']];
});

$currentUserCard = null;
$podiumUsers = [];
$otherUsers = [];

foreach ($rankedUsers as $index => &$user) {
    $user['rank'] = $index + 1;

    if ($user['rank'] <= 3) {
        $podiumUsers[] = $user;
    } else {
        $otherUsers[] = $user;
    }

    if ($user['is_me']) {
        $currentUserCard = $user;
    }
}
unset($user);

$participantCount = count($rankedUsers);
$totalCommunityScore = array_sum(array_column($rankedUsers, 'leaderboard_score'));
$communityAverage = $participantCount > 0 ? (int) round($totalCommunityScore / $participantCount) : 0;
$topScore = $participantCount > 0 ? (int) ($rankedUsers[0]['leaderboard_score'] ?? 0) : 0;

$pageTitle = 'Leaderboard';
$bodyClass = 'leaderboard-page';
include 'includes/header.php';
?>

<main class="site-content">
    <section class="leaderboard-shell">
        <section class="leaderboard-hero-card">
            <div class="leaderboard-hero-copy">
                <span class="leaderboard-kicker">Community leaderboard</span>
                <h1>Siapa paling konsisten minggu ini?</h1>
                <p>Ranking dihitung dari poin, streak, badge, check-in, aktivitas, hidrasi, dan tidur 7 hari terakhir supaya hasilnya tetap adil dan relevan.</p>
            </div>
            <div class="leaderboard-hero-stats">
                <article class="leaderboard-stat-card">
                    <span>Peserta aktif</span>
                    <strong><?= number_format($participantCount) ?></strong>
                </article>
                <article class="leaderboard-stat-card">
                    <span>Skor rata-rata</span>
                    <strong><?= number_format($communityAverage) ?></strong>
                </article>
                <article class="leaderboard-stat-card">
                    <span>Skor tertinggi</span>
                    <strong><?= number_format($topScore) ?></strong>
                </article>
            </div>
        </section>

        <?php if ($currentUserCard): ?>
            <section class="leaderboard-focus-card">
                <div class="leaderboard-focus-copy">
                    <span class="focus-label">Posisimu sekarang</span>
                    <h2>#<?= (int) $currentUserCard['rank'] ?> di leaderboard</h2>
                    <p><?= htmlspecialchars($currentUserCard['name'] !== '' ? $currentUserCard['name'] : $currentUserCard['email'], ENT_QUOTES, 'UTF-8') ?> sedang mengumpulkan ritme sehat dari aktivitas, hidrasi, tidur, dan streak harian.</p>
                </div>
                <div class="leaderboard-focus-metrics">
                    <span><strong><?= number_format($currentUserCard['leaderboard_score']) ?></strong> score</span>
                    <span><strong><?= number_format($currentUserCard['total_points']) ?></strong> points</span>
                    <span><strong><?= number_format($currentUserCard['current_streak']) ?></strong> hari streak</span>
                    <span><strong><?= number_format($currentUserCard['badge_count']) ?></strong> badge</span>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($participantCount > 0): ?>
            <section class="leaderboard-podium-grid">
                <?php foreach ($podiumUsers as $podiumUser): ?>
                    <?php
                    $podiumClass = $podiumUser['rank'] === 1 ? 'is-gold' : ($podiumUser['rank'] === 2 ? 'is-silver' : 'is-bronze');
                    $displayName = $podiumUser['name'] !== '' ? $podiumUser['name'] : $podiumUser['email'];
                    ?>
                    <article class="leaderboard-podium-card <?= $podiumClass ?>">
                        <div class="podium-rank">#<?= (int) $podiumUser['rank'] ?></div>
                        <div class="podium-avatar">
                            <?php if ($podiumUser['profile_image'] !== ''): ?>
                                <img src="<?= htmlspecialchars($podiumUser['profile_image'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>">
                            <?php else: ?>
                                <span><?= htmlspecialchars($podiumUser['avatar_label'], ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="podium-copy">
                            <h3><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></h3>
                            <p><?= $podiumUser['is_me'] ? 'Kamu lagi on fire.' : 'Performa mingguan paling stabil.' ?></p>
                        </div>
                        <div class="podium-score"><?= number_format($podiumUser['leaderboard_score']) ?> pts</div>
                        <div class="podium-meta">
                            <span><?= number_format($podiumUser['current_streak']) ?> hari streak</span>
                            <span><?= number_format($podiumUser['badge_count']) ?> badge</span>
                            <span><?= number_format($podiumUser['weekly_activity']) ?> menit aktif</span>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>

            <section class="leaderboard-table-card">
                <div class="leaderboard-section-head">
                    <div>
                        <h2>Peringkat lengkap</h2>
                        <p>Urutan ini otomatis menyesuaikan progres community setiap minggu.</p>
                    </div>
                </div>

                <div class="leaderboard-table-wrap">
                    <table class="leaderboard-table">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Member</th>
                                <th>Score</th>
                                <th>Points</th>
                                <th>Streak</th>
                                <th>Badge</th>
                                <th>Aktivitas</th>
                                <th>Tidur</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rankedUsers as $user): ?>
                                <?php $displayName = $user['name'] !== '' ? $user['name'] : $user['email']; ?>
                                <tr<?= $user['is_me'] ? ' class="is-current-user"' : '' ?>>
                                    <td data-label="Rank">
                                        <span class="rank-pill">#<?= (int) $user['rank'] ?></span>
                                    </td>
                                    <td data-label="Member">
                                        <div class="leaderboard-user">
                                            <div class="leaderboard-user-avatar">
                                                <?php if ($user['profile_image'] !== ''): ?>
                                                    <img src="<?= htmlspecialchars($user['profile_image'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>">
                                                <?php else: ?>
                                                    <span><?= htmlspecialchars($user['avatar_label'], ENT_QUOTES, 'UTF-8') ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="leaderboard-user-copy">
                                                <strong><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></strong>
                                                <span>
                                                    <?= $user['is_me'] ? 'Kamu' : 'Member FiVit' ?>
                                                    <?php if ($user['hydration_liters'] > 0): ?>
                                                        • <?= number_format($user['hydration_liters'], 1) ?>L minggu ini
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td data-label="Score"><strong><?= number_format($user['leaderboard_score']) ?></strong></td>
                                    <td data-label="Points"><?= number_format($user['total_points']) ?></td>
                                    <td data-label="Streak"><?= number_format($user['current_streak']) ?> hari</td>
                                    <td data-label="Badge"><?= number_format($user['badge_count']) ?></td>
                                    <td data-label="Aktivitas"><?= number_format($user['weekly_activity']) ?> mnt</td>
                                    <td data-label="Tidur"><?= number_format($user['avg_sleep_hours'], 1) ?> jam</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php else: ?>
            <section class="leaderboard-empty-card">
                <h2>Leaderboard belum punya data</h2>
                <p>Begitu user mulai check-in, tidur, kumpulkan badge, atau mendapat points, ranking akan muncul di sini.</p>
                <a href="health.php" class="leaderboard-cta">Mulai check-in</a>
            </section>
        <?php endif; ?>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
