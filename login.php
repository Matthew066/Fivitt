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

            $hash = password_hash($password, PASSWORD_DEFAULT);

            $insert = $pdo->prepare("
                INSERT INTO users 
                (name, email, password_hash, role, department, is_active, created_at)
                VALUES (?, ?, ?, 'user', 'General', 1, NOW())
            ");

            $insert->execute([
                explode("@", $email)[0],
                $email,
                $hash
            ]);

            $_SESSION['user_id'] = $pdo->lastInsertId();
            $_SESSION['user_name'] = explode("@", $email)[0];
            $_SESSION['user_role'] = 'user';

            header("Location: homescreen5vit.php");
            exit;
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
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="assets/css/media-query.css">

<style>
body {
    margin: 0;
    background: #efefef;
    font-family: "DM Sans", sans-serif;
    color: #0f172a;
}

.site-content {
    width: min(100%, 640px);
    min-height: 100vh;
    margin: 0 auto;
    padding: 0 28px 40px;
}

#top-header {
    background: #f9f9f9;
    border-radius: 0 0 14px 14px;
    height: 68px;
    display: flex;
    align-items: center;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
}

#top-header .header-wrap {
    width: 100%;
    padding: 0 16px;
}

#top-header .header-back a {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
}

#top-header .header-back img {
    width: 16px;
    height: 16px;
}

.login-main {
    max-width: 560px;
    margin: 64px auto 0;
}

.login-hero {
    text-align: center;
}

.login-hero img {
    width: 220px;
    max-width: 70%;
}

.login-hero h1 {
    margin: 34px 0 10px;
    font-family: "Zen Dots", sans-serif;
    font-size: 26px;
    letter-spacing: 0.01em;
}

.login-hero p {
    margin: 0 auto;
    max-width: 560px;
    color: #475569;
    font-size: 13px;
    line-height: 1.4;
}

.login-form-wrap {
    margin-top: 30px;
}

.field {
    display: flex;
    align-items: center;
    gap: 12px;
    width: 100%;
    min-height: 44px;
    border-radius: 12px;
    background: #f7f7f7;
    border: 1px solid #ececec;
    padding: 0 14px;
    margin-bottom: 14px;
}

.field:focus-within {
    border-color: #41bfc2;
    box-shadow: 0 0 0 3px rgba(65, 191, 194, 0.14);
}

.field i {
    color: #111827;
    font-size: 15px;
    line-height: 1;
}

.field input {
    border: none;
    outline: none;
    background: transparent;
    width: 100%;
    font-size: 16px;
    color: #334155;
}

.field input::placeholder {
    color: #64748b;
}

.field .toggle-eye {
    margin-left: auto;
    cursor: pointer;
    color: #64748b;
    font-size: 19px;
}

.error-msg {
    margin: 8px 0 2px;
    text-align: center;
    color: #ef4444;
    font-size: 14px;
}

.password-btn {
    text-align: center;
    margin-top: 14px;
}

.custom-login-btn {
    width: min(100%, 78%);
    min-height: 50px;
    border: none;
    border-radius: 12px;
    background: linear-gradient(90deg, #42c5be 0%, #36a8c0 100%);
    color: #fff;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
}

.custom-login-btn:hover {
    filter: brightness(0.97);
}

.preloader {
    position: fixed;
    inset: 0;
    z-index: 99999;
    display: flex;
    align-items: center;
    justify-content: center;
    background:
        radial-gradient(ellipse at center, rgb(0, 0, 0) 0%, rgba(8, 10, 15, 0.98) 100%),
        repeating-linear-gradient(90deg, rgb(0, 0, 0) 0 1px, transparent 1px 56px);
}

.preloader img {
    width: min(240px, 62vw);
    animation: preloadPulse 1.4s ease-in-out infinite;
}

@keyframes preloadPulse {
    0% { opacity: 0.7; transform: scale(0.96); }
    50% { opacity: 1; transform: scale(1); }
    100% { opacity: 0.7; transform: scale(0.96); }
}

@media (max-width: 560px) {
    .site-content {
        padding: 0 18px 32px;
    }

    .login-main {
        margin-top: 46px;
    }

    .field {
        min-height: 52px;
    }

    .custom-login-btn {
        min-height: 52px;
        font-size: 16px;
    }
}
</style>

</head>

<body>
    <div class="site-content">
        <div class="preloader">
            <img src="assets/images/splashscreen/logofivit.png" alt="Loading Fivit">
        </div>

        <main class="login-main" id="sign-in-main">
            <div class="login-hero">
                <img src="assets/images/splashscreen/logofivit.png" style="width:220px;" alt="Fivit Logo">
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

                <div class="password-btn">
                    <button type="submit" class="custom-login-btn">
                        Login
                    </button>
                </div>
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
            loader.style.transition = "opacity 1s ease";
            
            setTimeout(() => {
                loader.style.opacity = "0";
                setTimeout(() => loader.style.display = "none", 1000);
            }, 2000); // tampil 3 detik
        }
    });
    </script>
</body>
</html>
