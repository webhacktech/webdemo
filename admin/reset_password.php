<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: login.php');
    exit;
}

$adminId = intval($_GET['id'] ?? 0);

if ($adminId <= 0) {
    $_SESSION['error_msg'] = "Invalid admin ID.";
    header('Location: admin_users.php');
    exit;
}

$newPassword = password_hash('changeme123', PASSWORD_DEFAULT);
$stmt = $conn->prepare("UPDATE admin_users SET password = ? WHERE admin_id = ?");
$stmt->bind_param("si", $newPassword, $adminId);

if ($stmt->execute()) {
    $_SESSION['success_msg'] = "Password reset to 'changeme123'.";
} else {
    $_SESSION['error_msg'] = "Failed to reset password.";
}

$stmt->close();
header('Location: admin_users.php');
exit;