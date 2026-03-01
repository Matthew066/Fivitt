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

$name = trim((string)($_POST['name'] ?? ''));
$email = strtolower(trim((string)($_POST['email'] ?? '')));
$password = (string)($_POST['password'] ?? '');
$role = strtolower(trim((string)($_POST['role'] ?? 'user')));
$department = trim((string)($_POST['department'] ?? 'General'));
$validRoles = ['user', 'admin'];

if ($name === '' || $email === '' || $password === '') {
    redirect_with_status('danger', 'Nama, email, dan password wajib diisi.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirect_with_status('danger', 'Format email tidak valid.');
}

if (strlen($password) < 6) {
    redirect_with_status('danger', 'Password minimal 6 karakter.');
}

if (!in_array($role, $validRoles, true)) {
    $role = 'user';
}

if ($department === '') {
    $department = 'General';
}

$department = substr($department, 0, 100);

$checkStmt = $pdo->prepare("SELECT id_users FROM users WHERE LOWER(email) = ? LIMIT 1");
$checkStmt->execute([$email]);
if ($checkStmt->fetch()) {
    redirect_with_status('danger', 'Email sudah terdaftar.');
}

$insertStmt = $pdo->prepare(
    "INSERT INTO users (name, email, password_hash, role, department, is_active, created_at)
     VALUES (:name, :email, :password_hash, :role, :department, 1, NOW())"
);
$insertStmt->execute([
    ':name' => $name,
    ':email' => $email,
    ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
    ':role' => $role,
    ':department' => $department,
]);

redirect_with_status('success', 'User baru berhasil ditambahkan.');
?>
