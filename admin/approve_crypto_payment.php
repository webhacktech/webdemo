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

// Update order status to paid
$update = $conn->prepare("UPDATE orders SET status = 'paid' WHERE id = ?");
$update->bind_param("i", $order_id);
$update->execute();
$update->close();

// Insert transaction record (platform fee 10%)
$total = $order['total_amount'];
$vendor_id = $order['vendor_id'];
$payment_ref = $order['payment_ref'];
$platform_fee = round($total * 0.10, 2);
$vendor_credited = $total - $platform_fee;
$payment_method = 'crypto';

$tx_stmt = $conn->prepare("INSERT INTO transactions (vendor_id, amount_paid, platform_fee, vendor_credited, payment_method, reference, status, details, created_at)
VALUES (?, ?, ?, ?, ?, ?, 'successful', ?, NOW())");

$details = "Crypto payment approved, Order #$order_id";

$tx_stmt->bind_param("ddddsss", $vendor_id, $total, $platform_fee, $vendor_credited, $payment_method, $payment_ref, $details);
$tx_stmt->execute();
$tx_stmt->close();

// Add admin log (using admin_id=1 for now)
$admin_id = 1;
$log_stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action, created_at) VALUES (?, ?, NOW())");
$log_text = "Approved crypto payment Order #$order_id (₦$total)";
$log_stmt->bind_param("is", $admin_id, $log_text);
$log_stmt->execute();
$log_stmt->close();

// TODO: Unlock digital files or notify vendor/customer here (optional)

// Add order notification for dashboard
$conn->query("INSERT INTO order_notifications (vendor_id, order_id, amount, method, created_at) VALUES ($vendor_id, $order_id, $total, 'Crypto', NOW())");

// Redirect back with success
header("Location: admin_crypto_payments.php?msg=Payment approved");
exit;
?>