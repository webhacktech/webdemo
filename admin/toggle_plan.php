<?php
session_start();
require '../config.php';

if (!isset($_SESSION["is_admin"])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id']) || !isset($_GET['plan'])) {
    die("Invalid request");
}

$id = intval($_GET['id']);
$new_plan = $_GET['plan'] === 'premium' ? 'premium' : 'basic';

$stmt = $conn->prepare("UPDATE vendors SET subscription_plan = ? WHERE id = ?");
$stmt->bind_param("si", $new_plan, $id);

if ($stmt->execute()) {
    $_SESSION['flash_message'] = "Vendor plan updated to " . ucfirst($new_plan);
} else {
    $_SESSION['flash_message'] = "Failed to update vendor plan.";
}

header("Location: admin_vendors.php");
exit();