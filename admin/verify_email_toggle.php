<?php
session_start();
require '../config.php';

if (!isset($_SESSION['is_admin'])) {
    die("Unauthorized access.");
}

$vendor_id = $_POST['vendor_id'] ?? null;
$action = $_POST['action'] ?? null;

if (!$vendor_id || !in_array($action, ['verify', 'unverify'])) {
    die("Invalid request.");
}

$email_verified = $action === 'verify' ? 1 : 0;

// Update vendor verification status
$stmt = $conn->prepare("UPDATE vendors SET email_verified = ?, email_verified_at = NOW() WHERE id = ?");
$stmt->bind_param("ii", $email_verified, $vendor_id);
$stmt->execute();

header("Location: view_vendor.php?id=" . $vendor_id);
exit;