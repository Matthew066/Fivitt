<?php
session_start();
require_once 'includes/auth_guard.php';
require_login();

$pageTitle = 'Membership Payment';
include 'includes/header.php';
?>

<style>
.pay-wrap { width: min(720px, 92%); margin: 18px auto 80px; }
.pay-hero { background: linear-gradient(140deg,#0f766e 0%,#22c55e 55%,#7dd3fc 100%); color:#fff; border-radius:22px; padding:20px; box-shadow:0 12px 26px rgba(15,118,110,.2); }
.pay-hero h1 { margin:0 0 6px; font-size:22px; }
.pay-hero p { margin:0; font-size:13px; opacity:.92; }
.pay-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; margin-top:16px; }
.pay-card { background:#fff; border:1px solid #e2e8f0; border-radius:16px; padding:16px; box-shadow:0 8px 20px rgba(15,23,42,.07); }
.pay-title { margin:0 0 6px; font-size:16px; font-weight:700; }
.pay-price { font-size:18px; font-weight:800; color:#0f766e; margin-bottom:8px; }
.pay-list { margin:0; padding-left:18px; font-size:13px; color:#475569; }
.pay-btn { margin-top:12px; display:inline-flex; padding:10px 16px; border-radius:999px; background:#0ea5e9; color:#fff; font-weight:700; text-decoration:none; font-size:13px; }
.pay-btn.secondary { background:#22c55e; }
.note { font-size:12px; color:#64748b; margin-top:10px; }
@media (max-width: 760px) { .pay-grid { grid-template-columns:1fr; } }
</style>

<main class="pay-wrap">
    <section class="pay-hero">
        <h1>Membership Coach</h1>
        <p>Upgrade untuk akses sesi tanpa batas. Pembayaran akan memakai Xendit (segera).</p>
    </section>

    <section class="pay-grid">
        <article class="pay-card">
            <div class="pay-title">Free Tier</div>
            <div class="pay-price">Gratis</div>
            <ul class="pay-list">
                <li>Maks. 2 sesi request</li>
                <li>Akses coach public</li>
                <li>Tanpa prioritas</li>
            </ul>
            <div class="note">Aktif otomatis.</div>
        </article>

        <article class="pay-card">
            <div class="pay-title">Premium</div>
            <div class="pay-price">Rp 99.000 / bulan</div>
            <ul class="pay-list">
                <li>Request sesi tanpa batas</li>
                <li>Akses coach public + private (sesuai department)</li>
                <li>Prioritas jadwal</li>
            </ul>
            <a class="pay-btn secondary" href="#">Bayar (Coming Soon)</a>
            <div class="note">Integrasi Xendit akan ditambahkan.</div>
        </article>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
