<?php
require_once __DIR__ . '/auth.php';
require_once '../includes/db.php';

function toInt(mixed $value): int
{
	return (int) $value;
}

$totalUsers = 0;
$totalGymTools = 0;
$totalGymToolTypes = 0;
$totalArticles = 0;
$topEquipmentRows = [];
$topUserRows = [];
$topFoodRows = [];
$busiestDayLabel = '-';
$busiestDayCount = 0;

$dayMap = [
	'Monday' => 'Senin',
	'Tuesday' => 'Selasa',
	'Wednesday' => 'Rabu',
	'Thursday' => 'Kamis',
	'Friday' => 'Jumat',
	'Saturday' => 'Sabtu',
	'Sunday' => 'Minggu',
];

try {
	$totalUsers = toInt($pdo->query("SELECT COUNT(*) FROM users")->fetchColumn());
} catch (Throwable $e) {
	$totalUsers = 0;
}

try {
	$toolTotals = $pdo->query("
		SELECT COUNT(*) AS total_types, COALESCE(SUM(quantity), 0) AS total_tools
		FROM gym_equipments
	")->fetch();
	$totalGymToolTypes = toInt($toolTotals['total_types'] ?? 0);
	$totalGymTools = toInt($toolTotals['total_tools'] ?? 0);
} catch (Throwable $e) {
	$totalGymToolTypes = 0;
	$totalGymTools = 0;
}

try {
	$totalArticles = toInt($pdo->query("SELECT COUNT(*) FROM articles")->fetchColumn());
} catch (Throwable $e) {
	$totalArticles = 0;
}

try {
	$topFoodRows = $pdo->query("
		SELECT
			COALESCE(NULLIF(TRIM(f.name), ''), CONCAT('Food #', fl.food_id)) AS food_name,
			COUNT(*) AS total_orders
		FROM food_logs fl
		LEFT JOIN foods f ON f.id_foods = fl.food_id
		GROUP BY fl.food_id, f.name
		ORDER BY total_orders DESC, food_name ASC
		LIMIT 7
	")->fetchAll();
} catch (Throwable $e) {
	$topFoodRows = [];
}

try {
	$topEquipmentRows = $pdo->query("
		SELECT
			COALESCE(NULLIF(TRIM(ge.equipment_name), ''), CONCAT('Alat #', gb.equipment_id)) AS equipment_name,
			COUNT(*) AS total_bookings
		FROM gym_bookings gb
		LEFT JOIN gym_equipments ge ON ge.id_gym_equipments = gb.equipment_id
		GROUP BY gb.equipment_id, ge.equipment_name
		ORDER BY total_bookings DESC, equipment_name ASC
		LIMIT 7
	")->fetchAll();
} catch (Throwable $e) {
	$topEquipmentRows = [];
}

try {
	$topUserRows = $pdo->query("
		SELECT
			COALESCE(NULLIF(TRIM(u.name), ''), CONCAT('User #', gb.user_id)) AS user_name,
			COUNT(*) AS total_bookings
		FROM gym_bookings gb
		LEFT JOIN users u ON u.id_users = gb.user_id
		GROUP BY gb.user_id, u.name
		ORDER BY total_bookings DESC, user_name ASC
		LIMIT 7
	")->fetchAll();
} catch (Throwable $e) {
	$topUserRows = [];
}

try {
	$busiestDayRow = $pdo->query("
		SELECT DAYNAME(booking_date) AS day_name, COUNT(*) AS total_bookings
		FROM gym_bookings
		WHERE booking_date IS NOT NULL
		GROUP BY DAYNAME(booking_date), DAYOFWEEK(booking_date)
		ORDER BY total_bookings DESC, DAYOFWEEK(booking_date) ASC
		LIMIT 1
	")->fetch();

	if ($busiestDayRow) {
		$dayName = (string) ($busiestDayRow['day_name'] ?? '');
		$busiestDayLabel = $dayMap[$dayName] ?? $dayName;
		$busiestDayCount = toInt($busiestDayRow['total_bookings'] ?? 0);
	}
} catch (Throwable $e) {
	$busiestDayLabel = '-';
	$busiestDayCount = 0;
}

$equipmentLabels = [];
$equipmentCounts = [];
foreach ($topEquipmentRows as $row) {
	$equipmentLabels[] = (string) ($row['equipment_name'] ?? '-');
	$equipmentCounts[] = toInt($row['total_bookings'] ?? 0);
}

$userLabels = [];
$userCounts = [];
foreach ($topUserRows as $row) {
	$userLabels[] = (string) ($row['user_name'] ?? '-');
	$userCounts[] = toInt($row['total_bookings'] ?? 0);
}

$foodLabels = [];
$foodCounts = [];
foreach ($topFoodRows as $row) {
	$foodLabels[] = (string) ($row['food_name'] ?? '-');
	$foodCounts[] = toInt($row['total_orders'] ?? 0);
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
				<div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
					<div class="breadcrumb-title pe-3">Dashboard</div>
				</div>

				<div class="row g-3 mb-4">
					<div class="col-12 col-md-6 col-xl-3">
						<div class="card radius-10 h-100 bg-gradient-Ohhappiness">
							<div class="card-body">
								<p class="mb-1 text-white">Total Users</p>
								<h4 class="mb-0 text-white"><?php echo $totalUsers; ?></h4>
								<small class="text-white-50">Akun pengguna terdaftar</small>
							</div>
						</div>
					</div>
					<div class="col-12 col-md-6 col-xl-3">
						<div class="card radius-10 h-100">
							<div class="card-body">
								<p class="mb-1 text-secondary">Total Alat Gym</p>
								<h4 class="mb-0"><?php echo $totalGymTools; ?></h4>
								<small class="text-muted"><?php echo $totalGymToolTypes; ?> jenis alat</small>
							</div>
						</div>
					</div>
					<div class="col-12 col-md-6 col-xl-3">
						<div class="card radius-10 h-100">
							<div class="card-body">
								<p class="mb-1 text-secondary">Total Artikel</p>
								<h4 class="mb-0"><?php echo $totalArticles; ?></h4>
								<small class="text-muted">Artikel tersimpan di sistem</small>
							</div>
						</div>
					</div>
					<div class="col-12 col-md-6 col-xl-3">
						<div class="card radius-10 h-100">
							<div class="card-body">
								<p class="mb-1 text-secondary">Hari Booking Teramai</p>
								<h4 class="mb-0"><?php echo htmlspecialchars($busiestDayLabel); ?></h4>
								<small class="text-muted"><?php echo $busiestDayCount; ?> pemesanan</small>
							</div>
						</div>
					</div>
				</div>

				<div class="row g-3">
					<div class="col-12 col-xl-6">
						<div class="card radius-10 h-100">
							<div class="card-body">
								<div class="d-flex align-items-center justify-content-between mb-3">
									<h6 class="mb-0">Alat Gym Paling Banyak Dipesan</h6>
									<small class="text-muted">Top 7 alat</small>
								</div>
								<div id="equipmentBookingChart" style="min-height: 320px;"></div>
							</div>
						</div>
					</div>
					<div class="col-12 col-xl-6">
						<div class="card radius-10 h-100">
							<div class="card-body">
								<div class="d-flex align-items-center justify-content-between mb-3">
									<h6 class="mb-0">User Paling Sering Booking Alat Gym</h6>
									<small class="text-muted">Top 7 user</small>
								</div>
								<div id="userBookingChart" style="min-height: 320px;"></div>
							</div>
						</div>
					</div>
					<div class="col-12">
						<div class="card radius-10">
							<div class="card-body">
								<div class="d-flex align-items-center justify-content-between mb-3">
									<h6 class="mb-0">Menu Makanan Paling Banyak Dipesan</h6>
									<small class="text-muted">Top 7 Menu Makanan</small>
								</div>
								<div id="foodOrderChart" style="min-height: 320px;"></div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php include 'includes/footer.php'; ?>
		<script>
			(function () {
				if (typeof ApexCharts === 'undefined') {
					return;
				}

				const equipmentLabels = <?php echo json_encode($equipmentLabels, JSON_UNESCAPED_UNICODE); ?>;
				const equipmentCounts = <?php echo json_encode($equipmentCounts, JSON_UNESCAPED_UNICODE); ?>;
				const userLabels = <?php echo json_encode($userLabels, JSON_UNESCAPED_UNICODE); ?>;
				const userCounts = <?php echo json_encode($userCounts, JSON_UNESCAPED_UNICODE); ?>;
				const foodLabels = <?php echo json_encode($foodLabels, JSON_UNESCAPED_UNICODE); ?>;
				const foodCounts = <?php echo json_encode($foodCounts, JSON_UNESCAPED_UNICODE); ?>;

				const safeEquipmentLabels = equipmentLabels.length ? equipmentLabels : ['Belum ada booking'];
				const safeEquipmentCounts = equipmentCounts.length ? equipmentCounts : [0];
				const safeUserLabels = userLabels.length ? userLabels : ['Belum ada booking'];
				const safeUserCounts = userCounts.length ? userCounts : [0];
				const safeFoodLabels = foodLabels.length ? foodLabels : ['Belum ada pesanan'];
				const safeFoodCounts = foodCounts.length ? foodCounts : [0];

				new ApexCharts(document.querySelector('#equipmentBookingChart'), {
					chart: { type: 'bar', height: 320, toolbar: { show: false } },
					series: [{ name: 'Jumlah Booking', data: safeEquipmentCounts }],
					xaxis: { categories: safeEquipmentLabels },
					colors: ['#32C7D8'],
					dataLabels: { enabled: false },
					plotOptions: {
						bar: {
							borderRadius: 4,
							distributed: false,
							horizontal: false
						}
					},
					grid: { borderColor: 'rgba(0,0,0,0.08)', strokeDashArray: 4 },
					yaxis: { title: { text: 'Jumlah Booking' } }
				}).render();

				new ApexCharts(document.querySelector('#userBookingChart'), {
					chart: { type: 'bar', height: 320, toolbar: { show: false } },
					series: [{ name: 'Jumlah Booking', data: safeUserCounts }],
					xaxis: { categories: safeUserLabels },
					colors: ['#00B894'],
					dataLabels: { enabled: false },
					plotOptions: {
						bar: {
							borderRadius: 4,
							horizontal: true
						}
					},
					grid: { borderColor: 'rgba(0,0,0,0.08)', strokeDashArray: 4 },
					xaxis: {
						categories: safeUserLabels,
						title: { text: 'Jumlah Booking' }
					}
				}).render();

				new ApexCharts(document.querySelector('#foodOrderChart'), {
					chart: { type: 'bar', height: 320, toolbar: { show: false } },
					series: [{ name: 'Jumlah Pesanan', data: safeFoodCounts }],
					xaxis: {
						categories: safeFoodLabels,
						title: { text: 'Jumlah Pesanan' }
					},
					colors: ['#F59E0B'],
					dataLabels: { enabled: false },
					plotOptions: {
						bar: {
							borderRadius: 4,
							horizontal: true
						}
					},
					grid: { borderColor: 'rgba(0,0,0,0.08)', strokeDashArray: 4 }
				}).render();
			})();
		</script>
</body>

</html>
