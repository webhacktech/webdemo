<?php
session_start();
require '../config.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.php");
    exit;
}

$id = $_POST['id'] ?? 0;
$action = $_POST['action'] ?? '';

if (!in_array($action, ['approve', 'reject'])) {
    $_SESSION['message'] = "❌ Invalid action.";
    header("Location: admin_withdrawals.php");
    exit;
}

$status = $action === 'approve' ? 'approved' : 'rejected';

$stmt = $conn->prepare("UPDATE vendor_withdrawals SET status = ? WHERE id = ?");
$stmt->bind_param("si", $status, $id);
$stmt->execute();
$stmt->close();

// Optional: Log action (we’ll add to logs later)
$_SESSION['message'] = "✅ Withdrawal request has been $status.";
header("Location: admin_withdrawals.php");
exit;