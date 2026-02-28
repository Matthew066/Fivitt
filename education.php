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
.education-app { max-width: 420px; margin: 0 auto; padding: 10px 14px 30px; }
.education-title { font-size: 28px; font-weight: 700; margin: 8px 2px 14px; color: #111827; }
.menu-card { background: linear-gradient(145deg, #49ccd7, #33b7ca); border-radius: 16px; padding: 14px; margin-bottom: 14px; }
.menu-card h2 { margin: 0 0 10px; color: #fff; font-size: 22px; font-weight: 700; }
.menu-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.menu-link { display: flex; align-items: center; gap: 8px; background: #f9fcff; border-radius: 14px; padding: 12px 10px; text-decoration: none; color: #475569; font-weight: 700; font-size: 14px; }
.menu-link i { color: #94a3b8; }
.trending-title { font-size: 28px; font-weight: 700; margin: 4px 2px 10px; color: #111827; }
.trending-list { background: linear-gradient(150deg, #47c9d8, #35bad1); border-radius: 16px; padding: 10px; }
.trending-item { display: grid; grid-template-columns: 68px 1fr 22px; gap: 10px; align-items: start; margin-bottom: 8px; text-decoration: none; }
.trending-item:last-child { margin-bottom: 0; }
.trend-thumb { width: 68px; height: 68px; object-fit: cover; border-radius: 4px; background: #e2e8f0; }
.trend-content h3 { margin: 0; color: #fff; font-size: 17px; line-height: 1.2; font-weight: 700; }
.trend-meta { margin-top: 4px; color: #eafcff; font-size: 12px; }
.trend-share { color: #d9f8ff; align-self: center; font-size: 15px; }
.education-footer { text-align: center; margin-top: 18px; color: #64748b; font-size: 14px; }
</style>

<main class="education-app">
    <h1 class="education-title">Education</h1>

    <section class="menu-card">
        <h2>Menu</h2>
        <div class="menu-grid">
            <a class="menu-link" href="artikel.php">
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
        <?php if (!$trending): ?>
            <div class="trend-meta">Belum ada artikel aktif.</div>
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
                    <div class="trend-meta">Oleh : <?php echo htmlspecialchars($article['author_name'] ?: 'Admin', ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="trend-meta"><?php echo htmlspecialchars($article['published_at'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
                <i class="fa-solid fa-share-nodes trend-share"></i>
            </a>
        <?php endforeach; ?>
    </section>

    <div class="education-footer">@Fivit 2026</div>
</main>

<?php include 'includes/footer.php'; ?>
