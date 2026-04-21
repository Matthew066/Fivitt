<?php

require_once __DIR__ . '/user_profile.php';

function ensure_users_profile_image_schema(PDO $pdo): void
{
    ensure_users_profile_schema($pdo);
}
