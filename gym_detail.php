<?php
session_start();
require_once 'includes/db.php';

// ensure equipment_id column exists in gym_bookings table
try {
    $pdo->query("ALTER TABLE gym_bookings ADD COLUMN equipment_id bigint(20) NULL");
} catch (PDOException $e) {
    // column probably already exists, ignore
}


$pageTitle = 'Detail Gym';
include 'includes/header.php';

$user_id = $_SESSION['user_id'] ?? 0;
$equipment_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$success = '';
$error = '';
$date = '';
$start_time = '';
$end_time = '';

equipment:
$equipment = null;
if ($equipment_id > 0) {
    $stmt = $pdo->prepare("\n        SELECT ge.*, g.name AS gym_name\n        FROM gym_equipments ge\n        LEFT JOIN gyms g ON ge.gym_id = g.id_gyms\n        WHERE ge.id_gym_equipments = ?\n        LIMIT 1\n    ");
    $stmt->execute([$equipment_id]);
    $equipment = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'book_equipment') {
    $date = $_POST['booking_date'] ?? '';
    $start_time = $_POST['start_time'] ?? '';
    $end_time = $_POST['end_time'] ?? '';

    if (!$equipment) {
        $error = 'Alat tidak ditemukan.';
    } elseif ($date === '' || $start_time === '' || $end_time === '') {
        $error = 'Semua field waktu harus diisi.';
    } else {
        $gym_id = $equipment['gym_id'];
        $timeslot = $start_time . '-' . $end_time;
        // include equipment_id in booking data
        $stmt = $pdo->prepare("INSERT INTO gym_bookings (user_id, gym_id, equipment_id, booking_date, time_slot, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $gym_id, $equipment_id, $date, $timeslot, 'confirmed']);
        // decrement available quantity for equipment
        $upd = $pdo->prepare("UPDATE gym_equipments SET quantity = quantity - 1 WHERE id_gym_equipments = ? AND quantity > 0");
        $upd->execute([$equipment_id]);
        $success = 'Pemesanan sudah terkonfirmasi.';
    }
}
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

.message {
    background: #e8fff1;
    border: 1px solid #b7f1cd;
    color: #1b6c3d;
    padding: 10px 12px;
    border-radius: 12px;
    font-size: 12px;
    margin-bottom: 12px;
}

.form-group {
    margin-bottom: 10px;
}

.form-group label {
    display: block;
    font-size: 13px;
    margin-bottom: 4px;
}
</style>

<main class="app">
    <?php if (!$equipment): ?>
        <section class="card">
            <div class="detail-item">Alat tidak ditemukan.</div>
        </section>
    <?php else: ?>

        <section class="card hero">
            <h2><?= htmlspecialchars($equipment['equipment_name']) ?></h2>
            <p>Status: <?= ($equipment['quantity'] > 0 ? 'ada' : 'tidak') ?></p>
        </section>

        <section class="card detail-card">
            <div class="detail-title">Deskripsi alat</div>
            <div class="detail-item"><?= htmlspecialchars($equipment['description'] ?? 'Lorem Ipsum Dolor Sim Amet') ?></div>
        </section>

        <?php if ($success): ?>
            <section class="card">
                <div class="message"><?= htmlspecialchars($success) ?></div>
            </section>
            <section class="card detail-card">
                <div class="detail-title">Waktu dan Tanggal</div>
                <div class="detail-item">Tanggal: <?= htmlspecialchars($date) ?></div>
                <div class="detail-item">Jam: <?= htmlspecialchars($start_time) ?> - <?= htmlspecialchars($end_time) ?></div>
                <div class="detail-item">Gym: <?= htmlspecialchars($equipment['gym_name']) ?></div>
            </section>
        <?php endif; ?>

        <?php if (!$success): ?>
            <?php if ($error): ?>
                <section class="card">
                    <div class="message" style="background: #ffe6e6; border-color: #f1b7b7; color: #6c1b1b;">
                        <?= htmlspecialchars($error) ?>
                    </div>
                </section>
            <?php endif; ?>
            <section class="card">
                <form method="POST">
                    <input type="hidden" name="action" value="book_equipment">

                    <div class="form-group">
                        <label for="booking_date">Tanggal</label>
                        <input type="date" id="booking_date" name="booking_date" required
                               value="<?= htmlspecialchars($date) ?>">
                    </div>

                    <div class="form-group">
                        <label>Jam Mulai</label>
                        <input type="time" name="start_time" required
                               value="<?= htmlspecialchars($start_time) ?>">
                    </div>

                    <div class="form-group">
                        <label>Jam Selesai</label>
                        <input type="time" name="end_time" required
                               value="<?= htmlspecialchars($end_time) ?>">
                    </div>

                    <button class="btn-primary" type="submit">Pesan sekarang!</button>
                </form>
            </section>
        <?php endif; ?>

    <?php endif; ?>
</main>

<?php include 'includes/footer.php'; ?>
