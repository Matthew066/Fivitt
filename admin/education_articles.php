<?php
require_once __DIR__ . '/auth.php';
require_once '../includes/db.php';
require_once '../includes/articles.php';

ensure_articles_schema($pdo);
seed_default_articles($pdo);

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$imageDirRelative = 'assets/images/articles';
$imageDirAbsolute = dirname(__DIR__) . '/' . $imageDirRelative;
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'save_article') {
        $id = (int) ($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $summary = trim($_POST['summary'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $category = trim($_POST['category'] ?? 'artikel');
        $author = trim($_POST['author_name'] ?? '');
        $publishedAt = trim($_POST['published_at'] ?? '') ?: null;
        $sourceUrl = trim($_POST['source_url'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $currentImagePath = trim($_POST['current_image_path'] ?? '');
        $imagePath = $currentImagePath;
        if ($sourceUrl !== '' && !filter_var($sourceUrl, FILTER_VALIDATE_URL)) {
            $sourceUrl = '';
        }

        if (isset($_POST['remove_image'])) {
            $imagePath = '';
        }

        if (isset($_FILES['image']) && (int) ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $tmp = (string) ($_FILES['image']['tmp_name'] ?? '');
            $size = (int) ($_FILES['image']['size'] ?? 0);
            $mime = $tmp !== '' ? (string) mime_content_type($tmp) : '';
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            if ($size > 0 && $size <= 2 * 1024 * 1024 && isset($allowed[$mime])) {
                if (!is_dir($imageDirAbsolute)) {
                    mkdir($imageDirAbsolute, 0777, true);
                }
                $fileName = 'article-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
                $targetAbs = $imageDirAbsolute . '/' . $fileName;
                $targetRel = $imageDirRelative . '/' . $fileName;
                if (move_uploaded_file($tmp, $targetAbs)) {
                    if (
                        $currentImagePath !== '' &&
                        str_starts_with($currentImagePath, $imageDirRelative . '/') &&
                        is_file(dirname(__DIR__) . '/' . $currentImagePath)
                    ) {
                        @unlink(dirname(__DIR__) . '/' . $currentImagePath);
                    }
                    $imagePath = $targetRel;
                }
            }
        }

        if ($title !== '') {
            $creatorId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
            $creatorId = $creatorId > 0 ? $creatorId : null;

            if ($id > 0) {
                $stmt = $pdo->prepare("
                    UPDATE articles
                    SET title = ?, summary = ?, content = ?, category = ?, author_name = ?, published_at = ?, source_url = ?, is_active = ?, image_path = ?
                    WHERE id_articles = ?
                ");
                $stmt->execute([$title, $summary, $content, $category, $author, $publishedAt, ($sourceUrl !== '' ? $sourceUrl : null), $isActive, ($imagePath !== '' ? $imagePath : null), $id]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO articles (title, summary, content, category, author_name, published_at, source_url, is_active, image_path, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$title, $summary, $content, $category, $author, $publishedAt, ($sourceUrl !== '' ? $sourceUrl : null), $isActive, ($imagePath !== '' ? $imagePath : null), $creatorId]);
            }
        }

        header('Location: education_articles.php');
        exit;
    }

    if ($action === 'delete_article') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $imgStmt = $pdo->prepare("SELECT image_path FROM articles WHERE id_articles = ? LIMIT 1");
            $imgStmt->execute([$id]);
            $row = $imgStmt->fetch();

            $del = $pdo->prepare("DELETE FROM articles WHERE id_articles = ?");
            $del->execute([$id]);

            $imagePath = (string) ($row['image_path'] ?? '');
            if (
                $imagePath !== '' &&
                str_starts_with($imagePath, $imageDirRelative . '/') &&
                is_file(dirname(__DIR__) . '/' . $imagePath)
            ) {
                @unlink(dirname(__DIR__) . '/' . $imagePath);
            }
        }
        header('Location: education_articles.php');
        exit;
    }
}

$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$editArticle = null;
if ($editId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM articles WHERE id_articles = ? LIMIT 1");
    $stmt->execute([$editId]);
    $editArticle = $stmt->fetch();
}

$articles = $pdo->query("
    SELECT id_articles, title, summary, category, author_name, published_at, source_url, is_active, image_path
    FROM articles
    ORDER BY COALESCE(published_at, DATE(created_at)) DESC, id_articles DESC
")->fetchAll();
?>
<!doctype html>
<html lang="en">
<?php require 'includes/head.php'; ?>
<body>
<div class="wrapper">
    <?php include 'includes/sidebar.php'; ?>
    <?php include 'includes/header.php'; ?>
    <div class="page-wrapper">
        <div class="page-content">
            <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
                <div class="breadcrumb-title pe-3">Education & Artikel</div>
            </div>
            <div class="row">
                <div class="col-12 col-lg-5">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="mb-3"><?php echo $editArticle ? 'Edit Artikel' : 'Tambah Artikel'; ?></h6>
                            <form method="post" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="save_article">
                                <input type="hidden" name="id" value="<?php echo (int) ($editArticle['id_articles'] ?? 0); ?>">
                                <input type="hidden" name="current_image_path" value="<?php echo h($editArticle['image_path'] ?? ''); ?>">

                                <div class="mb-3">
                                    <label class="form-label">Judul</label>
                                    <input class="form-control" type="text" name="title" required value="<?php echo h($editArticle['title'] ?? ''); ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Ringkasan</label>
                                    <input class="form-control" type="text" name="summary" value="<?php echo h($editArticle['summary'] ?? ''); ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Isi Artikel</label>
                                    <textarea class="form-control" name="content" rows="5"><?php echo h($editArticle['content'] ?? ''); ?></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Kategori</label>
                                    <?php $category = $editArticle['category'] ?? 'artikel'; ?>
                                    <select class="form-select" name="category">
                                        <option value="education" <?php echo $category === 'education' ? 'selected' : ''; ?>>Education</option>
                                        <option value="artikel" <?php echo $category === 'artikel' ? 'selected' : ''; ?>>Artikel</option>
                                        <option value="tips" <?php echo $category === 'tips' ? 'selected' : ''; ?>>Tips</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Penulis</label>
                                    <input class="form-control" type="text" name="author_name" value="<?php echo h($editArticle['author_name'] ?? ''); ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Tanggal Publish</label>
                                    <input class="form-control" type="date" name="published_at" value="<?php echo h($editArticle['published_at'] ?? ''); ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Link Sumber Asli</label>
                                    <input class="form-control" type="url" name="source_url" placeholder="https://contoh.com/artikel" value="<?php echo h($editArticle['source_url'] ?? ''); ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Gambar (JPG/PNG/WEBP, max 2MB)</label>
                                    <input class="form-control" type="file" name="image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                                </div>
                                <?php if (!empty($editArticle['image_path'])): ?>
                                    <div class="mb-3">
                                        <img src="../<?php echo h($editArticle['image_path']); ?>" alt="Preview" style="width:90px;height:90px;object-fit:cover;border-radius:8px;">
                                        <div class="form-check mt-2">
                                            <input class="form-check-input" type="checkbox" id="remove_image" name="remove_image">
                                            <label class="form-check-label" for="remove_image">Hapus gambar saat simpan</label>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <div class="form-check form-switch mb-3">
                                    <?php $active = (int) ($editArticle['is_active'] ?? 1); ?>
                                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" <?php echo $active === 1 ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="is_active">Tampilkan di aplikasi user</label>
                                </div>
                                <button class="btn btn-primary" type="submit"><?php echo $editArticle ? 'Update' : 'Simpan'; ?></button>
                                <?php if ($editArticle): ?>
                                    <a class="btn btn-light ms-1" href="education_articles.php">Batal</a>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-lg-7">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="mb-3">Daftar Artikel</h6>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Judul</th>
                                        <th>Kategori</th>
                                        <th>Publish</th>
                                        <th>Sumber</th>
                                        <th>Status</th>
                                        <th>Aksi</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php if (!$articles): ?>
                                        <tr><td colspan="7" class="text-center">Belum ada artikel.</td></tr>
                                    <?php endif; ?>
                                    <?php foreach ($articles as $article): ?>
                                        <tr>
                                            <td><?php echo (int) $article['id_articles']; ?></td>
                                            <td>
                                                <?php echo h($article['title']); ?>
                                                <div class="text-muted small"><?php echo h($article['author_name']); ?></div>
                                            </td>
                                            <td><?php echo h($article['category']); ?></td>
                                            <td><?php echo h($article['published_at']); ?></td>
                                            <td>
                                                <?php if (!empty($article['source_url'])): ?>
                                                    <a href="<?php echo h($article['source_url']); ?>" target="_blank" rel="noopener noreferrer">Buka</a>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo ((int) $article['is_active'] === 1) ? 'bg-success' : 'bg-secondary'; ?>">
                                                    <?php echo ((int) $article['is_active'] === 1) ? 'Aktif' : 'Draft'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a class="btn btn-sm btn-outline-primary" href="education_articles.php?edit=<?php echo (int) $article['id_articles']; ?>">Edit</a>
                                                <form method="post" class="d-inline" onsubmit="return confirm('Hapus artikel ini?');">
                                                    <input type="hidden" name="action" value="delete_article">
                                                    <input type="hidden" name="id" value="<?php echo (int) $article['id_articles']; ?>">
                                                    <button class="btn btn-sm btn-outline-danger" type="submit">Hapus</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include 'includes/footer.php'; ?>
</div>
</body>
</html>
