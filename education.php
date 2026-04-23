<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'includes/db.php';
require_once 'includes/articles.php';

ensure_articles_schema($pdo);
seed_default_articles($pdo);

$pageTitle = 'Education';
$extraStyles = [
    'assets/css/all.min.css'
];
$bodyClass = 'education-page';
include 'includes/header.php';

$trendingStmt = $pdo->prepare("
    SELECT id_articles, title, summary, author_name, published_at, image_path, source_url
    FROM articles
    WHERE is_active = 1
    ORDER BY COALESCE(published_at, DATE(created_at)) DESC, id_articles DESC
    LIMIT 4
");
$trendingStmt->execute();
$trending = $trendingStmt->fetchAll();
?>

<style>
.education-page {
    background:
        radial-gradient(circle at top left, rgba(71, 201, 216, 0.16), transparent 24%),
        linear-gradient(180deg, #f6fbfb 0%, #eef8f7 56%, #f9fbff 100%);
}
.education-app { width: min(980px, 92%); margin: 18px auto 0; padding-bottom: 42px; }
.education-hero {
    background:
        radial-gradient(circle at top right, rgba(255,255,255,0.16), transparent 26%),
        linear-gradient(145deg, #14897f, #0f766e 60%, #155e75 100%);
    color: #fff; border-radius: 26px; padding: 28px 24px; box-shadow: 0 18px 38px rgba(15,118,110,.22);
}
.education-hero h1 { margin: 0 0 10px; font-size: clamp(28px, 4vw, 38px); }
.education-hero p { margin: 0; max-width: 680px; color: rgba(255,255,255,.84); line-height: 1.6; }
.education-pill-row { margin-top: 16px; display: flex; flex-wrap: wrap; gap: 10px; }
.education-pill { padding: 9px 12px; border-radius: 999px; font-size: 12px; font-weight: 700; background: rgba(255,255,255,.16); border: 1px solid rgba(255,255,255,.22); }
.education-grid { margin-top: 18px; display: grid; grid-template-columns: 320px 1fr; gap: 16px; }
.education-panel {
    background: rgba(255,255,255,.92);
    border: 1px solid rgba(186,230,253,.82);
    border-radius: 22px;
    padding: 18px;
    box-shadow: 0 14px 28px rgba(15,23,42,.06);
}
.education-panel h2 { margin: 0 0 8px; color: #0f172a; font-size: 22px; }
.education-sub { margin: 0 0 14px; color: #64748b; font-size: 13px; line-height: 1.5; }
.menu-grid { display: grid; gap: 12px; }
.menu-link {
    display: flex; align-items: center; gap: 12px; text-decoration: none; color: #1f2937;
    padding: 14px; border-radius: 18px; background: linear-gradient(145deg, #ffffff, #f4fbfb);
    border: 1px solid rgba(203,213,225,.76); box-shadow: 0 10px 18px rgba(15,23,42,.05);
    transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
}
.menu-link:hover { transform: translateY(-2px); box-shadow: 0 14px 22px rgba(15,118,110,.10); border-color: rgba(94,234,212,.8); }
.menu-icon {
    width: 44px; height: 44px; border-radius: 14px; display: inline-flex; align-items: center; justify-content: center;
    background: linear-gradient(145deg, #dff7ec, #e0f2fe); color: #0f766e; font-size: 18px;
}
.menu-copy strong { display: block; font-size: 15px; color: #0f172a; }
.menu-copy span { display: block; margin-top: 4px; color: #64748b; font-size: 12px; line-height: 1.45; }
.trending-list { display: grid; gap: 12px; }
.trending-item {
    display: grid; grid-template-columns: 92px 1fr 30px; gap: 14px; align-items: start;
    text-decoration: none; padding: 14px; border-radius: 20px;
    background: linear-gradient(145deg, #ffffff, #f8fafc 60%, #effaf7 100%);
    border: 1px solid rgba(226,232,240,.9); box-shadow: 0 12px 22px rgba(15,23,42,.05);
    transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
}
.trending-item:hover { transform: translateY(-3px); box-shadow: 0 16px 28px rgba(15,118,110,.10); border-color: rgba(125,211,252,.8); }
.trend-thumb { width: 92px; height: 92px; object-fit: cover; border-radius: 16px; background: #e2e8f0; }
.trend-content h3 { margin: 0; color: #0f172a; font-size: 18px; line-height: 1.25; font-weight: 700; }
.trend-summary { margin-top: 6px; color: #475569; font-size: 13px; line-height: 1.55; }
.trend-meta { margin-top: 8px; color: #0f766e; font-size: 12px; font-weight: 600; }
.trend-share { color: #0891b2; align-self: center; font-size: 16px; cursor: pointer; }
.empty-state { padding: 14px; border-radius: 16px; background: #f8fafc; color: #64748b; font-size: 13px; }
@media (max-width: 900px) {
    .education-grid { grid-template-columns: 1fr; }
}
@media (min-width: 900px) {
    .education-app {
        width: min(1160px, calc(100% - 40px));
        padding-bottom: 76px;
    }

    .education-hero {
        padding: 32px 28px;
        border-radius: 28px;
    }

    .education-hero h1 {
        margin-bottom: 12px;
        font-size: clamp(34px, 3.8vw, 44px);
    }

    .education-hero p {
        max-width: 860px;
        font-size: 16px;
        line-height: 1.8;
        color: rgba(255,255,255,.9);
    }

    .education-grid {
        grid-template-columns: 1fr;
        gap: 18px;
    }

    .education-panel {
        padding: 22px;
        border-radius: 24px;
    }

    .menu-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .menu-link {
        min-height: 96px;
        padding: 16px;
        border-radius: 20px;
    }
}
@media (max-width: 640px) {
    .education-app { width: min(94%, 560px); }
    .trending-item { grid-template-columns: 1fr; }
    .trend-thumb { width: 100%; height: 180px; }
}
</style>

<main class="education-app">
    <section class="education-hero">
        <h1>Education Hub</h1>
        <p>Konten singkat, artikel pilihan, dan insight kesehatan yang lebih relevan dengan ritme kerja dan kebugaran harian pengguna FiVit.</p>
        <div class="education-pill-row">
            <span class="education-pill">Lifestyle tips</span>
            <span class="education-pill">Artikel terkurasi</span>
            <span class="education-pill">Share cepat</span>
        </div>
    </section>

    <section class="education-grid">
        <article class="education-panel">
            <h2>Menu Belajar</h2>
            <p class="education-sub">Pilih pintu masuk yang paling cocok, mau langsung baca tips singkat atau eksplor artikel lengkap.</p>
            <div class="menu-grid">
                <a class="menu-link" href="artikel.php">
                    <span class="menu-icon"><i class="fa-solid fa-seedling"></i></span>
                    <span class="menu-copy">
                        <strong>Life Style Tips</strong>
                        <span>Tips sehat yang ringkas dan gampang dipraktikkan.</span>
                    </span>
                </a>
                <a class="menu-link" href="artikel.php">
                    <span class="menu-icon"><i class="fa-regular fa-newspaper"></i></span>
                    <span class="menu-copy">
                        <strong>Artikel</strong>
                        <span>Koleksi bacaan lebih lengkap untuk eksplor topik kesehatan.</span>
                    </span>
                </a>
            </div>
        </article>

        <article class="education-panel">
            <h2>Trending Sekarang</h2>
            <p class="education-sub">Artikel terbaru yang sedang relevan di FiVit, bisa kamu buka atau share langsung.</p>
            <div class="trending-list">
                <?php if (!$trending): ?>
                    <div class="empty-state">Belum ada artikel aktif.</div>
                <?php endif; ?>
                <?php foreach ($trending as $article): ?>
                    <?php
                        $isExternal = !empty($article['source_url']);
                        $articleLink = $isExternal ? $article['source_url'] : ('artikel_detail.php?id=' . (int) $article['id_articles']);
                    ?>
                    <a class="trending-item" href="<?php echo htmlspecialchars($articleLink, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $isExternal ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>>
                        <?php if (!empty($article['image_path'])): ?>
                            <img class="trend-thumb" src="<?php echo htmlspecialchars($article['image_path'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($article['title'], ENT_QUOTES, 'UTF-8'); ?>">
                        <?php else: ?>
                            <img class="trend-thumb" src="assets/images/main-img/verify-email-address-img.png" alt="Artikel">
                        <?php endif; ?>
                        <div class="trend-content">
                            <h3><?php echo htmlspecialchars($article['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                            <div class="trend-summary"><?php echo htmlspecialchars($article['summary'] ?: 'Buka artikel untuk baca ringkasan dan insight lengkapnya.', ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="trend-meta">Oleh <?php echo htmlspecialchars($article['author_name'] ?: 'Admin', ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars($article['published_at'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>
                        <i class="fa-solid fa-share-nodes trend-share"></i>
                    </a>
                <?php endforeach; ?>
            </div>
        </article>
    </section>
</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.trend-share').forEach(button => {
        button.addEventListener('click', async (event) => {
            event.preventDefault();
            event.stopPropagation();

            const link = button.closest('a');
            if (!link) return;

            const title = link.querySelector('h3')?.textContent || 'Artikel menarik dari FiVit';
            const url = link.href;

            if (navigator.share) {
                try {
                    await navigator.share({ title, url });
                } catch (err) {
                    console.error('Gagal share:', err);
                }
            } else if (navigator.clipboard) {
                try {
                    await navigator.clipboard.writeText(url);
                    alert('Link artikel berhasil disalin!');
                } catch (err) {
                    alert('Gagal menyalin link.');
                }
            }
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>
