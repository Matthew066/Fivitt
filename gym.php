<?php
session_start();
require_once 'includes/db.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$pageTitle = 'Gym Network';
include 'includes/header.php';

$userId = (int) ($_SESSION['user_id'] ?? 0);

$pdo->exec("
    CREATE TABLE IF NOT EXISTS gym_membership_plans (
        id_gym_membership_plans INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        plan_name VARCHAR(100) NOT NULL,
        package_label VARCHAR(80) NOT NULL DEFAULT '',
        price_text VARCHAR(80) NOT NULL,
        billing_cycle_text VARCHAR(80) NOT NULL DEFAULT '',
        visit_quota_text VARCHAR(80) NOT NULL DEFAULT '',
        audience_text VARCHAR(120) NOT NULL DEFAULT '',
        description_text VARCHAR(255) NOT NULL,
        features_text TEXT NOT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS gym_partner_gyms (
        id_gym_partner_gyms INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        gym_name VARCHAR(120) NOT NULL,
        address_text VARCHAR(255) NOT NULL,
        distance_text VARCHAR(32) NOT NULL,
        rating DECIMAL(2,1) NOT NULL DEFAULT 0.0,
        day_pass_price_text VARCHAR(80) NOT NULL,
        member_rate_price_text VARCHAR(80) NOT NULL,
        tags_text TEXT NOT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

function columnExists(PDO $pdo, string $tableName, string $columnName): bool
{
    static $dbName = null;
    if ($dbName === null) {
        $dbName = (string) $pdo->query("SELECT DATABASE()")->fetchColumn();
    }
    if ($dbName === '') return false;

    $stmt = $pdo->prepare("
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = ?
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
        LIMIT 1
    ");
    $stmt->execute([$dbName, $tableName, $columnName]);
    return (bool) $stmt->fetchColumn();
}

function addColumnIfMissing(PDO $pdo, string $tableName, string $columnName, string $ddl): void
{
    if (!columnExists($pdo, $tableName, $columnName)) {
        $pdo->exec("ALTER TABLE {$tableName} ADD COLUMN {$columnName} {$ddl}");
    }
}

addColumnIfMissing($pdo, 'gym_partner_gyms', 'address_text', 'VARCHAR(255) NULL');
addColumnIfMissing($pdo, 'gym_partner_gyms', 'latitude', 'DECIMAL(10,7) NULL');
addColumnIfMissing($pdo, 'gym_partner_gyms', 'longitude', 'DECIMAL(10,7) NULL');
addColumnIfMissing($pdo, 'gym_membership_plans', 'package_label', "VARCHAR(80) NOT NULL DEFAULT ''");
addColumnIfMissing($pdo, 'gym_membership_plans', 'billing_cycle_text', "VARCHAR(80) NOT NULL DEFAULT ''");
addColumnIfMissing($pdo, 'gym_membership_plans', 'visit_quota_text', "VARCHAR(80) NOT NULL DEFAULT ''");
addColumnIfMissing($pdo, 'gym_membership_plans', 'audience_text', "VARCHAR(120) NOT NULL DEFAULT ''");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS gym_map_settings (
        id_gym_map_settings INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        map_title VARCHAR(120) NOT NULL DEFAULT 'Peta Partner Gym',
        map_description VARCHAR(255) NOT NULL DEFAULT '',
        map_embed_url TEXT NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS gym_partnership_requests (
        id_gym_partnership_requests INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        company_name VARCHAR(150) NOT NULL,
        contact_name VARCHAR(120) NOT NULL,
        email VARCHAR(150) NOT NULL,
        phone VARCHAR(40) NOT NULL,
        employee_count INT UNSIGNED NOT NULL DEFAULT 0,
        city VARCHAR(120) NOT NULL,
        notes TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$partnershipErrors = [];
$partnershipSuccess = '';
$showPartnershipForm = isset($_GET['show_partnership']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_partnership') {
    $showPartnershipForm = true;

    $companyName = trim((string) ($_POST['company_name'] ?? ''));
    $contactName = trim((string) ($_POST['contact_name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $employeeCount = (int) ($_POST['employee_count'] ?? 0);
    $city = trim((string) ($_POST['city'] ?? ''));
    $notes = trim((string) ($_POST['notes'] ?? ''));

    if ($companyName === '') { $partnershipErrors[] = 'Nama perusahaan wajib diisi.'; }
    if ($contactName === '') { $partnershipErrors[] = 'Nama PIC wajib diisi.'; }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $partnershipErrors[] = 'Email belum valid.'; }
    if ($phone === '') { $partnershipErrors[] = 'Nomor telepon wajib diisi.'; }
    if ($employeeCount <= 0) { $partnershipErrors[] = 'Jumlah karyawan harus lebih dari 0.'; }
    if ($city === '') { $partnershipErrors[] = 'Kota wajib diisi.'; }

    if (!$partnershipErrors) {
        $insertRequest = $pdo->prepare("
            INSERT INTO gym_partnership_requests
            (company_name, contact_name, email, phone, employee_count, city, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $insertRequest->execute([
            $companyName, $contactName, $email, $phone, $employeeCount, $city, $notes
        ]);
        $partnershipSuccess = 'Pengajuan partnership berhasil dikirim. Tim FiVit akan menghubungi kamu.';
    }
}

$membershipRows = $pdo->query("
    SELECT
        id_gym_membership_plans,
        plan_name,
        package_label,
        price_text,
        billing_cycle_text,
        visit_quota_text,
        audience_text,
        description_text,
        features_text
    FROM gym_membership_plans
    WHERE is_active = 1
    ORDER BY sort_order ASC, id_gym_membership_plans ASC
")->fetchAll(PDO::FETCH_ASSOC);

$memberships = array_map(static function (array $row): array {
    $features = preg_split('/\r\n|\r|\n/', (string) ($row['features_text'] ?? '')) ?: [];
    $features = array_values(array_filter(array_map('trim', $features), static fn($item) => $item !== ''));
    return [
        'id' => (int) ($row['id_gym_membership_plans'] ?? 0),
        'name' => $row['plan_name'] ?? '',
        'package_label' => $row['package_label'] ?? '',
        'price' => $row['price_text'] ?? '',
        'billing_cycle' => $row['billing_cycle_text'] ?? '',
        'visit_quota' => $row['visit_quota_text'] ?? '',
        'audience' => $row['audience_text'] ?? '',
        'description' => $row['description_text'] ?? '',
        'features' => $features,
    ];
}, $membershipRows);

$activeMembershipStmt = $pdo->prepare("
    SELECT
        um.id_user_memberships,
        um.id_gym_membership_plans,
        um.start_date,
        um.end_date,
        um.status,
        gmp.plan_name
    FROM user_memberships um
    INNER JOIN gym_membership_plans gmp
        ON gmp.id_gym_membership_plans = um.id_gym_membership_plans
    WHERE um.id_users = ?
      AND um.status = 'active'
      AND (um.end_date IS NULL OR um.end_date >= CURDATE())
    ORDER BY um.end_date DESC, um.id_user_memberships DESC
    LIMIT 1
");
$activeMembershipStmt->execute([$userId]);
$activeMembership = $activeMembershipStmt->fetch(PDO::FETCH_ASSOC) ?: null;

$partnerRows = $pdo->query("
    SELECT gym_name, address_text, latitude, longitude, rating, day_pass_price_text, member_rate_price_text, tags_text
    FROM gym_partner_gyms
    WHERE is_active = 1
    ORDER BY sort_order ASC, id_gym_partner_gyms ASC
")->fetchAll(PDO::FETCH_ASSOC);

$partnerGyms = array_map(static function (array $row): array {
    $tags = array_filter(array_map('trim', explode(',', (string) ($row['tags_text'] ?? ''))), static fn($item) => $item !== '');
    return [
        'name' => $row['gym_name'] ?? '',
        'address' => $row['address_text'] ?? '',
        'lat' => $row['latitude'] ?? null,
        'lng' => $row['longitude'] ?? null,
        'rating' => number_format((float) ($row['rating'] ?? 0), 1),
        'day_pass' => $row['day_pass_price_text'] ?? '',
        'member_rate' => $row['member_rate_price_text'] ?? '',
        'tags' => array_values($tags),
    ];
}, $partnerRows);

$partnerMapPoints = array_values(array_filter(array_map(static function (array $gym): ?array {
    if (!is_numeric($gym['lat']) || !is_numeric($gym['lng'])) {
        return null;
    }

    return [
        'name' => (string) ($gym['name'] ?? ''),
        'address' => (string) ($gym['address'] ?? ''),
        'lat' => (float) $gym['lat'],
        'lng' => (float) $gym['lng'],
        'day_pass' => (string) ($gym['day_pass'] ?? ''),
        'member_rate' => (string) ($gym['member_rate'] ?? ''),
    ];
}, $partnerGyms)));

$mapRow = $pdo->query("
    SELECT map_title, map_description, map_embed_url
    FROM gym_map_settings
    WHERE is_active = 1
    ORDER BY id_gym_map_settings DESC
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC) ?: [];

$mapTitle = trim((string) ($mapRow['map_title'] ?? 'Peta Partner Gym'));
$mapDescription = trim((string) ($mapRow['map_description'] ?? 'Sebaran partner gym dari data admin.'));
$mapEmbedUrl = trim((string) ($mapRow['map_embed_url'] ?? ''));
$partnerMapPointsJson = json_encode($partnerMapPoints, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>

<link
    rel="stylesheet"
    href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
    crossorigin=""
>
<script
    src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
    integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
    crossorigin=""
></script>

<style>
.gym-app { width: min(980px, 92%); margin: 18px auto 0; padding-bottom: 90px; }
.gym-hero {
    background: linear-gradient(140deg, #0f766e 0%, #22c55e 55%, #7dd3fc 100%);
    color: #fff; border-radius: 24px; padding: 26px 22px; box-shadow: 0 16px 32px rgba(15,118,110,.28);
}
.gym-hero h1 { margin: 0 0 8px; font-size: 28px; }
.gym-hero p { margin: 0; font-size: 14px; max-width: 680px; opacity: .95; }
.pill-row { margin-top: 14px; display: flex; flex-wrap: wrap; gap: 8px; }
.pill { border-radius: 999px; padding: 8px 12px; font-size: 12px; font-weight: 600; background: rgba(255,255,255,.2); border: 1px solid rgba(255,255,255,.26); }
.gym-section { margin-top: 16px; background: rgba(255,255,255,.9); border: 1px solid rgba(125,211,252,.3); border-radius: 20px; padding: 18px; box-shadow: 0 8px 22px rgba(15,23,42,.07); }
.gym-title { margin: 0 0 8px; font-size: 20px; color: #134e4a; }
.gym-sub { margin: 0 0 14px; color: #4b5563; font-size: 13px; }
.grid-3 { display: grid; grid-template-columns: repeat(3,minmax(0,1fr)); gap: 14px; }
.grid-2 { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 14px; }
.mini-card { background:#fff; border:1px solid #dff3ff; border-radius:16px; padding:14px; }
.plan-name { margin:0; font-size:17px; color:#0f172a; }
.plan-price { margin:7px 0 8px; color:#047857; font-size:14px; font-weight:700; }
.plan-desc { margin:0 0 9px; color:#4b5563; font-size:13px; }
.feature-list { margin:0; padding-left:18px; font-size:13px; }
.plan-action-group { margin-top:14px; display:flex; flex-direction:column; gap:10px; }
.plan-action-btn {
    display:inline-flex;
    align-items:center;
    justify-content:center;
    width:100%;
    min-height:42px;
    padding:10px 14px;
    border-radius:999px;
    text-decoration:none;
    font-size:13px;
    font-weight:700;
    color:#fff;
    background:linear-gradient(135deg,#0f766e,#22c55e);
    border:none;
}
.plan-action-btn.is-secondary {
    color:#0f766e;
    background:#f0fdfa;
    border:1px solid #99f6e4;
}
.plan-action-btn.is-active {
    color:#166534;
    background:#ecfdf5;
    border:1px solid #86efac;
}
.basic-feature-links { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:8px; }
.basic-feature-link {
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-height:38px;
    padding:9px 10px;
    border-radius:12px;
    text-decoration:none;
    font-size:12px;
    font-weight:700;
    color:#134e4a;
    background:#ecfeff;
    border:1px solid #bae6fd;
    text-align:center;
}
.gym-head { display:flex; justify-content:space-between; gap:10px; margin-bottom:8px; }
.gym-name { margin:0; font-size:16px; }
.rating { font-size:12px; font-weight:700; color:#0f766e; background:#ecfeff; border-radius:999px; padding:6px 10px; white-space:nowrap; }
.meta { margin:0 0 8px; font-size:13px; color:#475569; }
.price-box { background:#f0fdf4; border:1px dashed #86efac; border-radius:14px; padding:10px 12px; font-size:13px; color:#14532d; }
.tag-row { margin-top:10px; display:flex; flex-wrap:wrap; gap:6px; }
.tag { font-size:11px; font-weight:600; color:#0f766e; background:#ecfeff; border-radius:999px; padding:6px 9px; }
.flow-list { margin:0; padding-left:18px; font-size:13px; color:#1f2937; }
.flow-list li { margin-bottom:8px; }
.partner-map {
    width:100%;
    height:280px;
    border-radius:16px;
    overflow:hidden;
    border:1px solid #dbeafe;
}
.map-wrap iframe { width:100%; height:280px; border:0; border-radius:16px; }
.empty-data { font-size:13px; color:#64748b; }
.cta-row { display:flex; flex-wrap:wrap; gap:10px; margin-top:12px; }
.btn { display:inline-flex; align-items:center; justify-content:center; text-decoration:none; border-radius:999px; padding:10px 16px; font-size:13px; font-weight:700; border:none; cursor:pointer; width:290px; max-width:100%; min-height:44px; text-align:center; }
.btn-primary { color:#fff; background:linear-gradient(135deg,#0f766e,#22c55e); }
.btn-outline { color:#0f766e; border:1px solid #5eead4; background:#f0fdfa; }
.partnership-form-wrap { margin-top: 12px; }
.partnership-form-wrap.hidden { display:none; }
.input-grid { display:grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap:12px; }
.input-group { display:flex; flex-direction:column; gap:6px; margin-bottom:10px; }
.input-group label { font-size:13px; font-weight:600; color:#0f766e; }
.input-group input,.input-group textarea { width:100%; min-height:44px; border:1px solid #d7e3ef; border-radius:12px; padding:10px 12px; font-size:13px; }
.input-group textarea { min-height:96px; resize:vertical; }
.form-message { margin-bottom:12px; border-radius:12px; padding:10px 12px; font-size:13px; }
.form-error { background:#fef2f2; color:#991b1b; border:1px solid #fecaca; }
.form-success { background:#ecfdf5; color:#166534; border:1px solid #bbf7d0; }
.membership-status-banner {
    margin-top:16px;
    border-radius:16px;
    padding:12px 14px;
    background:#ecfdf5;
    color:#166534;
    border:1px solid #bbf7d0;
    font-size:13px;
}

@media (max-width: 900px) {
    .grid-3,.grid-2,.input-grid { grid-template-columns:1fr; }
    .btn { width:100%; }
    .basic-feature-links { grid-template-columns:1fr; }
}
</style>

<main class="gym-app">
    <section class="gym-hero">
        <h1>FiVit Gym Network</h1>
        <p>Sekarang FiVit bukan hanya gym booking. Platform ini menghubungkan karyawan ke partner gym agar benefit kebugaran tetap merata walau perusahaan tidak punya gym sendiri.</p>
        <div class="pill-row">
            <span class="pill">Membership lintas partner</span>
            <span class="pill">Corporate partnership ready</span>
            <span class="pill">Harga transparan</span>
        </div>
    </section>

    <?php if ($activeMembership): ?>
        <section class="membership-status-banner">
            Membership aktif kamu: <strong><?= htmlspecialchars((string) ($activeMembership['plan_name'] ?? '')) ?></strong>
            <?php if (!empty($activeMembership['end_date'])): ?>
                sampai <?= htmlspecialchars((string) $activeMembership['end_date']) ?>
            <?php endif; ?>.
        </section>
    <?php endif; ?>

    <section class="gym-section">
        <h2 class="gym-title">Membership Plan</h2>
        <p class="gym-sub">Pilih plan sesuai frekuensi latihan tim kamu.</p>
        <div class="grid-3">
            <?php if (!$memberships): ?>
                <article class="mini-card"><div class="empty-data">Belum ada data membership di database.</div></article>
            <?php else: ?>
                <?php foreach ($memberships as $plan): ?>
                    <?php $normalizedPlanName = strtolower(trim((string) ($plan['name'] ?? ''))); ?>
                    <?php $isActivePlan = $activeMembership && (int) ($activeMembership['id_gym_membership_plans'] ?? 0) === (int) $plan['id']; ?>
                    <article class="mini-card">
                        <h3 class="plan-name"><?= htmlspecialchars($plan['name']) ?></h3>
                        <p class="plan-price"><?= htmlspecialchars($plan['price']) ?></p>
                        <p class="plan-desc"><?= htmlspecialchars($plan['description']) ?></p>
                        <ul class="feature-list">
                            <?php foreach ($plan['features'] as $feature): ?>
                                <li><?= htmlspecialchars($feature) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <div class="plan-action-group">
                            <?php if ($isActivePlan && $normalizedPlanName === 'basic'): ?>
                                <span class="plan-action-btn is-active">Membership Active</span>
                                <div class="basic-feature-links">
                                    <a class="basic-feature-link" href="health.php">Health Monitoring</a>
                                    <a class="basic-feature-link" href="sleep.php">Sleep Tracking</a>
                                    <a class="basic-feature-link" href="community.php">Community</a>
                                    <a class="basic-feature-link" href="foodselection.php">Food Selection</a>
                                </div>
                            <?php elseif ($isActivePlan): ?>
                                <span class="plan-action-btn is-active">Membership Active</span>
                            <?php elseif ($normalizedPlanName === 'big enterprise'): ?>
                                <a class="plan-action-btn is-secondary" href="mailto:01081240012@student.uph.edu">Hubungi Tim FiVit</a>
                            <?php else: ?>
                                <a class="plan-action-btn" href="payment.php?plan_id=<?= (int) $plan['id'] ?>">Pilih Paket</a>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <section class="gym-section">
        <h2 class="gym-title">Partner Gym & Harga</h2>
        <p class="gym-sub">Akses gym terdekat dengan harga member FiVit.</p>
        <div class="cta-row" style="margin-bottom:10px;">
            <button class="btn btn-outline" id="use-location-btn" type="button">Gunakan lokasi saya</button>
            <span class="pill" id="location-status">Tolong aktifkan lokasi</span>
        </div>
        <div class="grid-3">
            <?php if (!$partnerGyms): ?>
                <article class="mini-card"><div class="empty-data">Belum ada data partner gym di database.</div></article>
            <?php else: ?>
                <?php foreach ($partnerGyms as $gym): ?>
                    <article class="mini-card gym-card" data-lat="<?= htmlspecialchars((string)($gym['lat'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-lng="<?= htmlspecialchars((string)($gym['lng'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <div class="gym-head">
                            <h3 class="gym-name"><?= htmlspecialchars($gym['name']) ?></h3>
                            <span class="rating">&#9733; <?= htmlspecialchars($gym['rating']) ?></span>
                        </div>
                        <p class="meta">
                            <?= htmlspecialchars($gym['address']) ?>
                            &bull;
                            <span class="distance-text">Tolong aktifkan lokasi</span>
                        </p>
                        <div class="price-box">
                            Day Pass: <strong><?= htmlspecialchars($gym['day_pass']) ?></strong><br>
                            FiVit Member Rate: <strong><?= htmlspecialchars($gym['member_rate']) ?></strong>
                        </div>
                        <div class="tag-row">
                            <?php foreach ($gym['tags'] as $tag): ?>
                                <span class="tag"><?= htmlspecialchars($tag) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <section class="gym-section grid-2">
        <article class="mini-card">
            <h2 class="gym-title">Partnership Model</h2>
            <p class="gym-sub">Skema kolaborasi perusahaan, FiVit, dan partner gym.</p>
            <ol class="flow-list">
                <li>Perusahaan pilih budget benefit per karyawan (monthly pass atau per-visit).</li>
                <li>FiVit integrasikan akun karyawan dengan jaringan partner gym terdekat.</li>
                <li>Partner gym terima check-in digital dan laporan utilization otomatis.</li>
                <li>HR dapat dashboard pemakaian dan tren aktivitas karyawan.</li>
            </ol>
            <div class="cta-row">
                <a class="btn btn-primary" href="#">Mulai Membership</a>
                <a class="btn btn-outline" href="#partnership-form" id="show-partnership-form">Ajukan Partnership Perusahaan</a>
            </div>
        </article>

        <article class="mini-card map-wrap">
            <h2 class="gym-title"><?= htmlspecialchars($mapTitle) ?></h2>
            <p class="gym-sub"><?= htmlspecialchars($mapDescription) ?></p>
            <?php if (!empty($partnerMapPoints)): ?>
                <div id="partner-gym-map" class="partner-map" aria-label="Peta partner gym dengan pin lokasi"></div>
            <?php elseif ($mapEmbedUrl !== ''): ?>
                <iframe loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="<?= htmlspecialchars($mapEmbedUrl) ?>" title="Peta Partner Gym FiVit"></iframe>
            <?php else: ?>
                <div class="empty-data">Peta belum diatur. Isi `map_embed_url` lewat admin.</div>
            <?php endif; ?>
        </article>
    </section>

    <section id="partnership-form" class="gym-section partnership-form-wrap <?= $showPartnershipForm ? '' : 'hidden' ?>">
        <h2 class="gym-title">Form Pengajuan Partnership</h2>
        <p class="gym-sub">Isi data perusahaan, nanti tim FiVit follow up untuk skema kerja sama gym network.</p>

        <?php if ($partnershipErrors): ?>
            <div class="form-message form-error"><?= htmlspecialchars(implode(' ', $partnershipErrors)) ?></div>
        <?php endif; ?>
        <?php if ($partnershipSuccess): ?>
            <div class="form-message form-success"><?= htmlspecialchars($partnershipSuccess) ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="action" value="submit_partnership">
            <div class="input-grid">
                <div class="input-group">
                    <label for="company_name">Nama Perusahaan</label>
                    <input id="company_name" type="text" name="company_name" value="<?= htmlspecialchars((string) ($_POST['company_name'] ?? '')) ?>" required>
                </div>
                <div class="input-group">
                    <label for="contact_name">Nama PIC</label>
                    <input id="contact_name" type="text" name="contact_name" value="<?= htmlspecialchars((string) ($_POST['contact_name'] ?? '')) ?>" required>
                </div>
                <div class="input-group">
                    <label for="email">Email</label>
                    <input id="email" type="email" name="email" value="<?= htmlspecialchars((string) ($_POST['email'] ?? '')) ?>" required>
                </div>
                <div class="input-group">
                    <label for="phone">No. Telepon</label>
                    <input id="phone" type="text" name="phone" value="<?= htmlspecialchars((string) ($_POST['phone'] ?? '')) ?>" required>
                </div>
                <div class="input-group">
                    <label for="employee_count">Jumlah Karyawan</label>
                    <input id="employee_count" type="number" min="1" name="employee_count" value="<?= htmlspecialchars((string) ($_POST['employee_count'] ?? '')) ?>" required>
                </div>
                <div class="input-group">
                    <label for="city">Kota</label>
                    <input id="city" type="text" name="city" value="<?= htmlspecialchars((string) ($_POST['city'] ?? '')) ?>" required>
                </div>
            </div>
            <div class="input-group">
                <label for="notes">Kebutuhan / Catatan</label>
                <textarea id="notes" name="notes" placeholder="Contoh: target 80 karyawan aktif, 3 area kantor utama"><?= htmlspecialchars((string) ($_POST['notes'] ?? '')) ?></textarea>
            </div>
            <button class="btn btn-primary" type="submit">Kirim Pengajuan Partnership</button>
        </form>
    </section>
</main>

<script>
(() => {
    const trigger = document.getElementById('show-partnership-form');
    const formWrap = document.getElementById('partnership-form');
    if (!trigger || !formWrap) return;
    trigger.addEventListener('click', () => formWrap.classList.remove('hidden'));
})();

(() => {
    const cards = Array.from(document.querySelectorAll('.gym-card'));
    if (!cards.length) return;

    const statusEl = document.getElementById('location-status');
    const setFallback = (msg) => {
        cards.forEach((card) => {
            const el = card.querySelector('.distance-text');
            if (el) el.textContent = msg || 'Tolong aktifkan lokasi';
        });
        if (statusEl) statusEl.textContent = msg || 'Tolong aktifkan lokasi';
    };

    if (!navigator.geolocation) {
        setFallback('Browser tidak mendukung lokasi');
        return;
    }

    const toRad = (v) => (v * Math.PI) / 180;
    const distanceKm = (lat1, lng1, lat2, lng2) => {
        const R = 6371;
        const dLat = toRad(lat2 - lat1);
        const dLng = toRad(lng2 - lng1);
        const a = Math.sin(dLat / 2) ** 2 +
            Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) *
            Math.sin(dLng / 2) ** 2;
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        return R * c;
    };

    const applyLocation = (pos) => {
        const userLat = pos.coords.latitude;
        const userLng = pos.coords.longitude;
        cards.forEach((card) => {
            const lat = parseFloat(card.dataset.lat || '');
            const lng = parseFloat(card.dataset.lng || '');
            const el = card.querySelector('.distance-text');
            if (!el) return;
            if (isNaN(lat) || isNaN(lng)) {
                el.textContent = 'Alamat belum lengkap';
                return;
            }
            const km = distanceKm(userLat, userLng, lat, lng);
            el.textContent = `${km.toFixed(1)} km dari lokasi kamu`;
        });
        if (statusEl) statusEl.textContent = 'Lokasi aktif';
    };

    const requestLocation = () => {
        if (statusEl) statusEl.textContent = 'Meminta lokasi...';
        navigator.geolocation.getCurrentPosition(applyLocation, () => {
            setFallback('Izin lokasi ditolak');
        }, { enableHighAccuracy: false, timeout: 5000 });
    };

    const btn = document.getElementById('use-location-btn');
    if (btn) btn.addEventListener('click', requestLocation);

    // Auto prompt on load (optional), can be removed if you want button-only.
    requestLocation();
})();

(() => {
    const mapEl = document.getElementById('partner-gym-map');
    const partnerPoints = <?= $partnerMapPointsJson ?: '[]' ?>;

    if (!mapEl || !Array.isArray(partnerPoints) || !partnerPoints.length || typeof L === 'undefined') {
        return;
    }

    const map = L.map(mapEl, {
        scrollWheelZoom: false
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    const bounds = [];

    partnerPoints.forEach((point) => {
        if (typeof point.lat !== 'number' || typeof point.lng !== 'number') {
            return;
        }

        const marker = L.marker([point.lat, point.lng]).addTo(map);
        const popupParts = [
            `<strong>${String(point.name || '')}</strong>`
        ];

        if (point.address) {
            popupParts.push(String(point.address));
        }
        if (point.day_pass) {
            popupParts.push(`Day Pass: ${String(point.day_pass)}`);
        }
        if (point.member_rate) {
            popupParts.push(`Member Rate: ${String(point.member_rate)}`);
        }

        marker.bindPopup(popupParts.join('<br>'));
        bounds.push([point.lat, point.lng]);
    });

    if (bounds.length === 1) {
        map.setView(bounds[0], 13);
    } else if (bounds.length > 1) {
        map.fitBounds(bounds, { padding: [24, 24] });
    }
})();
</script>

<?php include 'includes/footer.php'; ?>
