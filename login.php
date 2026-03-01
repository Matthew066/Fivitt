<?php
session_start();
require 'includes/db.php';

$error = "";
$success = (string)($_SESSION['register_success'] ?? '');
unset($_SESSION['register_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (empty($email) || empty($password)) {
        $error = "Semua field wajib diisi.";
    } else {

        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {

            if (password_verify($password, $user['password_hash'])) {

                $_SESSION['user_id'] = $user['id_users'];
                $_SESSION['user_name'] = $user['name'];

                header("Location: homescreen5vit.php");
                exit;

            } else {
                $error = "Email atau password salah.";
            }

        } else {
            $error = "Email atau password salah.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Fivit - Login</title>

<link rel="icon" href="assets/images/favicon/icon-fivit.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/all.min.css">
<link rel="stylesheet" href="assets/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/css/style.css?v=20260301-login-clean3">
<link rel="stylesheet" href="assets/css/media-query.css">

</head>

<body class="login-page">
    <div class="site-content">
        <div class="preloader">
            <img src="assets/images/splashscreen/logofivit.png" alt="Loading Fivit">
        </div>

        <main class="login-main" id="sign-in-main">
            <div class="login-hero">
                <img src="assets/images/splashscreen/logofivit.png" alt="Fivit Logo">
                <h1>WELCOME BACK</h1>
                <p>
                    Login now to access your personalized fitness dashboard and stay on track.
                </p>
            </div>

<form class="mt-32" method="POST">

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

                <?php if ($error !== ""): ?>
                <p class="error-msg">
                    <?php echo htmlspecialchars($error); ?>
                </p>
                <?php endif; ?>

                <?php if ($success !== ""): ?>
                <p class="success-msg">
                    <?php echo htmlspecialchars($success); ?>
                </p>
                <?php endif; ?>

                <div class="password-btn">
                    <button type="submit" class="custom-login-btn">
                        Login
                    </button>
                </div>

                <p class="register-now-link">
                    Didn't have account?
                    <a href="register.php">Register now</a>
                </p>
            </form>
        </main>
    </div>

<script src="assets/js/jquery.min.js"></script>
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/custom.js"></script>

<script>
document.getElementById("eye").addEventListener("click", function () {
    const pass = document.getElementById("password");
    pass.type = pass.type === "password" ? "text" : "password";
    this.classList.toggle("fa-eye");
    this.classList.toggle("fa-eye-slash");
});
</script>

    <script>
    window.addEventListener("load", function () {
        const loader = document.querySelector(".preloader");
        if (loader) {
            loader.style.transition = "opacity 0.6s ease";

            setTimeout(() => {
                loader.style.opacity = "0";
                setTimeout(() => {
                    loader.remove();
                }, 700);
            }, 1200);
        }
    });
    </script>
</body>
</html>


