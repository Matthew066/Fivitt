<?php
require_once '../includes/db.php';
$totalUsers = 0;

try {
	$totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
} catch (Throwable $e) {
	$totalUsers = 0;
}
?>

<!doctype html>
<html lang="en">

<?php require 'includes/head.php'; ?>

<body>
	<!--wrapper-->
	<div class="wrapper">
		<!--sidebar wrapper -->
		<?php include 'includes/sidebar.php'; ?>
		<!--end sidebar wrapper -->
		<!--start header -->
		<?php include 'includes/header.php'; ?>
		<!--end header -->
		<!--start page wrapper -->
		<div class="page-wrapper">
			<div class="page-content">
				<!--breadcrumb-->
				<div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
					<div class="breadcrumb-title pe-3">Dashboard</div>
				</div>
				<!--end breadcrumb-->
				<div class="row row-cols-1 row-cols-lg-4">
					<div class="col">
						<div class="card radius-10 overflow-hidden bg-gradient-Ohhappiness">
							<div class="card-body">
								<div class="d-flex align-items-center">
									<div>
										<p class="mb-0 text-white">Total Users</p>
										<h5 class="mb-0 text-white"><?php echo $totalUsers; ?> Users</h5>
									</div>
									<div class="ms-auto text-white"><i class='bx bx-bulb font-30'></i>
									</div>
								</div>
								<div class="progress bg-white-2 radius-10 mt-4" style="height:4.5px;">
									<div class="progress-bar bg-white" role="progressbar" style="width: 68%"></div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<!--end page wrapper -->
		<!--start overlay-->
		<?php include 'includes/footer.php'; ?>
</body>

</html>
