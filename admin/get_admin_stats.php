<?php
function getPlatformStats($conn) {
    $data = [];

    $salesQ = $conn->query("SELECT SUM(amount_paid) FROM transactions");
    $data['total_sales'] = $salesQ->fetch_row()[0] ?? 0;

    $commissionQ = $conn->query("SELECT SUM(platform_fee) FROM transactions");
    $data['platform_revenue'] = $commissionQ->fetch_row()[0] ?? 0;

    $data['active_vendors'] = $conn->query("SELECT COUNT(*) FROM vendors WHERE status='active'")->fetch_row()[0];
    $data['total_products'] = $conn->query("SELECT COUNT(*) FROM products")->fetch_row()[0];

    return $data;
}