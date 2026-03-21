<?php
session_start();

$errorData = $_SESSION['__error_redirect'] ?? null;
$isValid = is_array($errorData) && isset($errorData['time']) && (time() - (int)$errorData['time'] <= 300);
if (!$isValid) {
    header('Location: homescreen5vit.php');
    exit;
}

unset($_SESSION['__error_redirect']);
$pageTitle = 'Halaman Tidak Ditemukan';
include 'includes/header.php';
?>
<style>
.error-wrap {
    width: min(560px, 92%);
    margin: 24px auto 80px;
    background: #ffffff;
    border-radius: 22px;
    padding: 22px;
    box-shadow: 0 14px 32px rgba(15,23,42,0.1);
    border: 1px solid #e2e8f0;
    text-align: center;
}
.error-code {
    font-size: 52px;
    font-weight: 700;
    color: #0f766e;
    margin-bottom: 6px;
}
.error-title {
    font-size: 20px;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 6px;
}
.error-sub {
    font-size: 13px;
    color: #64748b;
    margin-bottom: 14px;
}
.error-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 10px 18px;
    border-radius: 999px;
    background: linear-gradient(135deg,#0f766e,#22c55e);
    color: #fff;
    text-decoration: none;
    font-weight: 700;
    font-size: 13px;
}
</style>

<main class="app">
    <section class="card sleep-hero" style="margin-bottom:16px;">
        <div class="sleep-hero-inner">
            <div class="emoji-bubble">&#9888;</div>
            <div>
                <div class="sleep-title">Oops! Ada kendala</div>
                <div class="sleep-sub">Kami arahkan kamu ke halaman aman.</div>
            </div>
        </div>
    </section>

    <section class="error-wrap">
        <div class="error-code">404</div>
        <div class="error-title">Halaman tidak tersedia</div>
        <div class="error-sub">Silakan kembali ke beranda.</div>
        <a class="error-btn" href="homescreen5vit.php">Kembali ke Home</a>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
