<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$pageTitle = 'Home';
$bodyClass = 'home-page';
require 'includes/header.php';
?>

<div class="site-content">

<div class="preloader">
    <img src="assets/images/favicon/icon-fivit.png" style="width:250px;">
</div>

<main>

<!-- GREETING -->
<section class="greeting-section">
    <div class="avatar">??</div>
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
        <span class="location-pin">??</span>
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
<div class="tracking-icon" style="background:linear-gradient(135deg,#FFE082,#FDD835);">??</div>
<label>Lifestyle Tracking</label>
</div>

<div class="tracking-card fitness">
<div class="tracking-icon">??</div>
<label>Fitness and Sport</label>
</div>

<div class="tracking-card health">
<div class="tracking-icon">??</div>
<label>Healthy Canteen</label>
</div>

<a href="sleep.php" style="text-decoration:none; color:inherit;">
    <div class="tracking-card secondary">
        <div class="tracking-icon" style="background:linear-gradient(135deg,#C8E6C9,#A5D6A7);">??</div>
        <label>Sleep Status <br>00:59h</label>
    </div>
</a>

<div class="tracking-card secondary">
<div class="tracking-icon" style="background:linear-gradient(135deg,#FFCCBC,#FFAB91);">??</div>
<label>Drink Status <br>6/L</label>
</div>

<div class="tracking-card secondary">
<div class="tracking-icon" style="background:linear-gradient(135deg,#F8BBD0,#F48FB1);">?</div>
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
        <div class="other-icon">??</div>
        <label>Events</label>
    </div>
</a>

<div class="other-card">
<div class="other-icon">??</div>
<label>Education</label>
</div>

<div class="other-card">
<div class="other-icon">?</div>
<label>Recent Activity</label>
</div>

</div>
</section>

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

        }, 2500);
    }

});
</script>
<?php include 'includes/footer.php'; ?>

