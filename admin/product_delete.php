<?php
// product_delete.php

session_start();
require_once '../config.php';
require_once 'admin_auth.php';

if (!isset($_GET['id'])) {
    $_SESSION['message'] = "Invalid product ID.";
    header("Location: admin_products.php");
    exit();
}

$product_id = intval($_GET['id']);

// Get product name before deleting
$stmt = $conn->prepare("SELECT product_name FROM products WHERE id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$stmt->bind_result($product_name);
$stmt->fetch();
$stmt->close();

$delete = $conn->prepare("DELETE FROM products WHERE id = ?");
$delete->bind_param("i", $product_id);

if ($delete->execute()) {
    $_SESSION['message'] = "Product '{$product_name}' deleted successfully.";

    // Log action
    $admin_id = $_SESSION['admin_id'];
    $log_msg = "Admin " . $_SESSION['admin_username'] . " deleted product '{$product_name}'";
    $log = $conn->prepare("INSERT INTO admin_activity_logs (admin_id, action, created_at) VALUES (?, ?, NOW())");
    $log->bind_param("is", $admin_id, $log_msg);
    $log->execute();
    $log->close();
} else {
    $_SESSION['message'] = "Failed to delete product.";
}

$delete->close();
header("Location: admin_products.php");
exit;