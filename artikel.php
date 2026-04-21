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

function artikel_date(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return 'Tanggal belum tersedia';
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('d M Y', $timestamp) : $value;
}
?>

<style>
body {
    background:
        radial-gradient(circle at top left, rgba(245, 158, 11, 0.10), transparent 18%),
        radial-gradient(circle at top right, rgba(20, 184, 166, 0.14), transparent 20%),
        linear-gradient(180deg, #faf7f1 0%, #f4f8f6 44%, #eef5fb 100%);
}
.artikel-app { width: min(980px, 92%); margin: 16px auto 0; padding-bottom: 90px; }
.artikel-hero { padding: 24px; border-radius: 28px; color: #fffdf8; background: linear-gradient(145deg, #1f766e 0%, #2b8b82 48%, #d6b36f 118%); box-shadow: 0 22px 44px rgba(23, 63, 68, 0.16); }
.artikel-title { margin: 0 0 8px; font-size: clamp(30px, 4vw, 42px); line-height: 1.04; }
.artikel-sub { margin: 0; max-width: 680px; color: rgba(255,250,240,.84); font-size: 14px; line-height: 1.6; }
.artikel-list { margin-top: 16px; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
.artikel-item { display: flex; flex-direction: column; text-decoration: none; color: inherit; min-height: 100%; border-radius: 24px; overflow: hidden; background: linear-gradient(145deg, #ffffff, #fbfdfa 58%, #f4fbf8 100%); border: 1px solid rgba(226,232,240,.86); box-shadow: 0 16px 34px rgba(15, 23, 42, 0.08); transition: transform .18s ease, box-shadow .18s ease; }
.artikel-item:hover { transform: translateY(-3px); box-shadow: 0 20px 40px rgba(15, 23, 42, 0.12); }
.artikel-thumb { width: 100%; height: 210px; object-fit: cover; background: #e2e8f0; }
.artikel-content { padding: 18px; display: flex; flex: 1; flex-direction: column; }
.artikel-pill { display: inline-flex; align-self: flex-start; margin-bottom: 10px; padding: 7px 11px; border-radius: 999px; background: #ecfeff; color: #155e75; font-size: 11px; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; }
.artikel-content h3 { margin: 0; color: #16323b; font-size: 22px; line-height: 1.2; font-weight: 800; }
.artikel-summary { margin: 10px 0 0; color: #607077; font-size: 14px; line-height: 1.65; }
.artikel-meta { margin-top: auto; padding-top: 14px; color: #6b7280; font-size: 12px; line-height: 1.5; }
.artikel-empty { padding: 18px; border-radius: 22px; background: rgba(255,255,255,.82); color: #607077; border: 1px solid rgba(226,232,240,.82); }
@media (max-width: 760px) { .artikel-list { grid-template-columns: 1fr; } }
</style>

<main class="artikel-app">
    <section class="artikel-hero">
        <h1 class="artikel-title">Artikel & Insight</h1>
        <p class="artikel-sub">Bacaan singkat tentang pola hidup sehat, pemulihan, nutrisi, dan kebiasaan kecil yang bikin progres terasa lebih masuk akal.</p>
    </section>

    <section class="artikel-list">
        <?php if (!$articles): ?>
            <div class="artikel-empty">Belum ada artikel aktif.</div>
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
                    <span class="artikel-pill"><?= $isExternal ? 'External' : 'Fivit Article' ?></span>
                    <h3><?php echo htmlspecialchars($article['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                    <p class="artikel-summary"><?php echo htmlspecialchars($article['summary'] ?: 'Artikel singkat untuk bantu kamu memahami konteks dan langkah praktisnya.', ENT_QUOTES, 'UTF-8'); ?></p>
                    <div class="artikel-meta">
                        Oleh <?php echo htmlspecialchars($article['author_name'] ?: 'Admin', ENT_QUOTES, 'UTF-8'); ?><br>
                        <?php echo htmlspecialchars(artikel_date($article['published_at'] ?? null), ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
