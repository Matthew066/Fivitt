<?php
$pageTitle = $pageTitle ?? 'FIVIT';
$bodyClass = $bodyClass ?? '';
$extraStyles = $extraStyles ?? [];

if (!is_array($extraStyles)) {
    $extraStyles = [];
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
    <link rel="stylesheet" href="assets/css/style.css?v=1">
    <link rel="stylesheet" href="assets/css/media-query.css">
    <?php foreach ($extraStyles as $stylePath): ?>
        <?php if (!is_string($stylePath) || trim($stylePath) === '') continue; ?>
        <link rel="stylesheet" href="<?= htmlspecialchars($stylePath, ENT_QUOTES, 'UTF-8') ?>">
    <?php endforeach; ?>
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
        <a class="drawer-link sub" href="health.php">Basic Health Monitoring</a>
        <a class="drawer-link sub" href="sleep.php">Sleep Tracking</a>
        <a class="drawer-link sub" href="mens_fivit.php">Menstruation Tracking</a>

        <div class="drawer-section">Fitness</div>
        <a class="drawer-link sub" href="sportevent.php">Sport Events</a>
        <a class="drawer-link sub" href="workout.php">Work Out Personalization</a>
        <a class="drawer-link sub" href="gym.php">Gym booking</a>

        <div class="drawer-section">Canteen</div>
        <a class="drawer-link sub" href="">Food Selection</a>

        <div class="drawer-section">Event</div>
        <a class="drawer-link sub" href="#">Education</a>
    </nav>
</aside>

