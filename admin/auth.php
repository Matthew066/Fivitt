<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function normalize_admin_role(?string $role): string
{
    $role = strtolower(trim((string) $role));
    if ($role === 'manajerial') {
        return 'managerial';
    }
    if ($role === 'hrd') {
        return 'hr';
    }

    return $role;
}

function redirect_to_login(): void
{
    header('Location: ../login.php');
    exit();
}

function redirect_to_admin_dashboard(): void
{
    header('Location: index.php');
    exit();
}

if (!isset($_SESSION['user_id'])) {
    redirect_to_login();
}

$sessionRole = normalize_admin_role($_SESSION['user_role'] ?? '');
$_SESSION['user_role'] = $sessionRole;

$dashboardOnlyAdminRoles = ['managerial', 'hr'];

if ($sessionRole !== 'admin' && !in_array($sessionRole, $dashboardOnlyAdminRoles, true)) {
    redirect_to_login();
}

$currentAdminPage = basename((string) ($_SERVER['PHP_SELF'] ?? ''));
$dashboardOnlyAllowedPages = ['index.php'];
$canAccessAllAdminPages = $sessionRole === 'admin';
$canAccessCurrentAdminPage = $canAccessAllAdminPages || in_array($currentAdminPage, $dashboardOnlyAllowedPages, true);

if (!$canAccessCurrentAdminPage) {
    redirect_to_admin_dashboard();
}
