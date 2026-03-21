<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_login('../login.php');

$pageTitle = 'Admin - Community';
$adminUserId = (int) ($_SESSION['user_id'] ?? 0);

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$pdo->exec("
    CREATE TABLE IF NOT EXISTS community_admin_logs (
        id_community_admin_logs INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        id_users BIGINT(20) NULL,
        action VARCHAR(40) NOT NULL,
        target_type VARCHAR(30) NOT NULL,
        target_id BIGINT(20) NULL,
        meta_text TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_admin_logs (action, target_type, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

function logAdminAction(PDO $pdo, int $userId, string $action, string $targetType, ?int $targetId, string $meta = ''): void
{
    $stmt = $pdo->prepare("
        INSERT INTO community_admin_logs (id_users, action, target_type, target_id, meta_text)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$userId > 0 ? $userId : null, $action, $targetType, $targetId, $meta]);
}

$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'toggle_room') {
        $id = (int) ($_POST['id'] ?? 0);
        $active = (int) ($_POST['is_active'] ?? 1) === 1 ? 1 : 0;
        if ($id > 0) {
            $stmt = $pdo->prepare('UPDATE community_rooms SET is_active = ? WHERE id_community_rooms = ?');
            $stmt->execute([$active, $id]);
            logAdminAction($pdo, $adminUserId, 'toggle_room', 'room', $id, 'is_active=' . $active);
        }
        header('Location: community.php');
        exit;
    }

    if ($action === 'delete_room') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare('DELETE FROM community_room_messages WHERE id_community_rooms = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM community_room_members WHERE id_community_rooms = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM community_rooms WHERE id_community_rooms = ?')->execute([$id]);
            logAdminAction($pdo, $adminUserId, 'delete_room', 'room', $id);
        }
        header('Location: community.php');
        exit;
    }

    if ($action === 'delete_message') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare('DELETE FROM community_messages WHERE id_community_messages = ?')->execute([$id]);
            logAdminAction($pdo, $adminUserId, 'delete_message', 'community_message', $id);
        }
        header('Location: community.php');
        exit;
    }

    if ($action === 'delete_room_message') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare('DELETE FROM community_room_messages WHERE id_community_room_messages = ?')->execute([$id]);
            logAdminAction($pdo, $adminUserId, 'delete_room_message', 'room_message', $id);
        }
        header('Location: community.php');
        exit;
    }

    if ($action === 'delete_invite') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare('DELETE FROM community_invites WHERE id_community_invites = ?')->execute([$id]);
            logAdminAction($pdo, $adminUserId, 'delete_invite', 'invite', $id);
        }
        header('Location: community.php');
        exit;
    }
}

$rooms = $pdo->query("
    SELECT r.*,
           (SELECT COUNT(*) FROM community_room_members m WHERE m.id_community_rooms = r.id_community_rooms AND m.status = 'member') AS member_count,
           (SELECT COUNT(*) FROM community_room_members m WHERE m.id_community_rooms = r.id_community_rooms AND m.status = 'pending') AS pending_count
    FROM community_rooms r
    ORDER BY r.created_at DESC, r.id_community_rooms DESC
")->fetchAll(PDO::FETCH_ASSOC);

$messages = $pdo->query("
    SELECT id_community_messages, channel, visibility, display_name, message, created_at
    FROM community_messages
    ORDER BY created_at DESC, id_community_messages DESC
    LIMIT 80
")->fetchAll(PDO::FETCH_ASSOC);

$roomMessages = $pdo->query("
    SELECT rm.id_community_room_messages, rm.display_name, rm.message, rm.created_at, r.room_name
    FROM community_room_messages rm
    JOIN community_rooms r ON r.id_community_rooms = rm.id_community_rooms
    ORDER BY rm.created_at DESC, rm.id_community_room_messages DESC
    LIMIT 80
")->fetchAll(PDO::FETCH_ASSOC);

$invites = $pdo->query("
    SELECT id_community_invites, channel, invite_type, title, visibility, created_at
    FROM community_invites
    ORDER BY created_at DESC, id_community_invites DESC
    LIMIT 80
")->fetchAll(PDO::FETCH_ASSOC);

$adminLogs = $pdo->query("
    SELECT id_community_admin_logs, id_users, action, target_type, target_id, meta_text, created_at
    FROM community_admin_logs
    ORDER BY created_at DESC, id_community_admin_logs DESC
    LIMIT 80
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($pageTitle) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <style>
        body { font-family: 'Poppins', sans-serif; background: #f8fafc; }
        .page-wrapper { padding: 24px; }
        .card { border-radius: 14px; border: 1px solid #e2e8f0; }
        .table td, .table th { vertical-align: middle; }
    </style>
</head>
<body>
<div class="page-wrapper">
    <div class="mb-3">
        <h4 class="mb-0">Admin - Community</h4>
        <div class="text-muted">Kelola room, pesan, dan undangan komunitas.</div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <h6 class="mb-3">Rooms</h6>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Room</th>
                            <th>Code</th>
                            <th>Channel</th>
                            <th>Join Mode</th>
                            <th>Member</th>
                            <th>Pending</th>
                            <th>Active</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$rooms): ?>
                        <tr><td colspan="8" class="text-center">Belum ada room.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rooms as $room): ?>
                        <tr>
                            <td><?= h($room['room_name'] ?? '') ?></td>
                            <td><code><?= h($room['room_code'] ?? '') ?></code></td>
                            <td><?= h($room['channel'] ?? '') ?></td>
                            <td><?= h($room['join_mode'] ?? '') ?></td>
                            <td><?= (int) ($room['member_count'] ?? 0) ?></td>
                            <td><?= (int) ($room['pending_count'] ?? 0) ?></td>
                            <td>
                                <span class="badge <?= ((int)$room['is_active'] === 1) ? 'bg-success' : 'bg-secondary' ?>">
                                    <?= ((int)$room['is_active'] === 1) ? 'Aktif' : 'Nonaktif' ?>
                                </span>
                            </td>
                            <td>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="action" value="toggle_room">
                                    <input type="hidden" name="id" value="<?= (int) $room['id_community_rooms'] ?>">
                                    <input type="hidden" name="is_active" value="<?= ((int)$room['is_active'] === 1) ? 0 : 1 ?>">
                                    <button class="btn btn-sm btn-outline-primary" type="submit">
                                        <?= ((int)$room['is_active'] === 1) ? 'Nonaktifkan' : 'Aktifkan' ?>
                                    </button>
                                </form>
                                <form method="post" class="d-inline" onsubmit="return confirm('Hapus room ini? Semua member & chat akan terhapus.');">
                                    <input type="hidden" name="action" value="delete_room">
                                    <input type="hidden" name="id" value="<?= (int) $room['id_community_rooms'] ?>">
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

    <div class="card mb-4">
        <div class="card-body">
            <h6 class="mb-3">Global/Private Messages</h6>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Channel</th>
                            <th>Visibility</th>
                            <th>Nama</th>
                            <th>Pesan</th>
                            <th>Waktu</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$messages): ?>
                        <tr><td colspan="6" class="text-center">Belum ada pesan.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($messages as $msg): ?>
                        <tr>
                            <td><?= h($msg['channel'] ?? '') ?></td>
                            <td><?= h($msg['visibility'] ?? '') ?></td>
                            <td><?= h($msg['display_name'] ?? '') ?></td>
                            <td><?= h($msg['message'] ?? '') ?></td>
                            <td><?= h($msg['created_at'] ?? '') ?></td>
                            <td>
                                <form method="post" class="d-inline" onsubmit="return confirm('Hapus pesan ini?');">
                                    <input type="hidden" name="action" value="delete_message">
                                    <input type="hidden" name="id" value="<?= (int) $msg['id_community_messages'] ?>">
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

    <div class="card mb-4">
        <div class="card-body">
            <h6 class="mb-3">Room Messages</h6>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Room</th>
                            <th>Nama</th>
                            <th>Pesan</th>
                            <th>Waktu</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$roomMessages): ?>
                        <tr><td colspan="5" class="text-center">Belum ada pesan room.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($roomMessages as $msg): ?>
                        <tr>
                            <td><?= h($msg['room_name'] ?? '') ?></td>
                            <td><?= h($msg['display_name'] ?? '') ?></td>
                            <td><?= h($msg['message'] ?? '') ?></td>
                            <td><?= h($msg['created_at'] ?? '') ?></td>
                            <td>
                                <form method="post" class="d-inline" onsubmit="return confirm('Hapus pesan room ini?');">
                                    <input type="hidden" name="action" value="delete_room_message">
                                    <input type="hidden" name="id" value="<?= (int) $msg['id_community_room_messages'] ?>">
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

    <div class="card">
        <div class="card-body">
            <h6 class="mb-3">Undangan</h6>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Channel</th>
                            <th>Tipe</th>
                            <th>Judul</th>
                            <th>Visibility</th>
                            <th>Waktu</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$invites): ?>
                        <tr><td colspan="6" class="text-center">Belum ada undangan.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($invites as $inv): ?>
                        <tr>
                            <td><?= h($inv['channel'] ?? '') ?></td>
                            <td><?= h($inv['invite_type'] ?? '') ?></td>
                            <td><?= h($inv['title'] ?? '') ?></td>
                            <td><?= h($inv['visibility'] ?? '') ?></td>
                            <td><?= h($inv['created_at'] ?? '') ?></td>
                            <td>
                                <form method="post" class="d-inline" onsubmit="return confirm('Hapus undangan ini?');">
                                    <input type="hidden" name="action" value="delete_invite">
                                    <input type="hidden" name="id" value="<?= (int) $inv['id_community_invites'] ?>">
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

    <div class="card mt-4">
        <div class="card-body">
            <h6 class="mb-3">Admin Audit Log</h6>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>Action</th>
                            <th>Target</th>
                            <th>Meta</th>
                            <th>Waktu</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$adminLogs): ?>
                        <tr><td colspan="5" class="text-center">Belum ada log.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($adminLogs as $log): ?>
                        <tr>
                            <td><?= h((string) ($log['id_users'] ?? '')) ?></td>
                            <td><?= h($log['action'] ?? '') ?></td>
                            <td><?= h(($log['target_type'] ?? '') . ':' . (string) ($log['target_id'] ?? '')) ?></td>
                            <td><?= h($log['meta_text'] ?? '') ?></td>
                            <td><?= h($log['created_at'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</body>
</html>
