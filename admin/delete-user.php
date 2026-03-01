<?php
require_once __DIR__ . '/auth.php';
require_once '../includes/db.php';

function redirect_with_status(string $type, string $message): void
{
    header('Location: datauser.php?status=' . urlencode($type) . '&message=' . urlencode($message));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_with_status('danger', 'Metode request tidak valid.');
}

$userId = (int)($_POST['user_id'] ?? 0);
$currentAdminId = (int)($_SESSION['user_id'] ?? 0);

if ($userId <= 0) {
    redirect_with_status('danger', 'User tidak valid.');
}

if ($currentAdminId > 0 && $userId === $currentAdminId) {
    redirect_with_status('danger', 'Akun yang sedang login tidak bisa dihapus.');
}

$deleteStmt = $pdo->prepare("DELETE FROM users WHERE id_users = :id_users");
$deleteStmt->execute([':id_users' => $userId]);

redirect_with_status('success', 'User berhasil dihapus.');
?>
