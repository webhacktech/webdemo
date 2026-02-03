<?php
session_start();
require '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['upgrade_id'])) {
    die("Invalid request.");
}

$upgrade_id = intval($_POST['upgrade_id']);

// Fetch upgrade request
$stmt = $conn->prepare("SELECT * FROM vendor_premium_upgrades WHERE id = ? AND status = 'pending'");
$stmt->bind_param("i", $upgrade_id);
$stmt->execute();
$upgrade = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$upgrade) {
    die("Upgrade request not found or already processed.");
}

$vendor_id = $upgrade['vendor_id'];

// 1. Reject upgrade request
$update = $conn->prepare("UPDATE vendor_premium_upgrades SET status = 'rejected', rejected_at = NOW() WHERE id = ?");
$update->bind_param("i", $upgrade_id);
$update->execute();
$update->close();

// 2. Log admin action
$admin_id = 1; // Replace with $_SESSION['admin_id'] if using login sessions
$log = $conn->prepare("INSERT INTO admin_logs (admin_id, action, created_at) VALUES (?, ?, NOW())");
$action = "Rejected premium upgrade for vendor ID $vendor_id";
$log->bind_param("is", $admin_id, $action);
$log->execute();
$log->close();

// 3. Optional: notify vendor about rejection (future)

// Redirect back
header("Location: admin_premium_upgrades.php?msg=Upgrade rejected");
exit;