<?php
session_start();
require_once '../includes/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || (($_SESSION['user_role'] ?? '') !== 'admin')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$user_id = isset($data['user_id']) ? (int) $data['user_id'] : 0;
$role = isset($data['role']) ? $data['role'] : '';

if (!in_array($role, ['user', 'admin'], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid role']);
    exit();
}

if ($user_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid user id']);
    exit();
}

try {
    $stmt = $pdo->prepare("UPDATE users SET role = :role WHERE id_users = :id_users");
    $ok = $stmt->execute([
        ':role' => $role,
        ':id_users' => $user_id,
    ]);

    echo json_encode(['success' => (bool) $ok]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to update role']);
}
?>
