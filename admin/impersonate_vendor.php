<?php
session_start();
require '../config.php';

if (!isset($_SESSION["is_admin"])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id'])) {
    die("Invalid request");
}

$id = intval($_GET['id']);

// Check if vendor exists
$stmt = $conn->prepare("SELECT id FROM vendors WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Vendor not found.");
}

// Store impersonation session
$_SESSION['vendor_id'] = $id;
$_SESSION['is_admin_impersonating'] = true;

header("Location: ../dashboard.php");
exit();