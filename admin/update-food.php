<?php
require_once __DIR__ . '/auth.php';
require_once '../includes/db.php';

$foodImageDirRelative = 'assets/images/foods';
$foodImageDirAbsolute = dirname(__DIR__) . '/' . $foodImageDirRelative;

try {
    $pdo->query("ALTER TABLE foods ADD COLUMN image_path varchar(255) DEFAULT NULL");
} catch (Throwable $e) {
    // ignore when column already exists
}

function redirect_with_status(string $type, string $message): void
{
    header('Location: healthy_canteen_admin.php?status=' . urlencode($type) . '&message=' . urlencode($message));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_with_status('danger', 'Metode request tidak valid.');
}

$foodId = (int)($_POST['food_id'] ?? 0);
$name = trim((string)($_POST['name'] ?? ''));
$calories = (int)($_POST['calories'] ?? 0);
$protein = (float)($_POST['protein'] ?? 0);
$fat = (float)($_POST['fat'] ?? 0);
$carbs = (float)($_POST['carbs'] ?? 0);
$rating = (int)($_POST['rating'] ?? 0);
$currentImagePath = trim((string)($_POST['current_image_path'] ?? ''));
$removeImage = isset($_POST['remove_image']);
$imagePath = $removeImage ? '' : $currentImagePath;

if ($foodId <= 0) {
    redirect_with_status('danger', 'Menu tidak valid.');
}

if ($name === '') {
    redirect_with_status('danger', 'Nama menu wajib diisi.');
}

if ($calories < 0 || $protein < 0 || $fat < 0 || $carbs < 0 || $rating < 0 || $rating > 5) {
    redirect_with_status('danger', 'Nilai nutrisi/rating tidak valid.');
}

$name = substr($name, 0, 255);

$currentStmt = $pdo->prepare(
    "SELECT name, calories, protein, fat, carbs, rating, image_path
     FROM foods
     WHERE id_foods = :id_foods
     LIMIT 1"
);
$currentStmt->execute([':id_foods' => $foodId]);
$currentFood = $currentStmt->fetch();

if (!$currentFood) {
    redirect_with_status('danger', 'Menu tidak ditemukan.');
}

if (isset($_FILES['food_image']) && (int)($_FILES['food_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $uploadError = (int)($_FILES['food_image']['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($uploadError === UPLOAD_ERR_OK) {
        $maxFileSize = 2 * 1024 * 1024;
        $fileSize = (int)($_FILES['food_image']['size'] ?? 0);
        $tmpName = (string)($_FILES['food_image']['tmp_name'] ?? '');
        $allowedMimeTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $detectedMime = $tmpName !== '' ? (string)mime_content_type($tmpName) : '';

        if ($fileSize > 0 && $fileSize <= $maxFileSize && isset($allowedMimeTypes[$detectedMime])) {
            if (!is_dir($foodImageDirAbsolute)) {
                mkdir($foodImageDirAbsolute, 0777, true);
            }

            $fileName = 'food-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $allowedMimeTypes[$detectedMime];
            $targetAbsolutePath = $foodImageDirAbsolute . '/' . $fileName;
            $targetRelativePath = $foodImageDirRelative . '/' . $fileName;

            if (move_uploaded_file($tmpName, $targetAbsolutePath)) {
                if (
                    $currentImagePath !== '' &&
                    str_starts_with($currentImagePath, $foodImageDirRelative . '/') &&
                    is_file(dirname(__DIR__) . '/' . $currentImagePath)
                ) {
                    @unlink(dirname(__DIR__) . '/' . $currentImagePath);
                }
                $imagePath = $targetRelativePath;
            } else {
                redirect_with_status('danger', 'Upload foto gagal dipindahkan.');
            }
        } else {
            redirect_with_status('danger', 'Format foto harus JPG/PNG/WEBP dan maksimal 2MB.');
        }
    } else {
        redirect_with_status('danger', 'Upload foto makanan gagal.');
    }
} elseif (
    $removeImage &&
    $currentImagePath !== '' &&
    str_starts_with($currentImagePath, $foodImageDirRelative . '/') &&
    is_file(dirname(__DIR__) . '/' . $currentImagePath)
) {
    @unlink(dirname(__DIR__) . '/' . $currentImagePath);
}

$newImageValue = $imagePath !== '' ? $imagePath : null;

$updateStmt = $pdo->prepare(
    "UPDATE foods
     SET name = :name, calories = :calories, protein = :protein, fat = :fat, carbs = :carbs, rating = :rating, image_path = :image_path
     WHERE id_foods = :id_foods"
);
$updateStmt->execute([
    ':name' => $name,
    ':calories' => $calories,
    ':protein' => $protein,
    ':fat' => $fat,
    ':carbs' => $carbs,
    ':rating' => $rating,
    ':image_path' => $newImageValue,
    ':id_foods' => $foodId,
]);

$unchanged =
    trim((string)$currentFood['name']) === $name &&
    (int)$currentFood['calories'] === $calories &&
    (float)$currentFood['protein'] === $protein &&
    (float)$currentFood['fat'] === $fat &&
    (float)$currentFood['carbs'] === $carbs &&
    (int)$currentFood['rating'] === $rating &&
    (string)($currentFood['image_path'] ?? '') === (string)$newImageValue;

if ($unchanged) {
    redirect_with_status('success', 'Tidak ada perubahan data.');
}

redirect_with_status('success', 'Menu berhasil diupdate.');
?>
