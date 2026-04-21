<?php

function ensure_users_profile_schema(PDO $pdo): void
{
    try {
        $pdo->query("ALTER TABLE users ADD COLUMN profile_image varchar(255) DEFAULT NULL");
    } catch (Throwable $e) {
        // ignore if column already exists
    }

    try {
        $pdo->query("ALTER TABLE users ADD COLUMN birth_date date DEFAULT NULL");
    } catch (Throwable $e) {
        // ignore if column already exists
    }

    try {
        $pdo->query("ALTER TABLE users ADD COLUMN age_group varchar(32) DEFAULT NULL");
    } catch (Throwable $e) {
        // ignore if column already exists
    }
}

function get_age_group_options(): array
{
    return [
        'teen_12_17' => 'Remaja (12-17 tahun)',
        'adult' => 'Dewasa (18-64 tahun)',
        'older_adult' => 'Lansia (65+ tahun)',
    ];
}

function normalize_age_group(?string $value): string
{
    $value = trim((string) $value);
    $options = get_age_group_options();

    return array_key_exists($value, $options) ? $value : 'adult';
}

function get_age_group_from_birth_date(?string $birthDate): ?string
{
    $birthDate = trim((string) $birthDate);
    if ($birthDate === '') {
        return null;
    }

    $birth = DateTime::createFromFormat('Y-m-d', $birthDate);
    if (!$birth || $birth->format('Y-m-d') !== $birthDate) {
        return null;
    }

    $today = new DateTime('today');
    if ($birth > $today) {
        return null;
    }

    $age = $birth->diff($today)->y;

    if ($age >= 65) {
        return 'older_adult';
    }

    if ($age >= 12 && $age <= 17) {
        return 'teen_12_17';
    }

    return 'adult';
}

function get_sleep_target_by_age_group(string $ageGroup): array
{
    $ageGroup = normalize_age_group($ageGroup);

    switch ($ageGroup) {
        case 'teen_12_17':
            return [
                'key' => 'teen_12_17',
                'label' => '8-10 jam (remaja 12-17)',
                'profile_label' => 'Remaja',
                'min' => 8.0,
                'max' => 10.0,
            ];

        case 'older_adult':
            return [
                'key' => 'older_adult',
                'label' => '7-8 jam (lansia 65+)',
                'profile_label' => 'Lansia',
                'min' => 7.0,
                'max' => 8.0,
            ];

        default:
            return [
                'key' => 'adult',
                'label' => '7-9 jam (dewasa)',
                'profile_label' => 'Dewasa',
                'min' => 7.0,
                'max' => 9.0,
            ];
    }
}

function get_user_profile_settings(PDO $pdo, int $userId): array
{
    $defaults = [
        'birth_date' => '',
        'age_group' => 'adult',
        'effective_age_group' => 'adult',
        'effective_source' => 'default',
    ];

    if ($userId <= 0) {
        return $defaults;
    }

    $stmt = $pdo->prepare("
        SELECT birth_date, age_group
        FROM users
        WHERE id_users = ?
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $birthDate = (string) ($row['birth_date'] ?? '');
    $manualAgeGroup = normalize_age_group((string) ($row['age_group'] ?? 'adult'));
    $derivedAgeGroup = get_age_group_from_birth_date($birthDate);

    return [
        'birth_date' => $birthDate,
        'age_group' => $manualAgeGroup,
        'effective_age_group' => $derivedAgeGroup ?? $manualAgeGroup,
        'effective_source' => $derivedAgeGroup ? 'birth_date' : 'age_group',
    ];
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
