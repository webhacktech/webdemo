<?php
// admin_log_helper.php

/**
 * Logs an admin action to the admin_activity_logs table.
 *
 * @param int $admin_id ID of the admin performing the action.
 * @param string $action Short description of the action.
 * @param string $details Optional detailed info.
 * @return bool True on success, false on failure.
 */
function log_admin_action($admin_id, $action, $details = '') {
    global $conn;

    $stmt = $conn->prepare("INSERT INTO admin_activity_logs (admin_id, action, details, created_at) VALUES (?, ?, ?, NOW())");
    if (!$stmt) {
        error_log("Log admin action prepare failed: " . $conn->error);
        return false;
    }

    $stmt->bind_param("iss", $admin_id, $action, $details);
    $exec = $stmt->execute();

    if (!$exec) {
        error_log("Log admin action execute failed: " . $stmt->error);
        $stmt->close();
        return false;
    }

    $stmt->close();
    return true;
}