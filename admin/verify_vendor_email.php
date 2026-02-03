<?php
// verify_vendor_email.php

require '../config.php'; // adjust path if needed

if (isset($_GET['vendor_id']) && isset($_GET['action'])) {
    $vendor_id = intval($_GET['vendor_id']);
    $action = $_GET['action'];

    if ($action === 'verify') {
        $stmt = $conn->prepare("UPDATE vendors SET email_verified = 1 WHERE id = ?");
    } elseif ($action === 'unverify') {
        $stmt = $conn->prepare("UPDATE vendors SET email_verified = 0 WHERE id = ?");
    } else {
        die("Invalid action.");
    }

    $stmt->bind_param("i", $vendor_id);
    $stmt->execute();
}

header("Location: admin_vendors.php");
exit;
