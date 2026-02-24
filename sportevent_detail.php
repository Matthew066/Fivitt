<?php
session_start();
require_once 'includes/db.php';

$pageTitle = 'Detail Event';
$bodyClass = 'sportevent-detail-page';
require 'includes/header.php';

$user_id = $_SESSION['user_id'] ?? 1;
$today = date('Y-m-d');
$event_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
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

$event = null;
if ($event_id > 0) {
    $stmt = $pdo->prepare("
        SELECT *
        FROM events
        WHERE id_events = ?
        LIMIT 1
    ");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();
}
?>

<main class="app">

<?php if (!$event): ?>
    <section class="card">
        <div class="detail-item">Event tidak ditemukan.</div>
    </section>
<?php else: ?>
    <section class="card hero">
        <h2><?= htmlspecialchars($event['title']) ?></h2>
        <p><?= htmlspecialchars($event['description']) ?></p>
    </section>

    <?php if ($success): ?>
        <section class="card">
            <div class="message">
                <?= htmlspecialchars($success) ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="card detail-card">
        <div class="detail-title">Deskripsi Event</div>
        <div class="detail-item"><?= htmlspecialchars($event['description']) ?></div>

        <div class="detail-title">Waktu dan Tanggal</div>
        <div class="detail-item">Tanggal: <?= htmlspecialchars(formatIndoDate($event['start_date'])) ?> - <?= htmlspecialchars(formatIndoDate($event['end_date'])) ?></div>
        <div class="detail-item">Tipe: <?= htmlspecialchars($event['event_type']) ?></div>
        <div class="detail-item">Poin Reward: <?= (int) $event['reward_points'] ?></div>
    </section>

    <section class="card">
        <form method="POST">
            <input type="hidden" name="action" value="join_event">
            <input type="hidden" name="event_id" value="<?= (int) $event['id_events'] ?>">
            <button class="btn-primary" type="submit">Daftar Event</button>
        </form>
    </section>
<?php endif; ?>

</main>

<?php include 'includes/footer.php'; ?>


