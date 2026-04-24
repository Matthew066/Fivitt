<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/auth_guard.php';
require_login();

$userId = (int) ($_SESSION['user_id'] ?? 0);
$pageTitle = 'Gym Membership Payment';

function extractAmountFromPriceText(string $priceText): float
{
    if (!preg_match('/([\d\.\,]+)/', $priceText, $matches)) {
        return 0.0;
    }

    $number = str_replace('.', '', $matches[1]);
    $number = str_replace(',', '.', $number);
    return (float) $number;
}

$planId = isset($_GET['plan_id']) ? (int) $_GET['plan_id'] : (int) ($_POST['plan_id'] ?? 0);
$paymentMessage = '';
$paymentError = '';
$selectedMethod = 'qris';

$planStmt = $pdo->prepare("
    SELECT
        id_gym_membership_plans,
        plan_name,
        price_text,
        billing_cycle_text,
        description_text,
        features_text
    FROM gym_membership_plans
    WHERE id_gym_membership_plans = ?
      AND is_active = 1
    LIMIT 1
");
$planStmt->execute([$planId]);
$selectedPlan = $planStmt->fetch(PDO::FETCH_ASSOC) ?: null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'pay_membership') {
    $selectedMethod = trim((string) ($_POST['payment_method'] ?? 'qris'));

    if (!$selectedPlan) {
        $paymentError = 'Paket membership tidak ditemukan.';
    } elseif (stripos((string) ($selectedPlan['price_text'] ?? ''), 'negosiasi') !== false) {
        $paymentError = 'Paket ini memakai skema negosiasi. Silakan hubungi tim FiVit lewat form partnership.';
    } else {
        $amount = extractAmountFromPriceText((string) ($selectedPlan['price_text'] ?? ''));
        $billingCycle = strtolower((string) ($selectedPlan['billing_cycle_text'] ?? ''));
        $endDate = null;

        if (str_contains($billingCycle, 'tahun') || str_contains($billingCycle, 'year')) {
            $endDate = date('Y-m-d', strtotime('+1 year'));
        } else {
            $endDate = date('Y-m-d', strtotime('+30 days'));
        }

        try {
            $pdo->beginTransaction();

            $deactivateStmt = $pdo->prepare("
                UPDATE user_memberships
                SET status = 'inactive', auto_renew = 0
                WHERE id_users = ?
                  AND status = 'active'
            ");
            $deactivateStmt->execute([$userId]);

            $insertMembershipStmt = $pdo->prepare("
                INSERT INTO user_memberships
                    (id_users, id_gym_membership_plans, start_date, end_date, status, auto_renew)
                VALUES (?, ?, CURDATE(), ?, 'active', 1)
            ");
            $insertMembershipStmt->execute([$userId, (int) $selectedPlan['id_gym_membership_plans'], $endDate]);
            $userMembershipId = (int) $pdo->lastInsertId();

            $insertPaymentStmt = $pdo->prepare("
                INSERT INTO membership_payments
                    (id_user_memberships, amount, payment_method, payment_status, paid_at, created_at)
                VALUES (?, ?, ?, 'paid', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ");
            $insertPaymentStmt->execute([$userMembershipId, $amount, $selectedMethod]);

            $insertHistoryStmt = $pdo->prepare("
                INSERT INTO membership_histories
                    (id_users, id_gym_membership_plans, action, start_date, end_date, created_at)
                VALUES (?, ?, 'purchase', CURDATE(), ?, CURRENT_TIMESTAMP)
            ");
            $insertHistoryStmt->execute([$userId, (int) $selectedPlan['id_gym_membership_plans'], $endDate]);

            $pdo->commit();
            header('Location: gym.php');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $paymentError = 'Pembayaran belum berhasil diproses. Coba lagi.';
        }
    }
}

include 'includes/header.php';
?>

<style>
.pay-wrap { width: min(720px, 92%); margin: 18px auto 80px; }
.pay-hero { background: linear-gradient(140deg,#0f766e 0%,#22c55e 55%,#7dd3fc 100%); color:#fff; border-radius:22px; padding:20px; box-shadow:0 12px 26px rgba(15,118,110,.2); }
.pay-hero h1 { margin:0 0 6px; font-size:22px; }
.pay-hero p { margin:0; font-size:13px; opacity:.92; }
.pay-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px; margin-top:16px; }
.pay-card { background:#fff; border:1px solid #e2e8f0; border-radius:16px; padding:16px; box-shadow:0 8px 20px rgba(15,23,42,.07); }
.pay-title { margin:0 0 6px; font-size:16px; font-weight:700; }
.pay-price { font-size:18px; font-weight:800; color:#0f766e; margin-bottom:8px; }
.pay-list { margin:0; padding-left:18px; font-size:13px; color:#475569; }
.pay-btn { margin-top:12px; display:inline-flex; align-items:center; justify-content:center; width:100%; min-height:44px; padding:10px 16px; border-radius:999px; background:#0ea5e9; color:#fff; font-weight:700; text-decoration:none; font-size:13px; border:none; cursor:pointer; }
.pay-btn.secondary { background:#22c55e; }
.pay-label { display:block; font-size:13px; font-weight:700; color:#0f766e; margin-bottom:6px; }
.pay-select { width:100%; min-height:44px; border:1px solid #d7e3ef; border-radius:12px; padding:10px 12px; font-size:13px; }
.pay-message { margin-top:14px; border-radius:14px; padding:12px 14px; font-size:13px; }
.pay-message.error { background:#fef2f2; color:#991b1b; border:1px solid #fecaca; }
.pay-message.success { background:#ecfdf5; color:#166534; border:1px solid #bbf7d0; }
.note { font-size:12px; color:#64748b; margin-top:10px; }
@media (max-width: 760px) { .pay-grid { grid-template-columns:1fr; } }
</style>

<main class="pay-wrap">
    <section class="pay-hero">
        <h1>Pembayaran Membership Gym</h1>
        <p>Pilih paket terlebih dulu, selesaikan pembayaran, lalu membership kamu akan aktif otomatis.</p>
    </section>

    <?php if ($paymentError !== ''): ?>
        <div class="pay-message error"><?= htmlspecialchars($paymentError) ?></div>
    <?php endif; ?>

    <?php if (!$selectedPlan): ?>
        <section class="pay-card" style="margin-top:16px;">
            <div class="pay-title">Paket tidak ditemukan</div>
            <div class="note">Kembali ke halaman gym dan pilih membership plan yang tersedia.</div>
            <a class="pay-btn secondary" href="gym.php">Kembali ke Gym Network</a>
        </section>
    <?php else: ?>
    <section class="pay-grid">
        <article class="pay-card">
            <div class="pay-title"><?= htmlspecialchars((string) ($selectedPlan['plan_name'] ?? '')) ?></div>
            <div class="pay-price"><?= htmlspecialchars((string) ($selectedPlan['price_text'] ?? '')) ?></div>
            <ul class="pay-list">
                <li><?= htmlspecialchars((string) ($selectedPlan['description_text'] ?? '')) ?></li>
                <?php foreach (preg_split('/\r\n|\r|\n/', (string) ($selectedPlan['features_text'] ?? '')) ?: [] as $feature): ?>
                    <?php $feature = trim((string) $feature); ?>
                    <?php if ($feature !== ''): ?>
                        <li><?= htmlspecialchars($feature) ?></li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
            <div class="note">Membership akan aktif setelah pembayaran berhasil diproses.</div>
        </article>

        <article class="pay-card">
            <div class="pay-title">Checkout</div>
            <form method="post">
                <input type="hidden" name="action" value="pay_membership">
                <input type="hidden" name="plan_id" value="<?= (int) ($selectedPlan['id_gym_membership_plans'] ?? 0) ?>">
                <label class="pay-label" for="payment_method">Metode Pembayaran</label>
                <select class="pay-select" id="payment_method" name="payment_method">
                    <option value="qris" <?= $selectedMethod === 'qris' ? 'selected' : '' ?>>QRIS</option>
                    <option value="bank_transfer" <?= $selectedMethod === 'bank_transfer' ? 'selected' : '' ?>>Bank Transfer</option>
                    <option value="ewallet" <?= $selectedMethod === 'ewallet' ? 'selected' : '' ?>>E-Wallet</option>
                </select>
                <?php if (stripos((string) ($selectedPlan['price_text'] ?? ''), 'negosiasi') !== false): ?>
                    <a class="pay-btn secondary" href="gym.php?show_partnership=1">Lanjut ke Partnership</a>
                    <div class="note">Paket ini diproses lewat negosiasi dengan tim FiVit.</div>
                <?php else: ?>
                    <button class="pay-btn secondary" type="submit">Bayar Sekarang</button>
                    <div class="note">Saat ini pembayaran memakai simulasi internal agar flow membership bisa berjalan end-to-end.</div>
                <?php endif; ?>
            </form>
        </article>
    </section>
    <?php endif; ?>
</main>

<?php include 'includes/footer.php'; ?>
