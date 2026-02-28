<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
 
$pageTitle = 'Home';
$extraStyles = [
    'assets/css/all.min.css',
    'assets/css/homsescreen.css'
];
include 'includes/header.php';
?>

<style>
.preloader {
    position: fixed;
    inset: 0;
    z-index: 99999;
    display: flex;
    align-items: center;
    justify-content: center;
    background:
        radial-gradient(ellipse at center, rgb(0, 0, 0) 0%, rgba(8, 10, 15, 0.98) 100%),
        repeating-linear-gradient(90deg, rgb(0, 0, 0) 0 1px, transparent 1px 56px);
}

.preloader img {
    width: min(240px, 62vw);
    animation: preloadPulse 1.4s ease-in-out infinite;
}
    
@keyframes preloadPulse {
    0% { opacity: 0.7; transform: scale(0.96); }
    50% { opacity: 1; transform: scale(1); }
    100% { opacity: 0.7; transform: scale(0.96); }
}
</style>

<div class="preloader">
    <img src="assets/images/splashscreen/logofivit.png" alt="Loading Fivit">
</div>

<div class="homescreen-wrapper">
    <main class="home-main">
        <section class="hero-card">
            <div class="hero-top">
                <div class="avatar-circle"><i class="fa-solid fa-seedling"></i></div>
                <div class="hero-text">
                    <h1>Hi, <?php echo htmlspecialchars($_SESSION['user_name'], ENT_QUOTES, 'UTF-8'); ?>!</h1>
                    <p>Let's Stay Healthy Today</p>
                </div>
            </div>

            <div class="hero-progress">
                <div class="progress-labels">
                    <span>0%</span>
                    <span class="pin"><i class="fa-solid fa-location-dot"></i></span>
                    <span>100%</span>
                </div>
                <div class="progress-track">
                    <div class="progress-fill" style="width: 60%;"></div>
                </div>
            </div>
        </section>

        <section class="tracking-section">
            <h2>Daily Health Tracking</h2>
            <div class="tracking-grid">
                <div class="tracking-card">
                    <div class="tracking-icon icon-yellow"><i class="fa-solid fa-heart-pulse"></i></div>
                    <p>Lifestyle Tracking</p>
                </div>
                <a class="tracking-card link-card" href="gym_booking.php">
                    <div class="tracking-icon icon-purple"><i class="fa-solid fa-dumbbell"></i></div>
                    <p>Fitness and Sport</p>
                </a>
                <div class="tracking-card"> 
                    <div class="tracking-icon icon-cream"><i class="fa-solid fa-utensils"></i></div>
                    <p>Healthy Canteen</p>
                </div>
                <a class="tracking-card link-card" href="sleep.php">
                    <div class="tracking-icon icon-mint"><i class="fa-solid fa-bed"></i></div>
                    <p>Sleep Status<br>00:59h</p>
                </a>
                <div class="tracking-card">
                    <div class="tracking-icon icon-peach"><i class="fa-solid fa-glass-water"></i></div>
                    <p>Drink Status<br>6/L</p>
                </div>
                <div class="tracking-card">
                    <div class="tracking-icon icon-pink"><i class="fa-solid fa-check"></i></div>
                    <p>Check-in Status<br>Done</p>
                </div>
            </div>
        </section>

        <section class="chart-section">
            <div class="bars">
                <div class="bar h30"></div>
                <div class="bar h45"></div>
                <div class="bar h65"></div>
                <div class="bar h40"></div>
                <div class="bar h55"></div>
                <div class="bar h48"></div>
                <div class="bar h70"></div>
            </div>
            <div class="legend">
                <span><i class="dot teal"></i>Calories</span>
                <span><i class="dot sand"></i>Steps</span>
                <span><i class="dot green"></i>Activity</span>
            </div>
        </section>

        <section class="other-section">
            <h3>Other</h3>
            <div class="other-grid">
                <a class="other-card" href="sportevent.php">
                    <div class="other-icon"><i class="fa-solid fa-calendar-days"></i></div>
                    <p>Events</p>
                </a>
                <div class="other-card">
                    <div class="other-icon"><i class="fa-solid fa-graduation-cap"></i></div>
                    <p>Education</p>
                </div>
                <div class="other-card">
                    <div class="other-icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
                    <p>Recent Activity</p>
                </div>
            </div>
        </section>
    </main>

    <footer class="home-footer">@Fivit 2026</footer>
</div>



<?php include 'includes/footer.php'; ?>

<script>
window.addEventListener("load", function () {
    const loader = document.querySelector(".preloader");
    if (loader) {
        loader.style.transition = "opacity 1s ease";

        setTimeout(() => {
            loader.style.opacity = "0";
            setTimeout(() => {
                loader.style.display = "none";
            }, 1000);
        }, 2000);
    }
});
</script>
