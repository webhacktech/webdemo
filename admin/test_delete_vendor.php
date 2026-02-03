<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../config.php';

// CHANGE THIS to the ID of a vendor that should be deleted
$vendor_id = 20;

echo "<h3>Deleting Vendor ID: $vendor_id</h3>";

// Step 1: Check if vendor exists
$stmt = $conn->prepare("SELECT store_name FROM vendors WHERE id = ?");
$stmt->bind_param("i", $vendor_id);
$stmt->execute();
$stmt->bind_result($store_name);
if ($stmt->fetch()) {
    echo "✅ Vendor found: $store_name<br>";
} else {
    die("❌ Vendor not found.");
}
$stmt->close();

// Step 2: Try deleting vendor directly
$delete = $conn->prepare("DELETE FROM vendors WHERE id = ?");
$delete->bind_param("i", $vendor_id);
$delete->execute();

if ($delete->affected_rows > 0) {
    echo "✅ Vendor deleted successfully.";
} else {
    echo "❌ Vendor not deleted. MySQL error: " . $conn->error;
}
$delete->close();