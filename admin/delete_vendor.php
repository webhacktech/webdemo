<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../config.php';

// Only allow access if admin is logged in
if (!isset($_SESSION['is_admin']) || !isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$admin_id = $_SESSION['admin_id'];
$vendor_id = intval($_GET['id'] ?? 0);

// Validate vendor ID
if ($vendor_id > 0) {
    // Get vendor store name (for logging)
    $stmt = $conn->prepare("SELECT store_name FROM vendors WHERE id = ?");
    $stmt->bind_param("i", $vendor_id);
    $stmt->execute();
    $stmt->bind_result($store_name);
    if ($stmt->fetch()) {
        $stmt->close();

        // Optional: Delete vendor-related data first if you don't use ON DELETE CASCADE
        $conn->query("DELETE FROM products WHERE vendor_id = $vendor_id");
        $conn->query("DELETE FROM orders WHERE vendor_id = $vendor_id");
        $conn->query("DELETE FROM withdrawals WHERE vendor_id = $vendor_id");
        $conn->query("DELETE FROM vendor_upgrades WHERE vendor_id = $vendor_id");
        $conn->query("DELETE FROM vendor_referrals WHERE referrer_vendor_id = $vendor_id OR referred_vendor_id = $vendor_id");

        // Delete the vendor
        $delete = $conn->prepare("DELETE FROM vendors WHERE id = ?");
        $delete->bind_param("i", $vendor_id);
        $delete->execute();
        $delete->close();

        // Log action
        $action = "Deleted vendor: " . $store_name;
        $log = $conn->prepare("INSERT INTO admin_logs (admin_id, action, created_at) VALUES (?, ?, NOW())");
        $log->bind_param("is", $admin_id, $action);
        $log->execute();
        $log->close();

        $_SESSION['message'] = "✅ Vendor '$store_name' deleted successfully.";
    } else {
        $_SESSION['message'] = "❌ Vendor not found.";
    }
} else {
    $_SESSION['message'] = "❌ Invalid vendor ID.";
}

header("Location: admin_vendors.php");
exit;