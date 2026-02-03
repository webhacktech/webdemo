<?php
session_start();

require_once '../config.php';

// Redirect if not admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: login.php');
    exit;
}

function redirectBack($success = false, $msg = '') {
    if ($success) {
        $_SESSION['profile_success'] = $msg;
    } else {
        $_SESSION['profile_error'] = $msg;
    }
    header('Location: admin_dashboard.php');
    exit;
}

// Get admin ID from session
$adminId = $_SESSION['admin_id'] ?? null;

if (!$adminId) {
    redirectBack(false, "Admin session missing.");
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newUsername = trim($_POST['username'] ?? '');
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($newUsername === '') {
        redirectBack(false, "Username cannot be empty.");
    }

    // Fetch current admin from DB
    $stmt = $conn->prepare("SELECT username, password FROM admin_users WHERE admin_id = ?");
    $stmt->bind_param("i", $adminId);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        redirectBack(false, "Admin not found.");
    }

    $stmt->bind_result($currentUsername, $currentPasswordHash);
    $stmt->fetch();
    $stmt->close();

    // Verify current password
    if (!password_verify($currentPassword, $currentPasswordHash)) {
        redirectBack(false, "Current password is incorrect.");
    }

    // Update logic
    $updateQuery = "UPDATE admin_users SET username = ?";
    $types = "s";
    $params = [$newUsername];

    if (!empty($newPassword)) {
        if (strlen($newPassword) < 8) {
            redirectBack(false, "New password must be at least 8 characters.");
        }
        if ($newPassword !== $confirmPassword) {
            redirectBack(false, "New passwords do not match.");
        }

        $hashedNewPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $updateQuery .= ", password = ?";
        $types .= "s";
        $params[] = $hashedNewPassword;
    }

    $updateQuery .= " WHERE admin_id = ?";
    $types .= "i";
    $params[] = $adminId;

    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param($types, ...$params);
    if ($stmt->execute()) {
        $_SESSION['admin_username'] = $newUsername;
        redirectBack(true, "Profile updated successfully.");
    } else {
        redirectBack(false, "Update failed. Please try again.");
    }
} else {
    redirectBack(false, "Invalid request.");
}
?>