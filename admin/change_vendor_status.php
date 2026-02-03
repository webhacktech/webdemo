<?php
session_start();
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: login.php');
    exit;
}

require_once '../config.php'; // adjust path if needed

$id = intval($_GET['id'] ?? 0);
$action = $_GET['action'] ?? '';

if ($id > 0 && in_array($action, ['suspend', 'activate'])) {
    $new_status = ($action === 'suspend') ? 'suspended' : 'active';

    $stmt = $conn->prepare("UPDATE vendors SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $id);
    $stmt->execute();
    $stmt->close();
}

header("Location: admin_vendors.php");
exit;