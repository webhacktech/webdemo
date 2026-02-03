<?php
session_start();
if (!isset($_SESSION['is_admin'])) {
    header("Location: login.php");
    exit;
}
require_once '../config.php';

if (!isset($_POST['bulk_action'], $_POST['vendor_ids'])) {
    header("Location: admin_vendor.php");
    exit;
}

$action = $_POST['bulk_action'];
$ids = $_POST['vendor_ids'];

if (!in_array($action, ['suspend', 'activate', 'delete'])) {
    header("Location: admin_vendor.php");
    exit;
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$types = str_repeat('i', count($ids));

if ($action === 'delete') {
    $stmt = $conn->prepare("DELETE FROM vendors WHERE id IN ($placeholders)");
} else {
    $status = $action === 'activate' ? 'active' : 'suspended';
    $stmt = $conn->prepare("UPDATE vendors SET status = ? WHERE id IN ($placeholders)");
    $types = 's' . $types;
    $ids = array_merge([$status], $ids);
}

$stmt->bind_param($types, ...$ids);
$stmt->execute();

header("Location: admin_vendor.php?success=1");
exit;