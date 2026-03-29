<?php
session_start();
require_once 'includes/db.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// alias file for gym booking details to match requested name
$queryString = $_SERVER['QUERY_STRING'] ?? '';
$target = 'gym_detail.php' . ($queryString !== '' ? ('?' . $queryString) : '');
header('Location: ' . $target);
exit;
