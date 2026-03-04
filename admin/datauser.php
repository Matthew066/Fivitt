<?php
require_once '../includes/db.php';

$status = strtolower(trim((string)($_GET['status'] ?? '')));
$message = trim((string)($_GET['message'] ?? ''));
$allowedStatus = ['success', 'danger', 'warning', 'info'];
$departmentOptions = ['General', 'HR', 'Finance', 'IT', 'Marketing', 'Operations'];

if (!in_array($status, $allowedStatus, true)) {
    $status = '';
}

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
        <?php if ($status !== '' && $message !== ''): ?>
          <div class="alert alert-<?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?> border-0 bg-<?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?> alert-dismissible fade show">
            <div class="text-white"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endif; ?>

        <div class="card mb-4">
          <div class="card-body">
            <h6 class="mb-3">Add User</h6>
            <form action="create-user.php" method="POST" class="row g-3">
              <div class="col-md-3">
                <label for="name" class="form-label">Name</label>
                <input type="text" id="name" name="name" class="form-control" required>
              </div>
              <div class="col-md-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" id="email" name="email" class="form-control" required>
              </div>
              <div class="col-md-2">
                <label for="password" class="form-label">Password</label>
                <input type="password" id="password" name="password" class="form-control" minlength="6" required>
              </div>
              <div class="col-md-2">
                <label for="role" class="form-label">Role</label>
                <select id="role" name="role" class="form-select">
                  <option value="user">User</option>
                  <option value="admin">Admin</option>
                </select>
              </div>
              <div class="col-md-2">
                <label for="department" class="form-label">Department</label>
                <select id="department" name="department" class="form-select">
                  <?php foreach ($departmentOptions as $dept): ?>
                    <option value="<?php echo htmlspecialchars($dept, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $dept === 'General' ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($dept, ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
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
                      <td><?php echo (int)$row['id_users']; ?></td>
                      <td><?php echo htmlspecialchars((string)$row['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                      <td><?php echo htmlspecialchars((string)$row['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                      <td>
                        <form action="update-user.php" method="POST" class="d-flex flex-column flex-md-row gap-2 align-items-stretch">
                          <input type="hidden" name="user_id" value="<?php echo (int)$row['id_users']; ?>">
                          <?php echo htmlspecialchars(ucfirst(strtolower((string)$row['role'])), ENT_QUOTES, 'UTF-8'); ?>
                      </td>
                      <td>
                          <?php
                            $currentDepartment = trim((string)$row['department']);
                            $isCustomDepartment = !in_array($currentDepartment, $departmentOptions, true);
                          ?>
                          <select class="form-select" name="department">
                            <?php if ($isCustomDepartment && $currentDepartment !== ''): ?>
                              <option value="<?php echo htmlspecialchars($currentDepartment, ENT_QUOTES, 'UTF-8'); ?>" selected>
                                Custom: <?php echo htmlspecialchars($currentDepartment, ENT_QUOTES, 'UTF-8'); ?>
                              </option>
                            <?php endif; ?>
                            <?php foreach ($departmentOptions as $dept): ?>
                              <option value="<?php echo htmlspecialchars($dept, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $currentDepartment === $dept ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept, ENT_QUOTES, 'UTF-8'); ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                      </td>
                      <td>
                          <button type="submit" class="btn btn-warning btn-sm">Update</button>
                        </form>
                        <form action="delete-user.php" method="POST" class="d-inline-block mt-2" onsubmit="return confirm('Are you sure you want to delete this user?');">
                          <input type="hidden" name="user_id" value="<?php echo (int)$row['id_users']; ?>">
                          <button type="submit" class="btn btn-danger btn-sm">Delete</button>
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
