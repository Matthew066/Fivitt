<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function redirect_to_login(): void
{
    header('Location: ../login.php');
    exit();
}

if (!isset($_SESSION['user_id'])) {
    redirect_to_login();
}

$sessionRole = $_SESSION['user_role'] ?? '';
if ($sessionRole !== 'admin') {
    redirect_to_login();
}
