<?php
require_once '../includes/db.php';

$statusType = (string)($_GET['status'] ?? '');
$statusMessage = (string)($_GET['message'] ?? '');

$query = "SELECT id_users, name, email, role, department FROM users ORDER BY id_users DESC";
$result = $pdo->query($query);

?>

<!DOCTYPE html>
<html lang="en">
<?php require 'includes/head.php'; ?>

<body>
  <!--wrapper-->
  <div class="wrapper">
    <!--sidebar-->
    <?php include 'includes/sidebar.php'; ?>
    <!--end sidebar-->
    <!--header-->
    <?php include 'includes/header.php'; ?>
    <!--end header-->

    <div class="page-wrapper">
      <div class="page-content">
        <!--breadcrumb-->
        <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
          <div class="breadcrumb-title pe-3">Users Management</div>
        </div>
        <!--end breadcrumb-->
        <h6 class="mb-0 text-uppercase">Manage Users</h6>
        <hr/>

        <?php if ($statusType !== '' && $statusMessage !== ''): ?>
          <div class="alert alert-<?php echo htmlspecialchars($statusType, ENT_QUOTES, 'UTF-8'); ?> border-0 bg-<?php echo htmlspecialchars($statusType, ENT_QUOTES, 'UTF-8'); ?> alert-dismissible fade show">
            <div class="text-white"><?php echo htmlspecialchars($statusMessage, ENT_QUOTES, 'UTF-8'); ?></div>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endif; ?>

        <div class="card mb-4">
          <div class="card-body">
            <h6 class="mb-3">Add User</h6>
            <form method="post" action="create-user.php" class="row g-3">
              <div class="col-md-3">
                <label class="form-label">Name</label>
                <input type="text" name="name" class="form-control" required>
              </div>
              <div class="col-md-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" required>
              </div>
              <div class="col-md-2">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" minlength="6" required>
              </div>
              <div class="col-md-2">
                <label class="form-label">Role</label>
                <select name="role" class="form-select">
                  <option value="user">User</option>
                  <option value="admin">Admin</option>
                </select>
              </div>
              <div class="col-md-2">
                <label class="form-label">Department</label>
                <input type="text" name="department" class="form-control" value="General">
              </div>
              <div class="col-12">
                <button type="submit" class="btn btn-primary">Add User</button>
              </div>
            </form>
          </div>
        </div>

        <div class="card">
          <div class="card-body">
            <div class="table-responsive">
              <table id="example" class="table table-striped table-bordered" style="width:100%">
                <thead>
                  <tr>
                    <th>User ID</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Department</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php while ($row = $result->fetch()): ?>
                    <?php $userId = (int)$row['id_users']; ?>
                    <?php $formId = 'update_user_' . $userId; ?>
                    <tr>
                      <td><?php echo htmlspecialchars((string)$row['id_users'], ENT_QUOTES, 'UTF-8'); ?></td>
                      <td><?php echo htmlspecialchars((string)$row['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                      <td><?php echo htmlspecialchars((string)$row['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                      <td>
                        <select class="form-select" name="role" form="<?php echo htmlspecialchars($formId, ENT_QUOTES, 'UTF-8'); ?>">
                          <option value="user" <?php echo strtolower((string)$row['role']) === 'user' ? 'selected' : ''; ?>>User</option>
                          <option value="admin" <?php echo strtolower((string)$row['role']) === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        </select>
                      </td>
                      <td>
                        <input
                          type="text"
                          class="form-control"
                          name="department"
                          form="<?php echo htmlspecialchars($formId, ENT_QUOTES, 'UTF-8'); ?>"
                          value="<?php echo htmlspecialchars((string)$row['department'], ENT_QUOTES, 'UTF-8'); ?>"
                        >
                      </td>
                      <td class="text-nowrap">
                        <form id="<?php echo htmlspecialchars($formId, ENT_QUOTES, 'UTF-8'); ?>" method="post" action="update-user.php" class="d-inline">
                          <input type="hidden" name="user_id" value="<?php echo $userId; ?>">
                          <button type="submit" class="btn btn-sm btn-primary">Update</button>
                        </form>

                        <form method="post" action="delete-user.php" class="d-inline" onsubmit="return confirm('Yakin ingin menghapus user ini?');">
                          <input type="hidden" name="user_id" value="<?php echo $userId; ?>">
                          <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                        </form>
                      </td>
                    </tr>
                  <?php endwhile; ?>
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
