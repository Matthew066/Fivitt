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
    <link rel="stylesheet" href="assets/style.css?v=1">
    <link rel="stylesheet" href="assets/css/style.css?v=1">
    <link rel="stylesheet" href="assets/css/media-query.css">
    <?php foreach ($extraStyles as $stylePath): ?>
        <?php if (!is_string($stylePath) || trim($stylePath) === '') continue; ?>
        <link rel="stylesheet" href="<?= htmlspecialchars($stylePath, ENT_QUOTES, 'UTF-8') ?>">
    <?php endforeach; ?>
    <style>
        body.has-fivit-cursor{cursor:none}
        .fivit-cursor{position:fixed;top:0;left:0;width:14px;height:14px;border-radius:50%;background:#22c55e;box-shadow:0 0 0 4px rgba(34,197,94,.18);pointer-events:none;z-index:10000;transform:translate(-50%,-50%);transition:transform .15s ease,background .2s ease,box-shadow .2s ease,opacity .2s ease;opacity:0}
        .fivit-cursor-ring{position:fixed;top:0;left:0;width:34px;height:34px;border-radius:50%;border:2px solid rgba(79,172,254,.65);pointer-events:none;z-index:9999;transform:translate(-50%,-50%);transition:transform .18s ease,border-color .2s ease,opacity .2s ease;opacity:0}
        .fivit-cursor.is-active,.fivit-cursor-ring.is-active{opacity:1}
        .fivit-cursor.is-hover{transform:translate(-50%,-50%) scale(1.35);background:#4facfe;box-shadow:0 0 0 6px rgba(79,172,254,.2)}
        .fivit-cursor-ring.is-hover{transform:translate(-50%,-50%) scale(1.15);border-color:rgba(34,197,94,.7)}
        .fivit-cursor.is-click{transform:translate(-50%,-50%) scale(.85)}
        .fivit-cursor-ring.is-click{transform:translate(-50%,-50%) scale(.9)}
        @media (max-width:768px){body.has-fivit-cursor{cursor:auto}.fivit-cursor,.fivit-cursor-ring{display:none}}
    </style>
</head>
<body<?= $bodyClass !== '' ? ' class="' . htmlspecialchars((string) $bodyClass, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>

<header class="header">
    <img src="assets/Foto/Logo5vit.png" alt="FIVIT" class="logo" style="width: 50px; height: 50px;">
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
        <a class="drawer-link sub<?= $isLoggedIn ? '' : ' is-disabled' ?>" href="<?= $isLoggedIn ? 'foodselection.php' : 'login.php' ?>">Food Selection</a>

        <div class="drawer-section">Event</div>
        <a class="drawer-link sub<?= $isLoggedIn ? '' : ' is-disabled' ?>" href="<?= $isLoggedIn ? 'education.php' : 'login.php' ?>">Education</a>
    </nav>
</aside>

<style>
.drawer-link.is-disabled {
    opacity: 0.55;
}
</style>
