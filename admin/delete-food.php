<?php
require_once __DIR__ . '/auth.php';
require_once '../includes/db.php';

$foodImageDirRelative = 'assets/images/foods';

function redirect_with_status(string $type, string $message): void
{
    header('Location: healthy_canteen_admin.php?status=' . urlencode($type) . '&message=' . urlencode($message));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_with_status('danger', 'Metode request tidak valid.');
}

$foodId = (int)($_POST['food_id'] ?? 0);

if ($foodId <= 0) {
    redirect_with_status('danger', 'Menu tidak valid.');
}

$imageStmt = $pdo->prepare("SELECT image_path FROM foods WHERE id_foods = :id_foods LIMIT 1");
$imageStmt->execute([':id_foods' => $foodId]);
$foodRow = $imageStmt->fetch();

$deleteStmt = $pdo->prepare("DELETE FROM foods WHERE id_foods = :id_foods");
$deleteStmt->execute([':id_foods' => $foodId]);

if ($deleteStmt->rowCount() > 0) {
    $imagePath = trim((string)($foodRow['image_path'] ?? ''));
    if (
        $imagePath !== '' &&
        str_starts_with($imagePath, $foodImageDirRelative . '/') &&
        is_file(dirname(__DIR__) . '/' . $imagePath)
    ) {
        @unlink(dirname(__DIR__) . '/' . $imagePath);
    }
    redirect_with_status('success', 'Menu berhasil dihapus.');
}

redirect_with_status('warning', 'Menu tidak ditemukan atau sudah dihapus.');
?>
