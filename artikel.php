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

$pageTitle = 'Artikel';
$extraStyles = [
    'assets/css/all.min.css'
];
include 'includes/header.php';

$stmt = $pdo->prepare("
    SELECT id_articles, title, summary, author_name, published_at, image_path, source_url
    FROM articles
    WHERE is_active = 1
    ORDER BY COALESCE(published_at, DATE(created_at)) DESC, id_articles DESC
");
$stmt->execute();
$articles = $stmt->fetchAll();
?>

<style>
.artikel-app { max-width: 420px; margin: 0 auto; padding: 10px 10px 28px; }
.artikel-title { margin: 8px 8px 12px; font-size: 28px; font-weight: 700; color: #111827; }
.artikel-list { background: linear-gradient(150deg, #47c9d8, #35bad1); border-radius: 16px; padding: 10px; }
.artikel-item { display: grid; grid-template-columns: 68px 1fr; gap: 10px; text-decoration: none; margin-bottom: 8px; color: inherit; }
.artikel-item:last-child { margin-bottom: 0; }
.artikel-thumb { width: 68px; height: 68px; object-fit: cover; border-radius: 4px; background: #e2e8f0; }
.artikel-content h3 { margin: 0; color: #fff; font-size: 17px; line-height: 1.2; font-weight: 700; }
.artikel-meta { margin-top: 4px; color: #eafcff; font-size: 12px; }
.artikel-footer { text-align: center; margin-top: 16px; color: #64748b; font-size: 14px; }
</style>

<main class="artikel-app">
    <h1 class="artikel-title">Artikel</h1>
    <section class="artikel-list">
        <?php if (!$articles): ?>
            <div class="artikel-meta">Belum ada artikel aktif.</div>
        <?php endif; ?>
        <?php foreach ($articles as $article): ?>
            <?php
                $isExternal = !empty($article['source_url']);
                $articleLink = $isExternal ? $article['source_url'] : ('artikel_detail.php?id=' . (int) $article['id_articles']);
            ?>
            <a href="<?php echo htmlspecialchars($articleLink, ENT_QUOTES, 'UTF-8'); ?>" class="artikel-item" <?php echo $isExternal ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>>
                <?php if (!empty($article['image_path'])): ?>
                    <img class="artikel-thumb" src="<?php echo htmlspecialchars($article['image_path'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($article['title'], ENT_QUOTES, 'UTF-8'); ?>">
                <?php else: ?>
                    <img class="artikel-thumb" src="assets/images/main-img/verify-email-address-img.png" alt="Artikel">
                <?php endif; ?>
                <div class="artikel-content">
                    <h3><?php echo htmlspecialchars($article['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                    <div class="artikel-meta">Oleh : <?php echo htmlspecialchars($article['author_name'] ?: 'Admin', ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="artikel-meta"><?php echo htmlspecialchars($article['published_at'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
            </a>
        <?php endforeach; ?>
    </section>
    <div class="artikel-footer">@Fivit 2026</div>
</main>

<?php include 'includes/footer.php'; ?>
