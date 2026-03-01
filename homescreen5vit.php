<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Fivit - Home</title>

<link rel="icon" href="assets/images/favicon/icon-fivit.png">
<link href="https://fonts.googleapis.com/css2?family=Zen+Dots&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&display=swap" rel="stylesheet">

<link rel="stylesheet" href="assets/css/all.min.css">
<link rel="stylesheet" href="assets/css/bootstrap.min.css">
<link rel="stylesheet" href="assets/css/style.css?v=20260301-home-fix">
<link rel="stylesheet" href="assets/css/media-query.css">
<style>
.preloader{
    position: fixed;
    width: 100%;
    height: 100%;
    background: radial-gradient(circle at center, #1f1f1f 0%, #000000 70%);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 9999;
}
</style>
<style>
.preloader img{
    animation: fadeScale 5s ease-in-out infinite alternate;
}

@keyframes fadeScale{
    from{
        transform: scale(1);
        opacity: 0.9;
    }
    to{
        transform: scale(1.05);
        opacity: 1;
    }
}
</style>
</head>

<body class="home-page">

<div class="site-content">

<div class="preloader">
    <img src="assets/images/favicon/icon-fivit.png" style="width:250px;">
</div>

<header>
<div class="header-logo">
    <img src="assets/images/splashscreen/logofivit.png"
         alt="Fivit Logo"
         style="height:55px; width:auto;">
</div>
    <button class="hamburger" aria-label="Menu">
        <span></span>
        <span></span>
        <span></span>
    </button>
</header>

<main>

<!-- GREETING -->
<section class="greeting-section">
    <div class="avatar"><i class="fa-regular fa-user"></i></div>
    <div class="greeting-text">
        <h2>Hi, <?php echo htmlspecialchars($_SESSION['user_name'], ENT_QUOTES, 'UTF-8'); ?>!</h2>
        <p>Let's Stay Healthy Today</p>
    </div>
</section>

<!-- PROGRESS -->
<section class="progress-section">
<div class="progress-stats">

    <div class="progress-labels">
        <span>0%</span>
        <span class="location-pin"><i class="fa-solid fa-location-dot"></i></span>
        <span>100%</span>
    </div>

    <div style="display:flex; justify-content:center;">
        <div class="progress-container" style="width:70%;">
            <div class="progress-fill" style="width:60%;"></div>
        </div>
    </div>

</div>
</section>

<!-- TRACKING -->
<section class="tracking-section">
<h3>Daily Health Tracking</h3>

<div class="tracking-grid">

<div class="tracking-card">
<div class="tracking-icon" style="background:linear-gradient(135deg,#FFE082,#FDD835);"><i class="fa-solid fa-heart-pulse"></i></div>
<label>Lifestyle Tracking</label>
</div>

<div class="tracking-card fitness">
<div class="tracking-icon"><i class="fa-solid fa-dumbbell"></i></div>
<label>Fitness and Sport</label>
</div>

<div class="tracking-card health">
<div class="tracking-icon"><i class="fa-solid fa-utensils"></i></div>
<label>Healthy Canteen</label>
</div>

<a href="sleep.php" style="text-decoration:none; color:inherit;">
    <div class="tracking-card secondary">
        <div class="tracking-icon" style="background:linear-gradient(135deg,#C8E6C9,#A5D6A7);"><i class="fa-regular fa-moon"></i></div>
        <label>Sleep Status <br>00:59h</label>
    </div>
</a>

<div class="tracking-card secondary">
<div class="tracking-icon" style="background:linear-gradient(135deg,#FFCCBC,#FFAB91);"><i class="fa-solid fa-glass-water"></i></div>
<label>Drink Status <br>6/L</label>
</div>

<div class="tracking-card secondary">
<div class="tracking-icon" style="background:linear-gradient(135deg,#F8BBD0,#F48FB1);"><i class="fa-solid fa-square-check"></i></div>
<label>Check-in Status <br>Done</label>
</div>

</div>
</section>

<!-- CHART -->
<section class="chart-section">

<div class="chart-container">
<div class="chart-bar" style="height:30%; border-top:2px solid #20C5BA;"></div>
<div class="chart-bar" style="height:45%; border-top:2px solid #20C5BA;"></div>
<div class="chart-bar" style="height:65%; border-top:2px solid #20C5BA;"></div>
<div class="chart-bar" style="height:40%; border-top:2px solid #A1887F;"></div>
<div class="chart-bar" style="height:55%; border-top:2px solid #A1887F;"></div>
<div class="chart-bar" style="height:48%; border-top:2px solid #7CB342;"></div>
<div class="chart-bar" style="height:70%; border-top:2px solid #7CB342;"></div>
</div>

<div class="chart-legend">

<div class="legend-item">
<div class="legend-dot" style="background:#20C5BA;"></div>
<span>Calories</span>
</div>

<div class="legend-item">
<div class="legend-dot" style="background:#A1887F;"></div>
<span>Steps</span>
</div>

<div class="legend-item">
<div class="legend-dot" style="background:#7CB342;"></div>
<span>Activity</span>
</div>

</div>
</section>

<!-- OTHER -->
<section class="other-section">
<h3>Other</h3>

<div class="other-grid">

<a href="sportevent.php" style="text-decoration:none; color:inherit;">
    <div class="other-card">
        <div class="other-icon"><i class="fa-solid fa-calendar-days"></i></div>
        <label>Events</label>
    </div>
</a>

<div class="other-card">
<div class="other-icon"><i class="fa-solid fa-book-open-reader"></i></div>
<label>Education</label>
</div>

<div class="other-card">
<div class="other-icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
<label>Recent Activity</label>
</div>

</div>
</section>

<div class="footer-watermark">@Fivit 2026</div>

</main>
<script>
window.addEventListener("load", function() {

    const preloader = document.querySelector(".preloader");

    if(preloader){

        // Loader tampil minimal 2500ms
        setTimeout(function(){

            preloader.style.transition = "opacity 0.5s ease";
            preloader.style.opacity = "0";

            setTimeout(function(){
                preloader.style.display = "none";
            }, 500);

        }, 2000); // ubah ini kalau mau lebih lama
    }

});
</script>
</body>
</html>

