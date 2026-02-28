<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/profile_image.php';

ensure_users_profile_image_schema($pdo);

$adminUserId = (int) ($_SESSION['user_id'] ?? 0);
$adminName = $_SESSION['user_name'] ?? 'Admin';
$adminRole = $_SESSION['user_role'] ?? 'admin';
$adminImage = 'assets/images/avatars/avatar-10.png';

if ($adminUserId > 0) {
    $stmt = $pdo->prepare("SELECT name, role, profile_image FROM users WHERE id_users = ? LIMIT 1");
    $stmt->execute([$adminUserId]);
    $adminRow = $stmt->fetch();
    if ($adminRow) {
        $adminName = $adminRow['name'] ?: $adminName;
        $adminRole = $adminRow['role'] ?: $adminRole;
        if (!empty($adminRow['profile_image'])) {
            $adminImage = '../' . $adminRow['profile_image'];
        }
    }
}
?>
<style>
.profile-dropdown {
    min-width: 260px;
    padding: 8px 0;
    margin-top: 8px !important;
}

.profile-dropdown.dropdown-menu[data-bs-popper] {
    left: 100%;
    right: auto;
    margin-left: 8px;
}

.topbar .navbar .profile-dropdown::after {
    left: 14px;
    right: auto;
}

.profile-upload-wrap {
    padding: 0 12px 8px;
}

.profile-upload-title {
    font-size: 12px;
    font-weight: 600;
    color: #475569;
    margin-bottom: 8px;
}

.profile-upload-row {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
}

.profile-upload-avatar {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    object-fit: cover;
    border: 1px solid #e5e7eb;
}

.profile-file-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 32px;
    padding: 0 10px;
    border-radius: 8px;
    background: #eef7ff;
    color: #1d4ed8;
    border: 1px solid #dbeafe;
    cursor: pointer;
    font-size: 12px;
    font-weight: 600;
}

.profile-file-name {
    font-size: 11px;
    color: #64748b;
    margin-bottom: 8px;
    line-height: 1.2;
}

.profile-upload-btn {
    width: 100%;
    height: 34px;
    border: 0;
    border-radius: 8px;
    background: #32c7d8;
    color: #fff;
    font-weight: 700;
    font-size: 13px;
}

.profile-upload-btn:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}
</style>
<header>
    <div class="topbar d-flex align-items-center">
        <nav class="navbar navbar-expand gap-3">
            <div class="mobile-toggle-menu"><i class='bx bx-menu'></i></div>
            <div class="search-bar flex-grow-1">
                <div class="position-relative search-bar-box">
                    <form>
                        <input type="text" class="form-control search-control" autofocus placeholder="Type to search...">
                        <span class="position-absolute top-50 search-show translate-middle-y"><i class='bx bx-search'></i></span>
                        <span class="position-absolute top-50 search-close translate-middle-y"><i class='bx bx-x'></i></span>
                    </form>
                </div>
            </div>
            <div class="user-box dropdown px-3">
                <a class="d-flex align-items-center nav-link dropdown-toggle dropdown-toggle-nocaret" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <img src="<?php echo htmlspecialchars($adminImage, ENT_QUOTES, 'UTF-8'); ?>" class="user-img" alt="user avatar">
                    <div class="user-info ps-3">
                        <p class="user-name mb-0"><?php echo htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8'); ?></p>
                        <p class="designattion mb-0"><?php echo htmlspecialchars(strtoupper((string) $adminRole), ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                </a>
                <ul class="dropdown-menu dropdown-menu-start profile-dropdown">
                    <li>
                        <form class="profile-upload-wrap" method="post" action="../update_profile_image.php" enctype="multipart/form-data">
                            <div class="profile-upload-title">Foto Profil</div>
                            <div class="profile-upload-row">
                                <img src="<?php echo htmlspecialchars($adminImage, ENT_QUOTES, 'UTF-8'); ?>" class="profile-upload-avatar" alt="Avatar">
                                <label class="profile-file-btn" for="adminProfileImageInput">Pilih File</label>
                            </div>
                            <input id="adminProfileImageInput" type="file" name="profile_image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" hidden required>
                            <div id="adminProfileFileName" class="profile-file-name">Belum ada file dipilih</div>
                            <button id="adminProfileUploadBtn" class="profile-upload-btn" type="submit" disabled>Upload</button>
                        </form>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="logout.php"><i class='bx bx-log-out-circle'></i><span>Logout</span></a></li>
                </ul>
            </div>
        </nav>
    </div>
</header>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var input = document.getElementById('adminProfileImageInput');
    var fileName = document.getElementById('adminProfileFileName');
    var uploadBtn = document.getElementById('adminProfileUploadBtn');
    if (!input || !fileName || !uploadBtn) return;

    input.addEventListener('change', function () {
        if (input.files && input.files.length > 0) {
            fileName.textContent = input.files[0].name;
            uploadBtn.disabled = false;
        } else {
            fileName.textContent = 'Belum ada file dipilih';
            uploadBtn.disabled = true;
        }
    });
});
</script>
