<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$pageTitle = 'Artikel';
$extraStyles = [
    'assets/css/all.min.css'
];
include 'includes/header.php';
?>

<style>
.artikel-app {
    max-width: 420px;
    margin: 0 auto;
    padding: 10px 10px 28px;
}

.artikel-title {
    margin: 8px 8px 12px;
    font-size: 28px;
    font-weight: 700;
    color: #111827;
}

.artikel-list {
    background: linear-gradient(150deg, #47c9d8, #35bad1);
    border-radius: 16px;
    padding: 10px;
}

.artikel-item {
    display: grid;
    grid-template-columns: 68px 1fr;
    gap: 10px;
    text-decoration: none;
    margin-bottom: 8px;
    color: inherit;
}

.artikel-item:last-child {
    margin-bottom: 0;
}

.artikel-thumb {
    width: 68px;
    height: 68px;
    object-fit: cover;
    border-radius: 4px;
    background: #e2e8f0;
}

.artikel-content h3 {
    margin: 0;
    color: #fff;
    font-size: 17px;
    line-height: 1.2;
    font-weight: 700;
}

.artikel-meta {
    margin-top: 4px;
    color: #eafcff;
    font-size: 12px;
}

.artikel-footer {
    text-align: center;
    margin-top: 16px;
    color: #64748b;
    font-size: 14px;
}
</style>

<main class="artikel-app">
    <h1 class="artikel-title">Artikel</h1>

    <section class="artikel-list">
        <a href="#" class="artikel-item">
            <img class="artikel-thumb" src="assets/images/main-img/verify-email-address-img.png" alt="Artikel 1">
            <div class="artikel-content">
                <h3>ARTIKEL PENTINGNYA PENDIDIKAN BAGI MASA DEPAN</h3>
                <div class="artikel-meta">Oleh : Dispendik Mojokerto</div>
                <div class="artikel-meta">15 Agustus 2023</div>
            </div>
        </a>

        <a href="#" class="artikel-item">
            <img class="artikel-thumb" src="assets/images/main-img/contact-us-img.png" alt="Artikel 2">
            <div class="artikel-content">
                <h3>8 Cara Membuat Katalog Online untuk Tingkatkan Bisnis</h3>
                <div class="artikel-meta">Oleh : Redaksi Jagoan Hosting</div>
                <div class="artikel-meta">23 September 2023</div>
            </div>
        </a>

        <a href="#" class="artikel-item">
            <img class="artikel-thumb" src="assets/images/main-img/setting-img.png" alt="Artikel 3">
            <div class="artikel-content">
                <h3>Cara Penerima Beasiswa Amanah Bangun Desa Telah Memasuki Tahap Implementasi Proyek</h3>
                <div class="artikel-meta">Oleh : Kompasiana</div>
                <div class="artikel-meta">14 September 2023</div>
            </div>
        </a>

        <a href="#" class="artikel-item">
            <img class="artikel-thumb" src="assets/images/main-img/workout-plan-ready.png" alt="Artikel 4">
            <div class="artikel-content">
                <h3>LazisMu UMS Berikan Beasiswa Hingga Lulus</h3>
                <div class="artikel-meta">Oleh : Kompasiana</div>
                <div class="artikel-meta">08 September 2023</div>
            </div>
        </a>

        <a href="#" class="artikel-item">
            <img class="artikel-thumb" src="assets/images/main-img/reset-password-img.png" alt="Artikel 5">
            <div class="artikel-content">
                <h3>Pengumuman Pendaftaran Pewawancara Seleksi Beasiswa Indonesia Bangkit 2023</h3>
                <div class="artikel-meta">Oleh : Beasiswa Kemenag</div>
                <div class="artikel-meta">23 Juni 2023</div>
            </div>
        </a>

        <a href="#" class="artikel-item">
            <img class="artikel-thumb" src="assets/images/main-img/notification.png" alt="Artikel 6">
            <div class="artikel-content">
                <h3>Cerita Mahasiswa Asing Penerima Beasiswa Darmasiswa, Ingin Belajar Tari Tradisional</h3>
                <div class="artikel-meta">Oleh : Edukasi Okezone</div>
                <div class="artikel-meta">22 September 2023</div>
            </div>
        </a>

        <a href="#" class="artikel-item">
            <img class="artikel-thumb" src="assets/images/main-img/secure-img.png" alt="Artikel 7">
            <div class="artikel-content">
                <h3>5 Keunggulan Beasiswa Darmasiswa sebagai Diplomasi dan Pertukaran Budaya</h3>
                <div class="artikel-meta">Oleh : Edukasi Okezone</div>
                <div class="artikel-meta">23 September 2023</div>
            </div>
        </a>
    </section>

    <div class="artikel-footer">@Fivit 2026</div>
</main>

<?php include 'includes/footer.php'; ?>
