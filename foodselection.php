<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    if (
        $_SERVER['REQUEST_METHOD'] === 'POST' &&
        isset($_SERVER['CONTENT_TYPE']) &&
        stripos($_SERVER['CONTENT_TYPE'], 'application/json') !== false
    ) {
        header('Content-Type: application/json; charset=UTF-8');
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Silakan login terlebih dahulu.']);
        exit;
    }

    header('Location: login.php');
    exit;
}

require 'includes/db.php';

$userId = (int)($_SESSION['user_id'] ?? 0);

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_SERVER['CONTENT_TYPE']) &&
    stripos($_SERVER['CONTENT_TYPE'], 'application/json') !== false
) {
    header('Content-Type: application/json; charset=UTF-8');
    $rawBody = file_get_contents('php://input');
    $payload = json_decode($rawBody, true);

    if (!is_array($payload) || ($payload['action'] ?? '') !== 'confirm_order') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Permintaan tidak valid.']);
        exit;
    }

    $items = $payload['items'] ?? [];
    if (!is_array($items) || $items === []) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Keranjang kosong.']);
        exit;
    }

    $savedRows = 0;
    $consumedAt = date('Y-m-d');

    try {
        $pdo->beginTransaction();

        $findFoodByIdStmt = $pdo->prepare("SELECT id_foods FROM foods WHERE id_foods = ? LIMIT 1");
        $insertLogStmt = $pdo->prepare("INSERT INTO food_logs (id_users, id_foods, consumed_at) VALUES (?, ?, ?)");
        $validItems = [];

        foreach ($items as $item) {
            $qty = (int)($item['qty'] ?? 0);
            if ($qty <= 0) {
                continue;
            }

            $foodId = (int)($item['id'] ?? 0);
            if ($foodId > 0) {
                $findFoodByIdStmt->execute([$foodId]);
                $existing = $findFoodByIdStmt->fetch(PDO::FETCH_ASSOC);
                if (!$existing) {
                    $foodId = 0;
                }
            }

            if ($foodId <= 0) {
                continue;
            }

            $validItems[] = [
                'food_id' => $foodId,
                'qty' => $qty
            ];
        }

        if ($validItems === []) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Tidak ada item valid untuk disimpan.']);
            exit;
        }

        $insertOrderStmt = $pdo->prepare("INSERT INTO food_orders (user_id, status, created_at) VALUES (?, 'processing', NOW())");
        $insertOrderStmt->execute([$userId]);
        $orderId = (int)$pdo->lastInsertId();

        $insertOrderItemStmt = $pdo->prepare("INSERT INTO food_order_items (food_order_id, food_id, quantity) VALUES (?, ?, ?)");

        foreach ($validItems as $validItem) {
            $foodId = (int)$validItem['food_id'];
            $qty = (int)$validItem['qty'];

            $insertOrderItemStmt->execute([$orderId, $foodId, $qty]);

            for ($i = 0; $i < $qty; $i++) {
                $insertLogStmt->execute([$userId, $foodId, $consumedAt]);
                $savedRows++;
            }
        }

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Pesanan berhasil disimpan.',
            'saved_rows' => $savedRows,
        ]);
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan pesanan.']);
        exit;
    }
}

$menu = [];
try {
    $menuStmt = $pdo->query("SELECT id_foods, name, COALESCE(calories, 0) AS calories, image_path FROM foods ORDER BY id_foods ASC LIMIT 50");
    $menu = $menuStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $menu = [];
}

$lastOrder = [];
$lastOrderStatus = '';
try {
    $lastOrderStmt = $pdo->prepare("
        SELECT fo.id_food_orders, fo.status
        FROM food_orders fo
        WHERE fo.user_id = ?
        ORDER BY fo.created_at DESC
        LIMIT 1
    ");
    $lastOrderStmt->execute([$userId]);
    $lastOrderRow = $lastOrderStmt->fetch(PDO::FETCH_ASSOC);

    if ($lastOrderRow) {
        $lastOrderStatus = (string)($lastOrderRow['status'] ?? '');
        $orderItemsStmt = $pdo->prepare("
            SELECT f.name, f.image_path, foi.quantity
            FROM food_order_items foi
            JOIN foods f ON f.id_foods = foi.food_id
            WHERE foi.food_order_id = ?
        ");
        $orderItemsStmt->execute([(int)$lastOrderRow['id_food_orders']]);
        $lastOrder = $orderItemsStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    $lastOrder = [];
}

$menuPayload = array_map(static function (array $row): array {
    $imagePath = trim((string)($row['image_path'] ?? ''));
    $imageUrl = '';
    if ($imagePath !== '' && preg_match('/^[a-zA-Z0-9_\/\.\-]+$/', $imagePath)) {
        $imageUrl = $imagePath;
    }

    return [
        'id' => (int)($row['id_foods'] ?? 0),
        'name' => (string)($row['name'] ?? ''),
        'calories' => (int)($row['calories'] ?? 0),
        'image_url' => $imageUrl,
        'qty' => 0,
    ];
}, $menu);

$lastOrderPayload = array_map(static function (array $row): array {
    $imagePath = trim((string)($row['image_path'] ?? ''));
    $imageUrl = '';
    if ($imagePath !== '' && preg_match('/^[a-zA-Z0-9_\/\.\-]+$/', $imagePath)) {
        $imageUrl = $imagePath;
    }

    return [
        'name' => (string)($row['name'] ?? ''),
        'qty' => (int)($row['quantity'] ?? 0),
        'image_url' => $imageUrl,
    ];
}, $lastOrder);

$pageTitle = 'Food Selection';
$bodyClass = 'foodselection-page';
require 'includes/header.php';
?>

<link rel="stylesheet" href="assets/css/all.min.css">
<main class="food-screen">
    <section class="food-head">
        <h1 id="screen-title">Food Selection</h1>
        <button type="button" class="cart-btn" id="cart-toggle" aria-label="Toggle Order">
            <i class="fa-solid fa-cart-shopping"></i>
            <span class="cart-count" id="cart-count">0</span>
        </button>
    </section>

    <section class="panel">
        <h2 class="panel-title">Menu</h2>
        <div class="food-grid" id="food-grid"></div>
        <div class="cart-actions is-hidden" id="cart-actions">
            <button type="button" class="back-btn" id="back-btn" hidden>Back</button>
            <button type="button" class="confirm-btn" id="confirm-btn" hidden>Confirm</button>
        </div>
        <div class="order-status is-hidden" id="order-status">
            <div class="order-status-head">
                <h3>Pesanan</h3>
                <span class="order-badge" id="order-status-badge">Sedang diproses</span>
            </div>
            <div class="order-status-body" id="order-status-body"></div>
        </div>
    </section>
</main>

<script>
(() => {
    const menu = <?= json_encode($menuPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const lastOrderSeed = <?= json_encode($lastOrderPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const lastOrderStatusSeed = <?= json_encode($lastOrderStatus, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    let isOrderMode = false;
    let isSaving = false;
    let lastOrder = Array.isArray(lastOrderSeed) ? lastOrderSeed : [];
    let lastOrderStatus = typeof lastOrderStatusSeed === 'string' ? lastOrderStatusSeed : '';
    const grid = document.getElementById('food-grid');
    const title = document.getElementById('screen-title');
    const cartCount = document.getElementById('cart-count');
    const confirmBtn = document.getElementById('confirm-btn');
    const backBtn = document.getElementById('back-btn');
    const cartActions = document.getElementById('cart-actions');
    const cartToggle = document.getElementById('cart-toggle');
    const orderStatus = document.getElementById('order-status');
    const orderStatusBody = document.getElementById('order-status-body');
    const orderStatusBadge = document.getElementById('order-status-badge');

    function totalItems() {
        return menu.reduce((sum, item) => sum + item.qty, 0);
    }

    function visibleItems() {
        if (!isOrderMode) return menu;
        return menu.filter(item => item.qty > 0);
    }

    function render() {
        const items = visibleItems();
        const total = totalItems();

        title.textContent = isOrderMode ? 'Food Order' : 'Food Selection';
        cartCount.textContent = String(total);
        cartCount.style.display = total > 0 ? 'inline-flex' : 'none';

        const shouldShowOrderStatus = isOrderMode;
        orderStatus.style.display = shouldShowOrderStatus ? 'block' : 'none';
        if (!shouldShowOrderStatus) {
            orderStatus.classList.add('is-hidden');
            orderStatusBody.innerHTML = '';
        }

        const showBack = isOrderMode && !isSaving;
        const showConfirm = isOrderMode && total > 0 && !isSaving;
        backBtn.hidden = !showBack;
        confirmBtn.hidden = !showConfirm;
        cartActions.classList.toggle('is-hidden', !(showBack || showConfirm));

        confirmBtn.disabled = isSaving;
        backBtn.disabled = isSaving;

        if (isOrderMode && items.length === 0) {
            grid.innerHTML = `
                <article class="empty-order">
                    Belum ada makanan yang ditambahkan ke keranjang.
                </article>
            `;
            if (shouldShowOrderStatus) {
                renderOrderStatus();
            }
            return;
        }

        if (!isOrderMode && items.length === 0) {
            grid.innerHTML = `
                <article class="empty-order">
                    Menu belum tersedia. Silakan tunggu cooker menambahkan menu di Healthy Canteen.
                </article>
            `;
            if (shouldShowOrderStatus) {
                renderOrderStatus();
            }
            return;
        }

        grid.innerHTML = items.map(item => {
            const safeImageUrl = item.image_url ? String(item.image_url).replace(/'/g, "\\'") : '';
            const imageStyle = safeImageUrl ? ` style="background-image:url('${safeImageUrl}')"` : '';

            return `
            <article class="food-card">
                <div class="food-image"${imageStyle}></div>
                <h3 class="food-name">${item.name}</h3>
                <p class="food-meta">
                    <span>${item.calories} calories</span>
                    ${isOrderMode ? `<span class="order-qty">x${item.qty}</span>` : ''}
                    <button type="button"
                        class="action-btn ${isOrderMode ? 'minus' : 'plus'}"
                        data-id="${item.id}"
                        data-action="${isOrderMode ? 'remove' : 'add'}"
                        aria-label="${isOrderMode ? 'Remove' : 'Add'}">
                        <i class="fa-solid fa-${isOrderMode ? 'minus' : 'plus'}"></i>
                    </button>
                </p>
            </article>
        `;
        }).join('');

        if (shouldShowOrderStatus) {
            renderOrderStatus();
        }
    }

    function renderOrderStatus() {
        if (!isOrderMode) {
            orderStatus.classList.add('is-hidden');
            orderStatusBody.innerHTML = '';
            return;
        }

        orderStatus.classList.remove('is-hidden');
        if (!lastOrder || lastOrder.length === 0) {
            orderStatusBadge.textContent = 'Belum ada';
            orderStatusBody.innerHTML = `
                <div class="order-status-empty">
                    Belum ada pesanan yang dikonfirmasi.
                </div>
            `;
            return;
        }

        if (isSaving) {
            orderStatusBadge.textContent = 'Menyimpan...';
        } else if (lastOrderStatus === 'completed') {
            orderStatusBadge.textContent = 'Selesai';
        } else {
            orderStatusBadge.textContent = 'Sedang diproses';
        }

        orderStatusBody.innerHTML = `
            <ul class="order-status-list">
                ${lastOrder.map(item => {
                    const safeImageUrl = item.image_url ? String(item.image_url).replace(/'/g, "\\'") : '';
                    const imageStyle = safeImageUrl ? ` style="background-image:url('${safeImageUrl}')"` : '';
                    return `
                    <li class="order-status-item">
                        <div class="order-item-info">
                            <span class="order-item-thumb"${imageStyle}></span>
                            <span class="order-item-name">${item.name}</span>
                        </div>
                        <span class="order-item-qty">x${item.qty}</span>
                    </li>
                `;
                }).join('')}
            </ul>
        `;
    }

    grid.addEventListener('click', (event) => {
        const btn = event.target.closest('button[data-id]');
        if (!btn) return;

        const id = Number(btn.dataset.id);
        const item = menu.find(food => food.id === id);
        if (!item) return;

        if (btn.dataset.action === 'add') {
            item.qty += 1;
        } else {
            item.qty = Math.max(0, item.qty - 1);
        }

        render();
    });

    cartToggle.addEventListener('click', () => {
        isOrderMode = !isOrderMode;
        render();
    });

    backBtn.addEventListener('click', () => {
        isOrderMode = false;
        render();
    });

    confirmBtn.addEventListener('click', async () => {
        const selectedItems = menu
            .filter(item => item.qty > 0)
            .map(item => ({
                id: item.id,
                name: item.name,
                calories: item.calories,
                qty: item.qty,
                image_url: item.image_url || ''
            }));

        if (selectedItems.length === 0 || isSaving) {
            return;
        }

        isSaving = true;
        render();

        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'confirm_order',
                    items: selectedItems
                })
            });

            const data = await response.json();
            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Gagal menyimpan pesanan.');
            }

            lastOrder = selectedItems.map(item => ({
                name: item.name,
                qty: item.qty,
                image_url: item.image_url || ''
            }));
            lastOrderStatus = 'processing';
            menu.forEach(item => {
                item.qty = 0;
            });
            isOrderMode = false;
            alert(data.message || 'Pesanan berhasil disimpan.');
        } catch (error) {
            alert(error.message || 'Gagal menyimpan pesanan.');
        } finally {
            isSaving = false;
            render();
        }
    });

    render();
})();
</script>

<?php include 'includes/footer.php'; ?>
