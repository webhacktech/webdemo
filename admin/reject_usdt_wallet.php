<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../config.php';

if (!isset($_SESSION['is_admin'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['wallet_msg'] = "❌ Invalid wallet ID.";
    header("Location: admin_usdt_wallets.php");
    exit();
}

$wallet_id = intval($_GET['id']);
$admin_id = intval($_SESSION['admin_id'] ?? 0); // Assuming you store admin ID in session

// Optional: check if wallet exists and is pending before update
$check_stmt = $conn->prepare("SELECT status FROM vendor_wallets WHERE id = ?");
$check_stmt->bind_param("i", $wallet_id);
$check_stmt->execute();
$check_stmt->bind_result($current_status);
if (!$check_stmt->fetch() || $current_status !== 'pending') {
    $_SESSION['wallet_msg'] = "⚠️ Wallet not found or already processed.";
    $check_stmt->close();
    header("Location: admin_usdt_wallets.php");
    exit();
}
$check_stmt->close();

// Update wallet status and add reviewed_at and reviewer_admin_id
$stmt = $conn->prepare("UPDATE vendor_wallets SET status = 'rejected', reviewed_at = NOW(), reviewer_admin_id = ? WHERE id = ?");
$stmt->bind_param("ii", $admin_id, $wallet_id);
if ($stmt->execute()) {
    $_SESSION['wallet_msg'] = "🚫 Wallet rejected.";
} else {
    $_SESSION['wallet_msg'] = "❌ Failed to reject wallet.";
}
$stmt->close();

header("Location: admin_usdt_wallets.php");
exit();
?>