<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$sessionRole = strtolower(trim((string)($_SESSION['user_role'] ?? '')));
$isDashboardOnlyRole = in_array($sessionRole, ['managerial', 'manajerial', 'hr', 'hrd'], true);

function isActive(array $pages, string $currentPage): string
{
	return in_array($currentPage, $pages, true) ? 'mm-active' : '';
}
?>

<div class="sidebar-wrapper" data-simplebar="true">
	<div class="sidebar-header">
		<div>
			<h4 class="logo-text">Admin Panel</h4>
		</div>
	</div>
	<!--navigation-->
	<ul class="metismenu" id="menu">
		<li class="<?php echo isActive(['index.php'], $currentPage); ?>">
			<a href="index.php">Dashboard</a>
		</li>
		<?php if (!$isDashboardOnlyRole): ?>
			<li class="<?php echo isActive(['datauser.php'], $currentPage); ?>">
				<a href="datauser.php">Users</a>
			</li>
			<li class="<?php echo isActive(['add_gym_tools.php'], $currentPage); ?>">
				<a href="add_gym_tools.php">Gym Tools</a>
			</li>
			<li class="<?php echo isActive(['education_articles.php'], $currentPage); ?>">
				<a href="education_articles.php">Education & Artikel</a>
			</li>
			<li class="<?php echo isActive(['sporteventadmin.php'], $currentPage); ?>">
				<a href="sporteventadmin.php">Sport Events</a>
			</li>
			<li class="<?php echo isActive(['healthy_canteen_admin.php'], $currentPage); ?>">
				<a href="healthy_canteen_admin.php">Healthy Canteen</a>
			</li>
		<?php endif; ?>
		<!-- Logout Button -->
		<li class="<?php echo isActive(['logout.php'], $currentPage); ?>">
			<a href="#" data-bs-toggle="modal" data-bs-target="#logoutModal">
				<i class="bx bx-log-out"></i> Logout
			</a>
		</li>
	</ul>
	<!--end navigation-->
</div>
