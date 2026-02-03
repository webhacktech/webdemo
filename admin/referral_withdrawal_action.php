<?php
session_start();
require_once '../config.php';
require_once 'admin_auth.php';
require_once '../email_config.php'; // for sendEmail()

if (!isset($_POST['id'], $_POST['action'])) {
    header("Location: admin_referral_withdrawals.php");
    exit();
}

$withdrawal_id = intval($_POST['id']);
$action = $_POST['action'];
$admin_id = $_SESSION['admin_id'] ?? 0;
$comment = trim($_POST['comment'] ?? '');

if (!in_array($action, ['approve', 'reject'])) {
    header("Location: admin_referral_withdrawals.php");
    exit();
}

// Fetch vendor & withdrawal info
$stmt = $conn->prepare("SELECT rw.vendor_id, rw.amount, rw.status, v.store_name, v.email FROM referral_withdrawals rw JOIN vendors v ON rw.vendor_id = v.id WHERE rw.id = ?");
$stmt->bind_param("i", $withdrawal_id);
$stmt->execute();
$stmt->bind_result($vendor_id, $amount, $current_status, $store_name, $vendor_email);
if (!$stmt->fetch()) {
    $stmt->close();
    $_SESSION['error'] = "Withdrawal not found.";
    header("Location: admin_referral_withdrawals.php");
    exit();
}
$stmt->close();

if ($current_status !== 'pending') {
    $_SESSION['error'] = "This withdrawal has already been processed.";
    header("Location: admin_referral_withdrawals.php");
    exit();
}

// Process action
if ($action === 'approve') {
    $update = $conn->prepare("UPDATE referral_withdrawals SET status = 'approved', reviewed_at = NOW() WHERE id = ?");
    $update->bind_param("i", $withdrawal_id);
    $update->execute();
    $update->close();

    // Log admin action
    $log = $conn->prepare("INSERT INTO admin_logs (admin_id, action, created_at) VALUES (?, ?, NOW())");
    $action_text = "Approved referral withdrawal of ₦$amount for vendor '$store_name'";
    $log->bind_param("is", $admin_id, $action_text);
    $log->execute();
    $log->close();

    // Send email
    $subject = "✅ Referral Withdrawal Approved";
    $message = "Dear $store_name,<br><br>Your referral withdrawal request of <strong>₦" . number_format($amount, 2) . "</strong> has been approved. You will receive your funds shortly.<br><br>Thanks,<br>Sellevo Team";
    sendEmail($vendor_email, $subject, $message);

    $_SESSION['message'] = "Withdrawal approved successfully.";
} else {
    // Reject
    if (empty($comment)) {
        $_SESSION['error'] = "Rejection reason is required.";
        header("Location: admin_referral_withdrawals.php");
        exit();
    }

    $reject = $conn->prepare("UPDATE referral_withdrawals SET status = 'rejected', reviewed_at = NOW() WHERE id = ?");
    $reject->bind_param("i", $withdrawal_id);
    $reject->execute();
    $reject->close();

    // Save comment
    $com = $conn->prepare("INSERT INTO referral_withdrawal_comments (withdrawal_id, comment, created_at) VALUES (?, ?, NOW())");
    $com->bind_param("is", $withdrawal_id, $comment);
    $com->execute();
    $com->close();

    // Log admin action
    $log = $conn->prepare("INSERT INTO admin_logs (admin_id, action, created_at) VALUES (?, ?, NOW())");
    $action_text = "Rejected referral withdrawal of ₦$amount for vendor '$store_name'. Reason: $comment";
    $log->bind_param("is", $admin_id, $action_text);
    $log->execute();
    $log->close();

    // Send rejection email
    $subject = "❌ Referral Withdrawal Rejected";
    $message = "Dear $store_name,<br><br>Your referral withdrawal request of <strong>₦" . number_format($amount, 2) . "</strong> was rejected for the following reason:<br><br><em>$comment</em><br><br>Please contact support if you have any questions.<br><br>Thanks,<br>Sellevo Team";
    sendEmail($vendor_email, $subject, $message);

    $_SESSION['message'] = "Withdrawal rejected successfully.";
}

header("Location: admin_referral_withdrawals.php");
exit();