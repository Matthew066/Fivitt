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

$name = trim((string)($_POST['name'] ?? ''));
$calories = (int)($_POST['calories'] ?? 0);
$protein = (float)($_POST['protein'] ?? 0);
$fat = (float)($_POST['fat'] ?? 0);
$carbs = (float)($_POST['carbs'] ?? 0);
$rating = (int)($_POST['rating'] ?? 0);
$createdBy = (int)($_SESSION['user_id'] ?? 0);
$imagePath = null;

if ($name === '') {
    redirect_with_status('danger', 'Nama menu wajib diisi.');
}

if ($calories < 0 || $protein < 0 || $fat < 0 || $carbs < 0 || $rating < 0 || $rating > 5) {
    redirect_with_status('danger', 'Nilai nutrisi/rating tidak valid.');
}

$name = substr($name, 0, 255);

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
}

$insertStmt = $pdo->prepare(
    "INSERT INTO foods (name, calories, protein, fat, carbs, rating, image_path, id_users_created_by)
     VALUES (:name, :calories, :protein, :fat, :carbs, :rating, :image_path, :id_users_created_by)"
);
$insertStmt->execute([
    ':name' => $name,
    ':calories' => $calories,
    ':protein' => $protein,
    ':fat' => $fat,
    ':carbs' => $carbs,
    ':rating' => $rating,
    ':image_path' => $imagePath,
    ':id_users_created_by' => $createdBy,
]);

redirect_with_status('success', 'Menu baru berhasil ditambahkan.');
?>
