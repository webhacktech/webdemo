<?php
session_start();
if (!isset($_SESSION['is_admin'])) {
    header("Location: login.php");
    exit;
}
require_once '../config.php';

$id = intval($_GET['id'] ?? 0);
$action = $_GET['action'] ?? '';

if ($id > 0 && in_array($action, ['approve', 'reject'])) {
    $newStatus = $action === 'approve' ? 'approved' : 'rejected';
    $stmt = $conn->prepare("UPDATE vendors SET kyc_status = ? WHERE id = ?");
    $stmt->bind_param("si", $newStatus, $id);
    $stmt->execute();
    $stmt->close();
}

header("Location: kyc_review.php");
exit;