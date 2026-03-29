<?php
$pageTitle = $pageTitle ?? 'FIVIT';
$bodyClass = $bodyClass ?? '';
$extraStyles = $extraStyles ?? [];

if (!is_array($extraStyles)) {
    $extraStyles = [];
}

$isLoggedIn = isset($_SESSION['user_id']);
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
    <link rel="stylesheet" href="assets/css/style.css?v=2">
    <link rel="stylesheet" href="assets/css/media-query.css">
    <?php foreach ($extraStyles as $stylePath): ?>
        <?php if (!is_string($stylePath) || trim($stylePath) === '') continue; ?>
        <link rel="stylesheet" href="<?= htmlspecialchars($stylePath, ENT_QUOTES, 'UTF-8') ?>">
    <?php endforeach; ?>
</head>
<body<?= $bodyClass !== '' ? ' class="' . htmlspecialchars((string) $bodyClass, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>

<header class="header">
    <img src="assets/Foto/Logo5vit.png" alt="FIVIT" class="logo">
    <button class="menu" aria-label="Menu" aria-expanded="false" aria-controls="drawer">&#9776;</button>
</header>

<div class="drawer-backdrop" data-drawer-close></div>
<aside class="drawer" id="drawer" aria-hidden="true">
    <div class="drawer-header">
        <img src="assets/Foto/Logo5vit.png" alt="FIVIT" class="logo">
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
        <a class="drawer-link sub" href="community.php">Community Hub</a>

        <div class="drawer-section">Canteen</div>
        <a class="drawer-link sub<?= $isLoggedIn ? '' : ' is-disabled' ?>" href="<?= $isLoggedIn ? 'foodselection.php' : 'login.php' ?>">Food Selection</a>

        <div class="drawer-section">Event</div>
        <a class="drawer-link sub<?= $isLoggedIn ? '' : ' is-disabled' ?>" href="<?= $isLoggedIn ? 'education.php' : 'login.php' ?>">Education</a>
    </nav>
</aside>
