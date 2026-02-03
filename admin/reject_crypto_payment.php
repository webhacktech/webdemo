<?php
session_start();
require 'config.php';

// Add admin auth check here
// Example:
// if (!isset($_SESSION['admin_logged_in'])) {
//    header('Location: admin_login.php');
//    exit;
// }

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['order_id'])) {
    die("Invalid request.");
}

$order_id = intval($_POST['order_id']);

// Get order details
$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND payment_method = 'crypto' AND status = 'pending'");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    die("Order not found or not pending.");
}

// Update order status to rejected
$update = $conn->prepare("UPDATE orders SET status = 'rejected' WHERE id = ?");
$update->bind_param("i", $order_id);
$update->execute();
$update->close();

// Add admin log (using admin_id=1 for now)
$admin_id = 1;
$log_stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action, created_at) VALUES (?, ?, NOW())");
$log_text = "Rejected crypto payment Order #$order_id";
$log_stmt->bind_param("is", $admin_id, $log_text);
$log_stmt->execute();
$log_stmt->close();

// TODO: Notify vendor/customer about rejection (optional)

// Redirect back with message
header("Location: admin_crypto_payments.php?msg=Payment rejected");
exit;
?>