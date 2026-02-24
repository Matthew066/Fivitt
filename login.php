<?php
session_start();
require 'includes/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Semua field wajib diisi.';
    } else {
        $stmt = $pdo->prepare('SELECT id_users, name, email, password_hash, is_active FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            $error = 'Email atau password salah.';
        } elseif ((int) $user['is_active'] !== 1) {
            $error = 'Akun tidak aktif. Hubungi admin.';
        } else {
            $storedHash = (string) ($user['password_hash'] ?? '');
            $isValid = false;
            $needsUpgrade = false;

            if ($storedHash !== '') {
                if (preg_match('/^\$2y\$\d{2}\$.{53}$/', $storedHash) || str_starts_with($storedHash, '$argon2')) {
                    $isValid = password_verify($password, $storedHash);
                    if ($isValid && password_needs_rehash($storedHash, PASSWORD_DEFAULT)) {
                        $needsUpgrade = true;
                    }
                } elseif (preg_match('/^[a-f0-9]{32}$/i', $storedHash)) {
                    $isValid = hash_equals(strtolower($storedHash), md5($password));
                    $needsUpgrade = $isValid;
                } elseif (preg_match('/^[a-f0-9]{40}$/i', $storedHash)) {
                    $isValid = hash_equals(strtolower($storedHash), sha1($password));
                    $needsUpgrade = $isValid;
                } else {
                    $isValid = hash_equals($storedHash, $password);
                    $needsUpgrade = $isValid;
                }
            }

            if ($isValid) {
                if ($needsUpgrade) {
                    $newHash = password_hash($password, PASSWORD_DEFAULT);
                    $update = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id_users = ?');
                    $update->execute([$newHash, $user['id_users']]);
                }

                $_SESSION['user_id'] = (int) $user['id_users'];
                $_SESSION['user_name'] = (string) ($user['name'] ?: explode('@', $user['email'])[0]);

                header('Location: homescreen5vit.php');
                exit;
            }

            $error = 'Email atau password salah.';
        }
    }
}

$pageTitle = 'Login';
$bodyClass = 'login-page';
$showAppChrome = false;
require 'includes/header.php';
?>

<div class="site-content">
    <div class="preloader">
        <img src="assets/images/favicon/icon-fivit.png" class="login-preloader-logo" alt="Fivit Logo">
    </div>

    <div class="verify-email pb-80" id="sign-in-main">
        <div class="container">
            <div class="let-you-middle-wrap">

                <div class="middle-first mt-24 text-center">
                    <img src="assets/images/splashscreen/logofivit.png" class="login-brand-logo" alt="Fivit Logo">
                    <h1 class="md-font-zen fw-400 mt-24">WELCOME BACK</h1>
                    <p class="sm-font-sans fw-400 mt-12">
                        Login now to access your personalized fitness dashboard and stay on track.
                    </p>
                </div>

                <form class="mt-32" method="POST" action="">
                    <div class="form-details-sign-in border">
                        <span><img src="assets/svg/mail-icon.svg" alt="mail"></span>
                        <input
                            type="email"
                            name="email"
                            placeholder="Email Address"
                            class="sign-in-custom-input md-font-sans fw-400"
                            required>
                    </div>

                    <div class="form-details-sign-in border mt-8">
                        <span><img src="assets/svg/password-icon.svg" alt="password"></span>
                        <input
                            type="password"
                            name="password"
                            id="password"
                            placeholder="Password"
                            class="sign-in-custom-input md-font-sans fw-400"
                            required>
                        <i class="fas fa-eye-slash" id="eye"></i>
                    </div>

                    <?php if ($error !== ''): ?>
                        <p class="login-error-text"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endif; ?>

                    <div class="password-btn mt-16 login-password-btn-wrap">
                        <button type="submit" class="custom-login-btn">Login</button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

<script src="assets/js/jquery.min.js"></script>
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/custom.js"></script>
<script>
const eyeBtn = document.getElementById('eye');
if (eyeBtn) {
    eyeBtn.addEventListener('click', function () {
        const pass = document.getElementById('password');
        if (!pass) return;

        pass.type = pass.type === 'password' ? 'text' : 'password';
        this.classList.toggle('fa-eye');
        this.classList.toggle('fa-eye-slash');
    });
}
</script>

<?php include 'includes/footer.php'; ?>
