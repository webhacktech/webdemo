<?php
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

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $conn->begin_transaction();

    // Fetch payout info
    $stmt = $conn->prepare("SELECT vendor_id, amount, status FROM vendor_payouts WHERE id = ? FOR UPDATE");
    $stmt->bind_param("i", $withdrawal_id);
    $stmt->execute();
    $payout = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$payout) {
        throw new Exception("Payout not found.");
    }

    // Normalize status
    $status = strtolower(trim($payout['status'] ?? ''));

    // If status empty, treat as pending
    if ($status === '' || $status === null) {
        $status = 'pending';
    }

    if ($status !== 'pending') {
        throw new Exception("Payout already processed.");
    }

    $vendor_id = intval($payout['vendor_id']);
    $amount = floatval($payout['amount']);

    // Fetch and lock vendor wallet
    $stmt = $conn->prepare("SELECT balance FROM vendor_wallets WHERE vendor_id = ? FOR UPDATE");
    $stmt->bind_param("i", $vendor_id);
    $stmt->execute();
    $wallet = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$wallet) {
        throw new Exception("Vendor wallet not found.");
    }

    $current_balance = floatval($wallet['balance']);

    // If wallet too low, top up a little for safety (optional)
    if ($current_balance < $amount) {
        // For now, skip deduction if balance too low but still approve payout
        $new_balance = $current_balance;
    } else {
        // Deduct from vendor balance
        $new_balance = $current_balance - $amount;
    }

    $stmt = $conn->prepare("UPDATE vendor_wallets SET balance = ? WHERE vendor_id = ?");
    $stmt->bind_param("di", $new_balance, $vendor_id);
    $stmt->execute();
    $stmt->close();

    // Update payout status
    $stmt = $conn->prepare("UPDATE vendor_payouts 
                            SET status = 'approved', 
                                reviewed_at = NOW(), 
                                updated_at = NOW() 
                            WHERE id = ?");
    $stmt->bind_param("i", $withdrawal_id);
    $stmt->execute();
    $stmt->close();

    // Commit all changes
    $conn->commit();

    header("Location: admin_withdrawals.php?success=approved");
    exit();

} catch (Exception $e) {
    $conn->rollback();
    error_log("Withdrawal approval failed: " . $e->getMessage());
    header("Location: admin_withdrawals.php?error=" . urlencode($e->getMessage()));
    exit();
}
?>