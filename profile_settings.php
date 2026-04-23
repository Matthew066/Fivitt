<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/auth_guard.php';
require_once 'includes/user_profile.php';
require_login();

ensure_users_profile_schema($pdo);

$userId = (int) ($_SESSION['user_id'] ?? 0);
$message = '';
$error = '';

$userStmt = $pdo->prepare("
    SELECT name, email, department, gender, birth_date, age_group, profile_image
    FROM users
    WHERE id_users = ?
    LIMIT 1
");
$userStmt->execute([$userId]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC) ?: [];

if (!$user) {
    header('Location: index.php');
    exit;
}

$name = (string) ($user['name'] ?? '');
$email = (string) ($user['email'] ?? '');
$department = (string) ($user['department'] ?? '');
$gender = normalize_gender((string) ($user['gender'] ?? ''));
$birthDate = (string) ($user['birth_date'] ?? '');
$ageGroup = normalize_age_group((string) ($user['age_group'] ?? 'adult'));
$profileImage = get_user_profile_image($pdo, $userId) ?? (string) ($user['profile_image'] ?? '');
$genderOptions = get_gender_options();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gender = normalize_gender((string) ($_POST['gender'] ?? ''));
    $birthDate = trim((string) ($_POST['birth_date'] ?? ''));

    if ($gender === '') {
        $error = 'Gender wajib dipilih.';
    } elseif ($birthDate === '') {
        $error = 'Tanggal lahir wajib diisi.';
    } else {
        $derivedAgeGroup = get_age_group_from_birth_date($birthDate);
        if ($derivedAgeGroup === null) {
            $error = 'Tanggal lahir tidak valid.';
        } else {
            $ageGroup = $derivedAgeGroup;
        }
    }

    if ($error === '') {
        $update = $pdo->prepare("
            UPDATE users
            SET gender = ?, birth_date = ?, age_group = ?
            WHERE id_users = ?
        ");
        $update->execute([
            $gender,
            $birthDate !== '' ? $birthDate : null,
            $ageGroup,
            $userId
        ]);

        $_SESSION['user_gender'] = $gender;
        $_SESSION['user_age_group'] = $ageGroup;
        $message = 'Profil berhasil diperbarui.';
    }
}

$profileSettings = get_user_profile_settings($pdo, $userId);
$sleepTarget = get_sleep_target_by_age_group($profileSettings['effective_age_group']);

$pageTitle = 'Profil';
$bodyClass = 'sleep-page';
include 'includes/header.php';
?>
<style>
body.sleep-page .header {
    z-index: 140;
}

body.sleep-page .menu,
body.sleep-page .logo-link {
    position: relative;
    z-index: 141;
}

.profile-page-shell {
    width: min(1040px, calc(100% - 32px));
    margin: 0 auto;
    position: relative;
    z-index: 1;
}

.profile-avatar-bubble {
    width: 78px;
    height: 78px;
    border-radius: 24px;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.16);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.profile-avatar-bubble img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.profile-avatar-bubble span {
    font-size: 30px;
    line-height: 1;
}

@media (min-width: 900px) {
    .profile-page-shell {
        width: min(1120px, calc(100% - 40px));
        padding-bottom: 24px;
    }

    .profile-page-shell .sleep-hero {
        padding: 28px;
    }
}
</style>

<main class="app profile-page-shell">
    <section class="card sleep-hero" style="margin-bottom:16px;">
        <div class="sleep-hero-inner">
            <div class="emoji-bubble profile-avatar-bubble">
                <?php if ($profileImage !== ''): ?>
                    <img src="<?= htmlspecialchars($profileImage) ?>" alt="Foto profil">
                <?php else: ?>
                    <span>&#128100;</span>
                <?php endif; ?>
            </div>
            <div class="hero-copy">
                <div class="sleep-title">Profil</div>
                <div class="sleep-sub">
                    Pengaturan ini dipakai untuk menyesuaikan target tidur dan rekomendasi recovery kamu.
                </div>
                <div class="hero-badges">
                    <span class="hero-badge"><?= htmlspecialchars($sleepTarget['profile_label']) ?></span>
                    <span class="hero-badge">Target <?= htmlspecialchars($sleepTarget['label']) ?></span>
                    <span class="hero-badge"><?= $profileSettings['effective_source'] === 'birth_date' ? 'Otomatis dari tanggal lahir' : 'Manual dari profil' ?></span>
                </div>
            </div>
        </div>
    </section>

    <section class="card sleep-form-card">
        <div class="sleep-form-head">
            <div>
                <div class="summary-title">Atur Profil</div>
                <div class="sleep-sub">Kalau tanggal lahir diisi, kategori usia akan mengikuti umur secara otomatis.</div>
            </div>
        </div>

        <?php if ($message !== ''): ?>
            <div class="summary-pill score-tier-excellent" style="margin-bottom:16px;"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="summary-pill score-tier-low" style="margin-bottom:16px;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" class="sleep-entry-form">
            <div class="input-row">
                <div class="input-group input-card">
                    <label>Nama</label>
                    <input type="text" value="<?= htmlspecialchars($name) ?>" disabled>
                </div>
                <div class="input-group input-card">
                    <label>Email</label>
                    <input type="text" value="<?= htmlspecialchars($email) ?>" disabled>
                </div>
            </div>

            <div class="input-row">
                <div class="input-group input-card">
                    <label>Department</label>
                    <input type="text" value="<?= htmlspecialchars($department) ?>" disabled>
                </div>
                <div class="input-group input-card">
                    <label>Gender</label>
                    <select class="select-modern" name="gender" required>
                        <option value="" disabled <?= $gender === '' ? 'selected' : '' ?>>Pilih gender</option>
                        <?php foreach ($genderOptions as $genderKey => $genderLabel): ?>
                            <option value="<?= htmlspecialchars($genderKey) ?>" <?= $gender === $genderKey ? 'selected' : '' ?>>
                                <?= htmlspecialchars($genderLabel) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="input-row">
                <div class="input-group input-card input-card-accent">
                    <label>Tanggal Lahir</label>
                    <input type="date" name="birth_date" value="<?= htmlspecialchars($birthDate) ?>" max="<?= date('Y-m-d') ?>" required>
                    <small class="input-hint">Kategori usia akan dihitung otomatis dari tanggal lahir.</small>
                </div>
                <div class="input-group input-card sleep-date-summary">
                    <span class="sleep-date-summary-label">Kategori aktif</span>
                    <strong><?= htmlspecialchars($sleepTarget['profile_label']) ?></strong>
                    <small>Target tidur aktif: <?= htmlspecialchars($sleepTarget['label']) ?></small>
                </div>
            </div>

            <div class="input-row">
                <div class="input-group input-card">
                    <label>Kategori Usia</label>
                    <input type="text" value="<?= htmlspecialchars($sleepTarget['profile_label']) ?>" disabled>
                    <small class="input-hint">Diambil otomatis dari tanggal lahir yang tersimpan.</small>
                </div>
                <div class="input-group input-card">
                    <label>Status Gender</label>
                    <input type="text" value="<?= $gender !== '' ? htmlspecialchars($genderOptions[$gender] ?? ucfirst($gender)) : 'Belum diatur' ?>" disabled>
                </div>
            </div>

            <div class="sleep-form-footer">
                <div class="sleep-form-note">
                    <strong>Info:</strong> kategori lansia memakai target yang lebih sempit agar penilaian durasi tidur lebih relevan.
                </div>
                <button class="btn-primary sleep-submit-btn" type="submit">Simpan Profil</button>
            </div>
        </form>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
