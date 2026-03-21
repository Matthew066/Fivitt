<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_login('../login.php');

$pageTitle = 'Admin - Gym';

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

$config = require __DIR__ . '/../includes/api_config.php';
$orsApiKey = (string) ($config['ORS_API_KEY'] ?? '');

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

$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'save_plan') {
        $id = (int) ($_POST['id'] ?? 0);
        $planName = trim($_POST['plan_name'] ?? '');
        $priceText = trim($_POST['price_text'] ?? '');
        $descriptionText = trim($_POST['description_text'] ?? '');
        $featuresText = trim($_POST['features_text'] ?? '');
        $sortOrder = clampInt($_POST['sort_order'] ?? 0, 0, 999, 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($planName !== '') {
            if ($id > 0) {
                $stmt = $pdo->prepare("
                    UPDATE gym_membership_plans
                    SET plan_name = ?, price_text = ?, description_text = ?, features_text = ?, sort_order = ?, is_active = ?
                    WHERE id_gym_membership_plans = ?
                ");
                $stmt->execute([$planName, $priceText, $descriptionText, $featuresText, $sortOrder, $isActive, $id]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO gym_membership_plans (plan_name, price_text, description_text, features_text, sort_order, is_active)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$planName, $priceText, $descriptionText, $featuresText, $sortOrder, $isActive]);
            }
        }
        header('Location: gym.php');
        exit;
    }

    if ($action === 'delete_plan') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("DELETE FROM gym_membership_plans WHERE id_gym_membership_plans = ?");
            $stmt->execute([$id]);
        }
        header('Location: gym.php');
        exit;
    }

    if ($action === 'save_partner') {
        $id = (int) ($_POST['id'] ?? 0);
        $gymName = trim($_POST['gym_name'] ?? '');
        $addressText = trim($_POST['address_text'] ?? '');
        $latitude = isset($_POST['latitude']) && $_POST['latitude'] !== '' ? (float) $_POST['latitude'] : null;
        $longitude = isset($_POST['longitude']) && $_POST['longitude'] !== '' ? (float) $_POST['longitude'] : null;
        $rating = (float) ($_POST['rating'] ?? 0);
        $dayPass = trim($_POST['day_pass_price_text'] ?? '');
        $memberRate = trim($_POST['member_rate_price_text'] ?? '');
        $tagsText = trim($_POST['tags_text'] ?? '');
        $sortOrder = clampInt($_POST['sort_order'] ?? 0, 0, 999, 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($gymName !== '') {
            if ($id > 0) {
                $stmt = $pdo->prepare("
                    UPDATE gym_partner_gyms
                    SET gym_name = ?, address_text = ?, rating = ?, day_pass_price_text = ?,
                        member_rate_price_text = ?, tags_text = ?, sort_order = ?, is_active = ?,
                        latitude = ?, longitude = ?
                    WHERE id_gym_partner_gyms = ?
                ");
                $stmt->execute([
                    $gymName,
                    $addressText,
                    $rating,
                    $dayPass,
                    $memberRate,
                    $tagsText,
                    $sortOrder,
                    $isActive,
                    $latitude,
                    $longitude,
                    $id
                ]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO gym_partner_gyms
                        (gym_name, address_text, rating, day_pass_price_text, member_rate_price_text,
                         tags_text, sort_order, is_active, latitude, longitude)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $gymName,
                    $addressText,
                    $rating,
                    $dayPass,
                    $memberRate,
                    $tagsText,
                    $sortOrder,
                    $isActive,
                    $latitude,
                    $longitude
                ]);
            }
        }
        header('Location: gym.php');
        exit;
    }

    if ($action === 'delete_partner') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("DELETE FROM gym_partner_gyms WHERE id_gym_partner_gyms = ?");
            $stmt->execute([$id]);
        }
        header('Location: gym.php');
        exit;
    }

    if ($action === 'save_map') {
        $id = (int) ($_POST['id'] ?? 0);
        $mapTitle = trim($_POST['map_title'] ?? '');
        $mapDescription = trim($_POST['map_description'] ?? '');
        $mapEmbedUrl = trim($_POST['map_embed_url'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($mapEmbedUrl !== '') {
            if ($id > 0) {
                $stmt = $pdo->prepare("
                    UPDATE gym_map_settings
                    SET map_title = ?, map_description = ?, map_embed_url = ?, is_active = ?
                    WHERE id_gym_map_settings = ?
                ");
                $stmt->execute([$mapTitle, $mapDescription, $mapEmbedUrl, $isActive, $id]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO gym_map_settings (map_title, map_description, map_embed_url, is_active)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$mapTitle, $mapDescription, $mapEmbedUrl, $isActive]);
            }
        }
        header('Location: gym.php');
        exit;
    }

    if ($action === 'delete_map') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("DELETE FROM gym_map_settings WHERE id_gym_map_settings = ?");
            $stmt->execute([$id]);
        }
        header('Location: gym.php');
        exit;
    }

    if ($action === 'delete_request') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("DELETE FROM gym_partnership_requests WHERE id_gym_partnership_requests = ?");
            $stmt->execute([$id]);
        }
        header('Location: gym.php');
        exit;
    }
}

$editPlanId = isset($_GET['edit_plan']) ? (int) $_GET['edit_plan'] : 0;
$editPlan = null;
if ($editPlanId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM gym_membership_plans WHERE id_gym_membership_plans = ? LIMIT 1");
    $stmt->execute([$editPlanId]);
    $editPlan = $stmt->fetch();
}

$editPartnerId = isset($_GET['edit_partner']) ? (int) $_GET['edit_partner'] : 0;
$editPartner = null;
if ($editPartnerId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM gym_partner_gyms WHERE id_gym_partner_gyms = ? LIMIT 1");
    $stmt->execute([$editPartnerId]);
    $editPartner = $stmt->fetch();
}

$editMapId = isset($_GET['edit_map']) ? (int) $_GET['edit_map'] : 0;
$editMap = null;
if ($editMapId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM gym_map_settings WHERE id_gym_map_settings = ? LIMIT 1");
    $stmt->execute([$editMapId]);
    $editMap = $stmt->fetch();
}

$plans = $pdo->query("SELECT * FROM gym_membership_plans ORDER BY sort_order ASC, id_gym_membership_plans DESC")->fetchAll();
$partners = $pdo->query("SELECT * FROM gym_partner_gyms ORDER BY sort_order ASC, id_gym_partner_gyms DESC")->fetchAll();
$maps = $pdo->query("SELECT * FROM gym_map_settings ORDER BY id_gym_map_settings DESC")->fetchAll();
$requests = $pdo->query("SELECT * FROM gym_partnership_requests ORDER BY id_gym_partnership_requests DESC")->fetchAll();
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
        .table td, .table th { vertical-align: middle; }
        .suggestion-item { cursor: pointer; }
    </style>
</head>
<body>
<div class="page-wrapper">
    <div class="mb-3">
        <h4 class="mb-0">Admin - Gym</h4>
        <div class="text-muted">Kelola membership, partner gym, map, dan request partnership.</div>
    </div>

    <div class="row">
        <div class="col-12 col-lg-5">
            <div class="card mb-3">
                <div class="card-body">
                    <h6 class="mb-3"><?= $editPlan ? 'Edit Membership Plan' : 'Tambah Membership Plan' ?></h6>
                    <form method="post">
                        <input type="hidden" name="action" value="save_plan">
                        <input type="hidden" name="id" value="<?= (int) ($editPlan['id_gym_membership_plans'] ?? 0) ?>">

                        <div class="mb-3">
                            <label class="form-label">Nama Plan</label>
                            <input class="form-control" type="text" name="plan_name" required value="<?= h($editPlan['plan_name'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Harga</label>
                            <input class="form-control" type="text" name="price_text" value="<?= h($editPlan['price_text'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Deskripsi</label>
                            <input class="form-control" type="text" name="description_text" value="<?= h($editPlan['description_text'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Features (pisahkan dengan newline)</label>
                            <textarea class="form-control" name="features_text" rows="3"><?= h($editPlan['features_text'] ?? '') ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Sort Order</label>
                            <input class="form-control" type="number" name="sort_order" value="<?= h((string)($editPlan['sort_order'] ?? 0)) ?>">
                        </div>
                        <div class="form-check form-switch mb-3">
                            <?php $active = (int) ($editPlan['is_active'] ?? 1); ?>
                            <input class="form-check-input" type="checkbox" id="plan_active" name="is_active" <?= $active === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label" for="plan_active">Aktif</label>
                        </div>
                        <button class="btn btn-primary" type="submit"><?= $editPlan ? 'Update' : 'Simpan' ?></button>
                        <?php if ($editPlan): ?>
                            <a class="btn btn-light ms-1" href="gym.php">Batal</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-body">
                    <h6 class="mb-3"><?= $editPartner ? 'Edit Partner Gym' : 'Tambah Partner Gym' ?></h6>
                    <form method="post">
                        <input type="hidden" name="action" value="save_partner">
                        <input type="hidden" name="id" value="<?= (int) ($editPartner['id_gym_partner_gyms'] ?? 0) ?>">

                        <div class="mb-3">
                            <label class="form-label">Nama Gym</label>
                            <input class="form-control" type="text" name="gym_name" required value="<?= h($editPartner['gym_name'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Alamat (gunakan autocomplete)</label>
                            <input class="form-control" type="text" id="gymAddress" name="address_text" value="<?= h($editPartner['address_text'] ?? '') ?>" autocomplete="off">
                            <div id="addressSuggestions" class="list-group position-absolute w-100" style="z-index: 10; display:none;"></div>
                            <div class="form-text">Ketik alamat lalu pilih dari saran. Lat/Lng akan terisi otomatis.</div>
                        </div>
                        <input type="hidden" name="latitude" id="gymLat" value="<?= h((string)($editPartner['latitude'] ?? '')) ?>">
                        <input type="hidden" name="longitude" id="gymLng" value="<?= h((string)($editPartner['longitude'] ?? '')) ?>">
                        <div class="mb-3">
                            <div class="form-text">Jarak dihitung otomatis dari lokasi user (geolocation).</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Rating</label>
                            <input class="form-control" type="number" step="0.1" min="0" max="5" name="rating" value="<?= h((string)($editPartner['rating'] ?? 0)) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Day Pass</label>
                            <input class="form-control" type="text" name="day_pass_price_text" value="<?= h($editPartner['day_pass_price_text'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Member Rate</label>
                            <input class="form-control" type="text" name="member_rate_price_text" value="<?= h($editPartner['member_rate_price_text'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tags (pisahkan dengan koma)</label>
                            <input class="form-control" type="text" name="tags_text" value="<?= h($editPartner['tags_text'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Sort Order</label>
                            <input class="form-control" type="number" name="sort_order" value="<?= h((string)($editPartner['sort_order'] ?? 0)) ?>">
                        </div>
                        <div class="form-check form-switch mb-3">
                            <?php $active = (int) ($editPartner['is_active'] ?? 1); ?>
                            <input class="form-check-input" type="checkbox" id="partner_active" name="is_active" <?= $active === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label" for="partner_active">Aktif</label>
                        </div>
                        <button class="btn btn-primary" type="submit"><?= $editPartner ? 'Update' : 'Simpan' ?></button>
                        <?php if ($editPartner): ?>
                            <a class="btn btn-light ms-1" href="gym.php">Batal</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h6 class="mb-3"><?= $editMap ? 'Edit Map' : 'Tambah Map' ?></h6>
                    <form method="post">
                        <input type="hidden" name="action" value="save_map">
                        <input type="hidden" name="id" value="<?= (int) ($editMap['id_gym_map_settings'] ?? 0) ?>">

                        <div class="mb-3">
                            <label class="form-label">Judul Map</label>
                            <input class="form-control" type="text" name="map_title" value="<?= h($editMap['map_title'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Deskripsi</label>
                            <input class="form-control" type="text" name="map_description" value="<?= h($editMap['map_description'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Map Embed URL</label>
                            <textarea class="form-control" name="map_embed_url" rows="3" required><?= h($editMap['map_embed_url'] ?? '') ?></textarea>
                        </div>
                        <div class="form-check form-switch mb-3">
                            <?php $active = (int) ($editMap['is_active'] ?? 1); ?>
                            <input class="form-check-input" type="checkbox" id="map_active" name="is_active" <?= $active === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label" for="map_active">Aktif</label>
                        </div>
                        <button class="btn btn-primary" type="submit"><?= $editMap ? 'Update' : 'Simpan' ?></button>
                        <?php if ($editMap): ?>
                            <a class="btn btn-light ms-1" href="gym.php">Batal</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-7">
            <div class="card mb-3">
                <div class="card-body">
                    <h6 class="mb-3">Membership Plans</h6>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Plan</th>
                                    <th>Harga</th>
                                    <th>Aktif</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (!$plans): ?>
                                <tr><td colspan="4" class="text-center">Belum ada plan.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($plans as $plan): ?>
                                <tr>
                                    <td><?= h($plan['plan_name'] ?? '') ?></td>
                                    <td><?= h($plan['price_text'] ?? '') ?></td>
                                    <td>
                                        <span class="badge <?= ((int)$plan['is_active'] === 1) ? 'bg-success' : 'bg-secondary' ?>">
                                            <?= ((int)$plan['is_active'] === 1) ? 'Aktif' : 'Nonaktif' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a class="btn btn-sm btn-outline-primary" href="gym.php?edit_plan=<?= (int)$plan['id_gym_membership_plans'] ?>">Edit</a>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Hapus plan ini?');">
                                            <input type="hidden" name="action" value="delete_plan">
                                            <input type="hidden" name="id" value="<?= (int)$plan['id_gym_membership_plans'] ?>">
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

            <div class="card mb-3">
                <div class="card-body">
                    <h6 class="mb-3">Partner Gyms</h6>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Gym</th>
                                    <th>Alamat</th>
                                    <th>Rating</th>
                                    <th>Aktif</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (!$partners): ?>
                                <tr><td colspan="5" class="text-center">Belum ada partner gym.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($partners as $partner): ?>
                                <tr>
                                    <td><?= h($partner['gym_name'] ?? '') ?></td>
                                    <td><?= h($partner['address_text'] ?? '') ?></td>
                                    <td><?= h((string)($partner['rating'] ?? '')) ?></td>
                                    <td>
                                        <span class="badge <?= ((int)$partner['is_active'] === 1) ? 'bg-success' : 'bg-secondary' ?>">
                                            <?= ((int)$partner['is_active'] === 1) ? 'Aktif' : 'Nonaktif' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a class="btn btn-sm btn-outline-primary" href="gym.php?edit_partner=<?= (int)$partner['id_gym_partner_gyms'] ?>">Edit</a>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Hapus partner gym ini?');">
                                            <input type="hidden" name="action" value="delete_partner">
                                            <input type="hidden" name="id" value="<?= (int)$partner['id_gym_partner_gyms'] ?>">
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

            <div class="card mb-3">
                <div class="card-body">
                    <h6 class="mb-3">Map Settings</h6>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Judul</th>
                                    <th>Aktif</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (!$maps): ?>
                                <tr><td colspan="3" class="text-center">Belum ada map.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($maps as $map): ?>
                                <tr>
                                    <td><?= h($map['map_title'] ?? '') ?></td>
                                    <td>
                                        <span class="badge <?= ((int)$map['is_active'] === 1) ? 'bg-success' : 'bg-secondary' ?>">
                                            <?= ((int)$map['is_active'] === 1) ? 'Aktif' : 'Nonaktif' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a class="btn btn-sm btn-outline-primary" href="gym.php?edit_map=<?= (int)$map['id_gym_map_settings'] ?>">Edit</a>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Hapus map ini?');">
                                            <input type="hidden" name="action" value="delete_map">
                                            <input type="hidden" name="id" value="<?= (int)$map['id_gym_map_settings'] ?>">
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
                    <h6 class="mb-3">Partnership Requests</h6>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Perusahaan</th>
                                    <th>PIC</th>
                                    <th>Email</th>
                                    <th>Kota</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (!$requests): ?>
                                <tr><td colspan="5" class="text-center">Belum ada request.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($requests as $req): ?>
                                <tr>
                                    <td><?= h($req['company_name'] ?? '') ?></td>
                                    <td><?= h($req['contact_name'] ?? '') ?></td>
                                    <td><?= h($req['email'] ?? '') ?></td>
                                    <td><?= h($req['city'] ?? '') ?></td>
                                    <td>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Hapus request ini?');">
                                            <input type="hidden" name="action" value="delete_request">
                                            <input type="hidden" name="id" value="<?= (int)$req['id_gym_partnership_requests'] ?>">
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
</div>

<script>
(() => {
    const apiKey = <?= json_encode($orsApiKey) ?>;
    const input = document.getElementById('gymAddress');
    const list = document.getElementById('addressSuggestions');
    const latInput = document.getElementById('gymLat');
    const lngInput = document.getElementById('gymLng');

    if (!input || !list || !latInput || !lngInput) return;
    if (!apiKey) {
        input.placeholder = 'Isi ORS_API_KEY di includes/api_config.php untuk autocomplete';
        return;
    }

    let debounceTimer = null;
    const clearList = () => {
        list.innerHTML = '';
        list.style.display = 'none';
    };

    const renderItems = (items) => {
        list.innerHTML = '';
        if (!items.length) {
            clearList();
            return;
        }
        items.forEach((item) => {
            const div = document.createElement('button');
            div.type = 'button';
            div.className = 'list-group-item list-group-item-action suggestion-item';
            div.textContent = item.label;
            div.addEventListener('click', () => {
                input.value = item.label;
                latInput.value = item.lat;
                lngInput.value = item.lng;
                clearList();
            });
            list.appendChild(div);
        });
        list.style.display = 'block';
    };

    const fetchSuggestions = async (text) => {
        const url = `https://api.openrouteservice.org/geocode/autocomplete?api_key=${encodeURIComponent(apiKey)}&text=${encodeURIComponent(text)}&size=6`;
        const res = await fetch(url);
        if (!res.ok) throw new Error('Autocomplete error');
        const data = await res.json();
        const features = data.features || [];
        return features.map((f) => ({
            label: f.properties?.label || f.properties?.name || '',
            lat: f.geometry?.coordinates?.[1] ?? '',
            lng: f.geometry?.coordinates?.[0] ?? ''
        })).filter((i) => i.label);
    };

    input.addEventListener('input', () => {
        const val = input.value.trim();
        latInput.value = '';
        lngInput.value = '';
        if (debounceTimer) clearTimeout(debounceTimer);
        if (val.length < 3) {
            clearList();
            return;
        }
        debounceTimer = setTimeout(async () => {
            try {
                const items = await fetchSuggestions(val);
                renderItems(items);
            } catch (e) {
                clearList();
            }
        }, 300);
    });

    document.addEventListener('click', (e) => {
        if (!list.contains(e.target) && e.target !== input) {
            clearList();
        }
    });
})();
</script>
</body>
</html>
