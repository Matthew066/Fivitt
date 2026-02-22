<?php
session_start();
require_once 'includes/db.php';

$pageTitle = 'Sport Events';
include 'includes/header.php';

$user_id = $_SESSION['user_id'] ?? 1;
$user_role = $_SESSION['role'] ?? 'user';
$is_admin = $user_role === 'admin';
$today = date('Y-m-d');

$tab = $_GET['tab'] ?? 'upcoming';
$tab = in_array($tab, ['upcoming', 'finished'], true) ? $tab : 'upcoming';

$errors = [];
$success = '';

function formatIndoDate(string $date): string {
    $months = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $ts = strtotime($date);
    if (!$ts) {
        return $date;
    }
    $m = (int) date('n', $ts);
    return date('d', $ts) . ' ' . $months[$m] . ' ' . date('Y', $ts);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_event' && $is_admin) {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $event_type = trim($_POST['event_type'] ?? '');
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? '';
        $reward_points = (int) ($_POST['reward_points'] ?? 0);
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if ($title === '' || $description === '' || $event_type === '' || $start_date === '' || $end_date === '') {
            $errors[] = 'Semua field wajib diisi.';
        }

        if ($start_date && $end_date && $end_date < $start_date) {
            $errors[] = 'Tanggal selesai harus setelah tanggal mulai.';
        }

        if (!$errors) {
            $insert = $pdo->prepare("
                INSERT INTO events (title, description, event_type, start_date, end_date, reward_points, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $insert->execute([
                $title, $description, $event_type, $start_date, $end_date, $reward_points, $is_active
            ]);

            header('Location: sportevent.php?tab=upcoming');
            exit;
        }
    }

    if ($action === 'join_event') {
        $event_id = (int) ($_POST['event_id'] ?? 0);
        if ($event_id > 0) {
            $check = $pdo->prepare("
                SELECT id_event_participants
                FROM event_participants
                WHERE event_id = ? AND user_id = ?
                LIMIT 1
            ");
            $check->execute([$event_id, $user_id]);

            if (!$check->fetch()) {
                $join = $pdo->prepare("
                    INSERT INTO event_participants (event_id, user_id, status)
                    VALUES (?, ?, 'registered')
                ");
                $join->execute([$event_id, $user_id]);
                $success = 'Berhasil daftar event.';
            } else {
                $success = 'Kamu sudah terdaftar di event ini.';
            }
        }
    }
}

$countStmt = $pdo->prepare("
    SELECT COUNT(*) AS total
    FROM events
    WHERE start_date >= ? AND is_active = 1
");
$countStmt->execute([$today]);
$upcomingCount = (int) ($countStmt->fetch()['total'] ?? 0);

if ($tab === 'upcoming') {
    $listStmt = $pdo->prepare("
        SELECT *
        FROM events
        WHERE end_date >= ? AND is_active = 1
        ORDER BY start_date ASC
    ");
    $listStmt->execute([$today]);
} else {
    $listStmt = $pdo->prepare("
        SELECT *
        FROM events
        WHERE end_date < ?
        ORDER BY end_date DESC
    ");
    $listStmt->execute([$today]);
}

$events = $listStmt->fetchAll() ?: [];

?>

<style>
.app {
    max-width: 420px;
    margin: 0 auto;
    padding: 18px 18px 80px;
}

.card {
    background: #ffffff;
    border-radius: 20px;
    padding: 18px;
    box-shadow: 0 12px 26px rgba(22, 64, 94, 0.12);
    margin-bottom: 16px;
}

.hero {
    background: #2ec4cc;
    color: #ffffff;
    border-radius: 22px;
    padding: 20px;
}

.hero h2 {
    margin: 0 0 6px;
    font-size: 20px;
    font-weight: 700;
}

.hero p {
    margin: 0;
    font-size: 13px;
    opacity: 0.9;
}

.pill-row {
    display: flex;
    gap: 10px;
    margin-top: 14px;
}

.pill {
    background: #ffffff;
    color: #1f2a44;
    padding: 8px 12px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
    border: none;
    box-shadow: 0 8px 16px rgba(0,0,0,0.08);
}

.tab-row {
    display: flex;
    gap: 16px;
    margin: 8px 4px 12px;
    font-weight: 600;
    font-size: 13px;
}

.tab-row a {
    text-decoration: none;
    color: #7b8a9a;
    padding-bottom: 6px;
}

.tab-row a.active {
    color: #1a2e3a;
    border-bottom: 2px solid #2ec4cc;
}

.event-card {
    background: #2ec4cc;
    border-radius: 18px;
    padding: 12px;
    color: #ffffff;
    position: relative;
    margin-bottom: 16px;
}

.event-chip {
    position: absolute;
    top: 10px;
    left: 10px;
    background: rgba(255,255,255,0.3);
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 600;
}

.event-detail {
    background: #ffffff;
    border-radius: 16px;
    padding: 12px;
    color: #3a3a3a;
    margin-top: 36px;
    box-shadow: 0 6px 14px rgba(0,0,0,0.12);
}

.event-title {
    font-size: 15px;
    font-weight: 700;
    margin-bottom: 4px;
}

.event-desc {
    font-size: 12px;
    color: #6b7a88;
    margin-bottom: 10px;
}

.event-actions {
    display: flex;
    justify-content: flex-end;
}

.btn-detail {
    background: #3ad26f;
    color: #ffffff;
    padding: 6px 14px;
    border: none;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
}

.input-group {
    margin-bottom: 12px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.input-group label {
    font-size: 12px;
    font-weight: 600;
    color: #24343f;
}

.input-group input,
.input-group textarea,
.input-group select {
    padding: 10px 12px;
    border-radius: 12px;
    border: 1px solid #d7e1ea;
    font-size: 13px;
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

.detail-card {
    background: #ffffff;
    border-radius: 16px;
    padding: 14px;
    box-shadow: 0 6px 14px rgba(0,0,0,0.08);
}

.detail-title {
    font-weight: 700;
    margin-bottom: 6px;
}

.detail-item {
    font-size: 12px;
    color: #6b7a88;
    margin-bottom: 6px;
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
</style>

<main class="app">

<section class="card hero">
    <h2>Sport Events</h2>
    <p>Sehat Bersama, Senang Bersama</p>
    <div class="pill-row">
        <div class="pill"><?= $upcomingCount ?> Event Tersedia</div>
        <div class="pill">Riwayat & Badge</div>
    </div>
</section>

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

<?php if ($is_admin): ?>
<section class="card">
    <div class="summary-title">Buat Event</div>

    <form method="POST">
        <input type="hidden" name="action" value="create_event">

        <div class="input-group">
            <label>Judul Event</label>
            <input type="text" name="title" placeholder="Contoh: Fun Run 5K" required>
        </div>

        <div class="input-group">
            <label>Deskripsi Event</label>
            <textarea name="description" rows="3" placeholder="Ringkasan kegiatan" required></textarea>
        </div>

        <div class="input-group">
            <label>Tipe Event</label>
            <input type="text" name="event_type" placeholder="Contoh: Lari, Basket, Yoga" required>
        </div>

        <div class="input-group">
            <label>Tanggal Mulai</label>
            <input type="date" name="start_date" required>
        </div>

        <div class="input-group">
            <label>Tanggal Selesai</label>
            <input type="date" name="end_date" required>
        </div>

        <div class="input-group">
            <label>Poin Reward</label>
            <input type="number" name="reward_points" min="0" value="0" required>
        </div>

        <div class="input-group">
            <label>
                <input type="checkbox" name="is_active" checked> Aktif
            </label>
        </div>

        <button class="btn-primary" type="submit">Simpan Event</button>
    </form>
</section>
<?php endif; ?>

<section class="card">
    <div class="tab-row">
        <a class="<?= $tab === 'upcoming' ? 'active' : '' ?>" href="sportevent.php?tab=upcoming">Event Mendatang</a>
        <a class="<?= $tab === 'finished' ? 'active' : '' ?>" href="sportevent.php?tab=finished">Event Selesai</a>
    </div>

    <?php if (!$events): ?>
        <div class="event-desc">Belum ada event untuk kategori ini.</div>
    <?php endif; ?>

    <?php foreach ($events as $event): ?>
        <div class="event-card">
            <div class="event-chip"><?= htmlspecialchars($event['event_type']) ?></div>
            <div class="event-detail">
                <div class="event-title"><?= htmlspecialchars($event['title']) ?></div>
                <div class="event-desc"><?= htmlspecialchars($event['description']) ?></div>
                <div class="event-actions">
                    <a class="btn-detail" href="sportevent_detail.php?id=<?= (int) $event['id_events'] ?>">Details</a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</section>

</main>

<?php include 'includes/footer.php'; ?>
