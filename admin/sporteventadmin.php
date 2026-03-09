<?php
require_once __DIR__ . '/auth.php';
require_once '../includes/db.php';

try {
    $pdo->query("ALTER TABLE events ADD COLUMN image_path varchar(255) DEFAULT NULL");
} catch (Throwable $e) {
    // ignore if column already exists
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$eventImageDirRelative = 'assets/images/events';
$eventImageDirAbsolute = dirname(__DIR__) . '/' . $eventImageDirRelative;

$action = (string) ($_POST['action'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'save_event') {
        $eventId = (int) ($_POST['event_id'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $eventType = trim((string) ($_POST['event_type'] ?? ''));
        $startDate = trim((string) ($_POST['start_date'] ?? ''));
        $endDate = trim((string) ($_POST['end_date'] ?? ''));
        $rewardPoints = (int) ($_POST['reward_points'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $currentImagePath = trim((string) ($_POST['current_image_path'] ?? ''));
        $removeImage = isset($_POST['remove_image']);
        $imagePath = $removeImage ? '' : $currentImagePath;

        if ($rewardPoints < 0) {
            $rewardPoints = 0;
        }

        if (isset($_FILES['event_image']) && (int) ($_FILES['event_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $uploadError = (int) ($_FILES['event_image']['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($uploadError === UPLOAD_ERR_OK) {
                $maxFileSize = 2 * 1024 * 1024;
                $fileSize = (int) ($_FILES['event_image']['size'] ?? 0);
                $tmpName = (string) ($_FILES['event_image']['tmp_name'] ?? '');
                $allowedMimeTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                $detectedMime = $tmpName !== '' ? (string) mime_content_type($tmpName) : '';

                if ($fileSize > 0 && $fileSize <= $maxFileSize && isset($allowedMimeTypes[$detectedMime])) {
                    if (!is_dir($eventImageDirAbsolute)) {
                        mkdir($eventImageDirAbsolute, 0777, true);
                    }

                    $fileName = 'event-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $allowedMimeTypes[$detectedMime];
                    $targetAbsolutePath = $eventImageDirAbsolute . '/' . $fileName;
                    $targetRelativePath = $eventImageDirRelative . '/' . $fileName;

                    if (move_uploaded_file($tmpName, $targetAbsolutePath)) {
                        if (
                            $currentImagePath !== '' &&
                            str_starts_with($currentImagePath, $eventImageDirRelative . '/') &&
                            is_file(dirname(__DIR__) . '/' . $currentImagePath)
                        ) {
                            @unlink(dirname(__DIR__) . '/' . $currentImagePath);
                        }
                        $imagePath = $targetRelativePath;
                    }
                }
            }
        }

        if (
            $removeImage &&
            $currentImagePath !== '' &&
            str_starts_with($currentImagePath, $eventImageDirRelative . '/') &&
            is_file(dirname(__DIR__) . '/' . $currentImagePath)
        ) {
            @unlink(dirname(__DIR__) . '/' . $currentImagePath);
        }

        if ($title !== '' && $description !== '' && $eventType !== '' && $startDate !== '' && $endDate !== '') {
            if ($endDate < $startDate) {
                $endDate = $startDate;
            }

            if ($eventId > 0) {
                $update = $pdo->prepare("
                    UPDATE events
                    SET title = ?, description = ?, event_type = ?, start_date = ?, end_date = ?, reward_points = ?, is_active = ?, image_path = ?
                    WHERE id_events = ?
                ");
                $update->execute([$title, $description, $eventType, $startDate, $endDate, $rewardPoints, $isActive, ($imagePath !== '' ? $imagePath : null), $eventId]);
            } else {
                $insert = $pdo->prepare("
                    INSERT INTO events (title, description, event_type, start_date, end_date, reward_points, is_active, image_path)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $insert->execute([$title, $description, $eventType, $startDate, $endDate, $rewardPoints, $isActive, ($imagePath !== '' ? $imagePath : null)]);
            }
        }

        header('Location: sporteventadmin.php');
        exit;
    }

    if ($action === 'delete_event') {
        $eventId = (int) ($_POST['event_id'] ?? 0);
        if ($eventId > 0) {
            $imageStmt = $pdo->prepare("SELECT image_path FROM events WHERE id_events = ? LIMIT 1");
            $imageStmt->execute([$eventId]);
            $eventRow = $imageStmt->fetch();

            $delete = $pdo->prepare("DELETE FROM events WHERE id_events = ?");
            $delete->execute([$eventId]);

            $imagePath = (string) ($eventRow['image_path'] ?? '');
            if (
                $imagePath !== '' &&
                str_starts_with($imagePath, $eventImageDirRelative . '/') &&
                is_file(dirname(__DIR__) . '/' . $imagePath)
            ) {
                @unlink(dirname(__DIR__) . '/' . $imagePath);
            }
        }

        header('Location: sporteventadmin.php');
        exit;
    }
}

$search = trim((string) ($_GET['q'] ?? ''));
$statusFilter = (string) ($_GET['status'] ?? 'all');
if (!in_array($statusFilter, ['all', 'active', 'inactive'], true)) {
    $statusFilter = 'all';
}

$editEventId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$editEvent = null;
if ($editEventId > 0) {
    $editStmt = $pdo->prepare("SELECT * FROM events WHERE id_events = ? LIMIT 1");
    $editStmt->execute([$editEventId]);
    $editEvent = $editStmt->fetch();
}

$where = [];
$params = [];
if ($search !== '') {
    $where[] = "(e.title LIKE ? OR e.event_type LIKE ? OR e.description LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
if ($statusFilter === 'active') {
    $where[] = "e.is_active = 1";
} elseif ($statusFilter === 'inactive') {
    $where[] = "e.is_active = 0";
}

$sql = "
    SELECT
        e.*,
        COUNT(ep.id_event_participants) AS total_participants
    FROM events e
    LEFT JOIN event_participants ep ON ep.event_id = e.id_events
";
if ($where) {
    $sql .= " WHERE " . implode(' AND ', $where);
}
$sql .= "
    GROUP BY
        e.id_events, e.title, e.description, e.event_type,
        e.start_date, e.end_date, e.reward_points, e.is_active, e.image_path
    ORDER BY e.start_date DESC, e.id_events DESC
";

$eventsStmt = $pdo->prepare($sql);
$eventsStmt->execute($params);
$events = $eventsStmt->fetchAll();
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
                    <div class="breadcrumb-title pe-3">Sport Events Management</div>
                </div>

                <div class="row">
                    <div class="col-12 col-lg-5">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="mb-3"><?php echo $editEvent ? 'Edit Event' : 'Tambah Event'; ?></h6>
                                <form method="post" enctype="multipart/form-data">
                                    <input type="hidden" name="action" value="save_event">
                                    <input type="hidden" name="event_id" value="<?php echo (int) ($editEvent['id_events'] ?? 0); ?>">
                                    <input type="hidden" name="current_image_path" value="<?php echo h($editEvent['image_path'] ?? ''); ?>">

                                    <div class="mb-3">
                                        <label class="form-label">Judul Event</label>
                                        <input type="text" class="form-control" name="title" required value="<?php echo h($editEvent['title'] ?? ''); ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Deskripsi</label>
                                        <textarea class="form-control" name="description" rows="3" required><?php echo h($editEvent['description'] ?? ''); ?></textarea>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Tipe Event</label>
                                        <input type="text" class="form-control" name="event_type" required value="<?php echo h($editEvent['event_type'] ?? ''); ?>">
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Tanggal Mulai</label>
                                            <input type="date" class="form-control" name="start_date" required value="<?php echo h($editEvent['start_date'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Tanggal Selesai</label>
                                            <input type="date" class="form-control" name="end_date" required value="<?php echo h($editEvent['end_date'] ?? ''); ?>">
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Reward Points</label>
                                        <input type="number" min="0" class="form-control" name="reward_points" value="<?php echo (int) ($editEvent['reward_points'] ?? 0); ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Poster Event (JPG/PNG/WEBP, max 2MB)</label>
                                        <input type="file" class="form-control" name="event_image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                                    </div>
                                    <?php if (!empty($editEvent['image_path'])): ?>
                                        <div class="mb-3">
                                            <img src="../<?php echo h($editEvent['image_path']); ?>" alt="Poster event" style="width:100px;height:100px;object-fit:cover;border-radius:10px;">
                                            <div class="form-check mt-2">
                                                <input class="form-check-input" type="checkbox" id="remove_image" name="remove_image">
                                                <label class="form-check-label" for="remove_image">Hapus poster saat simpan</label>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php $isActiveChecked = ((int) ($editEvent['is_active'] ?? 1)) === 1; ?>
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" <?php echo $isActiveChecked ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_active">Event aktif</label>
                                    </div>

                                    <button type="submit" class="btn btn-primary">
                                        <?php echo $editEvent ? 'Update Event' : 'Simpan Event'; ?>
                                    </button>
                                    <?php if ($editEvent): ?>
                                        <a href="sporteventadmin.php" class="btn btn-light ms-1">Batal</a>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-lg-7">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="mb-3">Daftar Event (Mempengaruhi Halaman Sport Event User)</h6>
                                <form method="get" class="row g-2 mb-3">
                                    <div class="col-md-7">
                                        <input type="text" class="form-control" name="q" value="<?php echo h($search); ?>" placeholder="Cari judul / tipe / deskripsi event...">
                                    </div>
                                    <div class="col-md-3">
                                        <select class="form-select" name="status">
                                            <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>Semua status</option>
                                            <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Aktif</option>
                                            <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Nonaktif</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2 d-grid">
                                        <button type="submit" class="btn btn-outline-primary">Filter</button>
                                    </div>
                                </form>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Poster</th>
                                                <th>Event</th>
                                                <th>Periode</th>
                                                <th>Poin</th>
                                                <th>Peserta</th>
                                                <th>Status</th>
                                                <th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!$events): ?>
                                                <tr>
                                                    <td colspan="8" class="text-center">Belum ada event.</td>
                                                </tr>
                                            <?php endif; ?>
                                            <?php foreach ($events as $event): ?>
                                                <tr>
                                                    <td><?php echo (int) $event['id_events']; ?></td>
                                                    <td>
                                                        <?php if (!empty($event['image_path'])): ?>
                                                            <img src="../<?php echo h($event['image_path']); ?>" alt="Poster" style="width:58px;height:58px;object-fit:cover;border-radius:8px;">
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php echo h($event['title']); ?>
                                                        <div class="text-muted small"><?php echo h($event['event_type']); ?></div>
                                                    </td>
                                                    <td>
                                                        <?php echo h($event['start_date']); ?>
                                                        <div class="text-muted small">s/d <?php echo h($event['end_date']); ?></div>
                                                    </td>
                                                    <td><?php echo (int) $event['reward_points']; ?></td>
                                                    <td><?php echo (int) $event['total_participants']; ?> orang</td>
                                                    <td>
                                                        <span class="badge <?php echo ((int) $event['is_active'] === 1) ? 'bg-success' : 'bg-secondary'; ?>">
                                                            <?php echo ((int) $event['is_active'] === 1) ? 'Aktif' : 'Nonaktif'; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <a href="sporteventadmin.php?edit=<?php echo (int) $event['id_events']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                                        <form method="post" class="d-inline" onsubmit="return confirm('Hapus event ini?');">
                                                            <input type="hidden" name="action" value="delete_event">
                                                            <input type="hidden" name="event_id" value="<?php echo (int) $event['id_events']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
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
