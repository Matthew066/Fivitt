<?php
require_once __DIR__ . '/auth.php';
require_once '../includes/db.php';

try {
    $pdo->query("ALTER TABLE foods ADD COLUMN image_path varchar(255) DEFAULT NULL");
} catch (Throwable $e) {
    // ignore when column already exists
}

$status = strtolower(trim((string)($_GET['status'] ?? '')));
$message = trim((string)($_GET['message'] ?? ''));
$editId = (int)($_GET['edit_id'] ?? 0);
$allowedStatus = ['success', 'danger', 'warning', 'info'];

if (!in_array($status, $allowedStatus, true)) {
    $status = '';
}

$query = "
    SELECT
        f.id_foods,
        f.name,
        COALESCE(f.calories, 0) AS calories,
        COALESCE(f.protein, 0) AS protein,
        COALESCE(f.fat, 0) AS fat,
        COALESCE(f.carbs, 0) AS carbs,
        COALESCE(f.rating, 0) AS rating,
        f.image_path,
        f.id_users_created_by,
        COALESCE(u.name, 'Unknown') AS creator_name
    FROM foods f
    LEFT JOIN users u ON u.id_users = f.id_users_created_by
    ORDER BY f.id_foods DESC
";
$foods = $pdo->query($query);
$foodRows = $foods->fetchAll(PDO::FETCH_ASSOC);

$editFood = null;
if ($editId > 0) {
    foreach ($foodRows as $row) {
        if ((int)$row['id_foods'] === $editId) {
            $editFood = $row;
            break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<?php require 'includes/head.php'; ?>
<body>
  <div class="wrapper">
    <?php include 'includes/sidebar.php'; ?>
    <?php include 'includes/header.php'; ?>

    <div class="page-wrapper">
      <div class="page-content crud-page">
        <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
          <div class="breadcrumb-title pe-3">Healthy Canteen</div>
        </div>

        <h6 class="mb-0 text-uppercase">Manage Foods</h6>
        <hr class="mt-2 mb-4"/>

        <?php if ($status !== '' && $message !== ''): ?>
          <div class="alert alert-<?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?> border-0 bg-<?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?> alert-dismissible fade show mb-4">
            <div class="text-white"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endif; ?>

        <div class="card mb-4">
          <div class="card-body p-4">
            <h6 class="mb-3"><?php echo $editFood ? 'Edit Menu' : 'Add Menu'; ?></h6>
            <form action="<?php echo $editFood ? 'update-food.php' : 'create-food.php'; ?>" method="POST" enctype="multipart/form-data" class="row g-3 align-items-end">
              <?php if ($editFood): ?>
                <input type="hidden" name="food_id" value="<?php echo (int)$editFood['id_foods']; ?>">
                <input type="hidden" name="current_image_path" value="<?php echo htmlspecialchars((string)($editFood['image_path'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
              <?php endif; ?>
              <div class="col-md-3">
                <label for="name" class="form-label">Name</label>
                <input type="text" id="name" name="name" class="form-control" maxlength="255" value="<?php echo htmlspecialchars((string)($editFood['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
              </div>
              <div class="col-md-2">
                <label for="calories" class="form-label">Calories</label>
                <input type="number" id="calories" name="calories" class="form-control" min="0" value="<?php echo (int)($editFood['calories'] ?? 0); ?>" required>
              </div>
              <div class="col-md-2">
                <label for="protein" class="form-label">Protein</label>
                <input type="number" id="protein" name="protein" class="form-control" min="0" step="0.01" value="<?php echo htmlspecialchars((string)($editFood['protein'] ?? '0'), ENT_QUOTES, 'UTF-8'); ?>">
              </div>
              <div class="col-md-2">
                <label for="fat" class="form-label">Fat</label>
                <input type="number" id="fat" name="fat" class="form-control" min="0" step="0.01" value="<?php echo htmlspecialchars((string)($editFood['fat'] ?? '0'), ENT_QUOTES, 'UTF-8'); ?>">
              </div>
              <div class="col-md-2">
                <label for="carbs" class="form-label">Carbs</label>
                <input type="number" id="carbs" name="carbs" class="form-control" min="0" step="0.01" value="<?php echo htmlspecialchars((string)($editFood['carbs'] ?? '0'), ENT_QUOTES, 'UTF-8'); ?>">
              </div>
              <div class="col-md-1">
                <label for="rating" class="form-label">Rating</label>
                <input type="number" id="rating" name="rating" class="form-control" min="0" max="5" value="<?php echo (int)($editFood['rating'] ?? 0); ?>" required>
              </div>
              <div class="col-md-4">
                <label for="food_image" class="form-label">Food Image</label>
                <input type="file" id="food_image" name="food_image" class="form-control" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
              </div>
              <?php if ($editFood && !empty($editFood['image_path'])): ?>
                <div class="col-md-8">
                  <img src="../<?php echo htmlspecialchars((string)$editFood['image_path'], ENT_QUOTES, 'UTF-8'); ?>" alt="Food image" style="width:64px;height:64px;object-fit:cover;border-radius:8px;">
                  <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" id="remove_image" name="remove_image">
                    <label class="form-check-label" for="remove_image">Remove current image</label>
                  </div>
                </div>
              <?php endif; ?>
              <div class="col-12">
                <button type="submit" class="btn btn-primary btn-sport-primary btn-action"><?php echo $editFood ? 'Update Menu' : 'Add Menu'; ?></button>
                <?php if ($editFood): ?>
                  <a href="healthy_canteen_admin.php" class="btn btn-light ms-1">Cancel</a>
                <?php endif; ?>
              </div>
            </form>
          </div>
        </div>

        <div class="card">
          <div class="card-body p-4">
            <div class="table-responsive">
              <table class="table table-striped table-bordered">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Image</th>
                    <th>Name</th>
                    <th>Calories</th>
                    <th>Protein</th>
                    <th>Fat</th>
                    <th>Carbs</th>
                    <th>Rating</th>
                    <th>Creator</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($foodRows as $food): ?>
                    <tr>
                      <td><?php echo (int)$food['id_foods']; ?></td>
                      <td>
                        <?php if (!empty($food['image_path'])): ?>
                          <img src="../<?php echo htmlspecialchars((string)$food['image_path'], ENT_QUOTES, 'UTF-8'); ?>" alt="Food image" style="width:42px;height:42px;object-fit:cover;border-radius:8px;">
                        <?php else: ?>
                          <span class="text-muted">-</span>
                        <?php endif; ?>
                      </td>
                      <td><?php echo htmlspecialchars((string)$food['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                      <td><?php echo (int)$food['calories']; ?></td>
                      <td><?php echo htmlspecialchars((string)$food['protein'], ENT_QUOTES, 'UTF-8'); ?></td>
                      <td><?php echo htmlspecialchars((string)$food['fat'], ENT_QUOTES, 'UTF-8'); ?></td>
                      <td><?php echo htmlspecialchars((string)$food['carbs'], ENT_QUOTES, 'UTF-8'); ?></td>
                      <td><?php echo (int)$food['rating']; ?></td>
                        <td><?php echo htmlspecialchars((string)$food['creator_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="text-nowrap">
                          <a href="healthy_canteen_admin.php?edit_id=<?php echo (int)$food['id_foods']; ?>" class="btn btn-primary btn-sport-primary btn-action btn-sm">Edit</a>
                          <form action="delete-food.php" method="POST" class="d-inline-block ms-1" onsubmit="return confirm('Yakin ingin menghapus menu ini?');">
                            <input type="hidden" name="food_id" value="<?php echo (int)$food['id_foods']; ?>">
                            <button type="submit" class="btn btn-danger btn-sport-danger btn-action btn-sm">Delete</button>
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

  <?php include 'includes/footer.php'; ?>
</body>
</html>
