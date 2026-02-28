<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$pageTitle = 'Education';
$extraStyles = [
    'assets/css/all.min.css'
];
include 'includes/header.php';
?>

<style>
.education-app {
    max-width: 420px;
    margin: 0 auto;
    padding: 10px 14px 30px;
}

.education-title {
    font-size: 28px;
    font-weight: 700;
    margin: 8px 2px 14px;
    color: #111827;
}

.menu-card {
    background: linear-gradient(145deg, #49ccd7, #33b7ca);
    border-radius: 16px;
    padding: 14px;
    margin-bottom: 14px;
}

.menu-card h2 {
    margin: 0 0 10px;
    color: #fff;
    font-size: 22px;
    font-weight: 700;
}

.menu-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.menu-link {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #f9fcff;
    border-radius: 14px;
    padding: 12px 10px;
    text-decoration: none;
    color: #475569;
    font-weight: 700;
    font-size: 14px;
}

.menu-link i {
    color: #94a3b8;
}

.trending-title {
    font-size: 28px;
    font-weight: 700;
    margin: 4px 2px 10px;
    color: #111827;
}

.trending-list {
    background: linear-gradient(150deg, #47c9d8, #35bad1);
    border-radius: 16px;
    padding: 10px;
}

.trending-item {
    display: grid;
    grid-template-columns: 68px 1fr 22px;
    gap: 10px;
    align-items: start;
    margin-bottom: 8px;
}

.trending-item:last-child {
    margin-bottom: 0;
}

.trend-thumb {
    width: 68px;
    height: 68px;
    object-fit: cover;
    border-radius: 4px;
    background: #e2e8f0;
}

.trend-content h3 {
    margin: 0;
    color: #fff;
    font-size: 17px;
    line-height: 1.2;
    font-weight: 700;
}

.trend-meta {
    margin-top: 4px;
    color: #eafcff;
    font-size: 12px;
}

.trend-share {
    color: #d9f8ff;
    align-self: center;
    font-size: 15px;
}

.education-footer {
    text-align: center;
    margin-top: 18px;
    color: #64748b;
    font-size: 14px;
}
</style>

<main class="education-app">
    <h1 class="education-title">Education</h1>

    <section class="menu-card">
        <h2>Menu</h2>
        <div class="menu-grid">
            <a class="menu-link" href="#">
                <i class="fa-solid fa-book"></i>
                <span>Life Style Tips</span>
            </a>
            <a class="menu-link" href="artikel.php">
                <i class="fa-regular fa-newspaper"></i>
                <span>Artikel</span>
            </a>
        </div>
    </section>

    <h2 class="trending-title">Trending</h2>
    <section class="trending-list">
        <article class="trending-item">
            <img class="trend-thumb" src="assets/images/main-img/verify-email-address-img.png" alt="Artikel 1">
            <div class="trend-content">
                <h3>ARTIKEL PENTINGNYA PENDIDIKAN BAGI MASA DEPAN</h3>
                <div class="trend-meta">Oleh : Dispendik Mojokerto</div>
                <div class="trend-meta">15 Agustus 2023</div>
            </div>
            <i class="fa-solid fa-share-nodes trend-share"></i>
        </article>

        <article class="trending-item">
            <img class="trend-thumb" src="assets/images/main-img/contact-us-img.png" alt="Artikel 2">
            <div class="trend-content">
                <h3>8 Cara Membuat Katalog Online untuk Tingkatkan Bisnis</h3>
                <div class="trend-meta">Oleh : Redaksi Jagoan Hosting</div>
                <div class="trend-meta">24 September 2023</div>
            </div>
            <i class="fa-solid fa-share-nodes trend-share"></i>
        </article>

        <article class="trending-item">
            <img class="trend-thumb" src="assets/images/main-img/setting-img.png" alt="Artikel 3">
            <div class="trend-content">
                <h3>Pergi Penerima Beasiswa Anganah Bangun Desa Telah Memasuki Tahap Implementasi Proyek</h3>
                <div class="trend-meta">Oleh : Kompasiana</div>
                <div class="trend-meta">14 September 2023</div>
            </div>
            <i class="fa-solid fa-share-nodes trend-share"></i>
        </article>

        <article class="trending-item">
            <img class="trend-thumb" src="assets/images/main-img/workout-plan-ready.png" alt="Artikel 4">
            <div class="trend-content">
                <h3>LazisMu UMS Berikan Beasiswa hingga Lulus</h3>
                <div class="trend-meta">Oleh : Kompasiana</div>
                <div class="trend-meta">08 September 2023</div>
            </div>
            <i class="fa-solid fa-share-nodes trend-share"></i>
        </article>
    </section>

    <div class="education-footer">@Fivit 2026</div>
</main>

<?php include 'includes/footer.php'; ?>
