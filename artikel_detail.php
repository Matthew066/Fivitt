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

function artikel_detail_date(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return 'Tanggal belum tersedia';
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('d M Y', $timestamp) : $value;
}

$pageTitle = $article['title'] ?? 'Detail Artikel';
$extraStyles = [
    'assets/css/all.min.css'
];
include 'includes/header.php';
?>

<style>
body {
    background:
        radial-gradient(circle at top left, rgba(245, 158, 11, 0.10), transparent 18%),
        radial-gradient(circle at top right, rgba(20, 184, 166, 0.14), transparent 20%),
        linear-gradient(180deg, #faf7f1 0%, #f4f8f6 44%, #eef5fb 100%);
}
.detail-app { width: min(900px, 92%); margin: 16px auto 0; padding-bottom: 90px; }
.detail-back { display: inline-flex; align-items: center; gap: 8px; margin: 0 0 14px; text-decoration: none; font-weight: 800; color: #1f766e; }
.detail-card { background: linear-gradient(145deg, #ffffff, #fbfdfa 58%, #f4fbf8 100%); border-radius: 28px; padding: 18px; box-shadow: 0 16px 36px rgba(15, 23, 42, 0.08); border: 1px solid rgba(226,232,240,.86); }
.detail-image { width: 100%; height: 320px; object-fit: cover; border-radius: 22px; background: #e2e8f0; margin-bottom: 18px; }
.detail-pill { display: inline-flex; margin-bottom: 12px; padding: 7px 11px; border-radius: 999px; background: #ecfeff; color: #155e75; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; }
.detail-title { margin: 0 0 10px; font-size: clamp(28px, 4vw, 40px); line-height: 1.08; color: #16323b; }
.detail-meta { color: #6b7280; font-size: 13px; margin-bottom: 14px; line-height: 1.6; }
.detail-summary { margin: 0 0 16px; padding: 14px 16px; border-radius: 18px; background: #fff8ee; color: #7c5b2d; font-size: 14px; line-height: 1.65; border: 1px solid rgba(245, 158, 11, 0.16); }
.detail-content { color: #334155; font-size: 15px; line-height: 1.8; white-space: pre-line; }
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
            <span class="detail-pill"><?php echo htmlspecialchars($article['category'] ?: 'Artikel', ENT_QUOTES, 'UTF-8'); ?></span>
            <h1 class="detail-title"><?php echo htmlspecialchars($article['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
            <div class="detail-meta">
                Oleh <?php echo htmlspecialchars($article['author_name'] ?: 'Admin', ENT_QUOTES, 'UTF-8'); ?> |
                <?php echo htmlspecialchars(artikel_detail_date($article['published_at'] ?? null), ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <?php if (!empty($article['summary'])): ?>
                <p class="detail-summary"><?php echo htmlspecialchars($article['summary'], ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
            <div class="detail-content"><?php echo htmlspecialchars($article['content'] ?: 'Konten artikel belum diisi.', ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
