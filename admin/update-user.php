<?php
require_once __DIR__ . '/auth.php';
require_once '../includes/db.php';
require_once '../includes/user_profile.php';

ensure_users_profile_schema($pdo);

function redirect_with_status(string $type, string $message): void
{
    header('Location: datauser.php?status=' . urlencode($type) . '&message=' . urlencode($message));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_with_status('danger', 'Metode request tidak valid.');
}

$userId = (int)($_POST['user_id'] ?? 0);
$role = strtolower(trim((string)($_POST['role'] ?? '')));
$role = $role === 'manajerial' ? 'managerial' : $role;
$role = $role === 'hrd' ? 'hr' : $role;
$department = trim((string)($_POST['department'] ?? 'General'));
$gender = normalize_gender((string)($_POST['gender'] ?? ''));
$allowedRoles = ['managerial', 'hr', 'cooker', 'user'];
$genderOptions = get_gender_options();

if ($userId <= 0) {
    redirect_with_status('danger', 'User tidak valid.');
}

if ($department === '') {
    $department = 'General';
}

$department = substr($department, 0, 100);

$currentStmt = $pdo->prepare("SELECT role, department, gender FROM users WHERE id_users = :id_users LIMIT 1");
$currentStmt->execute([':id_users' => $userId]);
$currentUser = $currentStmt->fetch();

if (!$currentUser) {
    redirect_with_status('danger', 'User tidak ditemukan.');
}

$currentRole = strtolower(trim((string)($currentUser['role'] ?? 'user')));
if ($currentRole === 'manajerial') {
    $currentRole = 'managerial';
}
if ($currentRole === 'hrd') {
    $currentRole = 'hr';
}
$currentDepartment = trim((string)($currentUser['department'] ?? 'General'));
$currentGender = normalize_gender((string)($currentUser['gender'] ?? ''));

$newRole = $currentRole;
if ($currentRole !== 'admin') {
    if ($role === '' || !in_array($role, $allowedRoles, true)) {
        redirect_with_status('danger', 'Role yang dipilih tidak valid.');
    }

    $newRole = $role;
}

if ($gender === '' || !array_key_exists($gender, $genderOptions)) {
    redirect_with_status('danger', 'Gender yang dipilih tidak valid.');
}

$updateStmt = $pdo->prepare("UPDATE users SET role = :role, department = :department, gender = :gender WHERE id_users = :id_users");
$updateStmt->execute([
    ':role' => $newRole,
    ':department' => $department,
    ':gender' => $gender,
    ':id_users' => $userId,
]);

$changedParts = [];
if ($newRole !== $currentRole) {
    $changedParts[] = 'role';
}
if ($department !== $currentDepartment) {
    $changedParts[] = 'department';
}
if ($gender !== $currentGender) {
    $changedParts[] = 'gender';
}

if ($changedParts !== []) {
    $labels = [
        'role' => 'Role',
        'department' => 'Department',
        'gender' => 'Gender',
    ];
    $messageParts = array_map(static fn(string $key): string => $labels[$key] ?? $key, $changedParts);
    redirect_with_status('success', implode(', ', $messageParts) . ' berhasil diupdate.');
}

redirect_with_status('success', 'Tidak ada perubahan data.');
?>
