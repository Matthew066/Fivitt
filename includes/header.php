<?php
$pageTitle = $pageTitle ?? 'FIVIT';
$bodyClass = isset($bodyClass) ? trim((string) $bodyClass) : '';
$showAppChrome = isset($showAppChrome) ? (bool) $showAppChrome : true;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="assets/images/favicon/icon-fivit.png">
    <title><?= htmlspecialchars($pageTitle) ?> - FIVIT</title>
    <link rel="stylesheet" href="assets/css/all.min.css">
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/media-query.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body<?= $bodyClass !== '' ? ' class="' . htmlspecialchars($bodyClass, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>

<?php if ($showAppChrome): ?>
<header class="header">
    <a href="homescreen5vit.php">
        <img src="assets/images/favicon/icon-fivit.png" alt="FIVIT" class="logo" style="width: 50px; height: 50px;">
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
        <a class="drawer-link sub" href="health.php">Basic Health Monitoring</a>
        <a class="drawer-link sub" href="sleep.php">Sleep Tracking</a>

        <div class="drawer-section">Fitness</div>
        <a class="drawer-link sub" href="sportevent.php">Sport Events</a>
        <a class="drawer-link sub" href="workout.php">Work Out Personalization</a>
        <a class="drawer-link sub" href="#">Gym booking</a>

        <div class="drawer-section">Canteen</div>
        <a class="drawer-link sub" href="foodselection.php">Food Selection</a>

        <div class="drawer-section">Event</div>
        <a class="drawer-link sub" href="#">Education</a>
    </nav>
</aside>
<?php endif; ?>

