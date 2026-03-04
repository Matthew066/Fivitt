<?php
session_start();
require 'includes/db.php';

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (!$email || !$password) {
        $error = "Email dan password wajib diisi.";
    } else {

        // Cek apakah user sudah ada
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {

            // Login
            if (password_verify($password, $user['password_hash'])) {

                $_SESSION['user_id'] = $user['id_users'];
                $_SESSION['user_name'] = $user['name'];

                header("Location: health.php");
                exit;

            } else {
                $error = "Password salah.";
            }

        } else {

            // Auto register kalau belum ada
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

            header("Location: sleep.php");
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Login - Fivit</title>
<style>
body{
    font-family:Arial;
    background:#f5f7fa;
    display:flex;
    justify-content:center;
    align-items:center;
    height:100vh;
}
.card{
    background:white;
    padding:30px;
    border-radius:12px;
    width:320px;
    box-shadow:0 4px 12px rgba(0,0,0,0.1);
}
input{
    width:100%;
    padding:10px;
    margin-bottom:15px;
}
button{
    width:100%;
    padding:10px;
    background:#2ec4cc;
    border:none;
    color:white;
    border-radius:8px;
    cursor:pointer;
}
.error{
    color:red;
    margin-bottom:10px;
}
</style>
</head>
<body>

<div class="card">
    <h2>Login / Register</h2>

    <?php if($error): ?>
        <div class="error"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">Masuk</button>
    </form>

    <p style="font-size:12px;color:gray;margin-top:10px;">
        Jika email belum terdaftar, akun akan dibuat otomatis.
    </p>
</div>

</body>
</html>
