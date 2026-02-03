<?php
session_start();
require_once '../config.php';
require_once 'admin_auth.php';

if (!isset($_POST['action'], $_POST['product_ids']) || !is_array($_POST['product_ids'])) {
    http_response_code(400);
    exit('Invalid request.');
}

$action = $_POST['action'];
$product_ids = array_map('intval', $_POST['product_ids']);
$admin_id = $_SESSION['admin_id'];

$placeholders = implode(',', array_fill(0, count($product_ids), '?'));
$types = str_repeat('i', count($product_ids));

// Logging helper
function log_admin_action($conn, $admin_id, $message) {
    $stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action, created_at) VALUES (?, ?, NOW())");
    $stmt->bind_param("is", $admin_id, $message);
    $stmt->execute();
    $stmt->close();
}

if ($action === 'suspend' || $action === 'activate') {
    $new_status = ($action === 'suspend') ? 'suspended' : 'active';
    $stmt = $conn->prepare("UPDATE products SET status = ? WHERE id IN ($placeholders)");
    $stmt->bind_param('s' . $types, $new_status, ...$product_ids);
    if ($stmt->execute()) {
        log_admin_action($conn, $admin_id, "Bulk {$new_status} action on products: " . implode(',', $product_ids));
        echo "Products {$new_status} successfully.";
    } else {
        http_response_code(500);
        echo "Error updating product status.";
    }
    $stmt->close();

} elseif ($action === 'delete') {
    // Delete product records
    $stmt = $conn->prepare("DELETE FROM products WHERE id IN ($placeholders)");
    $stmt->bind_param($types, ...$product_ids);
    if ($stmt->execute()) {
        log_admin_action($conn, $admin_id, "Bulk deleted products: " . implode(',', $product_ids));
        echo "Products deleted successfully.";
    } else {
        http_response_code(500);
        echo "Error deleting products.";
    }
    $stmt->close();

} else {
    http_response_code(400);
    echo "Invalid action.";
}