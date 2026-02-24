<?php
require_once __DIR__ . '/../config/db.php';  // kalau memang butuh koneksi DB

session_start();
session_unset();
session_destroy();

// naik 1 folder dari /admin ke /UASS lalu ke account-login.php
header('Location: ../account-login.php');
exit;