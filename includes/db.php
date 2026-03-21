<?php
@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
error_reporting(0);
ob_start();

$host = 'localhost';
$db   = 'fivit_db';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    appRedirectTo404('DB connection failed');
}

function appRedirectTo404(?string $message = null): void
{
    if (!isset($_SERVER['SCRIPT_NAME'])) {
        exit;
    }

    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
    if (str_ends_with($script, '/404.php')) {
        exit;
    }

    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }

    $_SESSION['__error_redirect'] = [
        'time' => time(),
        'message' => $message ?? 'Unexpected error'
    ];

    $base = rtrim(dirname($script), '/');
    $base = preg_replace('#/admin$#', '', $base);
    $target = $base . '/404.php';

    if (ob_get_length()) {
        @ob_end_clean();
    }

    if (!headers_sent()) {
        header('Location: ' . $target);
        exit;
    }

    echo 'An error occurred.';
    exit;
}

set_exception_handler(function (Throwable $e): void {
    appRedirectTo404($e->getMessage());
});

set_error_handler(function (int $severity, string $message): bool {
    appRedirectTo404($message);
    return true;
});

register_shutdown_function(function (): void {
    $err = error_get_last();
    if (!$err) return;
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
    if (in_array($err['type'], $fatalTypes, true)) {
        appRedirectTo404($err['message'] ?? 'Fatal error');
    }
});
