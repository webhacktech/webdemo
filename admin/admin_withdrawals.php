<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require '../config.php';

if (!isset($_SESSION['is_admin'])) {
    header("Location: login.php");
    exit();
}

$search = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? '';
$limit = 20;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

// Fetch conversion rate
$rate_result = $conn->query("SELECT usd_to_ngn_rate FROM site_settings WHERE id = 1");
$rate_row = $rate_result->fetch_assoc();
$usd_to_ngn = floatval($rate_row['usd_to_ngn_rate'] ?? 1500);

$conditions = [];
$params = [];
$types = '';

if ($search !== '') {
    $conditions[] = "(v.store_name LIKE ? OR v.email LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $types .= 'ss';
}
if (in_array($status_filter, ['pending', 'approved', 'rejected'])) {
    $conditions[] = "p.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

$where = $conditions ? "WHERE " . implode(" AND ", $conditions) : "";

$count_sql = "SELECT COUNT(*) FROM vendor_payouts p JOIN vendors v ON p.vendor_id = v.id $where";
$count_stmt = $conn->prepare($count_sql);
if ($types) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$count_stmt->bind_result($total);
$count_stmt->fetch();
$count_stmt->close();
$total_pages = ceil($total / $limit);

// ✅ Updated SQL (no vendor_bank_details, fetch bank info from vendors table directly)
$sql = "
SELECT p.*, v.store_name, v.email, v.currency,
       v.settlement_account_name AS account_name,
       v.settlement_account_number AS account_number,
       v.settlement_bank_name AS bank_name
FROM vendor_payouts p
JOIN vendors v ON p.vendor_id = v.id
$where
ORDER BY p.created_at DESC
LIMIT ? OFFSET ?
";
$params[] = $limit;
$params[] = $offset;
$types .= 'ii';
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
  <title>Vendor Payouts - Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    body { font-family: 'Segoe UI', sans-serif; background: #f5f5f5; padding: 20px; }
    h2 { color: #106b28; }
    table { width: 100%; border-collapse: collapse; background: white; margin-top: 15px; border-radius: 10px; overflow: hidden; }
    th, td { padding: 12px; border-bottom: 1px solid #eee; font-size: 14px; }
    th { background: #106b28; color: white; }
    tr:nth-child(even) { background: #f9f9f9; }
    .filter-form { margin-bottom: 20px; }
    .filter-form input, .filter-form select {
      padding: 8px;
      margin-right: 10px;
      border: 1px solid #ccc;
      border-radius: 6px;
    }
    .filter-form button {
      background: #106b28; color: white;
      border: none; padding: 9px 16px; border-radius: 6px;
      font-weight: bold; cursor: pointer;
    }
    .status-pending { color: #b8860b; font-weight: bold; }
    .status-approved { color: green; font-weight: bold; }
    .status-rejected { color: red; font-weight: bold; }
    .actions a {
      margin-right: 10px;
      font-weight: bold;
      text-decoration: none;
    }
    .actions a.approve { color: green; }
    .actions a.reject { color: red; }
    .note { font-size: 13px; color: #888; margin-top: 5px; }
    .pagination { margin-top: 15px; }
    .pagination a {
      background: #106b28; color: white; padding: 8px 12px;
      margin: 0 4px; text-decoration: none;
      border-radius: 4px;
    }
    .pagination a.current { background: #064816; font-weight: bold; }

    @media(max-width: 768px) {
      table, thead, tbody, th, td, tr { display: block; }
      th { display: none; }
      tr {
        margin-bottom: 20px;
        border: 1px solid #ddd;
        border-radius: 10px;
        padding: 12px;
        background: white;
      }
      td {
        padding: 10px 10px 10px 50%;
        position: relative;
      }
      td::before {
        position: absolute;
        top: 10px; left: 10px;
        font-weight: bold;
        color: #106b28;
        content: attr(data-label);
        width: 45%;
        white-space: nowrap;
      }
    }
  </style>
</head>
<body>
  <h2>💸 Vendor Withdrawal Requests</h2>

<?php if (isset($_GET['success'])): ?>
  <div style="background: #d4edda; color: #155724; padding: 12px; margin-bottom: 20px; border: 1px solid #c3e6cb; border-radius: 6px;">
    ✅ Withdrawal approved successfully.
  </div>
<?php elseif (isset($_GET['rejected'])): ?>
  <div style="background: #f8d7da; color: #721c24; padding: 12px; margin-bottom: 20px; border: 1px solid #f5c6cb; border-radius: 6px;">
    ❌ Withdrawal request was rejected.
  </div>
<?php endif; ?>

  <form class="filter-form" method="get">
    <input type="text" name="search" placeholder="Search store or email" value="<?= htmlspecialchars($search) ?>" />
    <select name="status">
      <option value="">All Status</option>
      <option value="pending" <?= $status_filter == 'pending' ? 'selected' : '' ?>>Pending</option>
      <option value="approved" <?= $status_filter == 'approved' ? 'selected' : '' ?>>Approved</option>
      <option value="rejected" <?= $status_filter == 'rejected' ? 'selected' : '' ?>>Rejected</option>
    </select>
    <button type="submit">🔍 Filter</button>
  </form>

  <table>
    <thead>
      <tr>
        <th>Vendor</th>
        <th>Email</th>
        <th>Amount</th>
        <th>Currency</th>
        <th>Bank Details</th>
        <th>Status</th>
        <th>Requested</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($result->num_rows === 0): ?>
        <tr><td colspan="8" style="text-align:center;">No withdrawals found.</td></tr>
      <?php else: while ($row = $result->fetch_assoc()): ?>
        <tr>
          <td data-label="Vendor"><?= htmlspecialchars($row['store_name']) ?></td>
          <td data-label="Email"><?= htmlspecialchars($row['email']) ?></td>
          <td data-label="Amount">
            <?php if ($row['currency'] === 'USD'): ?>
              $<?= number_format($row['amount'], 2) ?><br>
              <small>≈ ₦<?= number_format($row['amount'] * $usd_to_ngn, 2) ?></small>
            <?php else: ?>
              ₦<?= number_format($row['amount'], 2) ?>
            <?php endif; ?>
          </td>
          <td data-label="Currency"><?= $row['currency'] ?></td>
          <td data-label="Bank Details">
            <?php if ($row['currency'] === 'NGN'): ?>
              <strong><?= htmlspecialchars($row['account_name']) ?></strong><br>
              Acc: <?= htmlspecialchars($row['account_number']) ?><br>
              Bank: <?= htmlspecialchars($row['bank_name']) ?>
            <?php else: ?>
              <em>Handled in crypto section</em>
            <?php endif; ?>
          </td>
          <td data-label="Status" class="status-<?= $row['status'] ?>"><?= ucfirst($row['status']) ?></td>
          <td data-label="Requested"><?= date('M j, Y h:i A', strtotime($row['created_at'])) ?></td>
          <td data-label="Actions" class="actions">
    <?php if ($row['status'] === 'pending' && $row['currency'] === 'NGN'): ?>
        <form action="upload_receipt.php" method="post" enctype="multipart/form-data" style="margin-bottom:5px;">
            <input type="hidden" name="withdrawal_id" value="<?= $row['id'] ?>">
            <input type="file" name="receipt" required>
            <button type="submit" style="background: #106b28; color: #fff; border:none; padding:4px 8px; border-radius:4px;">Upload & Approve</button>
        </form>
        <a href="reject_withdrawal.php?id=<?= $row['id'] ?>" class="reject" onclick="return confirm('Reject this payout?')">✖ Reject</a>
    <?php elseif($row['status'] === 'approved' && $row['receipt']): ?>
        <a href="../receipts/<?= htmlspecialchars($row['receipt']) ?>" target="_blank">📄 View Receipt</a>
    <?php else: ?>
        <span style="color:#999;">-</span>
    <?php endif; ?>
</td>
        </tr>
      <?php endwhile; endif; ?>
    </tbody>
  </table>

  <?php if ($total_pages > 1): ?>
    <div class="pagination">
      <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" class="<?= $i == $page ? 'current' : '' ?>"><?= $i ?></a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
</body>
</html>