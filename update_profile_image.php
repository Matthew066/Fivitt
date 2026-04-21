<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'includes/db.php';
require_once 'includes/profile_image.php';

ensure_users_profile_image_schema($pdo);

function slugifyProfileFilePart(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    $value = trim($value, '-');
    return $value !== '' ? $value : 'user';
}

function deleteOwnedProfileImage(?string $path, array $allowedBases): void
{
    $path = trim((string) $path);
    if ($path === '') {
        return;
    }

    foreach ($allowedBases as $baseDir) {
        if (str_starts_with($path, $baseDir . '/')) {
            $absolutePath = __DIR__ . '/' . $path;
            if (is_file($absolutePath)) {
                @unlink($absolutePath);
            }
            return;
        }
    }
}

$redirect = $_SERVER['HTTP_REFERER'] ?? 'index.php';
$userId = (int) $_SESSION['user_id'];
$userName = trim((string) ($_SESSION['user_name'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $userId <= 0) {
    header('Location: ' . $redirect);
    exit;
}

if (!isset($_FILES['profile_image']) || (int) ($_FILES['profile_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    header('Location: ' . $redirect);
    exit;
}

$tmp = (string) ($_FILES['profile_image']['tmp_name'] ?? '');
$size = (int) ($_FILES['profile_image']['size'] ?? 0);
$mime = $tmp !== '' ? (string) mime_content_type($tmp) : '';
$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

if ($size <= 0 || $size > 2 * 1024 * 1024 || !isset($allowed[$mime])) {
    header('Location: ' . $redirect);
    exit;
}

$dirRelative = 'assets/images/user';
$dirAbsolute = __DIR__ . '/' . $dirRelative;
if (!is_dir($dirAbsolute)) {
    mkdir($dirAbsolute, 0777, true);
}

$oldImage = get_user_profile_image($pdo, $userId);
$fileName = slugifyProfileFilePart($userName !== '' ? $userName : ('user-' . $userId)) . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
$targetAbsolute = $dirAbsolute . '/' . $fileName;
$targetRelative = $dirRelative . '/' . $fileName;

if (move_uploaded_file($tmp, $targetAbsolute)) {
    $update = $pdo->prepare("UPDATE users SET profile_image = ? WHERE id_users = ?");
    $update->execute([$targetRelative, $userId]);

    deleteOwnedProfileImage($oldImage, ['assets/images/user', 'assets/images/profiles']);
}

header('Location: ' . $redirect);
exit;
