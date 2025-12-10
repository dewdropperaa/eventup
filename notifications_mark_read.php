<?php
session_start();
require_once 'role_check.php';
requireLogin();
require_once 'notifications.php';

if (isset($_SESSION['user_id'])) {
    markAllNotificationsRead($_SESSION['user_id']);
}

$redirect = $_SERVER['HTTP_REFERER'] ?? 'dashboard.php';
header('Location: ' . $redirect);
exit;
