<?php
session_start();

// If admin was impersonating, clear vendor session
if (!empty($_SESSION['is_admin_impersonating'])) {
    unset($_SESSION['vendor_id']);
    unset($_SESSION['is_admin_impersonating']);
}

// Always keep admin logged in
$_SESSION['is_admin'] = true;

// Redirect back to the main admin dashboard
header("Location: admin_dashboard.php");
exit();