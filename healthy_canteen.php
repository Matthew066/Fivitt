<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require 'includes/db.php';

$userId = (int)($_SESSION['user_id'] ?? 0);
$role = strtolower(trim((string)($_SESSION['user_role'] ?? 'user')));
$allowedRoles = ['cooker'];
$foodImageDirRelative = 'assets/images/foods';
$foodImageDirAbsolute = __DIR__ . '/' . $foodImageDirRelative;

try {
    $pdo->query("ALTER TABLE foods ADD COLUMN image_path varchar(255) DEFAULT NULL");
} catch (Throwable $e) {
    // ignore when column already exists
}

try {
    $pdo->query("ALTER TABLE food_orders ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL");
} catch (Throwable $e) {
    // ignore when column already exists
}

if (!in_array($role, $allowedRoles, true)) {
    if ($role === 'admin') {
        header('Location: admin/healthy_canteen.php');
        exit;
    }
    header('Location: index.php');
    exit;
}

function is_managed_food_image_path(string $path, string $baseDir): bool
{
    if ($path === '' || !str_starts_with($path, $baseDir . '/')) {
        return false;
    }
    return is_file(__DIR__ . '/' . $path);
}

function redirect_with_status(string $status, string $message): void
{
    header('Location: healthy_canteen.php?status=' . urlencode($status) . '&message=' . urlencode($message));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim((string)($_POST['action'] ?? 'add'));

    if ($action === 'complete_order') {
        $orderId = (int)($_POST['order_id'] ?? 0);
        if ($orderId <= 0) {
            redirect_with_status('danger', 'Pesanan tidak valid.');
        }

        $updateOrderStmt = $pdo->prepare(
            "UPDATE food_orders SET status = 'completed', updated_at = NOW() WHERE id_food_orders = ? AND status = 'processing'"
        );
        $updateOrderStmt->execute([$orderId]);

        if ($updateOrderStmt->rowCount() > 0) {
            redirect_with_status('success', 'Pesanan berhasil diselesaikan.');
        }
        redirect_with_status('warning', 'Pesanan sudah selesai atau tidak ditemukan.');
    }

    if ($action === 'update') {
        $foodId = (int)($_POST['food_id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $calories = (int)($_POST['calories'] ?? 0);
        $protein = (float)($_POST['protein'] ?? 0);
        $fat = (float)($_POST['fat'] ?? 0);
        $carbs = (float)($_POST['carbs'] ?? 0);
        $rating = (int)($_POST['rating'] ?? 0);
        $currentImagePath = trim((string)($_POST['current_image_path'] ?? ''));
        $removeImage = isset($_POST['remove_image']);
        $imagePath = $removeImage ? '' : $currentImagePath;

        if ($foodId <= 0 || $name === '') {
            redirect_with_status('danger', 'Data menu untuk update tidak valid.');
        }

        if ($calories < 0 || $protein < 0 || $fat < 0 || $carbs < 0 || $rating < 0 || $rating > 5) {
            redirect_with_status('danger', 'Nilai nutrisi/rating tidak valid.');
        }

        $name = substr($name, 0, 255);

        $currentStmt = $pdo->prepare(
            'SELECT id_foods, id_users_created_by, name, calories, protein, fat, carbs, rating, image_path
             FROM foods
             WHERE id_foods = ?
             LIMIT 1'
        );
        $currentStmt->execute([$foodId]);
        $currentFood = $currentStmt->fetch(PDO::FETCH_ASSOC);
        if (!$currentFood) {
            redirect_with_status('danger', 'Menu tidak ditemukan.');
        }

        if (isset($_FILES['food_image']) && (int)($_FILES['food_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $uploadError = (int)($_FILES['food_image']['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($uploadError === UPLOAD_ERR_OK) {
                $maxFileSize = 2 * 1024 * 1024;
                $fileSize = (int)($_FILES['food_image']['size'] ?? 0);
                $tmpName = (string)($_FILES['food_image']['tmp_name'] ?? '');
                $allowedMimeTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                $detectedMime = $tmpName !== '' ? (string)mime_content_type($tmpName) : '';

                if ($fileSize > 0 && $fileSize <= $maxFileSize && isset($allowedMimeTypes[$detectedMime])) {
                    if (!is_dir($foodImageDirAbsolute)) {
                        mkdir($foodImageDirAbsolute, 0777, true);
                    }

                    $fileName = 'food-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $allowedMimeTypes[$detectedMime];
                    $targetAbsolutePath = $foodImageDirAbsolute . '/' . $fileName;
                    $targetRelativePath = $foodImageDirRelative . '/' . $fileName;

                    if (move_uploaded_file($tmpName, $targetAbsolutePath)) {
                        if (is_managed_food_image_path($currentImagePath, $foodImageDirRelative)) {
                            @unlink(__DIR__ . '/' . $currentImagePath);
                        }
                        $imagePath = $targetRelativePath;
                    }
                } else {
                    redirect_with_status('danger', 'Gagal upload foto. Gunakan JPG/PNG/WEBP max 2MB.');
                }
            } else {
                redirect_with_status('danger', 'Gagal upload foto makanan.');
            }
        } elseif ($removeImage && is_managed_food_image_path($currentImagePath, $foodImageDirRelative)) {
            @unlink(__DIR__ . '/' . $currentImagePath);
        }

        $updateStmt = $pdo->prepare(
            'UPDATE foods SET name = ?, calories = ?, protein = ?, fat = ?, carbs = ?, rating = ?, image_path = ? WHERE id_foods = ?'
        );
        $newImageValue = ($imagePath !== '' ? $imagePath : null);
        $oldImageValue = ($currentFood['image_path'] ?? null);
        $isUnchanged =
            trim((string)$currentFood['name']) === $name &&
            (int)$currentFood['calories'] === $calories &&
            (float)$currentFood['protein'] === $protein &&
            (float)$currentFood['fat'] === $fat &&
            (float)$currentFood['carbs'] === $carbs &&
            (int)$currentFood['rating'] === $rating &&
            (string)$oldImageValue === (string)$newImageValue;

        if ($isUnchanged) {
            redirect_with_status('info', 'Belum ada perubahan data.');
        }

        $updateStmt->execute([$name, $calories, $protein, $fat, $carbs, $rating, $newImageValue, $foodId]);
        redirect_with_status('success', 'Menu berhasil diupdate.');
    }

    if ($action === 'delete') {
        $foodId = (int)($_POST['food_id'] ?? 0);
        if ($foodId <= 0) {
            redirect_with_status('danger', 'Menu tidak valid.');
        }

        $imageStmt = $pdo->prepare('SELECT image_path FROM foods WHERE id_foods = ? LIMIT 1');
        $imageStmt->execute([$foodId]);
        $foodRow = $imageStmt->fetch(PDO::FETCH_ASSOC);

        $deleteStmt = $pdo->prepare('DELETE FROM foods WHERE id_foods = ?');
        $deleteStmt->execute([$foodId]);

        if ($deleteStmt->rowCount() > 0) {
            $imagePath = trim((string)($foodRow['image_path'] ?? ''));
            if (is_managed_food_image_path($imagePath, $foodImageDirRelative)) {
                @unlink(__DIR__ . '/' . $imagePath);
            }
        }

        if ($deleteStmt->rowCount() > 0) {
            redirect_with_status('success', 'Menu berhasil dihapus.');
        }
        redirect_with_status('warning', 'Menu tidak bisa dihapus.');
    }

    $name = trim((string)($_POST['name'] ?? ''));
    $calories = (int)($_POST['calories'] ?? 0);
    $protein = (float)($_POST['protein'] ?? 0);
    $fat = (float)($_POST['fat'] ?? 0);
    $carbs = (float)($_POST['carbs'] ?? 0);
    $rating = (int)($_POST['rating'] ?? 0);
    $imagePath = '';

    if ($name === '') {
        redirect_with_status('danger', 'Nama menu wajib diisi.');
    }

    if ($calories < 0 || $protein < 0 || $fat < 0 || $carbs < 0 || $rating < 0 || $rating > 5) {
        redirect_with_status('danger', 'Nilai nutrisi/rating tidak valid.');
    }

    $name = substr($name, 0, 255);

    if (isset($_FILES['food_image']) && (int)($_FILES['food_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $uploadError = (int)($_FILES['food_image']['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($uploadError === UPLOAD_ERR_OK) {
            $maxFileSize = 2 * 1024 * 1024;
            $fileSize = (int)($_FILES['food_image']['size'] ?? 0);
            $tmpName = (string)($_FILES['food_image']['tmp_name'] ?? '');
            $allowedMimeTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            $detectedMime = $tmpName !== '' ? (string)mime_content_type($tmpName) : '';

            if ($fileSize > 0 && $fileSize <= $maxFileSize && isset($allowedMimeTypes[$detectedMime])) {
                if (!is_dir($foodImageDirAbsolute)) {
                    mkdir($foodImageDirAbsolute, 0777, true);
                }

                $fileName = 'food-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $allowedMimeTypes[$detectedMime];
                $targetAbsolutePath = $foodImageDirAbsolute . '/' . $fileName;
                $targetRelativePath = $foodImageDirRelative . '/' . $fileName;

                if (move_uploaded_file($tmpName, $targetAbsolutePath)) {
                    $imagePath = $targetRelativePath;
                }
            } else {
                redirect_with_status('danger', 'Gagal upload foto. Gunakan JPG/PNG/WEBP max 2MB.');
            }
        } else {
            redirect_with_status('danger', 'Gagal upload foto makanan.');
        }
    }

    $insertStmt = $pdo->prepare(
        'INSERT INTO foods (name, calories, protein, fat, carbs, rating, image_path, id_users_created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $insertStmt->execute([$name, $calories, $protein, $fat, $carbs, $rating, ($imagePath !== '' ? $imagePath : null), $userId]);

    redirect_with_status('success', 'Menu berhasil ditambahkan.');
}

$status = strtolower(trim((string)($_GET['status'] ?? '')));
$message = trim((string)($_GET['message'] ?? ''));
$editId = (int)($_GET['edit_id'] ?? 0);
$allowedStatus = ['success', 'danger', 'warning', 'info'];
if (!in_array($status, $allowedStatus, true)) {
    $status = '';
}

$foodsStmt = $pdo->query(
    "SELECT f.id_foods, f.name, COALESCE(f.calories, 0) AS calories,
            COALESCE(f.protein, 0) AS protein, COALESCE(f.fat, 0) AS fat,
            COALESCE(f.carbs, 0) AS carbs, COALESCE(f.rating, 0) AS rating, f.image_path,
            f.id_users_created_by, COALESCE(u.name, 'Unknown') AS creator_name
     FROM foods f
     LEFT JOIN users u ON u.id_users = f.id_users_created_by
     ORDER BY f.id_foods DESC"
);
$foods = $foodsStmt->fetchAll(PDO::FETCH_ASSOC);

$processingOrders = [];
$processingOrderItems = [];
$completedOrders = [];
$completedOrderItems = [];
try {
    $ordersStmt = $pdo->query(
        "SELECT fo.id_food_orders, fo.user_id, fo.status, fo.created_at,
                COALESCE(u.name, 'Unknown') AS user_name
         FROM food_orders fo
         LEFT JOIN users u ON u.id_users = fo.user_id
         WHERE fo.status = 'processing'
         ORDER BY fo.created_at DESC"
    );
    $processingOrders = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);

    if ($processingOrders !== []) {
        $orderIds = array_map(static fn(array $row): int => (int)$row['id_food_orders'], $processingOrders);
        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
        $itemsStmt = $pdo->prepare(
            "SELECT foi.food_order_id, foi.quantity, f.name, f.image_path
             FROM food_order_items foi
             JOIN foods f ON f.id_foods = foi.food_id
             WHERE foi.food_order_id IN ($placeholders)
             ORDER BY foi.food_order_id DESC"
        );
        $itemsStmt->execute($orderIds);
        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($items as $item) {
            $orderId = (int)($item['food_order_id'] ?? 0);
            if (!isset($processingOrderItems[$orderId])) {
                $processingOrderItems[$orderId] = [];
            }
            $processingOrderItems[$orderId][] = $item;
        }
    }

    $todayStart = date('Y-m-d 00:00:00');
    $todayEnd = date('Y-m-d 23:59:59');
    $completedStmt = $pdo->prepare(
        "SELECT fo.id_food_orders, fo.user_id, fo.status, fo.created_at,
                COALESCE(u.name, 'Unknown') AS user_name
         FROM food_orders fo
         LEFT JOIN users u ON u.id_users = fo.user_id
         WHERE fo.status = 'completed'
           AND fo.updated_at BETWEEN ? AND ?
         ORDER BY fo.updated_at DESC
         LIMIT 10"
    );
    $completedStmt->execute([$todayStart, $todayEnd]);
    $completedOrders = $completedStmt->fetchAll(PDO::FETCH_ASSOC);

    if ($completedOrders !== []) {
        $completedIds = array_map(static fn(array $row): int => (int)$row['id_food_orders'], $completedOrders);
        $completedPlaceholders = implode(',', array_fill(0, count($completedIds), '?'));
        $completedItemsStmt = $pdo->prepare(
            "SELECT foi.food_order_id, foi.quantity, f.name, f.image_path
             FROM food_order_items foi
             JOIN foods f ON f.id_foods = foi.food_id
             WHERE foi.food_order_id IN ($completedPlaceholders)
             ORDER BY foi.food_order_id DESC"
        );
        $completedItemsStmt->execute($completedIds);
        $completedItems = $completedItemsStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($completedItems as $item) {
            $orderId = (int)($item['food_order_id'] ?? 0);
            if (!isset($completedOrderItems[$orderId])) {
                $completedOrderItems[$orderId] = [];
            }
            $completedOrderItems[$orderId][] = $item;
        }
    }
} catch (Throwable $e) {
    $processingOrders = [];
    $processingOrderItems = [];
    $completedOrders = [];
    $completedOrderItems = [];
}

$editFood = null;
if ($editId > 0) {
    foreach ($foods as $foodRow) {
        if ((int)$foodRow['id_foods'] === $editId) {
            $editFood = $foodRow;
            break;
        }
    }
}

$today = date('Y-m-d');
$totalOrdersToday = 0;
$topFoodTodayName = '-';
$topFoodTodayCount = 0;

try {
    $totalTodayStmt = $pdo->prepare("SELECT COUNT(*) FROM food_logs WHERE consumed_at = ?");
    $totalTodayStmt->execute([$today]);
    $totalOrdersToday = (int)($totalTodayStmt->fetchColumn() ?: 0);
} catch (Throwable $e) {
    $totalOrdersToday = 0;
}

try {
    $topFoodTodayStmt = $pdo->prepare(
        "SELECT COALESCE(NULLIF(TRIM(f.name), ''), CONCAT('Food #', fl.id_foods)) AS food_name, COUNT(*) AS total_orders
         FROM food_logs fl
         LEFT JOIN foods f ON f.id_foods = fl.id_foods
         WHERE fl.consumed_at = ?
         GROUP BY fl.id_foods, f.name
         ORDER BY total_orders DESC, food_name ASC
         LIMIT 1"
    );
    $topFoodTodayStmt->execute([$today]);
    $topFoodToday = $topFoodTodayStmt->fetch(PDO::FETCH_ASSOC);
    if ($topFoodToday) {
        $topFoodTodayName = (string)($topFoodToday['food_name'] ?? '-');
        $topFoodTodayCount = (int)($topFoodToday['total_orders'] ?? 0);
    }
} catch (Throwable $e) {
    $topFoodTodayName = '-';
    $topFoodTodayCount = 0;
}

$isEditing = $editFood !== null;

$pageTitle = 'Healthy Canteen';
$bodyClass = 'healthy-canteen-page';
require 'includes/header.php';
?>

<main class="container py-4">
    <div class="row g-4">
        <div class="col-12">
            <h1 class="h3 mb-1">Healthy Canteen</h1>
            <p class="text-muted mb-0">Cooker Menu</p>
        </div>

        <div class="col-12 col-md-6 col-xl-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <p class="text-muted mb-1">Total Menu</p>
                    <h4 class="mb-0"><?php echo count($foods); ?></h4>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <p class="text-muted mb-1">Pesanan Hari Ini</p>
                    <h4 class="mb-0"><?php echo $totalOrdersToday; ?></h4>
                    <small class="text-muted"><?php echo htmlspecialchars($today, ENT_QUOTES, 'UTF-8'); ?></small>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-12 col-xl-6">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <p class="text-muted mb-1">Menu Terlaris Hari Ini</p>
                    <h5 class="mb-1"><?php echo htmlspecialchars($topFoodTodayName, ENT_QUOTES, 'UTF-8'); ?></h5>
                    <small class="text-muted"><?php echo $topFoodTodayCount; ?> pesanan</small>
                </div>
            </div>
        </div>

        <?php if ($status !== '' && $message !== ''): ?>
            <div class="col-12">
                <div class="alert alert-<?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?> mb-0">
                    <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h5 mb-0">Pesanan</h2>
                        <span class="badge bg-warning text-dark"><?php echo count($processingOrders); ?> pesanan</span>
                    </div>
                    <?php if ($processingOrders === []): ?>
                        <div class="alert alert-info mb-0">Belum ada pesanan yang sedang diproses.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Customer</th>
                                        <th>Waktu</th>
                                        <th>Items</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($processingOrders as $order): ?>
                                        <?php
                                        $orderId = (int)($order['id_food_orders'] ?? 0);
                                        $orderItems = $processingOrderItems[$orderId] ?? [];
                                        ?>
                                        <tr>
                                            <td>#<?php echo $orderId; ?></td>
                                            <td><?php echo htmlspecialchars((string)($order['user_name'] ?? 'Unknown'), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars((string)($order['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td>
                                                <?php if ($orderItems === []): ?>
                                                    <span class="text-muted small">-</span>
                                                <?php else: ?>
                                                    <ul class="list-unstyled mb-0">
                                                        <?php foreach ($orderItems as $orderItem): ?>
                                                            <li class="d-flex align-items-center gap-2 mb-1">
                                                                <?php if (!empty($orderItem['image_path'])): ?>
                                                                    <img src="<?php echo htmlspecialchars((string)$orderItem['image_path'], ENT_QUOTES, 'UTF-8'); ?>" alt="Menu" style="width:28px;height:28px;object-fit:cover;border-radius:6px;">
                                                                <?php endif; ?>
                                                                <span><?php echo htmlspecialchars((string)($orderItem['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                                                                <span class="badge bg-light text-dark">x<?php echo (int)($orderItem['quantity'] ?? 0); ?></span>
                                                            </li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-nowrap">
                                                <form method="post" class="d-inline-block" onsubmit="return confirm('Selesaikan pesanan ini?');">
                                                    <input type="hidden" name="action" value="complete_order">
                                                    <input type="hidden" name="order_id" value="<?php echo $orderId; ?>">
                                                    <button type="submit" class="btn hc-btn hc-btn-complete hc-action-btn">Selesai</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h5 mb-0">Pesanan Selesai (Hari Ini)</h2>
                        <span class="badge bg-success"><?php echo count($completedOrders); ?> pesanan</span>
                    </div>
                    <?php if ($completedOrders === []): ?>
                        <div class="alert alert-info mb-0">Belum ada pesanan selesai.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Customer</th>
                                        <th>Waktu</th>
                                        <th>Items</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($completedOrders as $order): ?>
                                        <?php
                                        $orderId = (int)($order['id_food_orders'] ?? 0);
                                        $orderItems = $completedOrderItems[$orderId] ?? [];
                                        ?>
                                        <tr>
                                            <td>#<?php echo $orderId; ?></td>
                                            <td><?php echo htmlspecialchars((string)($order['user_name'] ?? 'Unknown'), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars((string)($order['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td>
                                                <?php if ($orderItems === []): ?>
                                                    <span class="text-muted small">-</span>
                                                <?php else: ?>
                                                    <ul class="list-unstyled mb-0">
                                                        <?php foreach ($orderItems as $orderItem): ?>
                                                            <li class="d-flex align-items-center gap-2 mb-1">
                                                                <?php if (!empty($orderItem['image_path'])): ?>
                                                                    <img src="<?php echo htmlspecialchars((string)$orderItem['image_path'], ENT_QUOTES, 'UTF-8'); ?>" alt="Menu" style="width:28px;height:28px;object-fit:cover;border-radius:6px;">
                                                                <?php endif; ?>
                                                                <span><?php echo htmlspecialchars((string)($orderItem['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                                                                <span class="badge bg-light text-dark">x<?php echo (int)($orderItem['quantity'] ?? 0); ?></span>
                                                            </li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-5">
            <div class="card shadow-sm" id="menu-form-card">
                <div class="card-body">
                    <h2 class="h5 mb-3"><?php echo $editFood ? 'Edit Menu' : 'Tambah Menu'; ?></h2>
                    <form method="post" enctype="multipart/form-data" class="row g-3" <?php echo $isEditing ? 'onsubmit="return confirm(\'Yakin ingin update menu ini?\');"' : ''; ?>>
                        <input type="hidden" name="action" value="<?php echo $editFood ? 'update' : 'add'; ?>">
                        <?php if ($editFood): ?>
                            <input type="hidden" name="food_id" value="<?php echo (int)$editFood['id_foods']; ?>">
                            <input type="hidden" name="current_image_path" value="<?php echo htmlspecialchars((string)($editFood['image_path'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                        <?php endif; ?>

                        <div class="col-12">
                            <label for="name" class="form-label">Nama Menu</label>
                            <input type="text" id="name" name="name" class="form-control" required maxlength="255" value="<?php echo htmlspecialchars((string)($editFood['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div class="col-6">
                            <label for="calories" class="form-label">Calories</label>
                            <input type="number" id="calories" name="calories" class="form-control" min="0" value="<?php echo (int)($editFood['calories'] ?? 0); ?>" required>
                        </div>

                        <div class="col-6">
                            <label for="rating" class="form-label">Rating (0-5)</label>
                            <input type="number" id="rating" name="rating" class="form-control" min="0" max="5" value="<?php echo (int)($editFood['rating'] ?? 0); ?>" required>
                        </div>

                        <div class="col-4">
                            <label for="protein" class="form-label">Protein</label>
                            <input type="number" id="protein" name="protein" class="form-control" min="0" step="0.01" value="<?php echo htmlspecialchars((string)($editFood['protein'] ?? '0'), ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div class="col-4">
                            <label for="fat" class="form-label">Fat</label>
                            <input type="number" id="fat" name="fat" class="form-control" min="0" step="0.01" value="<?php echo htmlspecialchars((string)($editFood['fat'] ?? '0'), ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div class="col-4">
                            <label for="carbs" class="form-label">Carbs</label>
                            <input type="number" id="carbs" name="carbs" class="form-control" min="0" step="0.01" value="<?php echo htmlspecialchars((string)($editFood['carbs'] ?? '0'), ENT_QUOTES, 'UTF-8'); ?>">
                        </div>

                        <div class="col-12">
                            <label for="food_image" class="form-label">Foto Makanan (JPG/PNG/WEBP, max 2MB)</label>
                            <input type="file" id="food_image" name="food_image" class="form-control" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                        </div>

                        <?php if ($editFood && !empty($editFood['image_path'])): ?>
                            <div class="col-12">
                                <img src="<?php echo htmlspecialchars((string)$editFood['image_path'], ENT_QUOTES, 'UTF-8'); ?>" alt="Foto menu" style="width:100px;height:100px;object-fit:cover;border-radius:10px;">
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" id="remove_image" name="remove_image">
                                    <label class="form-check-label" for="remove_image">Hapus foto saat update</label>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="col-12 hc-form-actions">
                            <button type="submit" class="btn hc-btn hc-btn-edit"><?php echo $editFood ? 'Update Menu' : 'Add Menu'; ?></button>
                            <?php if ($editFood): ?>
                                <a href="healthy_canteen.php" class="btn hc-btn hc-btn-cancel">Batal Edit</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-7">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h5 mb-0">Daftar Menu</h2>
                        <span class="badge bg-primary"><?php echo count($foods); ?> item</span>
                    </div>

                    <?php if ($foods === []): ?>
                        <div class="alert alert-info mb-0">Belum ada menu. Tambahkan menu pertama sekarang.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered align-middle mb-0 menu-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Gambar</th>
                                        <th>Nama</th>
                                        <th>Kalori</th>
                                        <th>Protein</th>
                                        <th>Fat</th>
                                        <th>Carbs</th>
                                        <th>Rating</th>
                                        <th>Maker</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($foods as $food): ?>
                                        <?php
                                        $canEdit = true;
                                        $canDelete = $canEdit;
                                        ?>
                                        <tr>
                                            <td><?php echo (int)$food['id_foods']; ?></td>
                                            <td>
                                                <?php if (!empty($food['image_path'])): ?>
                                                    <img src="<?php echo htmlspecialchars((string)$food['image_path'], ENT_QUOTES, 'UTF-8'); ?>" alt="Foto menu" style="width:42px;height:42px;object-fit:cover;border-radius:8px;">
                                                <?php else: ?>
                                                    <span class="text-muted small">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars((string)$food['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                            </td>
                                            <td><?php echo (int)$food['calories']; ?></td>
                                            <td><?php echo htmlspecialchars((string)$food['protein'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars((string)$food['fat'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars((string)$food['carbs'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo (int)$food['rating']; ?></td>
                                            <td><?php echo htmlspecialchars((string)$food['creator_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="text-nowrap hc-table-actions">
                                                <?php if ($canEdit || $canDelete): ?>
                                                    <div class="hc-table-actions-row">
                                                        <?php if ($canEdit): ?>
                                                            <a href="healthy_canteen.php?edit_id=<?php echo (int)$food['id_foods']; ?>#menu-form-card" class="btn btn-primary btn-sport-primary btn-action btn-sm">Edit</a>
                                                        <?php endif; ?>
                                                        <?php if ($canDelete): ?>
                                                            <form method="post" class="d-inline-block" onsubmit="return confirm('Yakin ingin menghapus menu ini?');">
                                                                <input type="hidden" name="action" value="delete">
                                                                <input type="hidden" name="food_id" value="<?php echo (int)$food['id_foods']; ?>">
                                                                <button type="submit" class="btn btn-danger btn-sport-danger btn-action btn-sm">Delete</button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted small">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
<script>
(() => {
    const status = <?= json_encode($status, JSON_UNESCAPED_UNICODE) ?>;
    const message = <?= json_encode($message, JSON_UNESCAPED_UNICODE) ?>;
    if (status && message) {
        alert(message);
    }
})();
</script>
