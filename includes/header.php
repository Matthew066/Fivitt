<?php
$pageTitle = $pageTitle ?? 'FIVIT';
$bodyClass = $bodyClass ?? '';
$extraStyles = $extraStyles ?? [];
$mainCssVersion = @filemtime(__DIR__ . '/../assets/css/style.css') ?: time();
$mediaCssVersion = @filemtime(__DIR__ . '/../assets/css/media-query.css') ?: time();

if (!is_array($extraStyles)) {
    $extraStyles = [];
}

$isLoggedIn = isset($_SESSION['user_id']);

$canteenLink = 'foodselection.php';
$canteenLabel = 'Food Selection';
if ($isLoggedIn) {
    $userRole = strtolower(trim((string)($_SESSION['user_role'] ?? 'user')));
    if (in_array($userRole, ['cooker', 'admin'])) {
        $canteenLink = 'healthy_canteen.php';
        $canteenLabel = 'Healthy Canteen';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars((string) $pageTitle, ENT_QUOTES, 'UTF-8') ?></title>

    <link rel="icon" href="assets/images/favicon/icon-fivit.png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/all.min.css">
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= urlencode((string) $mainCssVersion) ?>">
    <link rel="stylesheet" href="assets/css/media-query.css?v=<?= urlencode((string) $mediaCssVersion) ?>">
    <?php foreach ($extraStyles as $stylePath): ?>
        <?php if (!is_string($stylePath) || trim($stylePath) === '') continue; ?>
        <link rel="stylesheet" href="<?= htmlspecialchars($stylePath, ENT_QUOTES, 'UTF-8') ?>">
    <?php endforeach; ?>
</head>
<body<?= $bodyClass !== '' ? ' class="' . htmlspecialchars((string) $bodyClass, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>

<header class="header">
    <a href="homescreen5vit.php" class="logo-link">
        <img src="assets/Foto/Logo5vit.png" alt="FIVIT" class="logo" style="width: 50px; height: 50px;">
    </a>
    <button class="menu" aria-label="Menu" aria-expanded="false" aria-controls="drawer">&#9776;</button>
</header>

<div class="drawer-backdrop" data-drawer-close></div>
<aside class="drawer" id="drawer" aria-hidden="true">
    <div class="drawer-header">
        <img src="assets/Foto/Logo5vit.png" alt="FIVIT" class="logo" style="width: 50px; height: 50px;">
        <button class="menu drawer-close" aria-label="Tutup Menu" data-drawer-close>&#9776;</button>
    </div>
    <nav class="drawer-nav">
        <a class="drawer-link" href="homescreen5vit.php">Home</a>

        <div class="drawer-section">Daily</div>
        <a class="drawer-link sub<?= $isLoggedIn ? '' : ' is-disabled' ?>" href="<?= $isLoggedIn ? 'health.php' : 'login.php' ?>">Basic Health Monitoring</a>
        <a class="drawer-link sub<?= $isLoggedIn ? '' : ' is-disabled' ?>" href="<?= $isLoggedIn ? 'sleep.php' : 'login.php' ?>">Sleep Tracking</a>
        <a class="drawer-link sub<?= $isLoggedIn ? '' : ' is-disabled' ?>" href="<?= $isLoggedIn ? 'mens_fivit.php' : 'login.php' ?>">Menstruation Tracking</a>

        <div class="drawer-section">Fitness</div>
        <a class="drawer-link sub<?= $isLoggedIn ? '' : ' is-disabled' ?>" href="<?= $isLoggedIn ? 'sportevent.php' : 'login.php' ?>">Sport Events</a>
        <a class="drawer-link sub<?= $isLoggedIn ? '' : ' is-disabled' ?>" href="<?= $isLoggedIn ? 'workout.php' : 'login.php' ?>">Work Out Personalization</a>
        <a class="drawer-link sub" href="gym.php">Gym booking</a>
        <a class="drawer-link sub" href="coach.php">Coach Directory</a>
        <a class="drawer-link sub" href="coach_sessions.php">Coach Sessions</a>

        <div class="drawer-section">Canteen</div>
        <a class="drawer-link sub<?= $isLoggedIn ? '' : ' is-disabled' ?>" href="<?= $isLoggedIn ? htmlspecialchars($canteenLink, ENT_QUOTES, 'UTF-8') : 'login.php' ?>"><?= htmlspecialchars($canteenLabel, ENT_QUOTES, 'UTF-8') ?></a>

        <div class="drawer-section">Event</div>
        <a class="drawer-link sub<?= $isLoggedIn ? '' : ' is-disabled' ?>" href="<?= $isLoggedIn ? 'education.php' : 'login.php' ?>">Education</a>
        <div class="drawer-section">Community</div>
        <a class="drawer-link sub<?= $isLoggedIn ? '' : ' is-disabled' ?>" href="<?= $isLoggedIn ? 'community.php' : 'login.php'?>"> Community Hub</a>

        <?php if ($isLoggedIn): ?>
            <div class="drawer-section">Account</div>
            <a class="drawer-link drawer-link-logout" href="logout.php">
                <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
                <span>Logout</span>
            </a>
        <?php endif; ?>
    </nav>
</aside>

<style>
.drawer-link.is-disabled {
    opacity: 0.55;
}
</style>
