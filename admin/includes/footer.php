<div class="overlay toggle-icon"></div>
		<!--end overlay-->
		<!--Start Back To Top Button--> <a href="javaScript:;" class="back-to-top"><i class='bx bxs-up-arrow-alt'></i></a>
		<!--End Back To Top Button-->
		<footer class="page-footer">
			<p class="mb-0">Fivit &copy; <?php echo date('Y'); ?></p>
		</footer>
	</div>

	<div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-dialog-centered">
			<div class="modal-content" style="border-radius: 16px;">
				<div class="modal-header" style="border-bottom: none;">
					<h5 class="modal-title" id="logoutModalLabel">Konfirmasi Logout</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					Anda yakin ingin logout?
				</div>
				<div class="modal-footer" style="border-top: none;">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 999px;">Batal</button>
					<a href="/Fivitt/logout.php" class="btn btn-danger" style="border-radius: 999px;">Logout</a>
				</div>
			</div>
		</div>
	</div>

	<!--end wrapper-->
	<!-- Bootstrap JS -->
	<script src="assets/js/bootstrap.bundle.min.js"></script>
	<!--plugins-->
	<script src="assets/js/jquery.min.js"></script>
	<script src="assets/plugins/simplebar/js/simplebar.min.js"></script>
	<script src="assets/plugins/metismenu/js/metisMenu.min.js"></script>
	<script src="assets/plugins/perfect-scrollbar/js/perfect-scrollbar.js"></script>
	<script src="assets/plugins/apexcharts-bundle/js/apexcharts.min.js"></script>
	<script src="assets/js/index3.js"></script>
	<script>
		if (typeof PerfectScrollbar !== 'undefined') {
			if (document.querySelector('.best-selling-products')) {
				new PerfectScrollbar('.best-selling-products');
			}
			if (document.querySelector('.recent-reviews')) {
				new PerfectScrollbar('.recent-reviews');
			}
			if (document.querySelector('.support-list')) {
				new PerfectScrollbar('.support-list');
			}
		}
	</script>
	<!--app JS-->
	<script src="assets/js/app.js?v=20260301"></script>
