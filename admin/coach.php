<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_login('../login.php');

$pageTitle = 'Admin - Coach';

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

// Add coach meta columns if missing.
addColumnIfMissing($pdo, 'coaches', 'photo_path', 'VARCHAR(255) NULL');
addColumnIfMissing($pdo, 'coaches', 'is_blacklisted', 'TINYINT(1) NOT NULL DEFAULT 0');
addColumnIfMissing($pdo, 'coaches', 'is_trusted', 'TINYINT(1) NOT NULL DEFAULT 0');
addColumnIfMissing($pdo, 'coaches', 'trusted_badge_path', 'VARCHAR(255) NULL');

function autoTrustCoaches(PDO $pdo, int $minSessions = 100): void
{
    $stmt = $pdo->prepare("
        UPDATE coaches c
        JOIN (
            SELECT id_coaches, COUNT(*) AS total_sessions
            FROM coach_sessions
            GROUP BY id_coaches
        ) s ON s.id_coaches = c.id_coaches
        SET c.is_trusted = 1
        WHERE s.total_sessions >= ?
    ");
    $stmt->execute([$minSessions]);
}

$imageDirRelative = 'assets/images/coaches';
$imageDirAbsolute = dirname(__DIR__) . '/' . $imageDirRelative;

$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'save_coach') {
        $id = (int) ($_POST['id'] ?? 0);
        $userId = (int) ($_POST['id_users'] ?? 0);
        $coachName = trim($_POST['coach_name'] ?? '');
        $coachEmail = trim($_POST['coach_email'] ?? '');
        $coachPhone = trim($_POST['coach_phone'] ?? '');
        $coachBio = trim($_POST['coach_bio'] ?? '');
        $specialtiesText = trim($_POST['specialties_text'] ?? '');
        $coachType = trim($_POST['coach_type'] ?? 'external');
        $rateType = trim($_POST['rate_type'] ?? 'free');
        $rateText = trim($_POST['rate_text'] ?? '');
        $visibility = trim($_POST['visibility'] ?? 'public');
        $departmentScope = trim($_POST['department_scope'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $isBlacklisted = isset($_POST['is_blacklisted']) ? 1 : 0;
        $isTrusted = isset($_POST['is_trusted']) ? 1 : 0;
        $currentImagePath = trim($_POST['current_image_path'] ?? '');
        $currentBadgePath = trim($_POST['current_badge_path'] ?? '');
        $imagePath = $currentImagePath;
        $badgePath = $currentBadgePath;

        if (isset($_POST['remove_image'])) {
            $imagePath = '';
        }
        if (isset($_POST['remove_badge'])) {
            $badgePath = '';
        }

        if (isset($_FILES['photo']) && (int) ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $tmp = (string) ($_FILES['photo']['tmp_name'] ?? '');
            $size = (int) ($_FILES['photo']['size'] ?? 0);
            $mime = $tmp !== '' ? (string) mime_content_type($tmp) : '';
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            if ($size > 0 && $size <= 2 * 1024 * 1024 && isset($allowed[$mime])) {
                if (!is_dir($imageDirAbsolute)) {
                    mkdir($imageDirAbsolute, 0777, true);
                }
                $fileName = 'coach-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
                $targetAbs = $imageDirAbsolute . '/' . $fileName;
                $targetRel = $imageDirRelative . '/' . $fileName;
                if (move_uploaded_file($tmp, $targetAbs)) {
                    if (
                        $currentImagePath !== '' &&
                        str_starts_with($currentImagePath, $imageDirRelative . '/') &&
                        is_file(dirname(__DIR__) . '/' . $currentImagePath)
                    ) {
                        @unlink(dirname(__DIR__) . '/' . $currentImagePath);
                    }
                    $imagePath = $targetRel;
                }
            }
        }

        if (isset($_FILES['trusted_badge']) && (int) ($_FILES['trusted_badge']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $tmp = (string) ($_FILES['trusted_badge']['tmp_name'] ?? '');
            $size = (int) ($_FILES['trusted_badge']['size'] ?? 0);
            $mime = $tmp !== '' ? (string) mime_content_type($tmp) : '';
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            if ($size > 0 && $size <= 2 * 1024 * 1024 && isset($allowed[$mime])) {
                if (!is_dir($imageDirAbsolute)) {
                    mkdir($imageDirAbsolute, 0777, true);
                }
                $fileName = 'trusted-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
                $targetAbs = $imageDirAbsolute . '/' . $fileName;
                $targetRel = $imageDirRelative . '/' . $fileName;
                if (move_uploaded_file($tmp, $targetAbs)) {
                    if (
                        $currentBadgePath !== '' &&
                        str_starts_with($currentBadgePath, $imageDirRelative . '/') &&
                        is_file(dirname(__DIR__) . '/' . $currentBadgePath)
                    ) {
                        @unlink(dirname(__DIR__) . '/' . $currentBadgePath);
                    }
                    $badgePath = $targetRel;
                }
            }
        }

        if ($coachName !== '') {
            if ($id === 0 && $userId > 0) {
                $existing = $pdo->prepare("SELECT id_coaches FROM coaches WHERE id_users = ? LIMIT 1");
                $existing->execute([$userId]);
                $existingId = (int) ($existing->fetchColumn() ?: 0);
                if ($existingId > 0) {
                    $id = $existingId;
                }
            }
            if ($id > 0) {
                $stmt = $pdo->prepare("
                    UPDATE coaches
                    SET id_users = ?, coach_name = ?, coach_email = ?, coach_phone = ?, coach_bio = ?,
                        specialties_text = ?, coach_type = ?, rate_type = ?, rate_text = ?, visibility = ?,
                        department_scope = ?, is_active = ?, photo_path = ?, is_blacklisted = ?, is_trusted = ?, trusted_badge_path = ?
                    WHERE id_coaches = ?
                ");
                $stmt->execute([
                    $userId > 0 ? $userId : null,
                    $coachName,
                    $coachEmail,
                    $coachPhone,
                    $coachBio,
                    $specialtiesText,
                    $coachType,
                    $rateType,
                    $rateText,
                    $visibility,
                    $departmentScope,
                    $isActive,
                    ($imagePath !== '' ? $imagePath : null),
                    $isBlacklisted,
                    $isTrusted,
                    ($badgePath !== '' ? $badgePath : null),
                    $id
                ]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO coaches
                        (id_users, coach_name, coach_email, coach_phone, coach_bio, specialties_text, coach_type,
                         rate_type, rate_text, visibility, department_scope, id_users_created_by, is_active, photo_path, is_blacklisted, is_trusted, trusted_badge_path)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $creatorId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
                $stmt->execute([
                    $userId > 0 ? $userId : null,
                    $coachName,
                    $coachEmail,
                    $coachPhone,
                    $coachBio,
                    $specialtiesText,
                    $coachType,
                    $rateType,
                    $rateText,
                    $visibility,
                    $departmentScope,
                    $creatorId,
                    $isActive,
                    ($imagePath !== '' ? $imagePath : null),
                    $isBlacklisted,
                    $isTrusted,
                    ($badgePath !== '' ? $badgePath : null),
                ]);
            }
        }

        header('Location: coach.php');
        exit;
    }

    if ($action === 'delete_coach') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $imgStmt = $pdo->prepare("SELECT photo_path, trusted_badge_path FROM coaches WHERE id_coaches = ? LIMIT 1");
            $imgStmt->execute([$id]);
            $row = $imgStmt->fetch();

            $stmt = $pdo->prepare("DELETE FROM coaches WHERE id_coaches = ?");
            $stmt->execute([$id]);

            $imagePath = (string) ($row['photo_path'] ?? '');
            if (
                $imagePath !== '' &&
                str_starts_with($imagePath, $imageDirRelative . '/') &&
                is_file(dirname(__DIR__) . '/' . $imagePath)
            ) {
                @unlink(dirname(__DIR__) . '/' . $imagePath);
            }
            $badgePath = (string) ($row['trusted_badge_path'] ?? '');
            if (
                $badgePath !== '' &&
                str_starts_with($badgePath, $imageDirRelative . '/') &&
                is_file(dirname(__DIR__) . '/' . $badgePath)
            ) {
                @unlink(dirname(__DIR__) . '/' . $badgePath);
            }
        }
        header('Location: coach.php');
        exit;
    }

    if ($action === 'toggle_coach_active') {
        $id = (int) ($_POST['id'] ?? 0);
        $next = isset($_POST['next_active']) ? (int) $_POST['next_active'] : 0;
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE coaches SET is_active = ? WHERE id_coaches = ?");
            $stmt->execute([$next ? 1 : 0, $id]);
        }
        header('Location: coach.php');
        exit;
    }

    if ($action === 'toggle_coach_blacklist') {
        $id = (int) ($_POST['id'] ?? 0);
        $next = isset($_POST['next_blacklist']) ? (int) $_POST['next_blacklist'] : 0;
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE coaches SET is_blacklisted = ? WHERE id_coaches = ?");
            $stmt->execute([$next ? 1 : 0, $id]);
        }
        header('Location: coach.php');
        exit;
    }

    if ($action === 'toggle_coach_trusted') {
        $id = (int) ($_POST['id'] ?? 0);
        $next = isset($_POST['next_trusted']) ? (int) $_POST['next_trusted'] : 0;
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE coaches SET is_trusted = ? WHERE id_coaches = ?");
            $stmt->execute([$next ? 1 : 0, $id]);
        }
        header('Location: coach.php');
        exit;
    }
    if ($action === 'save_session') {
        $id = (int) ($_POST['id'] ?? 0);
        $coachId = (int) ($_POST['id_coaches'] ?? 0);
        $requesterId = (int) ($_POST['id_users'] ?? 0);
        $sessionDate = trim($_POST['session_date'] ?? '');
        $startTime = trim($_POST['start_time'] ?? '');
        $endTime = trim($_POST['end_time'] ?? '');
        $locationText = trim($_POST['location_text'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $status = trim($_POST['status'] ?? '');

        if ($coachId > 0 && $requesterId > 0 && $sessionDate !== '') {
            if ($id > 0) {
                $stmt = $pdo->prepare("
                    UPDATE coach_sessions
                    SET id_coaches = ?, id_users = ?, session_date = ?, start_time = ?, end_time = ?,
                        location_text = ?, notes = ?, status = ?
                    WHERE id_coach_sessions = ?
                ");
                $stmt->execute([
                    $coachId,
                    $requesterId,
                    $sessionDate,
                    ($startTime !== '' ? $startTime : null),
                    ($endTime !== '' ? $endTime : null),
                    $locationText,
                    $notes,
                    $status,
                    $id
                ]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO coach_sessions
                        (id_coaches, id_users, session_date, start_time, end_time, location_text, notes, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $coachId,
                    $requesterId,
                    $sessionDate,
                    ($startTime !== '' ? $startTime : null),
                    ($endTime !== '' ? $endTime : null),
                    $locationText,
                    $notes,
                    $status
                ]);
            }
        }

        header('Location: coach.php');
        exit;
    }

    if ($action === 'delete_session') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("DELETE FROM coach_sessions WHERE id_coach_sessions = ?");
            $stmt->execute([$id]);
        }
        header('Location: coach.php');
        exit;
    }
}

// Auto mark trusted coaches based on total sessions.
autoTrustCoaches($pdo, 100);

$users = $pdo->query("SELECT id_users, name, email FROM users ORDER BY name ASC")->fetchAll();

$editCoachId = isset($_GET['edit_coach']) ? (int) $_GET['edit_coach'] : 0;
$editCoach = null;
if ($editCoachId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM coaches WHERE id_coaches = ? LIMIT 1");
    $stmt->execute([$editCoachId]);
    $editCoach = $stmt->fetch();
}

$editSessionId = isset($_GET['edit_session']) ? (int) $_GET['edit_session'] : 0;
$editSession = null;
if ($editSessionId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM coach_sessions WHERE id_coach_sessions = ? LIMIT 1");
    $stmt->execute([$editSessionId]);
    $editSession = $stmt->fetch();
}

$coaches = $pdo->query("
    SELECT id_coaches, coach_name, coach_email, coach_phone, coach_type, rate_type, rate_text,
           visibility, department_scope, is_active, photo_path, is_blacklisted, is_trusted, trusted_badge_path, created_at
    FROM coaches
    ORDER BY created_at DESC, id_coaches DESC
")->fetchAll();

$sessions = $pdo->query("
    SELECT s.id_coach_sessions, s.id_coaches, s.id_users, s.session_date, s.start_time, s.end_time, s.location_text, s.notes, s.status, s.created_at,
           c.coach_name, c.is_active AS coach_active, c.is_blacklisted AS coach_blacklisted, c.is_trusted AS coach_trusted, c.trusted_badge_path,
           u.name AS requester_name, u.email AS requester_email
    FROM coach_sessions s
    LEFT JOIN coaches c ON c.id_coaches = s.id_coaches
    LEFT JOIN users u ON u.id_users = s.id_users
    ORDER BY s.session_date DESC, s.id_coach_sessions DESC
")->fetchAll();
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
        .preview-img { width: 72px; height: 72px; object-fit: cover; border-radius: 12px; border: 1px solid #e2e8f0; }
    </style>
</head>
<body>
<div class="page-wrapper">
    <div class="mb-3">
        <h4 class="mb-0">Admin - Coach</h4>
        <div class="text-muted">CRUD coach & coach sessions.</div>
    </div>

    <div class="row">
        <div class="col-12 col-lg-5">
            <div class="card mb-3">
                <div class="card-body">
                    <h6 class="mb-3"><?= $editCoach ? 'Edit Coach' : 'Tambah Coach' ?></h6>
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="save_coach">
                        <input type="hidden" name="id" value="<?= (int) ($editCoach['id_coaches'] ?? 0) ?>">
                        <input type="hidden" name="current_image_path" value="<?= h($editCoach['photo_path'] ?? '') ?>">
                        <input type="hidden" name="current_badge_path" value="<?= h($editCoach['trusted_badge_path'] ?? '') ?>">

                        <div class="mb-3">
                            <label class="form-label">User (opsional)</label>
                            <select class="form-select" name="id_users">
                                <option value="">Tidak terhubung</option>
                                <?php foreach ($users as $user): ?>
                                    <?php $selected = (int)($editCoach['id_users'] ?? 0) === (int)$user['id_users']; ?>
                                    <option value="<?= (int)$user['id_users'] ?>" <?= $selected ? 'selected' : '' ?>>
                                        <?= h($user['name'] ?: 'User') ?> (<?= h($user['email'] ?: 'no-email') ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Nama Coach</label>
                            <input class="form-control" type="text" name="coach_name" required value="<?= h($editCoach['coach_name'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input class="form-control" type="email" name="coach_email" value="<?= h($editCoach['coach_email'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Telepon</label>
                            <input class="form-control" type="text" name="coach_phone" value="<?= h($editCoach['coach_phone'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Bio</label>
                            <textarea class="form-control" name="coach_bio" rows="3"><?= h($editCoach['coach_bio'] ?? '') ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Spesialisasi</label>
                            <textarea class="form-control" name="specialties_text" rows="2"><?= h($editCoach['specialties_text'] ?? '') ?></textarea>
                        </div>

                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">Coach Type</label>
                                <?php $coachType = $editCoach['coach_type'] ?? 'external'; ?>
                                <select class="form-select" name="coach_type">
                                    <option value="internal" <?= $coachType === 'internal' ? 'selected' : '' ?>>Internal</option>
                                    <option value="external" <?= $coachType === 'external' ? 'selected' : '' ?>>External</option>
                                </select>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">Rate Type</label>
                                <?php $rateType = $editCoach['rate_type'] ?? 'free'; ?>
                                <select class="form-select" name="rate_type">
                                    <option value="free" <?= $rateType === 'free' ? 'selected' : '' ?>>Free</option>
                                    <option value="paid" <?= $rateType === 'paid' ? 'selected' : '' ?>>Paid</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Rate Text</label>
                            <input class="form-control" type="text" name="rate_text" value="<?= h($editCoach['rate_text'] ?? '') ?>" placeholder="contoh: Rp 250.000 / sesi">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Visibility</label>
                            <?php $visibility = $editCoach['visibility'] ?? 'public'; ?>
                            <select class="form-select" name="visibility">
                                <option value="public" <?= $visibility === 'public' ? 'selected' : '' ?>>Public</option>
                                <option value="private" <?= $visibility === 'private' ? 'selected' : '' ?>>Private</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Department Scope</label>
                            <input class="form-control" type="text" name="department_scope" value="<?= h($editCoach['department_scope'] ?? '') ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Foto Coach (JPG/PNG/WEBP, max 2MB)</label>
                            <input class="form-control" type="file" name="photo" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                        </div>
                        <?php if (!empty($editCoach['photo_path'])): ?>
                            <div class="mb-3">
                                <img src="../<?= h($editCoach['photo_path']) ?>" alt="Preview" class="preview-img">
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" id="remove_image" name="remove_image">
                                    <label class="form-check-label" for="remove_image">Hapus foto saat simpan</label>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label">Trusted Badge (opsional, JPG/PNG/WEBP)</label>
                            <input class="form-control" type="file" name="trusted_badge" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                        </div>
                        <?php if (!empty($editCoach['trusted_badge_path'])): ?>
                            <div class="mb-3">
                                <img src="../<?= h($editCoach['trusted_badge_path']) ?>" alt="Trusted Badge" class="preview-img">
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" id="remove_badge" name="remove_badge">
                                    <label class="form-check-label" for="remove_badge">Hapus badge saat simpan</label>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="form-check form-switch mb-3">
                            <?php $active = (int) ($editCoach['is_active'] ?? 1); ?>
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" <?= $active === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label" for="is_active">Aktif</label>
                        </div>
                        <div class="form-check form-switch mb-3">
                            <?php $blacklisted = (int) ($editCoach['is_blacklisted'] ?? 0); ?>
                            <input class="form-check-input" type="checkbox" id="is_blacklisted" name="is_blacklisted" <?= $blacklisted === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label" for="is_blacklisted">Blacklist</label>
                        </div>
                        <div class="form-check form-switch mb-3">
                            <?php $trusted = (int) ($editCoach['is_trusted'] ?? 0); ?>
                            <input class="form-check-input" type="checkbox" id="is_trusted" name="is_trusted" <?= $trusted === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label" for="is_trusted">Trusted</label>
                        </div>

                        <button class="btn btn-primary" type="submit"><?= $editCoach ? 'Update' : 'Simpan' ?></button>
                        <?php if ($editCoach): ?>
                            <a class="btn btn-light ms-1" href="coach.php">Batal</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h6 class="mb-3"><?= $editSession ? 'Edit Session' : 'Tambah Session' ?></h6>
                    <form method="post">
                        <input type="hidden" name="action" value="save_session">
                        <input type="hidden" name="id" value="<?= (int) ($editSession['id_coach_sessions'] ?? 0) ?>">

                        <div class="mb-3">
                            <label class="form-label">Coach</label>
                            <select class="form-select" name="id_coaches" required>
                                <option value="">Pilih coach</option>
                                <?php foreach ($coaches as $coach): ?>
                                    <?php $selected = (int)($editSession['id_coaches'] ?? 0) === (int)$coach['id_coaches']; ?>
                                    <option value="<?= (int)$coach['id_coaches'] ?>" <?= $selected ? 'selected' : '' ?>>
                                        <?= h($coach['coach_name'] ?? 'Coach') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Requester</label>
                            <select class="form-select" name="id_users" required>
                                <option value="">Pilih user</option>
                                <?php foreach ($users as $user): ?>
                                    <?php $selected = (int)($editSession['id_users'] ?? 0) === (int)$user['id_users']; ?>
                                    <option value="<?= (int)$user['id_users'] ?>" <?= $selected ? 'selected' : '' ?>>
                                        <?= h($user['name'] ?: 'User') ?> (<?= h($user['email'] ?: 'no-email') ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">Tanggal</label>
                                <input class="form-control" type="date" name="session_date" required value="<?= h($editSession['session_date'] ?? '') ?>">
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">Status</label>
                                <input class="form-control" type="text" name="status" value="<?= h($editSession['status'] ?? '') ?>" placeholder="scheduled/completed">
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">Start Time</label>
                                <input class="form-control" type="time" name="start_time" value="<?= h($editSession['start_time'] ?? '') ?>">
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">End Time</label>
                                <input class="form-control" type="time" name="end_time" value="<?= h($editSession['end_time'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Lokasi</label>
                            <input class="form-control" type="text" name="location_text" value="<?= h($editSession['location_text'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Catatan</label>
                            <textarea class="form-control" name="notes" rows="3"><?= h($editSession['notes'] ?? '') ?></textarea>
                        </div>

                        <button class="btn btn-primary" type="submit"><?= $editSession ? 'Update' : 'Simpan' ?></button>
                        <?php if ($editSession): ?>
                            <a class="btn btn-light ms-1" href="coach.php">Batal</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-7">
            <div class="card mb-3">
                <div class="card-body">
                    <h6 class="mb-3">Daftar Coach</h6>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Coach</th>
                                    <th>Type</th>
                                    <th>Rate</th>
                                    <th>Visibility</th>
                                    <th>Foto</th>
                                    <th>Trusted</th>
                                    <th>Blacklist</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (!$coaches): ?>
                                <tr><td colspan="9" class="text-center">Belum ada coach.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($coaches as $coach): ?>
                                <tr>
                                    <td>
                                        <?= h($coach['coach_name'] ?? '') ?>
                                        <div class="text-muted small"><?= h($coach['coach_email'] ?? '') ?></div>
                                    </td>
                                    <td><?= h($coach['coach_type'] ?? '') ?></td>
                                    <td><?= h($coach['rate_type'] ?? '') ?> <?= $coach['rate_text'] ? '(' . h($coach['rate_text']) . ')' : '' ?></td>
                                    <td><?= h($coach['visibility'] ?? '') ?></td>
                                    <td>
                                        <?php if (!empty($coach['photo_path'])): ?>
                                            <img src="../<?= h($coach['photo_path']) ?>" alt="Coach" class="preview-img">
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ((int)($coach['is_trusted'] ?? 0) === 1): ?>
                                            <?php if (!empty($coach['trusted_badge_path'])): ?>
                                                <img src="../<?= h($coach['trusted_badge_path']) ?>" alt="Trusted" class="preview-img">
                                            <?php else: ?>
                                                <span class="badge bg-info">Trusted</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= ((int)$coach['is_blacklisted'] === 1) ? 'bg-danger' : 'bg-secondary' ?>">
                                            <?= ((int)$coach['is_blacklisted'] === 1) ? 'Blacklisted' : 'No' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?= ((int)$coach['is_active'] === 1) ? 'bg-success' : 'bg-secondary' ?>">
                                            <?= ((int)$coach['is_active'] === 1) ? 'Aktif' : 'Nonaktif' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a class="btn btn-sm btn-outline-primary" href="coach.php?edit_coach=<?= (int)$coach['id_coaches'] ?>">Edit</a>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="action" value="toggle_coach_trusted">
                                            <input type="hidden" name="id" value="<?= (int)$coach['id_coaches'] ?>">
                                            <input type="hidden" name="next_trusted" value="<?= ((int)$coach['is_trusted'] === 1) ? 0 : 1 ?>">
                                            <button class="btn btn-sm <?= ((int)$coach['is_trusted'] === 1) ? 'btn-warning' : 'btn-info' ?>" type="submit">
                                                <?= ((int)$coach['is_trusted'] === 1) ? 'Untrust' : 'Trust' ?>
                                            </button>
                                        </form>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="action" value="toggle_coach_blacklist">
                                            <input type="hidden" name="id" value="<?= (int)$coach['id_coaches'] ?>">
                                            <input type="hidden" name="next_blacklist" value="<?= ((int)$coach['is_blacklisted'] === 1) ? 0 : 1 ?>">
                                            <button class="btn btn-sm <?= ((int)$coach['is_blacklisted'] === 1) ? 'btn-success' : 'btn-danger' ?>" type="submit">
                                                <?= ((int)$coach['is_blacklisted'] === 1) ? 'Unblacklist' : 'Blacklist' ?>
                                            </button>
                                        </form>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="action" value="toggle_coach_active">
                                            <input type="hidden" name="id" value="<?= (int)$coach['id_coaches'] ?>">
                                            <input type="hidden" name="next_active" value="<?= ((int)$coach['is_active'] === 1) ? 0 : 1 ?>">
                                            <button class="btn btn-sm <?= ((int)$coach['is_active'] === 1) ? 'btn-warning' : 'btn-success' ?>" type="submit">
                                                <?= ((int)$coach['is_active'] === 1) ? 'Inactive' : 'Activate' ?>
                                            </button>
                                        </form>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Hapus coach ini?');">
                                            <input type="hidden" name="action" value="delete_coach">
                                            <input type="hidden" name="id" value="<?= (int)$coach['id_coaches'] ?>">
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
                    <h6 class="mb-3">Daftar Coach Sessions</h6>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Coach</th>
                                    <th>Requester</th>
                                    <th>Tanggal</th>
                                    <th>Waktu</th>
                                    <th>Status</th>
                                    <th>Coach Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (!$sessions): ?>
                                <tr><td colspan="7" class="text-center">Belum ada sesi.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($sessions as $session): ?>
                                <tr>
                                    <td><?= h($session['coach_name'] ?? '-') ?></td>
                                    <td>
                                        <?= h($session['requester_name'] ?? 'User') ?>
                                        <div class="text-muted small"><?= h($session['requester_email'] ?? '') ?></div>
                                    </td>
                                    <td><?= h($session['session_date'] ?? '-') ?></td>
                                    <td><?= h(($session['start_time'] ?? '-') . ' - ' . ($session['end_time'] ?? '-')) ?></td>
                                    <td><?= h($session['status'] ?? '-') ?></td>
                                    <td>
                                        <span class="badge <?= ((int)$session['coach_active'] === 1) ? 'bg-success' : 'bg-secondary' ?>">
                                            <?= ((int)$session['coach_active'] === 1) ? 'Aktif' : 'Nonaktif' ?>
                                        </span>
                                        <span class="badge <?= ((int)$session['coach_blacklisted'] === 1) ? 'bg-danger' : 'bg-secondary' ?>">
                                            <?= ((int)$session['coach_blacklisted'] === 1) ? 'Blacklisted' : 'OK' ?>
                                        </span>
                                        <?php if ((int)($session['coach_trusted'] ?? 0) === 1): ?>
                                            <span class="badge bg-info">Trusted</span>
                                        <?php endif; ?>
                                        <?php if (!empty($session['id_coaches'])): ?>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="action" value="toggle_coach_active">
                                                <input type="hidden" name="id" value="<?= (int)$session['id_coaches'] ?>">
                                                <input type="hidden" name="next_active" value="<?= ((int)$session['coach_active'] === 1) ? 0 : 1 ?>">
                                                <button class="btn btn-sm <?= ((int)$session['coach_active'] === 1) ? 'btn-warning' : 'btn-success' ?>" type="submit">
                                                    <?= ((int)$session['coach_active'] === 1) ? 'Inactive' : 'Activate' ?>
                                                </button>
                                            </form>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="action" value="toggle_coach_trusted">
                                                <input type="hidden" name="id" value="<?= (int)$session['id_coaches'] ?>">
                                                <input type="hidden" name="next_trusted" value="<?= ((int)$session['coach_trusted'] === 1) ? 0 : 1 ?>">
                                                <button class="btn btn-sm <?= ((int)$session['coach_trusted'] === 1) ? 'btn-warning' : 'btn-info' ?>" type="submit">
                                                    <?= ((int)$session['coach_trusted'] === 1) ? 'Untrust' : 'Trust' ?>
                                                </button>
                                            </form>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="action" value="toggle_coach_blacklist">
                                                <input type="hidden" name="id" value="<?= (int)$session['id_coaches'] ?>">
                                                <input type="hidden" name="next_blacklist" value="<?= ((int)$session['coach_blacklisted'] === 1) ? 0 : 1 ?>">
                                                <button class="btn btn-sm <?= ((int)$session['coach_blacklisted'] === 1) ? 'btn-success' : 'btn-danger' ?>" type="submit">
                                                    <?= ((int)$session['coach_blacklisted'] === 1) ? 'Unblacklist' : 'Blacklist' ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a class="btn btn-sm btn-outline-primary" href="coach.php?edit_session=<?= (int)$session['id_coach_sessions'] ?>">Edit</a>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Hapus sesi ini?');">
                                            <input type="hidden" name="action" value="delete_session">
                                            <input type="hidden" name="id" value="<?= (int)$session['id_coach_sessions'] ?>">
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
</body>
</html>
