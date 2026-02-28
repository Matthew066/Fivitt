<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'includes/db.php';
require_once 'includes/articles.php';

ensure_articles_schema($pdo);

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$article = null;

if ($id > 0) {
    $stmt = $pdo->prepare("
        SELECT id_articles, title, summary, content, category, author_name, published_at, image_path
        FROM articles
        WHERE id_articles = ? AND is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $article = $stmt->fetch();
}

$pageTitle = $article['title'] ?? 'Detail Artikel';
$extraStyles = [
    'assets/css/all.min.css'
];
include 'includes/header.php';
?>

<style>
.detail-app { max-width: 420px; margin: 0 auto; padding: 10px 14px 26px; }
.detail-card { background: #ffffff; border-radius: 16px; padding: 14px; box-shadow: 0 8px 18px rgba(18, 44, 74, 0.12); }
.detail-image { width: 100%; height: 190px; object-fit: cover; border-radius: 12px; background: #e2e8f0; margin-bottom: 12px; }
.detail-title { margin: 0 0 8px; font-size: 24px; line-height: 1.2; color: #0f172a; }
.detail-meta { color: #64748b; font-size: 13px; margin-bottom: 10px; }
.detail-content { color: #1f2937; font-size: 15px; line-height: 1.55; white-space: pre-line; }
.detail-back { display: inline-block; margin: 12px 0; text-decoration: none; font-weight: 700; color: #1e90a3; }
</style>

<main class="detail-app">
    <a class="detail-back" href="artikel.php"><i class="fa-solid fa-arrow-left"></i> Kembali ke artikel</a>

    <section class="detail-card">
        <?php if (!$article): ?>
            <h1 class="detail-title">Artikel tidak ditemukan</h1>
            <p class="detail-meta">Artikel mungkin sudah dihapus atau tidak aktif.</p>
        <?php else: ?>
            <?php if (!empty($article['image_path'])): ?>
                <img class="detail-image" src="<?php echo htmlspecialchars($article['image_path'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($article['title'], ENT_QUOTES, 'UTF-8'); ?>">
            <?php endif; ?>
            <h1 class="detail-title"><?php echo htmlspecialchars($article['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
            <div class="detail-meta">
                Kategori: <?php echo htmlspecialchars($article['category'] ?: 'artikel', ENT_QUOTES, 'UTF-8'); ?> |
                Oleh: <?php echo htmlspecialchars($article['author_name'] ?: 'Admin', ENT_QUOTES, 'UTF-8'); ?> |
                <?php echo htmlspecialchars($article['published_at'] ?: '-', ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <?php if (!empty($article['summary'])): ?>
                <p class="detail-meta"><?php echo htmlspecialchars($article['summary'], ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
            <div class="detail-content"><?php echo htmlspecialchars($article['content'] ?: 'Konten artikel belum diisi.', ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
