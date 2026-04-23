
<?php
session_start();
require_once 'includes/db.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$pageTitle = 'Community Hub';
include 'includes/header.php';

$isLoggedIn = isset($_SESSION['user_id']);
$userId = (int) ($_SESSION['user_id'] ?? 0);
$userName = trim((string) ($_SESSION['user_name'] ?? ''));
$userDepartment = trim((string) ($_SESSION['user_department'] ?? ''));

if ($isLoggedIn && $userDepartment === '' && $userId > 0) {
    $userStmt = $pdo->prepare('SELECT name, department FROM users WHERE id_users = ? LIMIT 1');
    $userStmt->execute([$userId]);
    $userRow = $userStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    if ($userName === '') {
        $userName = trim((string) ($userRow['name'] ?? ''));
    }
    $userDepartment = trim((string) ($userRow['department'] ?? ''));
}
if ($userDepartment === '') $userDepartment = 'General';

$pdo->exec("
    CREATE TABLE IF NOT EXISTS community_messages (
        id_community_messages INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        channel VARCHAR(20) NOT NULL DEFAULT 'gym',
        visibility VARCHAR(12) NOT NULL DEFAULT 'global',
        department_scope VARCHAR(100) NOT NULL DEFAULT '',
        message TEXT NOT NULL,
        display_name VARCHAR(120) NOT NULL DEFAULT '',
        id_users BIGINT(20) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_messages_channel_visibility (channel, visibility, department_scope, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS community_invites (
        id_community_invites INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        channel VARCHAR(20) NOT NULL DEFAULT 'gym',
        invite_type VARCHAR(20) NOT NULL DEFAULT 'membership',
        title VARCHAR(150) NOT NULL,
        details TEXT NOT NULL,
        event_date DATE NULL,
        event_time TIME NULL,
        contact_text VARCHAR(120) NOT NULL DEFAULT '',
        visibility VARCHAR(12) NOT NULL DEFAULT 'global',
        department_scope VARCHAR(100) NOT NULL DEFAULT '',
        id_users BIGINT(20) NULL,
        status VARCHAR(12) NOT NULL DEFAULT 'open',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_invites_channel_visibility (channel, visibility, department_scope, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS community_rooms (
        id_community_rooms INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        channel VARCHAR(20) NOT NULL DEFAULT 'gym',
        room_name VARCHAR(120) NOT NULL,
        room_code VARCHAR(20) NOT NULL,
        join_mode VARCHAR(12) NOT NULL DEFAULT 'auto',
        created_by BIGINT(20) NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_room_code (room_code),
        KEY idx_rooms_channel (channel, is_active, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS community_room_members (
        id_community_room_members INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        id_community_rooms INT UNSIGNED NOT NULL,
        id_users BIGINT(20) NOT NULL,
        display_name VARCHAR(120) NOT NULL DEFAULT '',
        role VARCHAR(12) NOT NULL DEFAULT 'member',
        status VARCHAR(12) NOT NULL DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_room_user (id_community_rooms, id_users),
        KEY idx_room_members (id_community_rooms, status, role, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS community_room_messages (
        id_community_room_messages INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        id_community_rooms INT UNSIGNED NOT NULL,
        id_users BIGINT(20) NULL,
        display_name VARCHAR(120) NOT NULL DEFAULT '',
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_room_messages (id_community_rooms, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

function normalizeChannel(string $value): string {
    $value = strtolower(trim($value));
    if (in_array($value, ['community', 'general', 'room'], true)) {
        return 'gym';
    }
    return in_array($value, ['gym', 'coach'], true) ? $value : 'gym';
}

function normalizeVisibility(string $value): string {
    $value = strtolower(trim($value));
    return in_array($value, ['global', 'private'], true) ? $value : 'global';
}

function normalizeInviteType(string $value): string {
    $value = strtolower(trim($value));
    return in_array($value, ['membership', 'session'], true) ? $value : 'membership';
}

function normalizeJoinMode(string $value): string {
    $value = strtolower(trim($value));
    return in_array($value, ['auto', 'approve'], true) ? $value : 'auto';
}

function normalizeRole(string $value): string {
    $value = strtolower(trim($value));
    return in_array($value, ['owner', 'admin', 'member'], true) ? $value : 'member';
}

function normalizeMemberStatus(string $value): string {
    $value = strtolower(trim($value));
    return in_array($value, ['pending', 'member', 'rejected', 'kicked', 'left'], true) ? $value : 'pending';
}

function normalizeRoomCode(string $value): string {
    return strtoupper(preg_replace('/[^A-Z0-9]/', '', trim($value)));
}

function generateRoomCode(int $len = 6): string {
    $chars = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $max = strlen($chars) - 1;
    $out = '';
    for ($i = 0; $i < $len; $i++) {
        $out .= $chars[random_int(0, $max)];
    }
    return $out;
}

function getRoomRole(PDO $pdo, int $roomId, int $userId): ?string {
    $stmt = $pdo->prepare('SELECT role, status FROM community_room_members WHERE id_community_rooms = ? AND id_users = ? LIMIT 1');
    $stmt->execute([$roomId, $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$row) return null;
    if (strtolower((string) ($row['status'] ?? '')) !== 'member') return null;
    return normalizeRole((string) ($row['role'] ?? 'member'));
}

function isRoomManager(?string $role): bool {
    return in_array($role, ['owner', 'admin'], true);
}

function getPendingCount(PDO $pdo, int $roomId): int {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM community_room_members WHERE id_community_rooms = ? AND status = ?');
    $stmt->execute([$roomId, 'pending']);
    return (int) $stmt->fetchColumn();
}

// Keep legacy rows usable on older localhost schemas where is_active was stored as NULL.
$pdo->exec("UPDATE community_rooms SET is_active = 1 WHERE is_active IS NULL");

function getRoomByCode(PDO $pdo, string $code, ?string $channel = null): ?array {
    if ($code === '') {
        return null;
    }

    if ($channel !== null) {
        $stmt = $pdo->prepare('SELECT * FROM community_rooms WHERE room_code = ? AND channel = ? AND COALESCE(is_active, 1) = 1 LIMIT 1');
        $stmt->execute([$code, $channel]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($row) {
            return $row;
        }
    }

    $stmt = $pdo->prepare('SELECT * FROM community_rooms WHERE room_code = ? AND COALESCE(is_active, 1) = 1 LIMIT 1');
    $stmt->execute([$code]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function addRoomSystemMessage(PDO $pdo, int $roomId, string $message): void {
    if ($roomId <= 0 || trim($message) === '') {
        return;
    }

    $stmt = $pdo->prepare("
        INSERT INTO community_room_messages
        (id_community_rooms, id_users, display_name, message)
        VALUES (?, NULL, 'System', ?)
    ");
    $stmt->execute([$roomId, $message]);
}

$channel = normalizeChannel((string) ($_GET['channel'] ?? 'gym'));
$roomId = (int) ($_GET['room_id'] ?? 0);
$roomSearch = trim((string) ($_GET['room_search'] ?? ''));

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'post_message') {
        if (!$isLoggedIn) {
            $errors[] = 'Silakan login untuk mengirim pesan.';
        } else {
            $postChannel = normalizeChannel((string) ($_POST['channel'] ?? $channel));
            $visibility = normalizeVisibility((string) ($_POST['visibility'] ?? 'global'));
            $message = trim((string) ($_POST['message'] ?? ''));
            $displayName = $userName !== '' ? $userName : 'Member';

            if ($message === '') { $errors[] = 'Pesan tidak boleh kosong.'; }

            if (!$errors) {
                $dept = $visibility === 'private' ? $userDepartment : '';
                $insert = $pdo->prepare("
                    INSERT INTO community_messages
                    (channel, visibility, department_scope, message, display_name, id_users)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $insert->execute([
                    $postChannel,
                    $visibility,
                    $dept,
                    $message,
                    $displayName,
                    $userId > 0 ? $userId : null,
                ]);
                $success = 'Pesan berhasil dikirim.';
            }
        }
    }

    if ($action === 'create_invite') {
        if (!$isLoggedIn) {
            $errors[] = 'Silakan login untuk membuat undangan.';
        } else {
            $postChannel = normalizeChannel((string) ($_POST['channel'] ?? $channel));
            $inviteType = normalizeInviteType((string) ($_POST['invite_type'] ?? 'membership'));
            $visibility = normalizeVisibility((string) ($_POST['visibility'] ?? 'global'));
            $title = trim((string) ($_POST['title'] ?? ''));
            $details = trim((string) ($_POST['details'] ?? ''));
            $eventDate = trim((string) ($_POST['event_date'] ?? ''));
            $eventTime = trim((string) ($_POST['event_time'] ?? ''));
            $contactText = trim((string) ($_POST['contact_text'] ?? ''));

            if ($title === '') { $errors[] = 'Judul undangan wajib diisi.'; }
            if ($details === '') { $errors[] = 'Detail undangan wajib diisi.'; }

            $dateValue = $eventDate !== '' ? $eventDate : null;
            $timeValue = $eventTime !== '' ? $eventTime : null;

            if (!$errors) {
                $dept = $visibility === 'private' ? $userDepartment : '';
                $insert = $pdo->prepare("
                    INSERT INTO community_invites
                    (channel, invite_type, title, details, event_date, event_time, contact_text, visibility, department_scope, id_users)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $insert->execute([
                    $postChannel,
                    $inviteType,
                    $title,
                    $details,
                    $dateValue,
                    $timeValue,
                    $contactText,
                    $visibility,
                    $dept,
                    $userId > 0 ? $userId : null,
                ]);
                $success = 'Undangan berhasil diposting.';
            }
        }
    }

    if ($action === 'create_room') {
        if (!$isLoggedIn) {
            $errors[] = 'Silakan login untuk membuat private room.';
        } else {
            $postChannel = normalizeChannel((string) ($_POST['channel'] ?? $channel));
            $roomName = trim((string) ($_POST['room_name'] ?? ''));
            $joinMode = normalizeJoinMode((string) ($_POST['join_mode'] ?? 'auto'));

            if ($roomName === '') { $errors[] = 'Nama room wajib diisi.'; }

            if (!$errors) {
                $code = '';
                $tries = 0;
                do {
                    $code = generateRoomCode(6);
                    $check = $pdo->prepare('SELECT 1 FROM community_rooms WHERE room_code = ? LIMIT 1');
                    $check->execute([$code]);
                    $exists = (bool) $check->fetchColumn();
                    $tries++;
                } while ($exists && $tries < 5);

                if ($exists) {
                    $errors[] = 'Gagal membuat kode room. Coba lagi.';
                } else {
                    $insert = $pdo->prepare("
                        INSERT INTO community_rooms
                        (channel, room_name, room_code, join_mode, created_by, is_active)
                        VALUES (?, ?, ?, ?, ?, 1)
                    ");
                    $insert->execute([
                        $postChannel,
                        $roomName,
                        $code,
                        $joinMode,
                        $userId > 0 ? $userId : null,
                    ]);
                    $newRoomId = (int) $pdo->lastInsertId();

                    $ownerName = $userName !== '' ? $userName : 'Owner';
                    $insMember = $pdo->prepare("
                        INSERT INTO community_room_members
                        (id_community_rooms, id_users, display_name, role, status)
                        VALUES (?, ?, ?, 'owner', 'member')
                    ");
                    $insMember->execute([$newRoomId, $userId, $ownerName]);

                    addRoomSystemMessage(
                        $pdo,
                        $newRoomId,
                        'Room dibuat. Kode room ini adalah ' . $code . '. Kode tetap aktif dan bisa dipakai lagi selama room masih aktif.'
                    );

                    $success = 'Private room berhasil dibuat. Kode room: ' . $code;
                    $channel = $postChannel;
                    $roomId = $newRoomId;
                }
            }
        }
    }
    if ($action === 'join_room') {
        if (!$isLoggedIn) {
            $errors[] = 'Silakan login untuk join room.';
        } else {
            $requestedChannel = normalizeChannel((string) ($_POST['channel'] ?? $channel));
            $code = normalizeRoomCode((string) ($_POST['room_code'] ?? ''));
            if ($code === '') {
                $errors[] = 'Kode room wajib diisi.';
            } else {
                $roomRow = getRoomByCode($pdo, $code, $requestedChannel);

                if (!$roomRow) {
                    $errors[] = 'Room tidak ditemukan.';
                } else {
                    $resolvedChannel = normalizeChannel((string) ($roomRow['channel'] ?? $requestedChannel));
                    $resolvedRoomId = (int) ($roomRow['id_community_rooms'] ?? 0);
                    $joinMode = normalizeJoinMode((string) ($roomRow['join_mode'] ?? 'auto'));
                    $targetStatus = $joinMode === 'auto' ? 'member' : 'pending';

                    $memberStmt = $pdo->prepare('SELECT status FROM community_room_members WHERE id_community_rooms = ? AND id_users = ? LIMIT 1');
                    $memberStmt->execute([$resolvedRoomId, $userId]);
                    $memberRow = $memberStmt->fetch(PDO::FETCH_ASSOC) ?: null;

                    if ($memberRow) {
                        $currentStatus = normalizeMemberStatus((string) ($memberRow['status'] ?? 'pending'));
                        if ($currentStatus === 'member') {
                            $errors[] = 'Kamu sudah menjadi member room ini.';
                        } elseif ($currentStatus === 'kicked') {
                            $errors[] = 'Kamu sudah dikeluarkan dari room ini.';
                        } else {
                            $upd = $pdo->prepare('UPDATE community_room_members SET status = ? WHERE id_community_rooms = ? AND id_users = ?');
                            $upd->execute([$targetStatus, $resolvedRoomId, $userId]);
                            $success = $targetStatus === 'member'
                                ? 'Berhasil join room.'
                                : 'Request join dikirim. Menunggu approval owner.';
                            if ($targetStatus === 'member') {
                                addRoomSystemMessage($pdo, $resolvedRoomId, ($userName !== '' ? $userName : 'Member') . ' bergabung ke room dengan kode ' . $code . '.');
                            }
                            $channel = $resolvedChannel;
                            $roomId = $resolvedRoomId;
                        }
                    } else {
                        $display = $userName !== '' ? $userName : 'Member';
                        $ins = $pdo->prepare("
                            INSERT INTO community_room_members
                            (id_community_rooms, id_users, display_name, role, status)
                            VALUES (?, ?, ?, 'member', ?)
                        ");
                        $ins->execute([$resolvedRoomId, $userId, $display, $targetStatus]);
                        $success = $targetStatus === 'member'
                            ? 'Berhasil join room.'
                            : 'Request join dikirim. Menunggu approval owner.';
                        if ($targetStatus === 'member') {
                            addRoomSystemMessage($pdo, $resolvedRoomId, $display . ' bergabung ke room dengan kode ' . $code . '.');
                        }
                        $channel = $resolvedChannel;
                        $roomId = $resolvedRoomId;
                    }
                }
            }
        }
    }

    if ($action === 'post_room_message') {
        if (!$isLoggedIn) {
            $errors[] = 'Silakan login untuk chat di room.';
        } else {
            $targetRoomId = (int) ($_POST['room_id'] ?? 0);
            $message = trim((string) ($_POST['message'] ?? ''));
            if ($targetRoomId <= 0) {
                $errors[] = 'Room tidak valid.';
            } elseif ($message === '') {
                $errors[] = 'Pesan tidak boleh kosong.';
            } else {
                $role = getRoomRole($pdo, $targetRoomId, $userId);
                if (!$role) {
                    $errors[] = 'Kamu belum menjadi member room ini.';
                } else {
                    $display = $userName !== '' ? $userName : 'Member';
                    $ins = $pdo->prepare("
                        INSERT INTO community_room_messages
                        (id_community_rooms, id_users, display_name, message)
                        VALUES (?, ?, ?, ?)
                    ");
                    $ins->execute([$targetRoomId, $userId, $display, $message]);
                    $success = 'Pesan room terkirim.';
                    $roomRow = $pdo->prepare('SELECT channel FROM community_rooms WHERE id_community_rooms = ? LIMIT 1');
                    $roomRow->execute([$targetRoomId]);
                    $roomInfo = $roomRow->fetch(PDO::FETCH_ASSOC) ?: null;
                    if ($roomInfo) {
                        $channel = normalizeChannel((string) ($roomInfo['channel'] ?? $channel));
                    }
                    $roomId = $targetRoomId;
                }
            }
        }
    }

    if ($action === 'approve_member' || $action === 'reject_member' || $action === 'kick_member') {
        if (!$isLoggedIn) {
            $errors[] = 'Silakan login.';
        } else {
            $targetRoomId = (int) ($_POST['room_id'] ?? 0);
            $memberId = (int) ($_POST['member_id'] ?? 0);
            if ($targetRoomId <= 0 || $memberId <= 0) {
                $errors[] = 'Data member tidak valid.';
            } else {
                $role = getRoomRole($pdo, $targetRoomId, $userId);
                if (!isRoomManager($role)) {
                    $errors[] = 'Kamu tidak punya izin mengelola member.';
                } else {
                    $target = $pdo->prepare('SELECT id_users, role FROM community_room_members WHERE id_community_rooms = ? AND id_community_room_members = ? LIMIT 1');
                    $target->execute([$targetRoomId, $memberId]);
                    $targetRow = $target->fetch(PDO::FETCH_ASSOC) ?: null;
                    if (!$targetRow) {
                        $errors[] = 'Member tidak ditemukan.';
                    } else {
                        $targetRole = normalizeRole((string) ($targetRow['role'] ?? 'member'));
                        if ($targetRole === 'owner') {
                            $errors[] = 'Owner tidak bisa diubah.';
                        } else {
                            $newStatus = 'member';
                            if ($action === 'reject_member') $newStatus = 'rejected';
                            if ($action === 'kick_member') $newStatus = 'kicked';
                            $upd = $pdo->prepare('UPDATE community_room_members SET status = ? WHERE id_community_rooms = ? AND id_community_room_members = ?');
                            $upd->execute([$newStatus, $targetRoomId, $memberId]);
                            $success = 'Member berhasil diperbarui.';
                        }
                    }
                }
            }
        }
    }

    if ($action === 'set_role') {
        if (!$isLoggedIn) {
            $errors[] = 'Silakan login.';
        } else {
            $targetRoomId = (int) ($_POST['room_id'] ?? 0);
            $memberId = (int) ($_POST['member_id'] ?? 0);
            $newRole = normalizeRole((string) ($_POST['new_role'] ?? 'member'));
            if ($targetRoomId <= 0 || $memberId <= 0) {
                $errors[] = 'Data role tidak valid.';
            } else {
                $role = getRoomRole($pdo, $targetRoomId, $userId);
                if ($role !== 'owner') {
                    $errors[] = 'Hanya owner yang bisa mengubah role.';
                } else {
                    $target = $pdo->prepare('SELECT role, status FROM community_room_members WHERE id_community_rooms = ? AND id_community_room_members = ? LIMIT 1');
                    $target->execute([$targetRoomId, $memberId]);
                    $targetRow = $target->fetch(PDO::FETCH_ASSOC) ?: null;
                    if (!$targetRow) {
                        $errors[] = 'Member tidak ditemukan.';
                    } else {
                        $targetRole = normalizeRole((string) ($targetRow['role'] ?? 'member'));
                        $targetStatus = normalizeMemberStatus((string) ($targetRow['status'] ?? 'pending'));
                        if ($targetRole === 'owner') {
                            $errors[] = 'Owner tidak bisa diubah.';
                        } elseif ($targetStatus !== 'member') {
                            $errors[] = 'Hanya member aktif yang bisa diubah role.';
                        } else {
                            $upd = $pdo->prepare('UPDATE community_room_members SET role = ? WHERE id_community_rooms = ? AND id_community_room_members = ?');
                            $upd->execute([$newRole, $targetRoomId, $memberId]);
                            $success = 'Role member berhasil diperbarui.';
                        }
                    }
                }
            }
        }
    }

    if ($action === 'leave_room') {
        if (!$isLoggedIn) {
            $errors[] = 'Silakan login.';
        } else {
            $targetRoomId = (int) ($_POST['room_id'] ?? 0);
            if ($targetRoomId <= 0) {
                $errors[] = 'Room tidak valid.';
            } else {
                $role = getRoomRole($pdo, $targetRoomId, $userId);
                if (!$role) {
                    $errors[] = 'Kamu belum menjadi member room ini.';
                } elseif ($role === 'owner') {
                    $errors[] = 'Owner tidak bisa leave. Transfer admin dulu kalau mau keluar.';
                } else {
                    $upd = $pdo->prepare('UPDATE community_room_members SET status = ? WHERE id_community_rooms = ? AND id_users = ?');
                    $upd->execute(['left', $targetRoomId, $userId]);
                    $success = 'Kamu sudah keluar dari room.';
                }
            }
        }
    }
}

$messagesGlobalStmt = $pdo->prepare("
    SELECT display_name, message, created_at
    FROM community_messages
    WHERE channel = ? AND visibility = 'global'
    ORDER BY created_at DESC, id_community_messages DESC
    LIMIT 12
");
$messagesGlobalStmt->execute([$channel]);
$globalMessages = $messagesGlobalStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$privateMessages = [];
if ($isLoggedIn) {
    $messagesPrivateStmt = $pdo->prepare("
        SELECT display_name, message, created_at
        FROM community_messages
        WHERE channel = ? AND visibility = 'private' AND department_scope = ?
        ORDER BY created_at DESC, id_community_messages DESC
        LIMIT 12
    ");
    $messagesPrivateStmt->execute([$channel, $userDepartment]);
    $privateMessages = $messagesPrivateStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$invitesGlobalStmt = $pdo->prepare("
    SELECT invite_type, title, details, event_date, event_time, contact_text, created_at
    FROM community_invites
    WHERE channel = ? AND visibility = 'global' AND status = 'open'
    ORDER BY created_at DESC, id_community_invites DESC
    LIMIT 10
");
$invitesGlobalStmt->execute([$channel]);
$globalInvites = $invitesGlobalStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$privateInvites = [];
if ($isLoggedIn) {
    $invitesPrivateStmt = $pdo->prepare("
        SELECT invite_type, title, details, event_date, event_time, contact_text, created_at
        FROM community_invites
        WHERE channel = ? AND visibility = 'private' AND status = 'open' AND department_scope = ?
        ORDER BY created_at DESC, id_community_invites DESC
        LIMIT 10
    ");
    $invitesPrivateStmt->execute([$channel, $userDepartment]);
    $privateInvites = $invitesPrivateStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$myRooms = [];
$pendingRooms = [];
if ($isLoggedIn) {
    $baseSql = "
        SELECT r.id_community_rooms, r.room_name, r.room_code, r.join_mode, r.channel,
               m.role, m.status
        FROM community_rooms r
        JOIN community_room_members m ON m.id_community_rooms = r.id_community_rooms
        WHERE COALESCE(r.is_active, 1) = 1 AND r.channel = ? AND m.id_users = ?
    ";
    $params = [$channel, $userId];
    if ($roomSearch !== '') {
        $baseSql .= " AND (r.room_name LIKE ? OR r.room_code LIKE ?)";
        $like = '%' . $roomSearch . '%';
        $params[] = $like;
        $params[] = $like;
    }
    $baseSql .= " ORDER BY r.created_at DESC, r.id_community_rooms DESC";
    $roomsStmt = $pdo->prepare($baseSql);
    $roomsStmt->execute($params);
    $rows = $roomsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rows as $row) {
        $status = normalizeMemberStatus((string) ($row['status'] ?? 'pending'));
        if ($status === 'member') {
            $myRooms[] = $row;
        } elseif ($status === 'pending') {
            $pendingRooms[] = $row;
        }
    }
}

$activeRoom = null;
$roomRole = null;
$roomMembers = [];
$roomPending = [];
$roomMessages = [];
if ($roomId > 0) {
    $roomStmt = $pdo->prepare('SELECT * FROM community_rooms WHERE id_community_rooms = ? AND COALESCE(is_active, 1) = 1 LIMIT 1');
    $roomStmt->execute([$roomId]);
    $activeRoom = $roomStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($activeRoom && normalizeChannel((string) ($activeRoom['channel'] ?? 'gym')) !== $channel) {
        $activeRoom = null;
    }
    if ($activeRoom && $isLoggedIn) {
        $roomRole = getRoomRole($pdo, $roomId, $userId);
        if ($roomRole) {
            $messagesStmt = $pdo->prepare("
                SELECT display_name, message, created_at
                FROM community_room_messages
                WHERE id_community_rooms = ?
                ORDER BY created_at DESC, id_community_room_messages DESC
                LIMIT 20
            ");
            $messagesStmt->execute([$roomId]);
            $roomMessages = array_reverse($messagesStmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
        }
        if (isRoomManager($roomRole)) {
            $membersStmt = $pdo->prepare("
                SELECT id_community_room_members, display_name, role, status
                FROM community_room_members
                WHERE id_community_rooms = ?
                ORDER BY FIELD(role, 'owner', 'admin', 'member'), created_at ASC
            ");
            $membersStmt->execute([$roomId]);
            $memberRows = $membersStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($memberRows as $m) {
                $status = normalizeMemberStatus((string) ($m['status'] ?? 'pending'));
                if ($status === 'pending') {
                    $roomPending[] = $m;
                } elseif ($status === 'member') {
                    $roomMembers[] = $m;
                }
            }
        }
    }
}

function formatDate(?string $value): string {
    if (!$value) return '';
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function formatTime(?string $value): string {
    if (!$value) return '';
    return htmlspecialchars(substr($value, 0, 5), ENT_QUOTES, 'UTF-8');
}
?>
<style>
.community-app { width: min(980px, 92%); margin: 18px auto 0; padding-bottom: 90px; }
.community-hero {
    background: linear-gradient(140deg, #111827 0%, #0f766e 55%, #22c55e 100%);
    color: #fff; border-radius: 24px; padding: 26px 22px; box-shadow: 0 16px 32px rgba(2, 6, 23, .25);
}
.community-hero h1 { margin: 0 0 8px; font-size: 28px; }
.community-hero p { margin: 0; font-size: 14px; max-width: 760px; opacity: .95; }
.community-switcher { margin-top: 14px; padding: 14px 16px; background: rgba(255,255,255,.92); border: 1px solid rgba(148,163,184,.28); border-radius: 20px; box-shadow: 0 8px 22px rgba(15,23,42,.06); }
.tabs { display:flex; flex-wrap:wrap; gap:10px; }
.tab { display:inline-flex; align-items:center; justify-content:center; text-decoration:none; border-radius:999px; padding:10px 14px; font-size:13px; font-weight:800; border:1px solid rgba(255,255,255,.35); color:#fff; background:rgba(255,255,255,.12); }
.tab.active { background:#fff; color:#0f172a; border-color:#fff; }
 .community-switcher .tab { flex: 1 1 220px; border-color: rgba(15,118,110,.22); color: #0f766e; background: linear-gradient(180deg, #ffffff, #f8fbff); }
 .community-switcher .tab.active { background: linear-gradient(135deg,#0f766e,#22c55e); color: #fff; border-color: transparent; }
.section { margin-top: 16px; background: rgba(255,255,255,.92); border: 1px solid rgba(148,163,184,.35); border-radius: 20px; padding: 18px; box-shadow: 0 8px 22px rgba(15,23,42,.07); }
.title { margin: 0 0 8px; font-size: 20px; color: #0f172a; }
.sub { margin: 0 0 14px; color: #475569; font-size: 13px; }
.grid-2 { display:grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 14px; }
.card { background:#fff; border:1px solid #e2e8f0; border-radius:16px; padding:14px; }
.card h3 { margin:0 0 6px; font-size:15px; color:#0f172a; }
.meta { margin:6px 0 0; font-size:12px; color:#64748b; }
.badge { display:inline-flex; align-items:center; gap:6px; font-size:11px; font-weight:800; border-radius:999px; padding:6px 10px; border:1px solid #e2e8f0; background:#f8fafc; color:#0f172a; text-transform:capitalize; }
.badge.global { background:#eff6ff; border-color:#bfdbfe; color:#1d4ed8; }
.badge.private { background:#fff7ed; border-color:#fed7aa; color:#9a3412; }
.badge.membership { background:#ecfdf5; border-color:#bbf7d0; color:#166534; }
.badge.session { background:#f0f9ff; border-color:#bae6fd; color:#0369a1; }
.badge.auto { background:#ecfeff; border-color:#a5f3fc; color:#155e75; }
.badge.approve { background:#fef3c7; border-color:#fcd34d; color:#92400e; }
.badge.role-owner { background:#eef2ff; border-color:#c7d2fe; color:#3730a3; }
.badge.role-admin { background:#ecfdf5; border-color:#bbf7d0; color:#166534; }
.badge.role-member { background:#f8fafc; border-color:#e2e8f0; color:#0f172a; }
.list { display:grid; grid-template-columns:1fr; gap:10px; }
.chat-box { display:flex; flex-direction:column; gap:8px; max-height:260px; overflow:auto; padding-right:6px; }
.chat-msg { border:1px solid #e2e8f0; border-radius:12px; padding:10px 12px; background:#f8fafc; }
.chat-name { font-size:12px; font-weight:800; color:#0f172a; }
.chat-text { font-size:13px; color:#1f2937; margin-top:4px; }
.input-group { display:flex; flex-direction:column; gap:6px; margin-bottom:10px; }
.input-group label { font-size:13px; font-weight:700; color:#0f172a; }
.input-group input,.input-group textarea,.input-group select { width:100%; min-height:44px; border:1px solid #cbd5e1; border-radius:12px; padding:10px 12px; font-size:13px; background:#fff; }
.input-group textarea { min-height:90px; resize:vertical; }
.btn-row { display:flex; flex-wrap:wrap; gap:10px; }
.btn { display:inline-flex; align-items:center; justify-content:center; border-radius:999px; padding:10px 16px; font-size:13px; font-weight:900; border:1px solid #cbd5e1; background:#fff; cursor:pointer; min-height:44px; }
.btn.primary { color:#fff; border:none; background:linear-gradient(135deg,#0f766e,#22c55e); }
.btn.warn { color:#92400e; background:#fef3c7; border-color:#fcd34d; }
.btn.danger { color:#991b1b; background:#fef2f2; border-color:#fecaca; }
.btn.ok { color:#166534; background:#ecfdf5; border-color:#bbf7d0; }
.room-grid { display:grid; grid-template-columns: 1.2fr .8fr; gap: 14px; }
.room-code { font-size:12px; font-weight:900; letter-spacing:1px; padding:6px 10px; border-radius:10px; background:#0f172a; color:#fff; display:inline-block; }
.hint { font-size:12px; color:#64748b; margin-top:6px; }
.form-message { margin-bottom:12px; border-radius:12px; padding:10px 12px; font-size:13px; }
.form-error { background:#fef2f2; color:#991b1b; border:1px solid #fecaca; }
.form-success { background:#ecfdf5; color:#166534; border:1px solid #bbf7d0; }

@media (max-width: 900px) {
    .grid-2, .room-grid { grid-template-columns:1fr; }
}
@media (min-width: 900px) {
    .community-app {
        width: min(1180px, calc(100% - 40px));
    }

    .community-hero {
        padding: 30px 28px;
        border-radius: 28px;
    }

    .community-hero h1 {
        font-size: 34px;
        margin-bottom: 12px;
    }

    .community-hero p {
        max-width: 880px;
        font-size: 16px;
        line-height: 1.8;
        color: rgba(255,255,255,.9);
    }

    .community-switcher {
        padding: 16px;
        border-radius: 22px;
    }

    .community-switcher .tabs {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }

    .community-switcher .tab {
        min-height: 58px;
        border-radius: 20px;
        box-shadow: 0 14px 28px rgba(15, 23, 42, 0.08);
    }

    .section {
        padding: 22px;
        border-radius: 24px;
    }
}
</style>

<main class="community-app">
    <section class="community-hero">
        <h1>Community Hub</h1>
        <p>Tempat ngobrol dan promosi bareng untuk gym & coach. Ada channel global dan private seperti server game, plus fitur undang orang untuk join sesi coach atau membership gym.</p>
    </section>

    <section class="community-switcher" aria-label="Community channel">
        <nav class="tabs">
            <a class="tab <?= $channel === 'gym' ? 'active' : '' ?>" href="community.php?channel=gym">Gym Community</a>
            <a class="tab <?= $channel === 'coach' ? 'active' : '' ?>" href="community.php?channel=coach">Coach Community</a>
        </nav>
    </section>

    <?php if ($errors): ?>
        <section class="section">
            <div class="form-message form-error">
                <?php foreach ($errors as $e): ?>
                    <div><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
    <?php if ($success !== ''): ?>
        <section class="section">
            <div class="form-message form-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
        </section>
    <?php endif; ?>

    <section class="section">
        <h2 class="title">Global & Private Chat</h2>
        <p class="sub">Global bisa dilihat semua orang. Private hanya untuk department kamu (<?= htmlspecialchars($userDepartment, ENT_QUOTES, 'UTF-8') ?>).</p>

        <div class="grid-2">
            <article class="card">
                <div class="inline" style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                    <h3>Global Lounge</h3>
                    <span class="badge global">Global</span>
                </div>
                <div class="chat-box">
                    <?php if (!$globalMessages): ?>
                        <div class="hint">Belum ada chat global.</div>
                    <?php else: ?>
                        <?php foreach ($globalMessages as $msg): ?>
                            <div class="chat-msg">
                                <div class="chat-name"><?= htmlspecialchars((string) ($msg['display_name'] ?? 'Member'), ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="chat-text"><?= nl2br(htmlspecialchars((string) ($msg['message'] ?? ''), ENT_QUOTES, 'UTF-8')) ?></div>
                                <div class="meta"><?= htmlspecialchars((string) ($msg['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <?php if ($isLoggedIn): ?>
                    <form method="POST" style="margin-top:10px;">
                        <input type="hidden" name="action" value="post_message">
                        <input type="hidden" name="channel" value="<?= htmlspecialchars($channel, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="visibility" value="global">
                        <div class="input-group">
                            <label for="msg_global">Kirim pesan global</label>
                            <textarea id="msg_global" name="message" placeholder="Tanya jadwal latihan, promo membership, atau cari partner workout..."></textarea>
                        </div>
                        <button class="btn primary" type="submit">Kirim</button>
                    </form>
                <?php else: ?>
                    <div class="hint" style="margin-top:10px;">Login dulu untuk chat global.</div>
                    <a class="btn primary" href="login.php" style="text-decoration:none;">Login</a>
                <?php endif; ?>
            </article>

            <article class="card">
                <div class="inline" style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                    <h3>Private Room (Department)</h3>
                    <span class="badge private">Private</span>
                </div>
                <div class="chat-box">
                    <?php if (!$isLoggedIn): ?>
                        <div class="hint">Login untuk melihat chat private.</div>
                    <?php elseif (!$privateMessages): ?>
                        <div class="hint">Belum ada chat private di department ini.</div>
                    <?php else: ?>
                        <?php foreach ($privateMessages as $msg): ?>
                            <div class="chat-msg">
                                <div class="chat-name"><?= htmlspecialchars((string) ($msg['display_name'] ?? 'Member'), ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="chat-text"><?= nl2br(htmlspecialchars((string) ($msg['message'] ?? ''), ENT_QUOTES, 'UTF-8')) ?></div>
                                <div class="meta"><?= htmlspecialchars((string) ($msg['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <?php if ($isLoggedIn): ?>
                    <form method="POST" style="margin-top:10px;">
                        <input type="hidden" name="action" value="post_message">
                        <input type="hidden" name="channel" value="<?= htmlspecialchars($channel, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="visibility" value="private">
                        <div class="input-group">
                            <label for="msg_private">Kirim pesan private</label>
                            <textarea id="msg_private" name="message" placeholder="Ngobrol untuk team internal atau office gym..." ></textarea>
                        </div>
                        <button class="btn primary" type="submit">Kirim</button>
                    </form>
                <?php else: ?>
                    <div class="hint" style="margin-top:10px;">Login dulu untuk chat private.</div>
                    <a class="btn primary" href="login.php" style="text-decoration:none;">Login</a>
                <?php endif; ?>
            </article>
        </div>
    </section>

    <section class="section">
        <h2 class="title">Custom Private Rooms</h2>
        <p class="sub">Buat room sendiri, lalu bagikan kode. Join bisa auto-join atau butuh approval owner/admin.</p>

        <?php if (!$isLoggedIn): ?>
            <div class="hint">Login dulu untuk membuat atau join room.</div>
            <a class="btn primary" href="login.php" style="text-decoration:none;">Login</a>
        <?php else: ?>
            <form method="GET" class="card" style="margin-bottom:12px;">
                <input type="hidden" name="channel" value="<?= htmlspecialchars($channel, ENT_QUOTES, 'UTF-8') ?>">
                <div class="input-group" style="margin-bottom:0;">
                    <label for="room_search">Cari room (nama/kode)</label>
                    <input id="room_search" name="room_search" type="text" value="<?= htmlspecialchars($roomSearch, ENT_QUOTES, 'UTF-8') ?>" placeholder="Contoh: strength / A7K2Q9">
                </div>
                <div class="btn-row" style="margin-top:8px;">
                    <button class="btn primary" type="submit">Cari</button>
                    <a class="btn" href="community.php?channel=<?= htmlspecialchars($channel, ENT_QUOTES, 'UTF-8') ?>" style="text-decoration:none;">Reset</a>
                </div>
            </form>
            <div class="room-grid">
                <article class="card">
                    <h3>Buat Room</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="create_room">
                        <input type="hidden" name="channel" value="<?= htmlspecialchars($channel, ENT_QUOTES, 'UTF-8') ?>">
                        <div class="input-group">
                            <label for="room_name">Nama room</label>
                            <input id="room_name" name="room_name" type="text" placeholder="Contoh: Strength Squad" required>
                        </div>
                        <div class="input-group">
                            <label for="join_mode">Mode join</label>
                            <select id="join_mode" name="join_mode">
                                <option value="auto">Auto-join (punya kode langsung masuk)</option>
                                <option value="approve">Butuh approval owner/admin</option>
                            </select>
                        </div>
                        <button class="btn primary" type="submit">Buat Room</button>
                    </form>
                </article>

                <article class="card">
                    <h3>Join via Kode</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="join_room">
                        <input type="hidden" name="channel" value="<?= htmlspecialchars($channel, ENT_QUOTES, 'UTF-8') ?>">
                        <div class="input-group">
                            <label for="room_code">Kode room</label>
                            <input id="room_code" name="room_code" type="text" placeholder="Contoh: A7K2Q9" required>
                        </div>
                        <button class="btn primary" type="submit">Join Room</button>
                    </form>
                </article>
            </div>

            <div class="grid-2" style="margin-top:12px;">
                <article class="card">
                    <h3>Room Saya</h3>
                    <?php if (!$myRooms): ?>
                        <div class="hint">Belum ada room yang kamu join.</div>
                    <?php else: ?>
                        <div class="list">
                            <?php foreach ($myRooms as $r): ?>
                                <?php $pendingCount = isRoomManager((string) ($r['role'] ?? '')) ? getPendingCount($pdo, (int) $r['id_community_rooms']) : 0; ?>
                                <?php $mode = normalizeJoinMode((string) ($r['join_mode'] ?? 'auto')); ?>
                                <div class="chat-msg">
                                    <div class="chat-name"><?= htmlspecialchars((string) ($r['room_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="meta">Kode: <span class="room-code"><?= htmlspecialchars((string) ($r['room_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span></div>
                                    <div class="meta">
                                        <span class="badge <?= $mode ?>"><?= $mode === 'auto' ? 'Auto-join' : 'Approve' ?></span>
                                        <span class="badge role-<?= htmlspecialchars((string) ($r['role'] ?? 'member'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($r['role'] ?? 'member'), ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php if ($pendingCount > 0): ?>
                                            <span class="badge approve"><?= $pendingCount ?> pending</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="meta">
                                        <a class="btn" href="community.php?channel=<?= htmlspecialchars($channel, ENT_QUOTES, 'UTF-8') ?>&room_id=<?= (int) $r['id_community_rooms'] ?>" style="text-decoration:none;">Buka Room</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </article>

                <article class="card">
                    <h3>Request Pending</h3>
                    <?php if (!$pendingRooms): ?>
                        <div class="hint">Tidak ada request pending.</div>
                    <?php else: ?>
                        <div class="list">
                            <?php foreach ($pendingRooms as $r): ?>
                                <div class="chat-msg">
                                    <div class="chat-name"><?= htmlspecialchars((string) ($r['room_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="meta">Status: <?= htmlspecialchars((string) ($r['status'] ?? 'pending'), ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </article>
            </div>
        <?php endif; ?>
    </section>

    <?php if ($activeRoom): ?>
        <section class="section">
            <h2 class="title">Room: <?= htmlspecialchars((string) ($activeRoom['room_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="sub">Kode: <span class="room-code"><?= htmlspecialchars((string) ($activeRoom['room_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span> &bull; Mode: <?= htmlspecialchars((string) ($activeRoom['join_mode'] ?? 'auto'), ENT_QUOTES, 'UTF-8') ?></p>
            <div class="hint">Kode room ini permanen selama room aktif, jadi bisa dipakai berulang untuk member lain yang mau masuk grup.</div>

            <?php if (!$isLoggedIn): ?>
                <div class="hint">Login untuk mengakses room.</div>
                <a class="btn primary" href="login.php" style="text-decoration:none;">Login</a>
            <?php elseif (!$roomRole): ?>
                <div class="hint">Kamu belum menjadi member room ini. Join dulu dengan kode.</div>
            <?php else: ?>
                <div class="grid-2">
                    <article class="card">
                        <h3>Room Chat</h3>
                        <div class="chat-box">
                            <?php if (!$roomMessages): ?>
                                <div class="hint">Belum ada chat di room ini.</div>
                            <?php else: ?>
                                <?php foreach ($roomMessages as $msg): ?>
                                    <div class="chat-msg">
                                        <div class="chat-name"><?= htmlspecialchars((string) ($msg['display_name'] ?? 'Member'), ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="chat-text"><?= nl2br(htmlspecialchars((string) ($msg['message'] ?? ''), ENT_QUOTES, 'UTF-8')) ?></div>
                                        <div class="meta"><?= htmlspecialchars((string) ($msg['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <form method="POST" style="margin-top:10px;">
                            <input type="hidden" name="action" value="post_room_message">
                            <input type="hidden" name="room_id" value="<?= (int) $roomId ?>">
                            <div class="input-group">
                                <label for="room_msg">Kirim pesan room</label>
                                <textarea id="room_msg" name="message" placeholder="Tulis pesan untuk member room..."></textarea>
                            </div>
                            <button class="btn primary" type="submit">Kirim</button>
                        </form>
                        <?php if ($roomRole !== 'owner'): ?>
                            <form method="POST" style="margin-top:10px;">
                                <input type="hidden" name="action" value="leave_room">
                                <input type="hidden" name="room_id" value="<?= (int) $roomId ?>">
                                <button class="btn danger" type="submit">Leave Room</button>
                            </form>
                        <?php endif; ?>
                    </article>

                    <article class="card">
                        <h3>Member Room</h3>
                        <div class="meta">Role kamu: <span class="badge role-<?= htmlspecialchars($roomRole, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($roomRole, ENT_QUOTES, 'UTF-8') ?></span></div>

                        <?php if (!isRoomManager($roomRole)): ?>
                            <div class="hint">Hanya owner/admin yang bisa mengelola member.</div>
                        <?php else: ?>
                            <div class="list" style="margin-top:10px;">
                                <?php if ($roomPending): ?>
                                    <?php foreach ($roomPending as $m): ?>
                                        <div class="chat-msg">
                                            <div class="chat-name"><?= htmlspecialchars((string) ($m['display_name'] ?? 'Member'), ENT_QUOTES, 'UTF-8') ?></div>
                                            <div class="meta">Status: pending</div>
                                            <div class="btn-row" style="margin-top:6px;">
                                                <form method="POST">
                                                    <input type="hidden" name="action" value="approve_member">
                                                    <input type="hidden" name="room_id" value="<?= (int) $roomId ?>">
                                                    <input type="hidden" name="member_id" value="<?= (int) ($m['id_community_room_members'] ?? 0) ?>">
                                                    <button class="btn ok" type="submit">Approve</button>
                                                </form>
                                                <form method="POST">
                                                    <input type="hidden" name="action" value="reject_member">
                                                    <input type="hidden" name="room_id" value="<?= (int) $roomId ?>">
                                                    <input type="hidden" name="member_id" value="<?= (int) ($m['id_community_room_members'] ?? 0) ?>">
                                                    <button class="btn danger" type="submit">Reject</button>
                                                </form>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="hint">Tidak ada request pending.</div>
                                <?php endif; ?>
                            </div>

                            <div class="list" style="margin-top:12px;">
                                <?php foreach ($roomMembers as $m): ?>
                                    <div class="chat-msg">
                                        <div class="chat-name"><?= htmlspecialchars((string) ($m['display_name'] ?? 'Member'), ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="meta">
                                            Role: <span class="badge role-<?= htmlspecialchars((string) ($m['role'] ?? 'member'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($m['role'] ?? 'member'), ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                        <?php if (normalizeRole((string) ($m['role'] ?? 'member')) !== 'owner'): ?>
                                            <div class="btn-row" style="margin-top:6px;">
                                                <?php if ($roomRole === 'owner'): ?>
                                                    <form method="POST">
                                                        <input type="hidden" name="action" value="set_role">
                                                        <input type="hidden" name="room_id" value="<?= (int) $roomId ?>">
                                                        <input type="hidden" name="member_id" value="<?= (int) ($m['id_community_room_members'] ?? 0) ?>">
                                                        <input type="hidden" name="new_role" value="admin">
                                                        <button class="btn warn" type="submit">Set Admin</button>
                                                    </form>
                                                    <form method="POST">
                                                        <input type="hidden" name="action" value="set_role">
                                                        <input type="hidden" name="room_id" value="<?= (int) $roomId ?>">
                                                        <input type="hidden" name="member_id" value="<?= (int) ($m['id_community_room_members'] ?? 0) ?>">
                                                        <input type="hidden" name="new_role" value="member">
                                                        <button class="btn" type="submit">Set Member</button>
                                                    </form>
                                                <?php endif; ?>
                                                <form method="POST">
                                                    <input type="hidden" name="action" value="kick_member">
                                                    <input type="hidden" name="room_id" value="<?= (int) $roomId ?>">
                                                    <input type="hidden" name="member_id" value="<?= (int) ($m['id_community_room_members'] ?? 0) ?>">
                                                    <button class="btn danger" type="submit">Kick</button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </article>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
    <section class="section">
        <h2 class="title">Undang Join Sesi / Membership</h2>
        <p class="sub">Buat undangan untuk join sesi coach atau ajak membership gym. Bisa global atau private.</p>

        <?php if (!$isLoggedIn): ?>
            <div class="hint">Login dulu untuk membuat undangan.</div>
            <a class="btn primary" href="login.php" style="text-decoration:none;">Login</a>
        <?php else: ?>
            <form method="POST">
                <input type="hidden" name="action" value="create_invite">
                <input type="hidden" name="channel" value="<?= htmlspecialchars($channel, ENT_QUOTES, 'UTF-8') ?>">

                <div class="grid-2">
                    <div class="input-group">
                        <label for="invite_type">Tipe undangan</label>
                        <select id="invite_type" name="invite_type">
                            <option value="membership">Undang Membership Gym</option>
                            <option value="session">Undang Sesi Coach</option>
                        </select>
                    </div>
                    <div class="input-group">
                        <label for="invite_visibility">Visibility</label>
                        <select id="invite_visibility" name="visibility">
                            <option value="global">Global (semua orang)</option>
                            <option value="private">Private (department)</option>
                        </select>
                    </div>
                </div>

                <div class="input-group">
                    <label for="invite_title">Judul undangan</label>
                    <input id="invite_title" name="title" type="text" placeholder="Contoh: Join membership Platinum bareng" required>
                </div>

                <div class="grid-2">
                    <div class="input-group">
                        <label for="invite_date">Tanggal (opsional)</label>
                        <input id="invite_date" name="event_date" type="date">
                    </div>
                    <div class="input-group">
                        <label for="invite_time">Jam (opsional)</label>
                        <input id="invite_time" name="event_time" type="time">
                    </div>
                </div>

                <div class="input-group">
                    <label for="invite_details">Detail undangan</label>
                    <textarea id="invite_details" name="details" placeholder="Contoh: butuh 3 orang join sesi strength, lokasi Gym Alpha, tujuan 4 minggu." required></textarea>
                </div>

                <div class="input-group">
                    <label for="invite_contact">Kontak (opsional)</label>
                    <input id="invite_contact" name="contact_text" type="text" placeholder="Contoh: 08xxxx / email">
                </div>

                <div class="btn-row">
                    <button class="btn primary" type="submit">Post Undangan</button>
                </div>
            </form>
        <?php endif; ?>
    </section>

    <section class="section">
        <h2 class="title">Undangan Global</h2>
        <div class="list">
            <?php if (!$globalInvites): ?>
                <div class="hint">Belum ada undangan global.</div>
            <?php else: ?>
                <?php foreach ($globalInvites as $inv): ?>
                    <?php $type = normalizeInviteType((string) ($inv['invite_type'] ?? 'membership')); ?>
                    <article class="card">
                        <div class="inline" style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                            <h3><?= htmlspecialchars((string) ($inv['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h3>
                            <span class="badge <?= $type ?>"><?= $type === 'session' ? 'Session' : 'Membership' ?></span>
                        </div>
                        <div class="meta"><?= nl2br(htmlspecialchars((string) ($inv['details'] ?? ''), ENT_QUOTES, 'UTF-8')) ?></div>
                        <div class="meta">
                            <?php if (!empty($inv['event_date'])): ?>Tanggal: <?= formatDate((string) $inv['event_date']) ?><?php endif; ?>
                            <?php if (!empty($inv['event_time'])): ?> &bull; Jam: <?= formatTime((string) $inv['event_time']) ?><?php endif; ?>
                        </div>
                        <?php if (!empty($inv['contact_text'])): ?>
                            <div class="meta">Kontak: <?= htmlspecialchars((string) ($inv['contact_text'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>
                        <div class="meta"><?= htmlspecialchars((string) ($inv['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <section class="section">
        <h2 class="title">Undangan Private</h2>
        <p class="sub">Hanya terlihat untuk department kamu (<?= htmlspecialchars($userDepartment, ENT_QUOTES, 'UTF-8') ?>).</p>
        <div class="list">
            <?php if (!$isLoggedIn): ?>
                <div class="hint">Login untuk melihat undangan private.</div>
            <?php elseif (!$privateInvites): ?>
                <div class="hint">Belum ada undangan private di department ini.</div>
            <?php else: ?>
                <?php foreach ($privateInvites as $inv): ?>
                    <?php $type = normalizeInviteType((string) ($inv['invite_type'] ?? 'membership')); ?>
                    <article class="card">
                        <div class="inline" style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                            <h3><?= htmlspecialchars((string) ($inv['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h3>
                            <span class="badge <?= $type ?>"><?= $type === 'session' ? 'Session' : 'Membership' ?></span>
                        </div>
                        <div class="meta"><?= nl2br(htmlspecialchars((string) ($inv['details'] ?? ''), ENT_QUOTES, 'UTF-8')) ?></div>
                        <div class="meta">
                            <?php if (!empty($inv['event_date'])): ?>Tanggal: <?= formatDate((string) $inv['event_date']) ?><?php endif; ?>
                            <?php if (!empty($inv['event_time'])): ?> &bull; Jam: <?= formatTime((string) $inv['event_time']) ?><?php endif; ?>
                        </div>
                        <?php if (!empty($inv['contact_text'])): ?>
                            <div class="meta">Kontak: <?= htmlspecialchars((string) ($inv['contact_text'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>
                        <div class="meta"><?= htmlspecialchars((string) ($inv['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
</main>

<script>
(() => {
    document.querySelectorAll('.chat-box').forEach((box) => {
        box.scrollTop = box.scrollHeight;
    });
})();
</script>

<?php include 'includes/footer.php'; ?>
