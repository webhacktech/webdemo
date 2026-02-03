<?php
session_start();
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.php");
    exit;
}

require_once '../config.php';

function clean_input($data) {
    return trim(htmlspecialchars($data));
}

$paystack_key = clean_input($_POST['paystack_secret_key'] ?? '');
$flutterwave_key = clean_input($_POST['flutterwave_secret_key'] ?? '');
$crypto_wallet = clean_input($_POST['crypto_wallet_address'] ?? '');
$commission = $_POST['platform_commission_percent'] ?? ''; // ✅ fixed name
$threshold = $_POST['withdrawal_threshold'] ?? '';
$usd_to_ngn_rate = $_POST['usd_to_ngn_rate'] ?? '';

// Backend validation
$errors = [];

if ($paystack_key === '') {
    $errors[] = "Paystack Secret Key is required.";
}
if ($flutterwave_key === '') {
    $errors[] = "Flutterwave Secret Key is required.";
}
if ($crypto_wallet === '') {
    $errors[] = "Crypto Wallet Address is required.";
}
if (!is_numeric($commission) || $commission < 0 || $commission > 100) {
    $errors[] = "Platform Commission must be a number between 0 and 100.";
}
if (!is_numeric($threshold) || $threshold < 0) {
    $errors[] = "Withdrawal Threshold must be zero or a positive number.";
}
if (!is_numeric($usd_to_ngn_rate) || $usd_to_ngn_rate <= 0) {
    $errors[] = "USD to NGN rate must be a positive number.";
}

if ($errors) {
    $_SESSION['settings_error'] = implode(' ', $errors);
    header("Location: site_settings.php"); // ✅ fixed redirect
    exit;
}

// Check if settings row exists
$check = $conn->query("SELECT id FROM site_settings WHERE id = 1");
if ($check && $check->num_rows > 0) {
    // Update existing
    $stmt = $conn->prepare("UPDATE site_settings SET paystack_secret_key = ?, flutterwave_secret_key = ?, crypto_wallet_address = ?, platform_commission_percent = ?, withdrawal_threshold = ?, usd_to_ngn_rate = ? WHERE id = 1");
    $stmt->bind_param("sssddd", $paystack_key, $flutterwave_key, $crypto_wallet, $commission, $threshold, $usd_to_ngn_rate);
} else {
    // Insert new
    $stmt = $conn->prepare("INSERT INTO site_settings (id, paystack_secret_key, flutterwave_secret_key, crypto_wallet_address, platform_commission_percent, withdrawal_threshold, usd_to_ngn_rate) VALUES (1, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssddd", $paystack_key, $flutterwave_key, $crypto_wallet, $commission, $threshold, $usd_to_ngn_rate);
}

if ($stmt && $stmt->execute()) {
    $stmt->close();
    $_SESSION['settings_success'] = "✅ Settings updated successfully!";
} else {
    $_SESSION['settings_error'] = "❌ Failed to update settings. Please try again.";
}

header("Location: site_settings.php"); // ✅ fixed redirect
exit;