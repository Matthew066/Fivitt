<?php
session_start();
require_once 'includes/db.php';

$pageTitle = 'Gym Booking';
include 'includes/header.php';

// ensure description column exists (migration)
try {
    $pdo->query("ALTER TABLE gym_equipments ADD COLUMN description varchar(255) DEFAULT NULL");
} catch (PDOException $e) {
    // ignore if exists
}

try {
    $pdo->query("ALTER TABLE gym_equipments ADD COLUMN image_path varchar(255) DEFAULT NULL");
} catch (PDOException $e) {
    // ignore if exists
}

// auto seed sample data if empty
$countStmt = $pdo->query("SELECT COUNT(*) FROM gym_equipments");
$totalEquip = (int) $countStmt->fetchColumn();
if ($totalEquip === 0) {
    $pdo->exec("INSERT INTO gym_equipments (gym_id, equipment_name, quantity, description, image_path) VALUES
        (NULL,'Barang 01',5,'Detail singkat barang 01',NULL),
        (NULL,'Barang 02',3,'Detail singkat barang 02',NULL),
        (NULL,'Barang 03',2,'Detail singkat barang 03',NULL)");
}

$stmt = $pdo->prepare("\n    SELECT ge.*, g.name AS gym_name\n    FROM gym_equipments ge\n    LEFT JOIN gyms g ON ge.gym_id = g.id_gyms\n    WHERE g.is_active = 1 OR g.is_active IS NULL\n");
$stmt->execute();
$equipments = $stmt->fetchAll();
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
    text-align: center;
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

.equipment-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.equipment-main {
    display: flex;
    align-items: center;
}
.equipment-thumb {
    width: 50px;
    height: 50px;
    background: #3babab;
    border-radius: 12px;
    margin-right: 12px;
    object-fit: cover;
}

.equipment-name {
    font-weight: 700;
    font-size: 16px;
}

.equipment-detail {
    font-size: 12px;
    color: #6b7a88;
}

.btn-add {
    display: inline-block;
    background: #3ad26f;
    color: #ffffff;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    text-align: center;
    line-height: 30px;
    font-weight: 700;
    text-decoration: none;
}

.tab-row {
    display: flex;
    gap: 16px;
    margin: 8px 4px 12px;
    font-weight: 600;
    font-size: 13px;
}

.tab-row a, .tab-row span {
    text-decoration: none;
    color: #7b8a9a;
    padding-bottom: 6px;
}

.tab-row .active {
    color: #1a2e3a;
    border-bottom: 2px solid #2ec4cc;
}
</style>

<main class="app">
    <section class="card hero">
        <h2>Gym Booking!</h2>
        <p>Silahkan memilih alat gym</p>
    </section>
    <div class="tab-row">
        <span class="active">Available & Pesan alat</span>
        <a href="workout.php">Workout Recommend</a>
    </div>

    <?php if (empty($equipments)): ?>
        <section class="card">
            Tidak ada alat tersedia.
        </section>
    <?php else: ?>

        <?php foreach ($equipments as $index => $eq): ?>
            <section class="card equipment-card">
                <div class="equipment-main">
                    <?php if (!empty($eq['image_path'])): ?>
                        <img class="equipment-thumb" src="<?= htmlspecialchars($eq['image_path']) ?>" alt="<?= htmlspecialchars($eq['equipment_name']) ?>">
                    <?php else: ?>
                        <div class="equipment-thumb"></div>
                    <?php endif; ?>
                    <div>
                        <div class="equipment-name"><?= htmlspecialchars($eq['equipment_name']) ?></div>
                        <div class="equipment-detail"><?= htmlspecialchars($eq['description'] ?? 'Detail singkat ' . $eq['equipment_name']) ?></div>
                    </div>
                </div>
                <?php if ((int)$eq['quantity'] > 0): ?>
                    <a href="gym_booking_detail.php?id=<?= (int) $eq['id_gym_equipments'] ?>" class="btn-add">+</a>
                <?php else: ?>
                    <span style="opacity:0.4;">-</span>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>
</main>

<?php include 'includes/footer.php'; ?>
