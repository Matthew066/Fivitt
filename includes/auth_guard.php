<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function require_login(string $loginPath = 'login.php'): void
{
    if (!isset($_SESSION['user_id'])) {
        if (!headers_sent()) {
            header('Location: ' . $loginPath);
        }
        exit;
    }
}
