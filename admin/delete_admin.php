<?php
session_start();
require_once '../config.php';

// Block unauthorized access
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: login.php');
    exit;
}

// Ensure only super admins can delete
if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'super') {
    $_SESSION['error'] = "You don't have permission to delete admins.";
    header("Location: admin_users.php");
    exit;
}

$adminIdToDelete = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Prevent deleting yourself
if ($adminIdToDelete === $_SESSION['admin_id']) {
    $_SESSION['error'] = "You cannot delete your own account.";
    header("Location: admin_users.php");
    exit;
}

// Get admin username (for logging and email)
$stmt = $conn->prepare("SELECT username FROM admin_users WHERE admin_id = ?");
if (!$stmt) {
    $_SESSION['error'] = "Database error: " . $conn->error;
    header("Location: admin_users.php");
    exit;
}
$stmt->bind_param("i", $adminIdToDelete);
$stmt->execute();
$stmt->bind_result($usernameToDelete);
if ($stmt->fetch()) {
    $stmt->close();

    // Delete admin
    $delStmt = $conn->prepare("DELETE FROM admin_users WHERE admin_id = ?");
    if (!$delStmt) {
        $_SESSION['error'] = "Database error: " . $conn->error;
        header("Location: admin_users.php");
        exit;
    }
    $delStmt->bind_param("i", $adminIdToDelete);
    if ($delStmt->execute()) {
        $delStmt->close();

        // Log the action into admin_activity_logs table
        $logStmt = $conn->prepare("INSERT INTO admin_activity_logs (admin_id, action) VALUES (?, ?)");
        if ($logStmt) {
            $action = "Deleted admin '$usernameToDelete'";
            $logStmt->bind_param("is", $_SESSION['admin_id'], $action);
            $logStmt->execute();
            $logStmt->close();
        }

        // Send notification email
        require_once '../email_config.php';
        require_once '../send_mail.php';
        sendEmail(
            $mailer_username,
            "Admin Deleted",
            "<p>Admin <strong>$usernameToDelete</strong> was deleted by <strong>{$_SESSION['admin_username']}</strong>.</p>"
        );

        $_SESSION['success'] = "Admin '$usernameToDelete' has been deleted.";
    } else {
        $_SESSION['error'] = "Failed to delete the admin: " . $delStmt->error;
        $delStmt->close();
    }
} else {
    $stmt->close();
    $_SESSION['error'] = "Admin not found.";
}

header("Location: admin_users.php");
exit;