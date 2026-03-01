<?php
session_start();
require 'includes/db.php';

if (isset($_SESSION['user_id'])) {
    $role = strtolower(trim((string) ($_SESSION['user_role'] ?? 'user')));
    if ($role === 'admin') {
        header('Location: admin/index.php');
    } else {
        header('Location: homescreen5vit.php');
    }
    exit;
}

$error = '';
$name = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = trim((string) ($_POST['password'] ?? ''));
    $confirmPassword = trim((string) ($_POST['confirm_password'] ?? ''));

    if ($name === '' || $email === '' || $password === '' || $confirmPassword === '') {
        $error = 'Semua field wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Konfirmasi password tidak cocok.';
    } else {
        $check = $pdo->prepare('SELECT id_users FROM users WHERE LOWER(email) = ? LIMIT 1');
        $check->execute([$email]);

        if ($check->fetch()) {
            $error = 'Email sudah terdaftar. Silakan login.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $insert = $pdo->prepare(
                "INSERT INTO users (name, email, password_hash, role, department, is_active, created_at)
                 VALUES (?, ?, ?, 'user', 'General', 1, NOW())"
            );
            $insert->execute([$name, $email, $hash]);

            $_SESSION['register_success'] = 'Registrasi berhasil. Silakan login.';
            header('Location: login.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Fivit - Register</title>
    <link rel="icon" href="assets/images/favicon/icon-fivit.png">
    <link rel="stylesheet" href="assets/css/all.min.css">
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=20260301-login-no-inline">
    <link rel="stylesheet" href="assets/css/media-query.css">
</head>
<body class="login-page register-page">
    <div class="site-content">
        <div class="preloader">
            <img src="assets/images/splashscreen/logofivit.png" alt="Loading Fivit">
        </div>

        <main class="login-main" id="sign-up-main">
            <div class="register-back-wrap">
                <a href="login.php" class="register-back-link">
                    <i class="fa-solid fa-arrow-left"></i>
                    <span>Back to login</span>
                </a>
            </div>

            <div class="login-hero">
                <img src="assets/images/splashscreen/logofivit.png" alt="Fivit Logo">
                <h1>CREATE ACCOUNT</h1>
                <p>
                    Register now to start your fitness journey and access personalized features.
                </p>
            </div>

            <form class="login-form-wrap" method="POST" autocomplete="off">
                <div class="field">
                    <i class="fa-regular fa-user" aria-hidden="true"></i>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        value="<?php echo htmlspecialchars($name); ?>"
                        placeholder="Username"
                        class="sign-in-custom-input"
                        required
                    >
                </div>

                <div class="field">
                    <i class="fa-regular fa-envelope" aria-hidden="true"></i>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="<?php echo htmlspecialchars($email); ?>"
                        placeholder="Email Address"
                        class="sign-in-custom-input"
                        required
                    >
                </div>

                <div class="field">
                    <i class="fa-solid fa-lock" aria-hidden="true"></i>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Password"
                        class="sign-in-custom-input"
                        required
                    >
                    <i class="fas fa-eye-slash toggle-eye" id="eye"></i>
                </div>

                <div class="field">
                    <i class="fa-solid fa-lock" aria-hidden="true"></i>
                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        placeholder="Confirm Password"
                        class="sign-in-custom-input"
                        required
                    >
                    <i class="fas fa-eye-slash toggle-eye" id="eye1"></i>
                </div>

                <?php if ($error !== ''): ?>
                    <p class="error-msg"><?php echo htmlspecialchars($error); ?></p>
                <?php endif; ?>

                <div class="password-btn">
                    <button type="submit" class="custom-login-btn">Register</button>
                </div>

                <p class="register-now-link">
                    Already have an account?
                    <a href="login.php">Login</a>
                </p>
            </form>
        </main>
    </div>

    <script src="assets/js/jquery.min.js"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/custom.js"></script>
</body>
</html>
