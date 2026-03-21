<?php
session_start();
require_once 'includes/db.php';

$pageTitle = 'Coach Sessions';
include 'includes/header.php';

$userId = (int) ($_SESSION['user_id'] ?? 1);
$userName = trim((string) ($_SESSION['user_name'] ?? ''));
$userDepartment = trim((string) ($_SESSION['user_department'] ?? ''));

if ($userDepartment === '' && $userId > 0) {
    $userStmt = $pdo->prepare('SELECT name, department FROM users WHERE id_users = ? LIMIT 1');
    $userStmt->execute([$userId]);
    $userRow = $userStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    if ($userName === '') {
        $userName = trim((string) ($userRow['name'] ?? ''));
    }
    $userDepartment = trim((string) ($userRow['department'] ?? ''));
}
if ($userDepartment === '') {
    $userDepartment = 'General';
}

$pdo->exec("
    CREATE TABLE IF NOT EXISTS coaches (
        id_coaches INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        id_users BIGINT(20) NULL,
        coach_name VARCHAR(150) NOT NULL,
        coach_email VARCHAR(190) NOT NULL DEFAULT '',
        coach_phone VARCHAR(50) NOT NULL DEFAULT '',
        coach_bio TEXT NOT NULL,
        specialties_text TEXT NOT NULL,
        coach_type VARCHAR(20) NOT NULL DEFAULT 'external',
        rate_type VARCHAR(12) NOT NULL DEFAULT 'free',
        rate_text VARCHAR(140) NOT NULL DEFAULT '',
        visibility VARCHAR(12) NOT NULL DEFAULT 'public',
        department_scope VARCHAR(100) NOT NULL DEFAULT '',
        id_users_created_by BIGINT(20) NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_coaches_user_id (id_users),
        KEY idx_coaches_visibility (is_active, visibility, department_scope, created_at)
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

autoTrustCoaches($pdo, 100);

$pdo->exec("
    CREATE TABLE IF NOT EXISTS coach_sessions (
        id_coach_sessions INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        id_coaches INT UNSIGNED NOT NULL,
        id_users BIGINT(20) NOT NULL,
        session_date DATE NOT NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        location_text VARCHAR(190) NOT NULL DEFAULT '',
        notes TEXT NOT NULL,
        status VARCHAR(16) NOT NULL DEFAULT 'requested',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_sessions_coach_status (id_coaches, status, created_at),
        KEY idx_sessions_requester (id_users, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$tab = (string) ($_GET['tab'] ?? 'request');
$tab = in_array($tab, ['request', 'mine', 'manage'], true) ? $tab : 'request';

$errors = [];
$success = '';

function normalizeStatus(string $status): string {
    $status = strtolower(trim($status));
    return in_array($status, ['requested', 'approved', 'rejected', 'cancelled', 'done'], true) ? $status : 'requested';
}

function isValidTimeOrder(string $start, string $end): bool {
    $s = strtotime('1970-01-01 ' . $start);
    $e = strtotime('1970-01-01 ' . $end);
    return $s !== false && $e !== false && $e > $s;
}

$coachSelfStmt = $pdo->prepare('SELECT id_coaches, coach_name FROM coaches WHERE id_users = ? AND is_active = 1 AND is_blacklisted = 0 LIMIT 1');
$coachSelfStmt->execute([$userId]);
$selfCoach = $coachSelfStmt->fetch(PDO::FETCH_ASSOC) ?: null;
$isCoach = (bool) $selfCoach;
$selfCoachId = $isCoach ? (int) ($selfCoach['id_coaches'] ?? 0) : 0;

function canSeeCoach(array $coachRow, string $userDepartment): bool {
    $visibility = strtolower(trim((string) ($coachRow['visibility'] ?? 'public')));
    $scope = (string) ($coachRow['department_scope'] ?? '');
    if ($visibility === 'public') return true;
    if ($visibility !== 'private') return false;
    return $scope !== '' && $scope === $userDepartment;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'request_session') {
        $tab = 'request';

        $coachId = (int) ($_POST['id_coaches'] ?? 0);
        $sessionDate = trim((string) ($_POST['session_date'] ?? ''));
        $startTime = trim((string) ($_POST['start_time'] ?? ''));
        $endTime = trim((string) ($_POST['end_time'] ?? ''));
        $locationText = trim((string) ($_POST['location_text'] ?? ''));
        $notes = trim((string) ($_POST['notes'] ?? ''));

        if ($coachId <= 0) { $errors[] = 'Coach wajib dipilih.'; }
        if ($sessionDate === '') { $errors[] = 'Tanggal wajib diisi.'; }
        if ($startTime === '' || $endTime === '') { $errors[] = 'Jam mulai & selesai wajib diisi.'; }
        if ($startTime !== '' && $endTime !== '' && !isValidTimeOrder($startTime, $endTime)) {
            $errors[] = 'Jam selesai harus setelah jam mulai.';
        }
        if ($notes === '') { $errors[] = 'Catatan kebutuhan sesi wajib diisi.'; }

        $coachRow = null;
        if (!$errors) {
            $coachStmt = $pdo->prepare("
                SELECT id_coaches, coach_name, visibility, department_scope, is_active, is_blacklisted
                FROM coaches
                WHERE id_coaches = ? AND is_active = 1 AND is_blacklisted = 0
                LIMIT 1
            ");
            $coachStmt->execute([$coachId]);
            $coachRow = $coachStmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if (!$coachRow) {
                $errors[] = 'Coach tidak ditemukan / tidak aktif.';
            } elseif (!canSeeCoach($coachRow, $userDepartment)) {
                $errors[] = 'Coach ini tidak tersedia untuk scope kamu.';
            }
        }

        if (!$errors) {
            $insert = $pdo->prepare("
                INSERT INTO coach_sessions
                (id_coaches, id_users, session_date, start_time, end_time, location_text, notes, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'requested')
            ");
            $insert->execute([
                $coachId,
                $userId,
                $sessionDate,
                $startTime,
                $endTime,
                $locationText,
                $notes,
            ]);
            $success = 'Request sesi berhasil dibuat. Tunggu coach approve.';
        }
    } elseif ($action === 'update_session_status') {
        $targetTab = (string) ($_POST['return_tab'] ?? 'mine');
        $tab = in_array($targetTab, ['mine', 'manage'], true) ? $targetTab : 'mine';

        $sessionId = (int) ($_POST['session_id'] ?? 0);
        $newStatus = normalizeStatus((string) ($_POST['new_status'] ?? 'requested'));

        if ($sessionId <= 0) { $errors[] = 'Session tidak valid.'; }

        $sess = null;
        if (!$errors) {
            $sessStmt = $pdo->prepare("
                SELECT s.*, c.id_users AS coach_user_id
                FROM coach_sessions s
                JOIN coaches c ON c.id_coaches = s.id_coaches
                WHERE s.id_coach_sessions = ?
                LIMIT 1
            ");
            $sessStmt->execute([$sessionId]);
            $sess = $sessStmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if (!$sess) {
                $errors[] = 'Session tidak ditemukan.';
            }
        }

        if (!$errors && $sess) {
            $statusNow = normalizeStatus((string) ($sess['status'] ?? 'requested'));
            $requesterId = (int) ($sess['id_users'] ?? 0);
            $coachUserId = (int) ($sess['coach_user_id'] ?? 0);

            $isRequester = ($requesterId > 0 && $requesterId === $userId);
            $isCoachOwner = ($coachUserId > 0 && $coachUserId === $userId);

            $allowed = false;

            if ($isRequester) {
                if ($newStatus === 'cancelled' && in_array($statusNow, ['requested', 'approved'], true)) {
                    $allowed = true;
                }
            }

            if ($isCoachOwner) {
                if (in_array($newStatus, ['approved', 'rejected'], true) && $statusNow === 'requested') {
                    $allowed = true;
                }
                if ($newStatus === 'done' && $statusNow === 'approved') {
                    $allowed = true;
                }
            }

            if (!$allowed) {
                $errors[] = 'Aksi tidak diizinkan untuk status saat ini.';
            } else {
                $up = $pdo->prepare('UPDATE coach_sessions SET status = ? WHERE id_coach_sessions = ?');
                $up->execute([$newStatus, $sessionId]);
                $success = 'Status sesi berhasil diperbarui.';
            }
        }
    }
}

$coachesStmt = $pdo->prepare("
    SELECT id_coaches, coach_name, rate_type, rate_text, visibility, department_scope, is_trusted
    FROM coaches
    WHERE is_active = 1 AND is_blacklisted = 0
      AND (visibility = 'public' OR (visibility = 'private' AND department_scope = ?))
    ORDER BY created_at DESC, id_coaches DESC
");
$coachesStmt->execute([$userDepartment]);
$availableCoaches = $coachesStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$mineStmt = $pdo->prepare("
    SELECT s.id_coach_sessions, s.session_date, s.start_time, s.end_time, s.location_text, s.notes, s.status, s.created_at,
           c.coach_name, c.rate_type, c.rate_text, c.is_trusted, c.trusted_badge_path
    FROM coach_sessions s
    JOIN coaches c ON c.id_coaches = s.id_coaches
    WHERE s.id_users = ?
    ORDER BY s.created_at DESC, s.id_coach_sessions DESC
");
$mineStmt->execute([$userId]);
$mySessions = $mineStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$managedSessions = [];
if ($isCoach && $selfCoachId > 0) {
    $manageStmt = $pdo->prepare("
        SELECT s.id_coach_sessions, s.session_date, s.start_time, s.end_time, s.location_text, s.notes, s.status, s.created_at,
               u.name AS requester_name, u.email AS requester_email
        FROM coach_sessions s
        JOIN users u ON u.id_users = s.id_users
        WHERE s.id_coaches = ?
        ORDER BY s.created_at DESC, s.id_coach_sessions DESC
    ");
    $manageStmt->execute([$selfCoachId]);
    $managedSessions = $manageStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
?>

<style>
.coach-app { width: min(980px, 92%); margin: 18px auto 0; padding-bottom: 90px; }
.coach-hero {
    background: linear-gradient(140deg, #0f766e 0%, #22c55e 55%, #7dd3fc 100%);
    color: #fff; border-radius: 24px; padding: 26px 22px; box-shadow: 0 16px 32px rgba(15,118,110,.25);
}
.coach-hero h1 { margin: 0 0 8px; font-size: 28px; }
.coach-hero p { margin: 0; font-size: 14px; max-width: 760px; opacity: .95; }
.tabs { display:flex; flex-wrap:wrap; gap:10px; margin-top:14px; }
.tab { display:inline-flex; align-items:center; justify-content:center; text-decoration:none; border-radius:999px; padding:10px 14px; font-size:13px; font-weight:800; border:1px solid rgba(255,255,255,.35); color:#fff; background:rgba(255,255,255,.12); }
.tab.active { background:#fff; color:#0f172a; border-color:#fff; }
.section { margin-top: 16px; background: rgba(255,255,255,.92); border: 1px solid rgba(125,211,252,.35); border-radius: 20px; padding: 18px; box-shadow: 0 8px 22px rgba(15,23,42,.07); }
.title { margin: 0 0 8px; font-size: 20px; color: #0f172a; }
.sub { margin: 0 0 14px; color: #475569; font-size: 13px; }
.form-message { margin-bottom:12px; border-radius:12px; padding:10px 12px; font-size:13px; }
.form-error { background:#fef2f2; color:#991b1b; border:1px solid #fecaca; }
.form-success { background:#ecfdf5; color:#166534; border:1px solid #bbf7d0; }
.input-grid { display:grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap:12px; }
.input-group { display:flex; flex-direction:column; gap:6px; margin-bottom:10px; }
.input-group label { font-size:13px; font-weight:800; color:#0f172a; }
.input-group input,.input-group textarea,.input-group select { width:100%; min-height:44px; border:1px solid #cbd5e1; border-radius:12px; padding:10px 12px; font-size:13px; background:#fff; }
.input-group textarea { min-height:96px; resize:vertical; }
.btn-row { display:flex; flex-wrap:wrap; gap:10px; margin-top:12px; }
.btn { display:inline-flex; align-items:center; justify-content:center; border-radius:999px; padding:10px 16px; font-size:13px; font-weight:900; border:1px solid #cbd5e1; background:#fff; cursor:pointer; min-height:44px; }
.btn.primary { color:#fff; border:none; background:linear-gradient(135deg,#0f766e,#22c55e); }
.btn.danger { color:#991b1b; background:#fef2f2; border-color:#fecaca; }
.btn.ok { color:#166534; background:#ecfdf5; border-color:#bbf7d0; }
.btn.warn { color:#9a3412; background:#fff7ed; border-color:#fed7aa; }
.list { display:grid; grid-template-columns: 1fr; gap:12px; }
.card { background:#fff; border:1px solid #e2e8f0; border-radius:16px; padding:14px; }
.card-top { display:flex; justify-content:space-between; gap:10px; flex-wrap:wrap; align-items:flex-start; }
.card h3 { margin:0; font-size:15px; color:#0f172a; }
.meta { margin:8px 0 0; font-size:13px; color:#475569; }
.badge { font-size:11px; font-weight:900; border-radius:999px; padding:6px 10px; border:1px solid #e2e8f0; background:#f8fafc; color:#0f172a; text-transform:capitalize; }
.badge.requested { background:#eff6ff; border-color:#bfdbfe; color:#1d4ed8; }
.badge.approved { background:#ecfdf5; border-color:#bbf7d0; color:#166534; }
.badge.rejected, .badge.cancelled { background:#fef2f2; border-color:#fecaca; color:#991b1b; }
.badge.done { background:#f0f9ff; border-color:#bae6fd; color:#0369a1; }
.hint { font-size:12px; color:#64748b; margin-top:6px; }
.inline { display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
@media (max-width: 900px) { .input-grid { grid-template-columns:1fr; } }
</style>

<main class="coach-app">
    <section class="coach-hero">
        <h1>Coach Sessions</h1>
        <p>Request jadwal sesi dengan coach, lalu coach internal bisa approve/reject. Kamu juga bisa cancel request kamu sendiri.</p>
        <nav class="tabs" aria-label="Coach sessions menu">
            <a class="tab <?= $tab === 'request' ? 'active' : '' ?>" href="coach_sessions.php?tab=request">Request Sesi</a>
            <a class="tab <?= $tab === 'mine' ? 'active' : '' ?>" href="coach_sessions.php?tab=mine">Sesi Saya</a>
            <?php if ($isCoach): ?>
                <a class="tab <?= $tab === 'manage' ? 'active' : '' ?>" href="coach_sessions.php?tab=manage">Kelola Request</a>
            <?php endif; ?>
        </nav>
    </section>

    <?php if ($errors): ?>
        <section class="section">
            <div class="form-message form-error">
                <?php foreach ($errors as $e): ?>
                    <div><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
    <?php if ($success !== ''): ?>
        <section class="section">
            <div class="form-message form-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
        </section>
    <?php endif; ?>

    <?php if ($tab === 'request'): ?>
        <section class="section">
            <h2 class="title">Buat request sesi</h2>
            <p class="sub">Coach yang muncul di sini adalah coach public + coach private yang scope-nya sama dengan department kamu (<?= htmlspecialchars($userDepartment, ENT_QUOTES, 'UTF-8') ?>).</p>

            <?php if (!$availableCoaches): ?>
                <div class="hint">Belum ada coach. Tambahkan dulu dari halaman Coach Directory.</div>
            <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="action" value="request_session">

                    <div class="input-grid">
                        <div class="input-group">
                            <label for="id_coaches">Pilih coach</label>
                            <select id="id_coaches" name="id_coaches" required>
                                <option value="">-- pilih --</option>
                                <?php $selCoach = (string) ($_POST['id_coaches'] ?? ''); ?>
                                <?php foreach ($availableCoaches as $c): ?>
                                    <?php
                                        $cid = (int) ($c['id_coaches'] ?? 0);
                                        $label = (string) ($c['coach_name'] ?? '');
                                        $rateType = strtolower(trim((string) ($c['rate_type'] ?? 'free')));
                                        $rateText = trim((string) ($c['rate_text'] ?? ''));
                                        $suffix = $rateType === 'paid' ? (' — Paid: ' . ($rateText !== '' ? $rateText : 'lihat info')) : ' — Free';
                                        if (!empty($c['is_trusted'])) {
                                            $suffix = ' — Trusted' . $suffix;
                                        }
                                    ?>
                                    <option value="<?= $cid ?>"<?= ((string) $cid) === $selCoach ? ' selected' : '' ?>>
                                        <?= htmlspecialchars($label . $suffix, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="input-group">
                            <label for="session_date">Tanggal</label>
                            <input id="session_date" type="date" name="session_date" value="<?= htmlspecialchars((string) ($_POST['session_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>
                    </div>

                    <div class="input-grid">
                        <div class="input-group">
                            <label for="start_time">Jam mulai</label>
                            <input id="start_time" type="time" name="start_time" value="<?= htmlspecialchars((string) ($_POST['start_time'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>
                        <div class="input-group">
                            <label for="end_time">Jam selesai</label>
                            <input id="end_time" type="time" name="end_time" value="<?= htmlspecialchars((string) ($_POST['end_time'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>
                    </div>

                    <div class="input-group">
                        <label for="location_text">Lokasi / media (opsional)</label>
                        <input id="location_text" type="text" name="location_text" value="<?= htmlspecialchars((string) ($_POST['location_text'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="contoh: Meeting Room 2 / Zoom / Gym partner">
                    </div>

                    <div class="input-group">
                        <label for="notes">Catatan kebutuhan sesi</label>
                        <textarea id="notes" name="notes" required><?= htmlspecialchars((string) ($_POST['notes'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                        <div class="hint">Contoh: goal fat loss 8 minggu, fokus mobility, riwayat cedera, dsb.</div>
                    </div>

                    <button class="btn primary" type="submit">Kirim Request</button>
                </form>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($tab === 'mine'): ?>
        <section class="section">
            <h2 class="title">Sesi saya</h2>
            <p class="sub">Riwayat request kamu ke coach.</p>

            <?php if (!$mySessions): ?>
                <div class="hint">Belum ada request sesi. Buat dari tab <b>Request Sesi</b>.</div>
            <?php else: ?>
                <div class="list">
                    <?php foreach ($mySessions as $s): ?>
                        <?php
                            $st = normalizeStatus((string) ($s['status'] ?? 'requested'));
                            $canCancel = in_array($st, ['requested', 'approved'], true);
                        ?>
                        <article class="card">
                            <div class="card-top">
                                <h3><?= htmlspecialchars((string) ($s['coach_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h3>
                                <span class="badge <?= htmlspecialchars($st, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($st, ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                            <?php if (!empty($s['is_trusted'])): ?>
                                <div class="meta"><strong>Trusted Coach</strong></div>
                            <?php endif; ?>
                            <div class="meta">
                                Tanggal: <?= htmlspecialchars((string) ($s['session_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?>,
                                <?= htmlspecialchars((string) ($s['start_time'] ?? ''), ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars((string) ($s['end_time'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                <?php if (trim((string) ($s['location_text'] ?? '')) !== ''): ?>
                                    <br>Lokasi: <?= htmlspecialchars((string) ($s['location_text'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                <?php endif; ?>
                            </div>
                            <div class="meta"><?= nl2br(htmlspecialchars((string) ($s['notes'] ?? ''), ENT_QUOTES, 'UTF-8')) ?></div>
                            <div class="meta">
                                <?= strtolower(trim((string) ($s['rate_type'] ?? 'free'))) === 'paid'
                                    ? ('Rate: ' . htmlspecialchars((string) ($s['rate_text'] ?? ''), ENT_QUOTES, 'UTF-8'))
                                    : 'Rate: Free / Sponsored'
                                ?>
                            </div>

                            <?php if ($canCancel): ?>
                                <form method="POST" class="btn-row">
                                    <input type="hidden" name="action" value="update_session_status">
                                    <input type="hidden" name="return_tab" value="mine">
                                    <input type="hidden" name="session_id" value="<?= (int) ($s['id_coach_sessions'] ?? 0) ?>">
                                    <input type="hidden" name="new_status" value="cancelled">
                                    <button class="btn danger" type="submit">Cancel</button>
                                </form>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($tab === 'manage' && $isCoach): ?>
        <section class="section">
            <h2 class="title">Kelola request (coach)</h2>
            <p class="sub">Kamu terdaftar sebagai coach: <b><?= htmlspecialchars((string) ($selfCoach['coach_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></b>. Di sini kamu bisa approve/reject, atau mark done.</p>

            <?php if (!$managedSessions): ?>
                <div class="hint">Belum ada request masuk.</div>
            <?php else: ?>
                <div class="list">
                    <?php foreach ($managedSessions as $s): ?>
                        <?php
                            $st = normalizeStatus((string) ($s['status'] ?? 'requested'));
                            $canApproveReject = ($st === 'requested');
                            $canDone = ($st === 'approved');
                        ?>
                        <article class="card">
                            <div class="card-top">
                                <h3><?= htmlspecialchars((string) ($s['requester_name'] ?? 'User'), ENT_QUOTES, 'UTF-8') ?></h3>
                                <span class="badge <?= htmlspecialchars($st, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($st, ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                            <div class="meta">
                                Email: <?= htmlspecialchars((string) ($s['requester_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?><br>
                                Tanggal: <?= htmlspecialchars((string) ($s['session_date'] ?? ''), ENT_QUOTES, 'UTF-8') ?>,
                                <?= htmlspecialchars((string) ($s['start_time'] ?? ''), ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars((string) ($s['end_time'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                <?php if (trim((string) ($s['location_text'] ?? '')) !== ''): ?>
                                    <br>Lokasi: <?= htmlspecialchars((string) ($s['location_text'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                <?php endif; ?>
                            </div>
                            <div class="meta"><?= nl2br(htmlspecialchars((string) ($s['notes'] ?? ''), ENT_QUOTES, 'UTF-8')) ?></div>

                            <form method="POST" class="btn-row">
                                <input type="hidden" name="action" value="update_session_status">
                                <input type="hidden" name="return_tab" value="manage">
                                <input type="hidden" name="session_id" value="<?= (int) ($s['id_coach_sessions'] ?? 0) ?>">

                                <?php if ($canApproveReject): ?>
                                    <button class="btn ok" type="submit" name="new_status" value="approved">Approve</button>
                                    <button class="btn danger" type="submit" name="new_status" value="rejected">Reject</button>
                                <?php endif; ?>
                                <?php if ($canDone): ?>
                                    <button class="btn warn" type="submit" name="new_status" value="done">Mark Done</button>
                                <?php endif; ?>
                            </form>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($tab === 'manage' && !$isCoach): ?>
        <section class="section">
            <h2 class="title">Kelola request</h2>
            <p class="sub">Menu ini hanya muncul kalau akun kamu terdaftar sebagai coach internal.</p>
            <a class="btn primary" href="coach.php?tab=register" style="text-decoration:none;">Daftar jadi coach</a>
        </section>
    <?php endif; ?>
</main>

<?php include 'includes/footer.php'; ?>
