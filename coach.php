<?php
session_start();
require_once 'includes/db.php';

$pageTitle = 'Coach Directory';
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
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT(20) NULL,
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
        created_by BIGINT(20) NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_coaches_user_id (user_id),
        KEY idx_coaches_visibility (is_active, visibility, department_scope, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$tab = (string) ($_GET['tab'] ?? 'directory');
$tab = in_array($tab, ['directory', 'invite', 'register'], true) ? $tab : 'directory';

$errors = [];
$success = '';

function normalizeCoachType(string $value): string {
    $value = strtolower(trim($value));
    return in_array($value, ['internal', 'external'], true) ? $value : 'external';
}

function normalizeRateType(string $value): string {
    $value = strtolower(trim($value));
    return in_array($value, ['free', 'paid'], true) ? $value : 'free';
}

function normalizeVisibility(string $value): string {
    $value = strtolower(trim($value));
    return in_array($value, ['public', 'private'], true) ? $value : 'public';
}

function buildSpecialties(string $raw): string {
    $parts = preg_split('/\r\n|\r|\n|,/', $raw) ?: [];
    $parts = array_values(array_filter(array_map('trim', $parts), static fn($v) => $v !== ''));
    $parts = array_slice(array_unique($parts), 0, 12);
    return implode("\n", $parts);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'invite_coach') {
        $tab = 'invite';

        $coachName = trim((string) ($_POST['coach_name'] ?? ''));
        $coachEmail = strtolower(trim((string) ($_POST['coach_email'] ?? '')));
        $coachPhone = trim((string) ($_POST['coach_phone'] ?? ''));
        $coachBio = trim((string) ($_POST['coach_bio'] ?? ''));
        $specialtiesText = buildSpecialties((string) ($_POST['specialties_text'] ?? ''));
        $rateType = normalizeRateType((string) ($_POST['rate_type'] ?? 'free'));
        $rateText = trim((string) ($_POST['rate_text'] ?? ''));
        $visibility = normalizeVisibility((string) ($_POST['visibility'] ?? 'public'));
        $departmentScope = trim((string) ($_POST['department_scope'] ?? ''));

        if ($coachName === '') { $errors[] = 'Nama coach wajib diisi.'; }
        if ($coachEmail === '' && $coachPhone === '') { $errors[] = 'Minimal isi email atau nomor WhatsApp.'; }
        if ($coachEmail !== '' && !filter_var($coachEmail, FILTER_VALIDATE_EMAIL)) { $errors[] = 'Format email coach belum valid.'; }
        if ($coachBio === '') { $errors[] = 'Bio singkat wajib diisi.'; }

        if ($rateType === 'paid' && $rateText === '') {
            $errors[] = 'Kalau paid, isi rate (contoh: Rp 250.000 / sesi).';
        }

        if ($visibility === 'private') {
            if ($departmentScope === '') {
                $departmentScope = $userDepartment;
            }
        } else {
            $departmentScope = '';
        }

        if (!$errors) {
            $insert = $pdo->prepare("
                INSERT INTO coaches
                (user_id, coach_name, coach_email, coach_phone, coach_bio, specialties_text, coach_type, rate_type, rate_text, visibility, department_scope, created_by)
                VALUES (NULL, ?, ?, ?, ?, ?, 'external', ?, ?, ?, ?, ?)
            ");
            $insert->execute([
                $coachName,
                $coachEmail,
                $coachPhone,
                $coachBio,
                $specialtiesText,
                $rateType,
                $rateText,
                $visibility,
                $departmentScope,
                $userId > 0 ? $userId : null,
            ]);
            $success = 'Coach berhasil diundang/ditambahkan ke directory.';
        }
    } elseif ($action === 'register_self') {
        $tab = 'register';

        $coachName = trim((string) ($_POST['coach_name'] ?? $userName));
        $coachBio = trim((string) ($_POST['coach_bio'] ?? ''));
        $specialtiesText = buildSpecialties((string) ($_POST['specialties_text'] ?? ''));
        $rateType = normalizeRateType((string) ($_POST['rate_type'] ?? 'free'));
        $rateText = trim((string) ($_POST['rate_text'] ?? ''));
        $visibility = normalizeVisibility((string) ($_POST['visibility'] ?? 'private'));
        $departmentScope = $userDepartment;

        if ($coachName === '') { $errors[] = 'Nama coach wajib diisi.'; }
        if ($coachBio === '') { $errors[] = 'Bio singkat wajib diisi.'; }
        if ($rateType === 'paid' && $rateText === '') {
            $errors[] = 'Kalau paid, isi rate (contoh: Rp 150.000 / sesi).';
        }

        if ($visibility === 'public') {
            $departmentScope = '';
        }

        if (!$errors) {
            $existing = $pdo->prepare('SELECT id FROM coaches WHERE user_id = ? LIMIT 1');
            $existing->execute([$userId]);
            $existingRow = $existing->fetch(PDO::FETCH_ASSOC);

            if ($existingRow) {
                $update = $pdo->prepare("
                    UPDATE coaches
                    SET coach_name = ?, coach_bio = ?, specialties_text = ?, coach_type = 'internal',
                        rate_type = ?, rate_text = ?, visibility = ?, department_scope = ?, is_active = 1
                    WHERE user_id = ?
                ");
                $update->execute([
                    $coachName,
                    $coachBio,
                    $specialtiesText,
                    $rateType,
                    $rateText,
                    $visibility,
                    $departmentScope,
                    $userId,
                ]);
                $success = 'Profil coach kamu berhasil diperbarui.';
            } else {
                $insert = $pdo->prepare("
                    INSERT INTO coaches
                    (user_id, coach_name, coach_email, coach_phone, coach_bio, specialties_text, coach_type, rate_type, rate_text, visibility, department_scope, created_by)
                    VALUES (?, ?, '', '', ?, ?, 'internal', ?, ?, ?, ?, ?)
                ");
                $insert->execute([
                    $userId,
                    $coachName,
                    $coachBio,
                    $specialtiesText,
                    $rateType,
                    $rateText,
                    $visibility,
                    $departmentScope,
                    $userId,
                ]);
                $success = 'Kamu berhasil terdaftar sebagai coach.';
            }
        }
    }
}

$coachStmt = $pdo->prepare("
    SELECT id, coach_name, coach_email, coach_phone, coach_bio, specialties_text, coach_type, rate_type, rate_text, visibility, department_scope, created_at
    FROM coaches
    WHERE is_active = 1
      AND (visibility = 'public' OR (visibility = 'private' AND department_scope = ?))
    ORDER BY created_at DESC, id DESC
");
$coachStmt->execute([$userDepartment]);
$coachRows = $coachStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$coaches = array_map(static function (array $row): array {
    $specialties = preg_split('/\r\n|\r|\n/', (string) ($row['specialties_text'] ?? '')) ?: [];
    $specialties = array_values(array_filter(array_map('trim', $specialties), static fn($v) => $v !== ''));
    $specialties = array_slice($specialties, 0, 12);

    $coachType = normalizeCoachType((string) ($row['coach_type'] ?? 'external'));
    $rateType = normalizeRateType((string) ($row['rate_type'] ?? 'free'));
    $visibility = normalizeVisibility((string) ($row['visibility'] ?? 'public'));

    return [
        'id' => (int) ($row['id'] ?? 0),
        'name' => (string) ($row['coach_name'] ?? ''),
        'email' => (string) ($row['coach_email'] ?? ''),
        'phone' => (string) ($row['coach_phone'] ?? ''),
        'bio' => (string) ($row['coach_bio'] ?? ''),
        'specialties' => $specialties,
        'coach_type' => $coachType,
        'rate_type' => $rateType,
        'rate_text' => (string) ($row['rate_text'] ?? ''),
        'visibility' => $visibility,
        'department_scope' => (string) ($row['department_scope'] ?? ''),
        'created_at' => (string) ($row['created_at'] ?? ''),
    ];
}, $coachRows);
?>

<style>
.coach-app { width: min(980px, 92%); margin: 18px auto 0; padding-bottom: 90px; }
.coach-hero {
    background: linear-gradient(140deg, #1d4ed8 0%, #0ea5e9 55%, #22c55e 100%);
    color: #fff; border-radius: 24px; padding: 26px 22px; box-shadow: 0 16px 32px rgba(2,132,199,.22);
}
.coach-hero h1 { margin: 0 0 8px; font-size: 28px; }
.coach-hero p { margin: 0; font-size: 14px; max-width: 720px; opacity: .95; }
.tabs { display:flex; flex-wrap:wrap; gap:10px; margin-top:14px; }
.tab { display:inline-flex; align-items:center; justify-content:center; text-decoration:none; border-radius:999px; padding:10px 14px; font-size:13px; font-weight:700; border:1px solid rgba(255,255,255,.35); color:#fff; background:rgba(255,255,255,.12); }
.tab.active { background:#fff; color:#0f172a; border-color:#fff; }
.coach-section { margin-top: 16px; background: rgba(255,255,255,.92); border: 1px solid rgba(147,197,253,.35); border-radius: 20px; padding: 18px; box-shadow: 0 8px 22px rgba(15,23,42,.07); }
.coach-title { margin: 0 0 8px; font-size: 20px; color: #0f172a; }
.coach-sub { margin: 0 0 14px; color: #475569; font-size: 13px; }
.grid-2 { display:grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 14px; }
.card { background:#fff; border:1px solid #e2e8f0; border-radius:16px; padding:14px; }
.card-top { display:flex; justify-content:space-between; gap:10px; align-items:flex-start; }
.coach-name { margin:0; font-size:16px; color:#0f172a; }
.badge { font-size:11px; font-weight:800; border-radius:999px; padding:6px 10px; white-space:nowrap; border:1px solid #e2e8f0; background:#f8fafc; color:#0f172a; }
.badge.internal { background:#ecfdf5; border-color:#bbf7d0; color:#166534; }
.badge.external { background:#eff6ff; border-color:#bfdbfe; color:#1d4ed8; }
.badge.private { background:#fff7ed; border-color:#fed7aa; color:#9a3412; }
.badge.public { background:#f0f9ff; border-color:#bae6fd; color:#0369a1; }
.meta { margin:8px 0 0; font-size:13px; color:#475569; }
.rate { margin:10px 0 0; font-size:13px; font-weight:800; color:#0f766e; }
.spec-row { margin-top:10px; display:flex; flex-wrap:wrap; gap:6px; }
.spec { font-size:11px; font-weight:700; color:#155e75; background:#ecfeff; border:1px solid #a5f3fc; border-radius:999px; padding:6px 9px; }
.form-message { margin-bottom:12px; border-radius:12px; padding:10px 12px; font-size:13px; }
.form-error { background:#fef2f2; color:#991b1b; border:1px solid #fecaca; }
.form-success { background:#ecfdf5; color:#166534; border:1px solid #bbf7d0; }
.input-grid { display:grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap:12px; }
.input-group { display:flex; flex-direction:column; gap:6px; margin-bottom:10px; }
.input-group label { font-size:13px; font-weight:700; color:#0f172a; }
.input-group input,.input-group textarea,.input-group select { width:100%; min-height:44px; border:1px solid #cbd5e1; border-radius:12px; padding:10px 12px; font-size:13px; background:#fff; }
.input-group textarea { min-height:96px; resize:vertical; }
.btn { display:inline-flex; align-items:center; justify-content:center; text-decoration:none; border-radius:999px; padding:10px 16px; font-size:13px; font-weight:800; border:none; cursor:pointer; min-height:44px; }
.btn-primary { color:#fff; background:linear-gradient(135deg,#1d4ed8,#0ea5e9); }
.hint { font-size:12px; color:#64748b; margin-top:6px; }
.empty { font-size:13px; color:#64748b; }
.dept-pill { display:inline-flex; align-items:center; gap:6px; font-size:12px; font-weight:800; border-radius:999px; padding:8px 12px; background:rgba(255,255,255,.2); border:1px solid rgba(255,255,255,.3); margin-top:12px; }

@media (max-width: 900px) { .grid-2,.input-grid { grid-template-columns:1fr; } }
</style>

<main class="coach-app">
    <section class="coach-hero">
        <h1>Coach Directory</h1>
        <p>Karyawan bisa undang coach (eksternal), atau daftar jadi coach (internal). Kamu bisa set coach ini <b>public</b> (lintas perusahaan) atau <b>private</b> (hanya terlihat di perusahaan/department kamu).</p>
        <div class="dept-pill">Scope kamu: <?= htmlspecialchars($userDepartment, ENT_QUOTES, 'UTF-8') ?></div>
        <div class="pill-row" style="margin-top:12px;">
            <a class="tab" href="coach_sessions.php" style="border-color:rgba(255,255,255,.35);">Buat / Kelola Sesi Coach</a>
        </div>
        <nav class="tabs" aria-label="Coach menu">
            <a class="tab <?= $tab === 'directory' ? 'active' : '' ?>" href="coach.php?tab=directory">Directory</a>
            <a class="tab <?= $tab === 'invite' ? 'active' : '' ?>" href="coach.php?tab=invite">Undang Coach</a>
            <a class="tab <?= $tab === 'register' ? 'active' : '' ?>" href="coach.php?tab=register">Daftar Jadi Coach</a>
        </nav>
    </section>

    <?php if ($tab === 'directory'): ?>
        <section class="coach-section">
            <h2 class="coach-title">Coach tersedia</h2>
            <p class="coach-sub">Menampilkan coach public + coach private yang scope-nya sama dengan department kamu.</p>

            <?php if (!$coaches): ?>
                <div class="empty">Belum ada coach. Mulai dari tab <b>Undang Coach</b> atau <b>Daftar Jadi Coach</b>.</div>
            <?php else: ?>
                <div class="grid-2">
                    <?php foreach ($coaches as $coach): ?>
                        <article class="card">
                            <div class="card-top">
                                <h3 class="coach-name"><?= htmlspecialchars($coach['name'], ENT_QUOTES, 'UTF-8') ?></h3>
                                <div style="display:flex; gap:6px; flex-wrap:wrap; justify-content:flex-end;">
                                    <span class="badge <?= $coach['coach_type'] === 'internal' ? 'internal' : 'external' ?>">
                                        <?= $coach['coach_type'] === 'internal' ? 'Internal' : 'External' ?>
                                    </span>
                                    <span class="badge <?= $coach['visibility'] === 'private' ? 'private' : 'public' ?>">
                                        <?= $coach['visibility'] === 'private' ? 'Private' : 'Public' ?>
                                    </span>
                                </div>
                            </div>

                            <p class="meta"><?= htmlspecialchars($coach['bio'], ENT_QUOTES, 'UTF-8') ?></p>

                            <?php if ($coach['specialties']): ?>
                                <div class="spec-row">
                                    <?php foreach ($coach['specialties'] as $spec): ?>
                                        <span class="spec"><?= htmlspecialchars($spec, ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($coach['rate_type'] === 'paid'): ?>
                                <div class="rate">Paid: <?= htmlspecialchars($coach['rate_text'], ENT_QUOTES, 'UTF-8') ?></div>
                            <?php else: ?>
                                <div class="rate">Free / Sponsored</div>
                            <?php endif; ?>

                            <?php if ($coach['visibility'] === 'private' && $coach['department_scope'] !== ''): ?>
                                <div class="meta">Scope: <?= htmlspecialchars($coach['department_scope'], ENT_QUOTES, 'UTF-8') ?></div>
                            <?php endif; ?>

                            <?php if ($coach['email'] !== '' || $coach['phone'] !== ''): ?>
                                <div class="meta">
                                    <?php if ($coach['email'] !== ''): ?>
                                        Email: <?= htmlspecialchars($coach['email'], ENT_QUOTES, 'UTF-8') ?><br>
                                    <?php endif; ?>
                                    <?php if ($coach['phone'] !== ''): ?>
                                        WA: <?= htmlspecialchars($coach['phone'], ENT_QUOTES, 'UTF-8') ?>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($tab === 'invite'): ?>
        <section class="coach-section">
            <h2 class="coach-title">Undang / input coach</h2>
            <p class="coach-sub">Dipakai karyawan untuk input coach eksternal. Setelah diinput, coach muncul di Directory sesuai visibility.</p>

            <?php if ($errors): ?>
                <div class="form-message form-error">
                    <?php foreach ($errors as $e): ?>
                        <div><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($success !== ''): ?>
                <div class="form-message form-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="action" value="invite_coach">

                <div class="input-grid">
                    <div class="input-group">
                        <label for="invite_name">Nama coach</label>
                        <input id="invite_name" name="coach_name" type="text" value="<?= htmlspecialchars((string) ($_POST['coach_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                    <div class="input-group">
                        <label for="invite_email">Email coach (opsional)</label>
                        <input id="invite_email" name="coach_email" type="email" value="<?= htmlspecialchars((string) ($_POST['coach_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <div class="hint">Isi email atau WA minimal salah satu.</div>
                    </div>
                </div>

                <div class="input-grid">
                    <div class="input-group">
                        <label for="invite_phone">Nomor WhatsApp (opsional)</label>
                        <input id="invite_phone" name="coach_phone" type="text" value="<?= htmlspecialchars((string) ($_POST['coach_phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="contoh: 08xxxxxxxxxx">
                    </div>
                    <div class="input-group">
                        <label for="invite_visibility">Visibility</label>
                        <select id="invite_visibility" name="visibility">
                            <?php $v = (string) ($_POST['visibility'] ?? 'public'); ?>
                            <option value="public"<?= $v === 'public' ? ' selected' : '' ?>>Public (lintas perusahaan)</option>
                            <option value="private"<?= $v === 'private' ? ' selected' : '' ?>>Private (hanya perusahaan/department)</option>
                        </select>
                    </div>
                </div>

                <div class="input-grid">
                    <div class="input-group">
                        <label for="invite_rate_type">Tipe pembayaran</label>
                        <?php $rt = (string) ($_POST['rate_type'] ?? 'paid'); ?>
                        <select id="invite_rate_type" name="rate_type">
                            <option value="paid"<?= $rt === 'paid' ? ' selected' : '' ?>>Paid</option>
                            <option value="free"<?= $rt === 'free' ? ' selected' : '' ?>>Free / Sponsored</option>
                        </select>
                    </div>
                    <div class="input-group">
                        <label for="invite_rate_text">Rate (jika paid)</label>
                        <input id="invite_rate_text" name="rate_text" type="text" value="<?= htmlspecialchars((string) ($_POST['rate_text'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="contoh: Rp 250.000 / sesi">
                    </div>
                </div>

                <div class="input-group">
                    <label for="invite_specs">Spesialisasi (pisahkan dengan koma / enter)</label>
                    <input id="invite_specs" name="specialties_text" type="text" value="<?= htmlspecialchars((string) ($_POST['specialties_text'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="contoh: Strength Training, HIIT, Mobility">
                </div>

                <div class="input-group">
                    <label for="invite_bio">Bio singkat</label>
                    <textarea id="invite_bio" name="coach_bio" required><?= htmlspecialchars((string) ($_POST['coach_bio'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>

                <div class="input-group">
                    <label for="invite_scope">Scope private (opsional)</label>
                    <input id="invite_scope" name="department_scope" type="text" value="<?= htmlspecialchars((string) ($_POST['department_scope'] ?? $userDepartment), ENT_QUOTES, 'UTF-8') ?>" placeholder="default: department kamu">
                    <div class="hint">Dipakai kalau visibility = private. Kalau public, field ini akan diabaikan.</div>
                </div>

                <button class="btn btn-primary" type="submit">Simpan Coach</button>
            </form>
        </section>
    <?php endif; ?>

    <?php if ($tab === 'register'): ?>
        <section class="coach-section">
            <h2 class="coach-title">Daftar jadi coach (internal)</h2>
            <p class="coach-sub">Kalau kamu karyawan dan mau jadi coach, profil kamu akan tampil di Directory. Default-nya private (scope: <?= htmlspecialchars($userDepartment, ENT_QUOTES, 'UTF-8') ?>).</p>

            <?php if ($errors): ?>
                <div class="form-message form-error">
                    <?php foreach ($errors as $e): ?>
                        <div><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($success !== ''): ?>
                <div class="form-message form-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="action" value="register_self">

                <div class="input-grid">
                    <div class="input-group">
                        <label for="self_name">Nama coach</label>
                        <input id="self_name" name="coach_name" type="text" value="<?= htmlspecialchars((string) ($_POST['coach_name'] ?? ($userName !== '' ? $userName : '')), ENT_QUOTES, 'UTF-8') ?>" required>
                        <div class="hint">Di-link ke akun kamu (user_id: <?= (int) $userId ?>).</div>
                    </div>
                    <div class="input-group">
                        <label for="self_visibility">Visibility</label>
                        <?php $sv = (string) ($_POST['visibility'] ?? 'private'); ?>
                        <select id="self_visibility" name="visibility">
                            <option value="private"<?= $sv === 'private' ? ' selected' : '' ?>>Private (hanya perusahaan/department)</option>
                            <option value="public"<?= $sv === 'public' ? ' selected' : '' ?>>Public (lintas perusahaan)</option>
                        </select>
                    </div>
                </div>

                <div class="input-grid">
                    <div class="input-group">
                        <label for="self_rate_type">Tipe pembayaran</label>
                        <?php $srt = (string) ($_POST['rate_type'] ?? 'paid'); ?>
                        <select id="self_rate_type" name="rate_type">
                            <option value="paid"<?= $srt === 'paid' ? ' selected' : '' ?>>Paid</option>
                            <option value="free"<?= $srt === 'free' ? ' selected' : '' ?>>Free / Sponsored</option>
                        </select>
                    </div>
                    <div class="input-group">
                        <label for="self_rate_text">Rate (jika paid)</label>
                        <input id="self_rate_text" name="rate_text" type="text" value="<?= htmlspecialchars((string) ($_POST['rate_text'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="contoh: Rp 150.000 / sesi">
                    </div>
                </div>

                <div class="input-group">
                    <label for="self_specs">Spesialisasi (pisahkan dengan koma / enter)</label>
                    <input id="self_specs" name="specialties_text" type="text" value="<?= htmlspecialchars((string) ($_POST['specialties_text'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="contoh: Corporate Wellness, Fat Loss, Strength">
                </div>

                <div class="input-group">
                    <label for="self_bio">Bio singkat</label>
                    <textarea id="self_bio" name="coach_bio" required><?= htmlspecialchars((string) ($_POST['coach_bio'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>

                <button class="btn btn-primary" type="submit">Simpan Profil Coach</button>
            </form>
        </section>
    <?php endif; ?>
</main>

<?php include 'includes/footer.php'; ?>
