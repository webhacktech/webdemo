<?php
session_start();
require_once '../config.php';

// Check admin login
if (!isset($_SESSION['is_admin'])) {
    header("Location: login.php");
    exit();
}

$search = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? '';
$limit = 20;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

// Build filter conditions
$conditions = [];
$params = [];
$types = '';

if ($search !== '') {
    $conditions[] = "(v.store_name LIKE ? OR v.email LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'ss';
}

if (in_array($status_filter, ['pending', 'approved', 'rejected'])) {
    $conditions[] = "w.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Get total count
$count_sql = "
    SELECT COUNT(*)
    FROM vendor_wallets w
    JOIN vendors v ON w.vendor_id = v.id
    $where
";
$count_stmt = $conn->prepare($count_sql);
if ($types) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$count_stmt->bind_result($total);
$count_stmt->fetch();
$count_stmt->close();

$total_pages = ceil($total / $limit);

// Fetch wallet submissions
$sql = "
    SELECT w.id, w.wallet_address, w.status, w.created_at, v.store_name, v.email
    FROM vendor_wallets w
    JOIN vendors v ON w.vendor_id = v.id
    $where
    ORDER BY w.created_at DESC
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
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Vendor Wallet Approvals - Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    body { font-family: 'Segoe UI', sans-serif; background: #f9f9f9; padding: 20px; }
    h2 { color: #106b28; margin-bottom: 20px; text-align: center; }
    form { margin-bottom: 20px; text-align: center; }
    input[type="text"], select {
      padding: 8px; margin-right: 10px;
      border: 1px solid #ccc; border-radius: 5px;
      max-width: 180px;
    }
    button {
      background: #106b28; color: #fff;
      padding: 9px 16px;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      font-weight: bold;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      background: #fff;
      box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }
    th, td {
      padding: 12px; text-align: left;
      border-bottom: 1px solid #eee;
      font-size: 14px;
    }
    th {
      background: #106b28;
      color: white;
    }
    .status {
      font-weight: bold;
    }
    .pending { color: #b8860b; }
    .approved { color: #228b22; }
    .rejected { color: #b22222; }
    .action a {
      margin-right: 10px;
      font-weight: bold;
    }
    .pagination {
      text-align: center; margin-top: 20px;
    }
    .pagination a {
      background: #106b28;
      color: white;
      padding: 6px 12px;
      margin: 0 4px;
      border-radius: 4px;
      text-decoration: none;
    }
    .pagination a.active {
      background: #064816;
    }

    @media(max-width: 768px) {
      table, thead, tbody, th, td, tr { display: block; }
      thead { display: none; }
      tr { margin-bottom: 15px; background: white; padding: 10px; border-radius: 8px; }
      td { position: relative; padding-left: 50%; }
      td::before {
        position: absolute;
        top: 12px;
        left: 12px;
        width: 45%;
        font-weight: bold;
        color: #106b28;
      }
      td:nth-child(1)::before { content: "Vendor"; }
      td:nth-child(2)::before { content: "Email"; }
      td:nth-child(3)::before { content: "Wallet"; }
      td:nth-child(4)::before { content: "Status"; }
      td:nth-child(5)::before { content: "Date"; }
      td:nth-child(6)::before { content: "Action"; }
    }
  </style>
</head>
<body>
  <h2>ðŸª™ Vendor Wallet Approvals</h2>

<?php if (isset($_GET['success'])): ?>
  <div style="text-align:center; background:#e6fbe9; padding:10px; color:#1d7a38; margin-bottom:15px;">
    ✅ Wallet <?= htmlspecialchars($_GET['success']) ?> successfully.
  </div>
<?php endif; ?>

  <form method="get">
    <input type="text" name="search" placeholder="Vendor or email" value="<?= htmlspecialchars($search) ?>" />
    <select name="status">
      <option value="">All Statuses</option>
      <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
      <option value="approved" <?= $status_filter === 'approved' ? 'selected' : '' ?>>Approved</option>
      <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
    </select>
    <button type="submit">Filter</button>
  </form>

  <table>
    <thead>
      <tr>
        <th>Vendor</th>
        <th>Email</th>
        <th>Wallet Address</th>
        <th>Status</th>
        <th>Date</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
    <?php if ($result->num_rows === 0): ?>
      <tr><td colspan="6" style="text-align:center;">No wallet submissions found.</td></tr>
    <?php else: ?>
      <?php while ($row = $result->fetch_assoc()): ?>
        <tr>
          <td><?= htmlspecialchars($row['store_name']) ?></td>
          <td><?= htmlspecialchars($row['email']) ?></td>
          <td><?= htmlspecialchars($row['wallet_address']) ?></td>
          <td class="status <?= $row['status'] ?>"><?= ucfirst($row['status']) ?></td>
          <td><?= date('M j, Y H:i', strtotime($row['created_at'])) ?></td>
          <td class="action">
            <?php if ($row['status'] === 'pending'): ?>
              <a href="approve_wallet.php?id=<?= $row['id'] ?>" style="color:green;" onclick="return confirm('Approve this wallet?')">Approve</a>
              <a href="reject_wallet.php?id=<?= $row['id'] ?>" style="color:red;" onclick="return confirm('Reject this wallet?')">Reject</a>
            <?php else: ?>
              <span style="color:#666;">-</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endwhile; ?>
    <?php endif; ?>
    </tbody>
  </table>

  <?php if ($total_pages > 1): ?>
    <div class="pagination">
      <?php for ($p = 1; $p <= $total_pages; $p++): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>" class="<?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
</body>
</html>