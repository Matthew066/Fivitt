<?php

function ensure_users_profile_image_schema(PDO $pdo): void
{
    try {
        $pdo->query("ALTER TABLE users ADD COLUMN profile_image varchar(255) DEFAULT NULL");
    } catch (Throwable $e) {
        // ignore if column already exists
    }
}

function get_user_profile_image(PDO $pdo, int $userId): ?string
{
    if ($userId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare("SELECT profile_image FROM users WHERE id_users = ? LIMIT 1");
    $stmt->execute([$userId]);
    $row = $stmt->fetch();

    $path = (string) ($row['profile_image'] ?? '');
    return $path !== '' ? $path : null;
}
