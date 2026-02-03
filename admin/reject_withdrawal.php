<?php
// admin/reject_withdrawal.php
session_start();
require '../config.php';

if (!isset($_SESSION['is_admin'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: admin_withdrawals.php?error=invalid_id");
    exit();
}

$withdrawal_id = intval($_GET['id']);

$stmt = $conn->prepare("SELECT status FROM vendor_payouts WHERE id = ?");
$stmt->bind_param("i", $withdrawal_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    header("Location: admin_withdrawals.php?error=notfound");
    exit();
}

if (strtolower($row['status']) !== 'pending') {
    header("Location: admin_withdrawals.php?error=already_processed");
    exit();
}

// Only change status — don't touch balances here (there was no deduction on request)
$up = $conn->prepare("UPDATE vendor_payouts SET status = 'rejected' WHERE id = ?");
$up->bind_param("i", $withdrawal_id);
$up->execute();
$up->close();

header("Location: admin_withdrawals.php?rejected=1");
exit();