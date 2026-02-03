<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['is_admin'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid wallet ID.");
}

$wallet_id = intval($_GET['id']);

// Update wallet status
$stmt = $conn->prepare("UPDATE vendor_wallets SET status = 'approved' WHERE id = ?");
$stmt->bind_param("i", $wallet_id);
$stmt->execute();
$stmt->close();

// Log the action
$admin_id = $_SESSION['admin_id'] ?? 0;
$action = "Approved USDT wallet ID $wallet_id";
$ip = $_SERVER['REMOTE_ADDR'];
$user_agent = $_SERVER['HTTP_USER_AGENT'];

$log = $conn->prepare("INSERT INTO admin_logs (admin_id, action, ip_address, user_agent, log_time) VALUES (?, ?, ?, ?, NOW())");
$log->bind_param("isss", $admin_id, $action, $ip, $user_agent);
$log->execute();
$log->close();

header("Location: admin_wallets.php?success=approved");
exit();
?>