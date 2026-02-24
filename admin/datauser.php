<?php
require_once '../includes/db.php';

// Ambil semua data user
$query = "SELECT id_users, name, email, role, department FROM users";
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
                    <tr>
                      <td><?php echo $row['id_users']; ?></td>
                      <td><?php echo $row['name']; ?></td>
                      <td><?php echo $row['email']; ?></td>
                      <td>
                        <select class="form-control" id="role_<?php echo $row['id_users']; ?>" onchange="updateUserRole(<?php echo $row['id_users']; ?>)">
                          <option value="customer" <?php echo $row['role'] == 'user' ? 'selected' : ''; ?>>User</option>
                          <option value="admin" <?php echo $row['role'] == 'admin' ? 'selected' : ''; ?>>Admin</option>
                        </select>
                      </td>
                      <td><?php echo $row['department']; ?></td>
                      <td>
                        <a href="delete-user.php?id_users=<?php echo $row['id_users']; ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this user?')">Delete</a>
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
  <script>
    // Function to update user role
    function updateUserRole(userId) {
      const role = document.getElementById(`role_${userId}`).value;
      
      // Send AJAX request to update role
      fetch('update-user-role.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({ user_id: userId, role: role })
      }).then(response => response.json()).then(data => {
        if (data.success) {
          alert('User role updated successfully!');
        } else {
          alert('Failed to update user role.');
        }
      });
    }
  </script>
</body>
</html>
