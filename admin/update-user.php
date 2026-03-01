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
$role = strtolower(trim((string)($_POST['role'] ?? 'user')));
$department = trim((string)($_POST['department'] ?? 'General'));
$validRoles = ['user', 'admin'];

if ($userId <= 0) {
    redirect_with_status('danger', 'User tidak valid.');
}

if (!in_array($role, $validRoles, true)) {
    redirect_with_status('danger', 'Role tidak valid.');
}

if ($department === '') {
    $department = 'General';
}

$department = substr($department, 0, 100);

$currentStmt = $pdo->prepare("SELECT role, department FROM users WHERE id_users = :id_users LIMIT 1");
$currentStmt->execute([':id_users' => $userId]);
$currentUser = $currentStmt->fetch();

if (!$currentUser) {
    redirect_with_status('danger', 'User tidak ditemukan.');
}

$currentRole = strtolower(trim((string)($currentUser['role'] ?? 'user')));
$currentDepartment = trim((string)($currentUser['department'] ?? 'General'));

$updateStmt = $pdo->prepare("UPDATE users SET role = :role, department = :department WHERE id_users = :id_users");
$updateStmt->execute([
    ':role' => $role,
    ':department' => $department,
    ':id_users' => $userId,
]);

$isRoleChanged = $role !== $currentRole;
$isDepartmentChanged = $department !== $currentDepartment;

if ($isRoleChanged && $isDepartmentChanged) {
    redirect_with_status('success', 'Role dan department berhasil diupdate.');
}

if ($isRoleChanged) {
    redirect_with_status('success', 'Role berhasil diupdate.');
}

if ($isDepartmentChanged) {
    redirect_with_status('success', 'Department berhasil diupdate.');
}

redirect_with_status('success', 'Tidak ada perubahan data.');
?>
