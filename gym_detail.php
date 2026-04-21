<?php
session_start();
require_once 'includes/db.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

try {
    $pdo->query("ALTER TABLE gym_bookings ADD COLUMN equipment_id bigint(20) NULL");
} catch (PDOException $e) {
}

$pageTitle = 'Detail Gym';
$bodyClass = 'gym-booking-page';
include 'includes/header.php';

$user_id = $_SESSION['user_id'] ?? 0;
$equipment_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$success = '';
$error = '';
$date = '';
$start_time = '';
$end_time = '';

$equipment = null;
if ($equipment_id > 0) {
    $stmt = $pdo->prepare("
        SELECT ge.*, g.name AS gym_name
        FROM gym_equipments ge
        LEFT JOIN gyms g ON ge.id_gyms = g.id_gyms
        WHERE ge.id_gym_equipments = ?
        LIMIT 1
    ");
    $stmt->execute([$equipment_id]);
    $equipment = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'book_equipment') {
    $date = $_POST['booking_date'] ?? '';
    $start_time = $_POST['start_time'] ?? '';
    $end_time = $_POST['end_time'] ?? '';

    if (!$equipment) {
        $error = 'Alat tidak ditemukan.';
    } elseif ((int) ($equipment['quantity'] ?? 0) <= 0) {
        $error = 'Alat ini sedang tidak tersedia.';
    } elseif ($date === '' || $start_time === '' || $end_time === '') {
        $error = 'Semua field waktu harus diisi.';
    } else {
        $gym_id = $equipment['id_gyms'];
        $timeslot = $start_time . '-' . $end_time;
        $stmt = $pdo->prepare("INSERT INTO gym_bookings (id_users, id_gyms, equipment_id, booking_date, time_slot, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $gym_id, $equipment_id, $date, $timeslot, 'confirmed']);
        $upd = $pdo->prepare("UPDATE gym_equipments SET quantity = quantity - 1 WHERE id_gym_equipments = ? AND quantity > 0");
        $upd->execute([$equipment_id]);
        $success = 'Pemesanan sudah terkonfirmasi.';
    }
}
?>

<style>
.gym-booking-page {
    background:
        radial-gradient(circle at top left, rgba(45, 212, 191, 0.16), transparent 22%),
        linear-gradient(180deg, #f7fbfb 0%, #eef8f6 56%, #f8fbff 100%);
}
.detail-app { width:min(820px, 92%); margin:18px auto 0; padding-bottom:42px; }
.detail-hero {
    background:
        radial-gradient(circle at top right, rgba(255,255,255,.15), transparent 24%),
        linear-gradient(145deg, #14897f, #0f766e 60%, #155e75 100%);
    color:#fff; border-radius:26px; padding:24px; box-shadow:0 18px 38px rgba(15,118,110,.2);
}
.detail-hero h2 { margin:0 0 8px; font-size:clamp(26px, 4vw, 34px); }
.detail-hero p { margin:0; color:rgba(255,255,255,.84); }
.detail-status { margin-top:14px; display:inline-flex; padding:8px 12px; border-radius:999px; font-size:12px; font-weight:700; background:rgba(255,255,255,.16); }
.detail-grid { margin-top:16px; display:grid; grid-template-columns: 1fr 1fr; gap:16px; }
.detail-card {
    background:#fff; border:1px solid rgba(226,232,240,.92); border-radius:22px; padding:18px; box-shadow:0 14px 26px rgba(15,23,42,.06);
}
.detail-title { font-weight:700; margin-bottom:10px; color:#0f172a; font-size:18px; }
.detail-item { font-size:13px; color:#64748b; line-height:1.6; margin-bottom:8px; }
.detail-strong { color:#0f172a; font-weight:700; }
.message {
    padding:12px 14px; border-radius:14px; font-size:13px; margin-bottom:14px;
}
.message.success { background:#ecfdf5; border:1px solid #bbf7d0; color:#166534; }
.message.error { background:#fef2f2; border:1px solid #fecaca; color:#991b1b; }
.form-group { margin-bottom:12px; }
.form-group label { display:block; font-size:13px; font-weight:700; color:#0f766e; margin-bottom:6px; }
.form-group input {
    width:100%; min-height:46px; border:1px solid #d7e3ef; border-radius:14px; padding:10px 12px; font-size:13px; background:linear-gradient(180deg,#fff,#f7fbff);
}
.btn-primary {
    width:100%; min-height:48px; border:none; border-radius:999px; background:linear-gradient(135deg,#14b8a6,#22c55e);
    color:#fff; font-weight:700; cursor:pointer;
}
@media (max-width: 760px) {
    .detail-grid { grid-template-columns: 1fr; }
}
</style>

<main class="detail-app">
    <?php if (!$equipment): ?>
        <section class="detail-card">
            <div class="detail-item">Alat tidak ditemukan.</div>
        </section>
    <?php else: ?>
        <section class="detail-hero">
            <h2><?= htmlspecialchars($equipment['equipment_name']) ?></h2>
            <p><?= htmlspecialchars($equipment['description'] ?? 'Alat ini siap dipakai untuk latihan kamu.') ?></p>
            <span class="detail-status"><?= (int) $equipment['quantity'] > 0 ? 'Available sekarang' : 'Sedang habis' ?></span>
        </section>

        <section class="detail-grid">
            <article class="detail-card">
                <div class="detail-title">Detail Alat</div>
                <div class="detail-item"><span class="detail-strong">Gym:</span> <?= htmlspecialchars($equipment['gym_name'] ?: 'Gym partner FiVit') ?></div>
                <div class="detail-item"><span class="detail-strong">Stok tersisa:</span> <?= (int) $equipment['quantity'] ?></div>
                <div class="detail-item"><span class="detail-strong">Deskripsi:</span> <?= htmlspecialchars($equipment['description'] ?? 'Belum ada deskripsi tambahan.') ?></div>
            </article>

            <article class="detail-card">
                <div class="detail-title">Pesan Alat</div>
                <?php if ($success): ?>
                    <div class="message success"><?= htmlspecialchars($success) ?></div>
                    <div class="detail-item"><span class="detail-strong">Tanggal:</span> <?= htmlspecialchars($date) ?></div>
                    <div class="detail-item"><span class="detail-strong">Jam:</span> <?= htmlspecialchars($start_time) ?> - <?= htmlspecialchars($end_time) ?></div>
                <?php else: ?>
                    <?php if ($error): ?>
                        <div class="message error"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="book_equipment">
                        <div class="form-group">
                            <label for="booking_date">Tanggal</label>
                            <input type="date" id="booking_date" name="booking_date" required value="<?= htmlspecialchars($date) ?>">
                        </div>
                        <div class="form-group">
                            <label for="start_time">Jam Mulai</label>
                            <input type="time" id="start_time" name="start_time" required value="<?= htmlspecialchars($start_time) ?>">
                        </div>
                        <div class="form-group">
                            <label for="end_time">Jam Selesai</label>
                            <input type="time" id="end_time" name="end_time" required value="<?= htmlspecialchars($end_time) ?>">
                        </div>
                        <button class="btn-primary" type="submit">Konfirmasi Pemesanan</button>
                    </form>
                <?php endif; ?>
            </article>
        </section>
    <?php endif; ?>
</main>

<?php include 'includes/footer.php'; ?>
