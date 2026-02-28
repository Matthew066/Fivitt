<?php
require_once __DIR__ . '/auth.php';
require_once '../includes/db.php';

try {
    $pdo->query("ALTER TABLE gym_equipments ADD COLUMN description varchar(255) DEFAULT NULL");
} catch (Throwable $e) {
    // ignore if column already exists
}

try {
    $pdo->query("ALTER TABLE gym_equipments ADD COLUMN image_path varchar(255) DEFAULT NULL");
} catch (Throwable $e) {
    // ignore if column already exists
}

try {
    $pdo->query("ALTER TABLE gym_bookings ADD COLUMN equipment_id bigint(20) DEFAULT NULL");
} catch (Throwable $e) {
    // ignore if column already exists
}

$equipmentImageDirRelative = 'assets/images/gym-tools';
$equipmentImageDirAbsolute = dirname(__DIR__) . '/' . $equipmentImageDirRelative;

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'save_equipment') {
        $equipmentId = (int) ($_POST['equipment_id'] ?? 0);
        $equipmentName = trim($_POST['equipment_name'] ?? '');
        $quantity = (int) ($_POST['quantity'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $currentImagePath = trim($_POST['current_image_path'] ?? '');
        $removeImage = isset($_POST['remove_image']);
        $imagePath = $currentImagePath;

        if ($removeImage) {
            $imagePath = '';
        }

        if (isset($_FILES['equipment_image']) && (int) ($_FILES['equipment_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $uploadError = (int) $_FILES['equipment_image']['error'];

            if ($uploadError === UPLOAD_ERR_OK) {
                $maxFileSize = 2 * 1024 * 1024;
                $fileSize = (int) ($_FILES['equipment_image']['size'] ?? 0);
                $tmpName = (string) ($_FILES['equipment_image']['tmp_name'] ?? '');

                $allowedMimeTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                $detectedMime = $tmpName !== '' ? (string) mime_content_type($tmpName) : '';

                if ($fileSize > 0 && $fileSize <= $maxFileSize && isset($allowedMimeTypes[$detectedMime])) {
                    if (!is_dir($equipmentImageDirAbsolute)) {
                        mkdir($equipmentImageDirAbsolute, 0777, true);
                    }

                    $fileName = 'gym-tool-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $allowedMimeTypes[$detectedMime];
                    $targetAbsolutePath = $equipmentImageDirAbsolute . '/' . $fileName;
                    $targetRelativePath = $equipmentImageDirRelative . '/' . $fileName;

                    if (move_uploaded_file($tmpName, $targetAbsolutePath)) {
                        if (
                            $currentImagePath !== '' &&
                            str_starts_with($currentImagePath, $equipmentImageDirRelative . '/') &&
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
            str_starts_with($currentImagePath, $equipmentImageDirRelative . '/') &&
            is_file(dirname(__DIR__) . '/' . $currentImagePath)
        ) {
            @unlink(dirname(__DIR__) . '/' . $currentImagePath);
        }

        if ($equipmentName !== '' && $quantity >= 0) {
            if ($equipmentId > 0) {
                $update = $pdo->prepare("
                    UPDATE gym_equipments
                    SET equipment_name = ?, quantity = ?, description = ?, image_path = ?
                    WHERE id_gym_equipments = ?
                ");
                $update->execute([$equipmentName, $quantity, $description, ($imagePath !== '' ? $imagePath : null), $equipmentId]);
            } else {
                $insert = $pdo->prepare("
                    INSERT INTO gym_equipments (gym_id, equipment_name, quantity, description, image_path)
                    VALUES (NULL, ?, ?, ?, ?)
                ");
                $insert->execute([$equipmentName, $quantity, $description, ($imagePath !== '' ? $imagePath : null)]);
            }
        }

        header('Location: add_gym_tools.php');
        exit;
    }

    if ($action === 'delete_equipment') {
        $equipmentId = (int) ($_POST['equipment_id'] ?? 0);
        if ($equipmentId > 0) {
            $imageStmt = $pdo->prepare("SELECT image_path FROM gym_equipments WHERE id_gym_equipments = ? LIMIT 1");
            $imageStmt->execute([$equipmentId]);
            $equipmentRow = $imageStmt->fetch();

            $delete = $pdo->prepare("DELETE FROM gym_equipments WHERE id_gym_equipments = ?");
            $delete->execute([$equipmentId]);

            $imagePath = (string) ($equipmentRow['image_path'] ?? '');
            if (
                $imagePath !== '' &&
                str_starts_with($imagePath, $equipmentImageDirRelative . '/') &&
                is_file(dirname(__DIR__) . '/' . $imagePath)
            ) {
                @unlink(dirname(__DIR__) . '/' . $imagePath);
            }
        }

        header('Location: add_gym_tools.php');
        exit;
    }

    if ($action === 'save_booking') {
        $bookingId = (int) ($_POST['booking_id'] ?? 0);
        $equipmentId = (int) ($_POST['equipment_id'] ?? 0);
        $bookingDate = $_POST['booking_date'] ?? '';
        $startTime = $_POST['start_time'] ?? '';
        $endTime = $_POST['end_time'] ?? '';
        $status = trim($_POST['status'] ?? 'confirmed');

        $allowedStatus = ['pending', 'confirmed', 'cancelled', 'completed'];
        if (!in_array($status, $allowedStatus, true)) {
            $status = 'confirmed';
        }

        if ($bookingId > 0 && $bookingDate !== '' && $startTime !== '' && $endTime !== '') {
            $timeSlot = $startTime . '-' . $endTime;
            $update = $pdo->prepare("
                UPDATE gym_bookings
                SET equipment_id = ?, booking_date = ?, time_slot = ?, status = ?
                WHERE id_gym_bookings = ?
            ");
            $update->execute([$equipmentId ?: null, $bookingDate, $timeSlot, $status, $bookingId]);
        }

        header('Location: add_gym_tools.php');
        exit;
    }

    if ($action === 'delete_booking') {
        $bookingId = (int) ($_POST['booking_id'] ?? 0);
        if ($bookingId > 0) {
            $bookingStmt = $pdo->prepare("SELECT equipment_id FROM gym_bookings WHERE id_gym_bookings = ? LIMIT 1");
            $bookingStmt->execute([$bookingId]);
            $booking = $bookingStmt->fetch();

            $pdo->beginTransaction();
            try {
                $delete = $pdo->prepare("DELETE FROM gym_bookings WHERE id_gym_bookings = ?");
                $delete->execute([$bookingId]);

                $equipmentId = (int) ($booking['equipment_id'] ?? 0);
                if ($equipmentId > 0) {
                    $restoreStock = $pdo->prepare("
                        UPDATE gym_equipments
                        SET quantity = quantity + 1
                        WHERE id_gym_equipments = ?
                    ");
                    $restoreStock->execute([$equipmentId]);
                }

                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
            }
        }

        header('Location: add_gym_tools.php');
        exit;
    }
}

$editEquipmentId = isset($_GET['edit_equipment']) ? (int) $_GET['edit_equipment'] : 0;
$editBookingId = isset($_GET['edit_booking']) ? (int) $_GET['edit_booking'] : 0;

$editEquipment = null;
if ($editEquipmentId > 0) {
    $equipmentStmt = $pdo->prepare("SELECT * FROM gym_equipments WHERE id_gym_equipments = ? LIMIT 1");
    $equipmentStmt->execute([$editEquipmentId]);
    $editEquipment = $equipmentStmt->fetch();
}

$editBooking = null;
if ($editBookingId > 0) {
    $bookingStmt = $pdo->prepare("SELECT * FROM gym_bookings WHERE id_gym_bookings = ? LIMIT 1");
    $bookingStmt->execute([$editBookingId]);
    $editBooking = $bookingStmt->fetch();
}

$equipmentsStmt = $pdo->query("
    SELECT ge.*, g.name AS gym_name
    FROM gym_equipments ge
    LEFT JOIN gyms g ON ge.gym_id = g.id_gyms
    ORDER BY ge.id_gym_equipments DESC
");
$equipments = $equipmentsStmt->fetchAll();

$bookingsStmt = $pdo->query("
    SELECT
        gb.*,
        u.name AS user_name,
        u.email AS user_email,
        ge.equipment_name
    FROM gym_bookings gb
    LEFT JOIN users u ON u.id_users = gb.user_id
    LEFT JOIN gym_equipments ge ON ge.id_gym_equipments = gb.equipment_id
    ORDER BY gb.booking_date DESC, gb.id_gym_bookings DESC
");
$bookings = $bookingsStmt->fetchAll();
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
					<div class="breadcrumb-title pe-3">Gym Tools Management</div>
				</div>

                <div class="row">
                    <div class="col-12 col-lg-5">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="mb-3">
                                    <?php echo $editEquipment ? 'Edit Alat Gym' : 'Tambah Alat Gym'; ?>
                                </h6>
                                <form method="post" enctype="multipart/form-data">
                                    <input type="hidden" name="action" value="save_equipment">
                                    <input type="hidden" name="equipment_id" value="<?php echo (int) ($editEquipment['id_gym_equipments'] ?? 0); ?>">
                                    <input type="hidden" name="current_image_path" value="<?php echo h($editEquipment['image_path'] ?? ''); ?>">
                                    <div class="mb-3">
                                        <label class="form-label">Nama Alat</label>
                                        <input type="text" class="form-control" name="equipment_name" required value="<?php echo h($editEquipment['equipment_name'] ?? ''); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Stok</label>
                                        <input type="number" min="0" class="form-control" name="quantity" required value="<?php echo (int) ($editEquipment['quantity'] ?? 0); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Deskripsi</label>
                                        <input type="text" class="form-control" name="description" value="<?php echo h($editEquipment['description'] ?? ''); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Gambar Alat (JPG/PNG/WEBP, max 2MB)</label>
                                        <input type="file" class="form-control" name="equipment_image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                                    </div>
                                    <?php if (!empty($editEquipment['image_path'])): ?>
                                        <div class="mb-3">
                                            <img src="../<?php echo h($editEquipment['image_path']); ?>" alt="Preview alat" style="width:90px;height:90px;object-fit:cover;border-radius:10px;">
                                            <div class="form-check mt-2">
                                                <input class="form-check-input" type="checkbox" id="remove_image" name="remove_image">
                                                <label class="form-check-label" for="remove_image">Hapus gambar saat simpan</label>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    <button type="submit" class="btn btn-primary">
                                        <?php echo $editEquipment ? 'Update Alat' : 'Tambah Alat'; ?>
                                    </button>
                                    <?php if ($editEquipment): ?>
                                        <a href="add_gym_tools.php" class="btn btn-light ms-1">Batal</a>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-lg-7">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="mb-3">Daftar Alat (Data User Gym Booking)</h6>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Gambar</th>
                                                <th>Nama</th>
                                                <th>Stok</th>
                                                <th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!$equipments): ?>
                                                <tr>
                                                    <td colspan="5" class="text-center">Belum ada alat.</td>
                                                </tr>
                                            <?php endif; ?>
                                            <?php foreach ($equipments as $equipment): ?>
                                                <tr>
                                                    <td><?php echo (int) $equipment['id_gym_equipments']; ?></td>
                                                    <td>
                                                        <?php if (!empty($equipment['image_path'])): ?>
                                                            <img src="../<?php echo h($equipment['image_path']); ?>" alt="Gambar alat" style="width:56px;height:56px;object-fit:cover;border-radius:8px;">
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php echo h($equipment['equipment_name']); ?>
                                                        <div class="text-muted small"><?php echo h($equipment['description']); ?></div>
                                                    </td>
                                                    <td><?php echo (int) $equipment['quantity']; ?></td>
                                                    <td>
                                                        <a href="add_gym_tools.php?edit_equipment=<?php echo (int) $equipment['id_gym_equipments']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                                        <form method="post" class="d-inline" onsubmit="return confirm('Hapus alat ini?');">
                                                            <input type="hidden" name="action" value="delete_equipment">
                                                            <input type="hidden" name="equipment_id" value="<?php echo (int) $equipment['id_gym_equipments']; ?>">
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

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="mb-3"><?php echo $editBooking ? 'Edit Pemesanan Alat' : 'Data Pemesanan User'; ?></h6>
                                <?php if ($editBooking): ?>
                                    <?php
                                    $slot = explode('-', (string) ($editBooking['time_slot'] ?? ''), 2);
                                    $editStart = trim($slot[0] ?? '');
                                    $editEnd = trim($slot[1] ?? '');
                                    ?>
                                    <form method="post" class="mb-4">
                                        <input type="hidden" name="action" value="save_booking">
                                        <input type="hidden" name="booking_id" value="<?php echo (int) $editBooking['id_gym_bookings']; ?>">
                                        <div class="row g-2">
                                            <div class="col-md-3">
                                                <label class="form-label">Alat</label>
                                                <select name="equipment_id" class="form-select">
                                                    <option value="0">Pilih alat</option>
                                                    <?php foreach ($equipments as $equipment): ?>
                                                        <option value="<?php echo (int) $equipment['id_gym_equipments']; ?>" <?php echo ((int) $editBooking['equipment_id'] === (int) $equipment['id_gym_equipments']) ? 'selected' : ''; ?>>
                                                            <?php echo h($equipment['equipment_name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">Tanggal</label>
                                                <input type="date" class="form-control" name="booking_date" value="<?php echo h($editBooking['booking_date']); ?>" required>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">Jam Mulai</label>
                                                <input type="time" class="form-control" name="start_time" value="<?php echo h($editStart); ?>" required>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">Jam Selesai</label>
                                                <input type="time" class="form-control" name="end_time" value="<?php echo h($editEnd); ?>" required>
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label">Status</label>
                                                <select name="status" class="form-select">
                                                    <?php
                                                    $statuses = ['pending', 'confirmed', 'cancelled', 'completed'];
                                                    foreach ($statuses as $status):
                                                    ?>
                                                        <option value="<?php echo $status; ?>" <?php echo (($editBooking['status'] ?? '') === $status) ? 'selected' : ''; ?>>
                                                            <?php echo ucfirst($status); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-1 d-flex align-items-end">
                                                <button type="submit" class="btn btn-primary w-100">Simpan</button>
                                            </div>
                                        </div>
                                    </form>
                                <?php endif; ?>

                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Siapa</th>
                                                <th>Alat</th>
                                                <th>Kapan</th>
                                                <th>Sampai Jam</th>
                                                <th>Status</th>
                                                <th>Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!$bookings): ?>
                                                <tr>
                                                    <td colspan="7" class="text-center">Belum ada pemesanan.</td>
                                                </tr>
                                            <?php endif; ?>
                                            <?php foreach ($bookings as $booking): ?>
                                                <?php
                                                $slot = explode('-', (string) ($booking['time_slot'] ?? ''), 2);
                                                $start = trim($slot[0] ?? '-');
                                                $end = trim($slot[1] ?? '-');
                                                ?>
                                                <tr>
                                                    <td><?php echo (int) $booking['id_gym_bookings']; ?></td>
                                                    <td>
                                                        <?php echo h($booking['user_name'] ?: 'User #' . (int) $booking['user_id']); ?>
                                                        <div class="text-muted small"><?php echo h($booking['user_email']); ?></div>
                                                    </td>
                                                    <td><?php echo h($booking['equipment_name'] ?: 'Alat tidak ditemukan'); ?></td>
                                                    <td><?php echo h($booking['booking_date']); ?><div class="text-muted small"><?php echo h($start); ?></div></td>
                                                    <td><?php echo h($end); ?></td>
                                                    <td><span class="badge bg-light text-dark"><?php echo h($booking['status']); ?></span></td>
                                                    <td>
                                                        <a href="add_gym_tools.php?edit_booking=<?php echo (int) $booking['id_gym_bookings']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                                        <form method="post" class="d-inline" onsubmit="return confirm('Hapus booking ini?');">
                                                            <input type="hidden" name="action" value="delete_booking">
                                                            <input type="hidden" name="booking_id" value="<?php echo (int) $booking['id_gym_bookings']; ?>">
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
