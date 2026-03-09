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
        $insertLogStmt = $pdo->prepare("INSERT INTO food_logs (user_id, food_id, consumed_at) VALUES (?, ?, ?)");

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

            for ($i = 0; $i < $qty; $i++) {
                $insertLogStmt->execute([$userId, $foodId, $consumedAt]);
                $savedRows++;
            }
        }

        if ($savedRows === 0) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Tidak ada item valid untuk disimpan.']);
            exit;
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
    </section>
</main>

<script>
(() => {
    const menu = <?= json_encode($menuPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    let isOrderMode = false;
    let isSaving = false;
    const grid = document.getElementById('food-grid');
    const title = document.getElementById('screen-title');
    const cartCount = document.getElementById('cart-count');
    const confirmBtn = document.getElementById('confirm-btn');
    const backBtn = document.getElementById('back-btn');
    const cartActions = document.getElementById('cart-actions');
    const cartToggle = document.getElementById('cart-toggle');

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
            return;
        }

        if (!isOrderMode && items.length === 0) {
            grid.innerHTML = `
                <article class="empty-order">
                    Menu belum tersedia. Silakan tunggu cooker menambahkan menu di Healthy Canteen.
                </article>
            `;
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
                qty: item.qty
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
