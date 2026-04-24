<?php
session_start();
require_once 'includes/db.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$pageTitle = 'Gym Booking';
$bodyClass = 'gym-booking-page';
include 'includes/header.php';

try {
    $pdo->query("ALTER TABLE gym_equipments ADD COLUMN description varchar(255) DEFAULT NULL");
} catch (PDOException $e) {
}

try {
    $pdo->query("ALTER TABLE gym_equipments ADD COLUMN image_path varchar(255) DEFAULT NULL");
} catch (PDOException $e) {
}

$countStmt = $pdo->query("SELECT COUNT(*) FROM gym_equipments");
$totalEquip = (int) $countStmt->fetchColumn();
if ($totalEquip === 0) {
    $pdo->exec("INSERT INTO gym_equipments (id_gyms, equipment_name, quantity, description, image_path) VALUES
        (NULL,'Barang 01',5,'Detail singkat barang 01',NULL),
        (NULL,'Barang 02',3,'Detail singkat barang 02',NULL),
        (NULL,'Barang 03',2,'Detail singkat barang 03',NULL)");
}

$stmt = $pdo->prepare("
    SELECT ge.*, g.name AS gym_name
    FROM gym_equipments ge
    LEFT JOIN gyms g ON ge.id_gyms = g.id_gyms
    WHERE g.is_active = 1 OR g.is_active IS NULL
");
$stmt->execute();
$equipments = $stmt->fetchAll();
?>

<style>
.gym-booking-page {
    background:
        radial-gradient(circle at top left, rgba(45, 212, 191, 0.16), transparent 22%),
        linear-gradient(180deg, #f7fbfb 0%, #eef8f6 56%, #f8fbff 100%);
}
.booking-app { width: min(980px, 92%); margin: 18px auto 0; padding-bottom: 42px; }
.booking-hero {
    background:
        radial-gradient(circle at top right, rgba(255,255,255,0.15), transparent 24%),
        linear-gradient(145deg, #14897f, #0f766e 60%, #155e75 100%);
    color:#fff; border-radius:26px; padding:26px 24px; box-shadow:0 18px 38px rgba(15,118,110,.2);
}
.booking-hero h2 { margin:0 0 8px; font-size: clamp(26px, 4vw, 34px); }
.booking-hero p { margin:0; max-width:660px; color: rgba(255,255,255,.84); line-height:1.55; }
.booking-pills { margin-top:16px; display:flex; flex-wrap:wrap; gap:10px; }
.booking-pill { padding:9px 12px; border-radius:999px; font-size:12px; font-weight:700; background:rgba(255,255,255,.14); border:1px solid rgba(255,255,255,.22); }
.booking-tabs { margin:16px 0; display:flex; gap:10px; flex-wrap:wrap; }
.booking-tab {
    text-decoration:none; padding:11px 16px; border-radius:999px; font-size:13px; font-weight:700;
    background:linear-gradient(180deg, #ffffff 0%, #f9fbfd 100%);
    color:#415466; border:1px solid #cfddea; box-shadow: 0 8px 18px rgba(15,23,42,.04);
    flex: 1 1 0;
    min-width: 0;
    text-align: center;
    line-height: 1.3;
}
.booking-tab.active {
    color:#0f766e;
    background:linear-gradient(145deg, #ecfeff, #f0fdfa);
    border-color:#76e4dc;
    box-shadow: 0 10px 22px rgba(15,118,110,.08);
}
.equipment-grid { display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap:14px; }
.equipment-card {
    background: rgba(255,255,255,.94); border:1px solid rgba(226,232,240,.92); border-radius:22px; padding:16px;
    box-shadow:0 14px 26px rgba(15,23,42,.06); display:flex; flex-direction:column; justify-content:space-between; min-height:210px;
}
.equipment-main { display:flex; align-items:flex-start; gap:14px; }
.equipment-thumb {
    width:64px; height:64px; background: linear-gradient(145deg, #d7f4f1, #dbeafe); border-radius:18px; object-fit:cover; flex-shrink:0;
}
.equipment-name { font-weight:700; font-size:17px; color:#0f172a; }
.equipment-detail { margin-top:6px; font-size:13px; color:#64748b; line-height:1.5; }
.equipment-meta { margin-top:10px; display:flex; gap:8px; flex-wrap:wrap; }
.equipment-badge { padding:7px 10px; border-radius:999px; font-size:11px; font-weight:700; background:#ecfeff; color:#0f766e; }
.equipment-badge.out { background:#fef2f2; color:#b91c1c; }
.equipment-footer { margin-top:14px; display:flex; align-items:center; justify-content:space-between; gap:12px; }
.equipment-gym { font-size:12px; color:#475569; }
.btn-add {
    display:inline-flex; align-items:center; justify-content:center; padding:10px 16px; border-radius:999px; text-decoration:none;
    background:linear-gradient(135deg,#14b8a6,#22c55e); color:#fff; font-weight:700; min-width:110px;
}
.btn-add.is-disabled { background:#cbd5e1; color:#64748b; pointer-events:none; }
.empty-card { background:#fff; border-radius:18px; padding:18px; color:#64748b; }
@media (max-width: 720px) {
    .equipment-grid { grid-template-columns: 1fr; }
    .booking-tabs { gap: 6px; }
    .booking-tab {
        padding: 9px 8px;
        font-size: 11px;
        box-shadow: 0 6px 14px rgba(15,23,42,.04);
    }
}
</style>

<main class="booking-app">
    <section class="booking-hero">
        <h2>Available & Pesan Alat</h2>
        <p>Pilih alat yang masih tersedia, cek detail singkatnya, lalu lanjut booking dengan alur yang lebih jelas.</p>
        <div class="booking-pills">
            <span class="booking-pill"><?= count($equipments) ?> alat terdata</span>
            <span class="booking-pill">Booking cepat</span>
            <span class="booking-pill">Status stok langsung terlihat</span>
        </div>
    </section>

    <div class="booking-tabs">
        <span class="booking-tab active">Available & Pesan Alat</span>
        <a class="booking-tab" href="workout.php">Workout Recommend</a>
    </div>

    <?php if (empty($equipments)): ?>
        <section class="empty-card">Tidak ada alat tersedia.</section>
    <?php else: ?>
        <section class="equipment-grid">
            <?php foreach ($equipments as $eq): ?>
                <?php $isAvailable = (int) $eq['quantity'] > 0; ?>
                <article class="equipment-card">
                    <div class="equipment-main">
                        <?php if (!empty($eq['image_path'])): ?>
                            <img class="equipment-thumb" src="<?= htmlspecialchars($eq['image_path']) ?>" alt="<?= htmlspecialchars($eq['equipment_name']) ?>">
                        <?php else: ?>
                            <div class="equipment-thumb"></div>
                        <?php endif; ?>
                        <div>
                            <div class="equipment-name"><?= htmlspecialchars($eq['equipment_name']) ?></div>
                            <div class="equipment-detail"><?= htmlspecialchars($eq['description'] ?? ('Detail singkat ' . $eq['equipment_name'])) ?></div>
                            <div class="equipment-meta">
                                <span class="equipment-badge <?= $isAvailable ? '' : 'out' ?>"><?= $isAvailable ? 'Tersedia' : 'Habis' ?></span>
                                <span class="equipment-badge">Sisa <?= (int) $eq['quantity'] ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="equipment-footer">
                        <span class="equipment-gym"><?= htmlspecialchars($eq['gym_name'] ?: 'Gym partner FiVit') ?></span>
                        <?php if ($isAvailable): ?>
                            <a href="gym_booking_detail.php?id=<?= (int) $eq['id_gym_equipments'] ?>" class="btn-add">Pesan alat</a>
                        <?php else: ?>
                            <span class="btn-add is-disabled">Stok habis</span>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
</main>

<?php include 'includes/footer.php'; ?>
