<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require '../config.php';
require 'admin_auth.php';

// Log admin activity
$admin_id = $_SESSION['admin_id'];
$log = $conn->prepare("INSERT INTO admin_logs (admin_id, action, created_at) VALUES (?, 'Viewed referral activities', NOW())");
$log->bind_param("i", $admin_id);
$log->execute();
$log->close();

// Handle filters
$search = $_GET['search'] ?? '';
$limit = 20;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$conditions = [];
$params = [];
$types = '';

if (!empty($search)) {
    $conditions[] = "(LOWER(rv.store_name) LIKE ? OR LOWER(rd.store_name) LIKE ?)";
    $params[] = '%' . strtolower($search) . '%';
    $params[] = '%' . strtolower($search) . '%';
    $types .= 'ss';
}

$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$query = "
    SELECT r.*, rv.store_name AS referrer_name, rd.store_name AS referred_name
    FROM vendor_referrals r
    LEFT JOIN vendors rv ON r.referrer_vendor_id = rv.id
    LEFT JOIN vendors rd ON r.referred_vendor_id = rd.id
    $where
    ORDER BY r.created_at DESC
    LIMIT ? OFFSET ?
";
$params[] = $limit;
$params[] = $offset;
$types .= 'ii';

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin - Referrals</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body { font-family: 'Segoe UI', sans-serif; background: #f9f9f9; margin: 0; padding: 20px; }
    h2 { color: #106b28; margin-bottom: 10px; }

    .filters {
      margin-bottom: 20px;
    }

    .filters input[type="text"] {
      padding: 8px;
      width: 250px;
      border: 1px solid #ccc;
      border-radius: 4px;
    }

    .filters button {
      padding: 8px 12px;
      background-color: #106b28;
      color: white;
      border: none;
      border-radius: 4px;
      margin-left: 8px;
      cursor: pointer;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      background: white;
      border-radius: 8px;
      overflow: hidden;
      box-shadow: 0 2px 6px rgba(0,0,0,0.05);
    }

    th, td {
      padding: 12px;
      text-align: left;
      font-size: 14px;
      border-bottom: 1px solid #eee;
    }

    th {
      background-color: #106b28;
      color: white;
      font-weight: bold;
    }

    tr:nth-child(even) {
      background-color: #fcfcfc;
    }

    .pagination {
      margin-top: 20px;
      text-align: center;
    }

    .pagination a {
      display: inline-block;
      padding: 6px 12px;
      margin: 0 3px;
      background-color: #eee;
      color: #333;
      border-radius: 4px;
      text-decoration: none;
    }

    .pagination a.active {
      background-color: #106b28;
      color: white;
    }

    @media (max-width: 768px) {
      table, thead, tbody, th, td, tr {
        display: block;
        width: 100%;
      }

      thead {
        display: none;
      }

      tr {
        margin-bottom: 15px;
        background: white;
        border-radius: 8px;
        padding: 10px;
        box-shadow: 0 1px 4px rgba(0,0,0,0.05);
      }

      td {
        padding: 10px;
        position: relative;
        border: none;
      }

      td::before {
        content: attr(data-label);
        position: absolute;
        left: 10px;
        top: 10px;
        font-weight: bold;
        color: #106b28;
      }
    }
  </style>
</head>
<body>

  <h2>👥 Referral Commissions</h2>

  <form method="get" class="filters">
    <input type="text" name="search" placeholder="Search vendor name..." value="<?= htmlspecialchars($search) ?>">
    <button type="submit">🔍 Filter</button>
  </form>

  <table>
    <thead>
      <tr>
        <th>Referrer</th>
        <th>Referred</th>
        <th>Commission (₦)</th>
        <th>Upgrade Paid (₦)</th>
        <th>Referral Code</th>
        <th>Date</th>
      </tr>
    </thead>
    <tbody>
    <?php while ($row = $result->fetch_assoc()): ?>
      <tr>
        <td data-label="Referrer"><?= htmlspecialchars($row['referrer_name'] ?? 'N/A') ?></td>
        <td data-label="Referred"><?= htmlspecialchars($row['referred_name'] ?? 'N/A') ?></td>
        <td data-label="Commission">₦<?= number_format($row['commission_earned'], 2) ?></td>
        <td data-label="Upgrade Paid">₦<?= number_format($row['commission_amount'], 2) ?></td>
        <td data-label="Referral Code"><?= htmlspecialchars($row['referrer_code']) ?></td>
        <td data-label="Date"><?= date('M j, Y h:i A', strtotime($row['created_at'])) ?></td>
      </tr>
    <?php endwhile; ?>
    <?php if ($result->num_rows === 0): ?>
      <tr><td colspan="6" style="text-align:center;">No referral data found.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>

  <?php
    $count_query = "
        SELECT COUNT(*) 
        FROM vendor_referrals r 
        LEFT JOIN vendors rv ON r.referrer_vendor_id = rv.id 
        LEFT JOIN vendors rd ON r.referred_vendor_id = rd.id 
        $where
    ";
    $count_stmt = $conn->prepare($count_query);
    if ($conditions) {
        $count_stmt->bind_param(substr($types, 0, -2), ...array_slice($params, 0, -2));
    }
    $count_stmt->execute();
    $count_stmt->bind_result($total_rows);
    $count_stmt->fetch();
    $count_stmt->close();

    $total_pages = ceil($total_rows / $limit);
    if ($total_pages > 1):
  ?>
    <div class="pagination">
      <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" class="<?= $i == $page ? 'active' : '' ?>">
          <?= $i ?>
        </a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>

</body>
</html>