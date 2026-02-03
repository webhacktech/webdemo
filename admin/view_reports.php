<?php
session_start();
if (!isset($_SESSION['is_admin'])) {
    header("Location: login.php");
    exit;
}
require_once '../config.php';

$totalVendors = $conn->query("SELECT COUNT(*) AS total FROM vendors")->fetch_assoc()['total'];
$totalProducts = $conn->query("SELECT COUNT(*) AS total FROM products")->fetch_assoc()['total'];
$todayVendors = $conn->query("SELECT COUNT(*) AS today FROM vendors WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['today'];
$todayProducts = $conn->query("SELECT COUNT(*) AS today FROM products WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['today'];
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Reports</title>
    <style>
        body { font-family: Arial; background: #f9f9f9; padding: 20px; }
        .card { background: #fff; padding: 20px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 3px 6px rgba(0,0,0,0.1); }
        h2 { color: #007bff; }
    </style>
</head>
<body>
<h2>Platform Overview</h2>
<div class="card">
    <p><strong>Total Vendors:</strong> <?= $totalVendors ?></p>
    <p><strong>Total Products:</strong> <?= $totalProducts ?></p>
    <p><strong>Vendors Joined Today:</strong> <?= $todayVendors ?></p>
    <p><strong>Products Added Today:</strong> <?= $todayProducts ?></p>
</div>
</body>
</html>