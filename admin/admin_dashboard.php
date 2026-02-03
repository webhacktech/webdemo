<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.php");
    exit;
}

// Fetch Stats
$activeVendors = $conn->query("SELECT COUNT(*) FROM vendors WHERE kyc_status='approved'")->fetch_row()[0];
$totalVendors = $conn->query("SELECT COUNT(*) FROM vendors")->fetch_row()[0];
$totalProducts = $conn->query("SELECT COUNT(*) FROM products")->fetch_row()[0];
$pendingKYC = $conn->query("SELECT COUNT(*) FROM vendors WHERE kyc_status='pending'")->fetch_row()[0];

// Total Orders
$totalOrders = $conn->query("SELECT COUNT(*) FROM orders")->fetch_row()[0];

// Fetch total platform sales & profit from all vendor transactions (successful payments only)
$salesData = $conn->query("
    SELECT 
      COALESCE(SUM(amount_paid), 0) AS total_sales, 
      COALESCE(SUM(platform_fee), 0) AS total_platform_profit
    FROM transactions
    WHERE status = 'successful'
")->fetch_assoc();

$totalSales = number_format($salesData['total_sales'], 2);
$platformProfit = number_format($salesData['total_platform_profit'], 2);

// ✅ Premium Upgrade Stats
$premiumRevenue = 0;
$premiumVendors = 0;
$vendors = [];
$methodBreakdown = ["paystack" => 0, "flutterwave" => 0, "crypto" => 0];

$premiumResult = $conn->query("
    SELECT vendor_id, amount, method, status 
    FROM transactions 
    WHERE type='premium_upgrade'
");

if ($premiumResult && $premiumResult->num_rows > 0) {
    while ($row = $premiumResult->fetch_assoc()) {
        $method = strtolower($row['method']);
        if ($method === "crypto") {
            $premiumRevenue += 5000; // fixed conversion
            $methodBreakdown["crypto"] += 5000;
        } else {
            $premiumRevenue += $row['amount'];
            $methodBreakdown[$method] += $row['amount'];
        }
        $vendors[$row['vendor_id']] = true;
    }
    $premiumVendors = count($vendors);
}

// Fetch current site settings
$settings = $conn->query("SELECT * FROM site_settings WHERE id = 1")->fetch_assoc();

function safe($val) {
    return htmlspecialchars($val ?? '', ENT_QUOTES);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Sellevo Admin Dashboard</title>
  <style>
    :root {
      --brand: #106b28;
      --dark: #1f2937;
      --bg: #f4f6f8;
      --white: #fff;
    }
    body {
      margin: 0;
      font-family: 'Segoe UI', sans-serif;
      background: var(--bg);
    }
    .topbar {
      background: var(--brand);
      color: white;
      padding: 1rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .sidebar {
      width: 220px;
      background: var(--dark);
      position: fixed;
      top: 0;
      bottom: 0;
      padding-top: 60px;
      overflow-y: auto;
      color: #ccc;
    }
    .sidebar a {
      display: block;
      padding: 15px 20px;
      color: #ccc;
      text-decoration: none;
      border-left: 4px solid transparent;
      transition: 0.3s;
    }
    .sidebar a:hover, .sidebar a.active {
      background: #374151;
      border-left-color: var(--brand);
      color: white;
    }
    .main {
      margin-left: 220px;
      padding: 2rem;
    }
    .grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1rem;
    }
    .card {
      background: var(--white);
      border-radius: 8px;
      padding: 1.5rem;
      box-shadow: 0 2px 4px rgba(0,0,0,0.05);
      text-decoration: none;
    }
    .card h4 {
      margin: 0;
      font-size: 14px;
      color: #666;
    }
    .card p {
      font-size: 22px;
      font-weight: bold;
      margin: 8px 0 0;
      color: var(--brand);
    }
    .form-card {
      margin-top: 2rem;
      padding: 1.5rem;
      background: var(--white);
      border-radius: 8px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    .form-card label {
      font-weight: 600;
      display: block;
      margin-top: 15px;
    }
    .form-card input {
      width: 100%;
      padding: 10px;
      margin-top: 6px;
      border: 1px solid #ccc;
      border-radius: 6px;
    }
    .form-card button {
      margin-top: 20px;
      padding: 12px 20px;
      background: var(--brand);
      border: none;
      border-radius: 6px;
      color: white;
      font-weight: bold;
      cursor: pointer;
    }
    .form-card button:hover {
      background: #0b4c1b;
    }
    .hamburger {
      display: none;
      font-size: 1.5rem;
      cursor: pointer;
    }
    .success-msg {
      padding: 10px;
      background: #d1fae5;
      border: 1px solid #10b981;
      color: #065f46;
      border-radius: 5px;
      margin-bottom: 15px;
    }
    @media(max-width: 768px) {
      .sidebar {
        left: -250px;
        transition: all 0.3s;
        z-index: 999;
      }
      .sidebar.show {
        left: 0;
      }
      .main {
        margin-left: 0;
        padding: 1rem;
      }
      .hamburger {
        display: block;
      }
    }
  </style>
</head>
<body>

<div class="topbar">
  <span class="hamburger" onclick="toggleSidebar()">☰</span>
  <strong>Sellevo Admin</strong>
  <a href="logout.php" style="color: white;">Logout</a>
</div>

<div class="sidebar" id="sidebar">
  <a href="admin_dashboard.php" class="active">📊 Dashboard</a>
  <a href="admin_users.php">👤 Admins</a>
  <a href="admin_vendors.php">🏪 Vendors</a>
  <a href="admin_products.php">📦 Products</a>
  <a href="kyc_review.php">🛂 KYC Review</a>
  <a href="admin_reviews.php">🛂 Admin Review</a>
  <a href="admin_revenue.php">♻️Vendors Revenue</a>
  <a href="admin_withdrawals.php">💵 Withdrawals</a>
  <a href="admin_usdt_wallets.php">🪙 Wallet Approval</a>
  <a href="admin_transactions.php">📈 Transactions</a>
  <a href="admin_logs.php">🧾 Admin Logs</a>
  <a href="admin_crypto_payments.php">📈 Crypto Pay</a>
  <a href="admin_premium_upgrades.php">⭐ Premium Upgrades</a>
  <a href="admin_send_email.php">📬 Send Email</a>
  <a href="admin_blog.php">📝 Blog Posts</a>
  <a href="admin_affiliates.php">🤝 Affiliates</a>
  <a href="admin_affiliate_referrals.php">📨 Affiliate Referrals</a>
  <a href="admin_affiliate_withdrawals.php">💸 Affiliate Withdrawals</a>
  <a href="admin_blog_categories.php">📂 Blog Categories</a>
  <a href="site_settings.php">⚙️ Settings</a>
</div>

<div class="main">
  <?php if (isset($_SESSION['success_message'])): ?>
    <div class="success-msg"><?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
  <?php endif; ?>

  <h2>👋 Welcome back, Admin</h2>
  <p><?= date('l, F j, Y h:i A') ?></p>

  <!-- Regular Stats -->
  <div class="grid">
    <div class="card"><h4>Active Vendors</h4><p><?= $activeVendors ?></p></div>
    <div class="card"><h4>Total Vendors</h4><p><?= $totalVendors ?></p></div>
    <div class="card"><h4>Total Products</h4><p><?= $totalProducts ?></p></div>
    <div class="card"><h4>Pending KYC</h4><p><?= $pendingKYC ?></p></div>
    <div class="card"><h4>Total Sales</h4><p>₦<?= $totalSales ?></p></div>
    <div class="card"><h4>Platform Profit</h4><p>₦<?= $platformProfit ?></p></div>
    <a href="admin_view_orders.php" class="card hover:bg-gray-100 transition">
    <h4>Total Orders</h4>
    <p><?= $totalOrders ?></p>
</a>
  </div>

  <!-- ✅ Premium Upgrade Stats -->
  <div class="grid" style="margin-top: 20px;">
    <div class="card"><h4>Total Premium Revenue</h4><p>₦<?= number_format($premiumRevenue, 2) ?></p></div>
    <div class="card"><h4>Total Premium Vendors</h4><p><?= $premiumVendors ?></p></div>
    <div class="card">
      <h4>Revenue by Method</h4>
      <p style="color:#666; font-size:14px;">
        Paystack: ₦<?= number_format($methodBreakdown['paystack'], 2) ?><br>
        Flutterwave: ₦<?= number_format($methodBreakdown['flutterwave'], 2) ?><br>
        Crypto: ₦<?= number_format($methodBreakdown['crypto'], 2) ?>
      </p>
    </div>
  </div>

  <div class="grid" style="margin-top: 30px;">
    <a href="admin_blog.php" class="card">
      <h4>📝 Manage Blog</h4>
      <p style="color:#666; font-size:14px;">Add/edit blog posts</p>
    </a>
    <a href="admin_send_email.php" class="card">
      <h4>📬 Send Email</h4>
      <p style="color:#666; font-size:14px;">Email users/vendors</p>
    </a>
  </div>

  <form action="update_settings.php" method="POST" class="form-card">
    <h3>⚙️ Update Payment Settings</h3>

    <label>Paystack Secret Key</label>
    <input type="text" name="paystack_secret_key" value="<?= safe($settings['paystack_secret_key']) ?>" required>

    <label>Flutterwave Secret Key</label>
    <input type="text" name="flutterwave_secret_key" value="<?= safe($settings['flutterwave_secret_key']) ?>" required>

    <label>USDT Wallet Address (Main Wallet)</label>
    <input type="text" name="crypto_wallet_address" value="<?= safe($settings['crypto_wallet_address']) ?>" required>

    <label>Platform Commission (%)</label>
    <input type="number" step="0.01" min="0" max="100" name="platform_commission_percent" value="<?= safe($settings['platform_commission_percent']) ?>" required>

    <label>Withdrawal Threshold (₦)</label>
    <input type="number" step="1" min="0" name="withdrawal_threshold" value="<?= safe($settings['withdrawal_threshold']) ?>" required>

    <label>USD to NGN Rate</label>
    <input type="number" step="0.01" name="usd_to_ngn_rate" value="<?= safe($settings['usd_to_ngn_rate']) ?>" required>

    <button type="submit">💾 Save Settings</button>
  </form>
</div>

<script>
function toggleSidebar() {
  document.getElementById("sidebar").classList.toggle("show");
}
</script>

</body>
</html>