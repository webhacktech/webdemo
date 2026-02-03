<?php
// product_status_action.php

session_start();
require_once '../config.php';
require_once 'admin_auth.php';

if (!isset($_GET['id']) || !isset($_GET['action'])) {
    $_SESSION['message'] = "Invalid request.";
    header("Location: admin_products.php");
    exit();
}

$product_id = intval($_GET['id']);
$action = $_GET['action'] === 'suspend' ? 'suspend' : 'active';

// Get product name before updating
$stmt = $conn->prepare("SELECT product_name FROM products WHERE id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$stmt->bind_result($product_name);
$stmt->fetch();
$stmt->close();

$update = $conn->prepare("UPDATE products SET status = ? WHERE id = ?");
$update->bind_param("si", $action, $product_id);

if ($update->execute()) {
    $_SESSION['message'] = "Product '{$product_name}' successfully " . ($action === 'suspend' ? "suspended" : "activated") . ".";

    // Log action
    $admin_id = $_SESSION['admin_id'];
    $log_msg = "Admin " . $_SESSION['admin_username'] . " set product '{$product_name}' to status '{$action}'";
    $log = $conn->prepare("INSERT INTO admin_activity_logs (admin_id, action, created_at) VALUES (?, ?, NOW())");
    $log->bind_param("is", $admin_id, $log_msg);
    $log->execute();
    $log->close();
} else {
    $_SESSION['message'] = "Failed to update product status.";
}

$update->close();
header("Location: admin_products.php");
exit;