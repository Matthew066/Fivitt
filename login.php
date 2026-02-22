<?php
session_start();
require 'includes/db.php';

$error = "";

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
<link href="https://fonts.googleapis.com/css2?family=Zen+Dots&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,wght@0,100..1000;1,100..1000&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/all.min.css">
<link rel="stylesheet" href="assets/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="assets/css/media-query.css">
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="assets/css/media-query.css">

<style>
.custom-login-btn{
    width: 70%;
    padding: 12px 0;
    background: linear-gradient(135deg,#20C5BA,#17a2b8);
    border: none;
    border-radius: 10px;
    color: white;
    font-size: 16px;
    font-weight: 600;
    transition: 0.3s ease;
    cursor: pointer;
}

.custom-login-btn:hover{
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(32,197,186,0.3);
}
</style>

</head>

<body>
<div class="site-content">

<div class="preloader">
    <img src="assets/images/favicon/icon-fivit.png" style="width:250px;">
</div>

<header id="top-header" class="border-0">
    <div class="header-wrap">
        <div class="header-back">
            <a href="javascript:history.go(-1)">
                <img src="assets/svg/black-left-arrow.svg" alt="back">
            </a>
        </div>
    </div>
</header>

<div class="verify-email pb-80" id="sign-in-main">
<div class="container">
<div class="let-you-middle-wrap">

<div class="middle-first mt-24 text-center">
    <img src="assets/images/splashscreen/logofivit.png" style="width:220px;" alt="Fivit Logo">
    <h1 class="md-font-zen fw-400 mt-24">WELCOME BACK</h1>
    <p class="sm-font-sans fw-400 mt-12">
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
<p style="color:#ff4d4f;text-align:center;margin-top:10px;">
<?php echo htmlspecialchars($error); ?>
</p>
<?php endif; ?>

<div class="password-btn mt-16" style="text-align:center;">
    <button type="submit" class="custom-login-btn">
        Login
    </button>
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
document.getElementById("eye").addEventListener("click", function () {
    const pass = document.getElementById("password");
    pass.type = pass.type === "password" ? "text" : "password";
    this.classList.toggle("fa-eye");
    this.classList.toggle("fa-eye-slash");
});
</script>

</body>
</html>