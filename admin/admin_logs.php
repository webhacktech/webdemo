<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../config.php';

// Check admin login
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.php");
    exit;
}

$limit = 20;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$searchAdmin = trim($_GET['admin'] ?? '');
$searchAction = trim($_GET['action'] ?? '');

$conditions = [];
$params = [];
$types = '';

// Build search conditions
if ($searchAdmin !== '') {
    $conditions[] = "a.username LIKE ?";
    $params[] = "%$searchAdmin%";
    $types .= 's';
}
if ($searchAction !== '') {
    $conditions[] = "l.action LIKE ?";
    $params[] = "%$searchAction%";
    $types .= 's';
}

$where = $conditions ? "WHERE " . implode(" AND ", $conditions) : '';

// Get total count for pagination
$countSql = "SELECT COUNT(*) FROM admin_logs l JOIN admin_users a ON l.admin_id = a.admin_id $where";
$countStmt = $conn->prepare($countSql);
if ($types) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$countStmt->bind_result($totalRows);
$countStmt->fetch();
$countStmt->close();

$totalPages = ceil($totalRows / $limit);

// Get logs for current page
$sql = "SELECT l.admin_id, a.username, l.action, 
        COALESCE(l.ip_address, '') AS ip_address, 
        COALESCE(l.user_agent, '') AS user_agent,
        COALESCE(l.log_time, l.created_at) AS log_time,
        l.id
        FROM admin_logs l
        JOIN admin_users a ON l.admin_id = a.admin_id
        $where
        ORDER BY log_time DESC
        LIMIT ? OFFSET ?";

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
  <meta charset="UTF-8" />
  <title>Admin Activity Logs</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f9f9f9; margin: 20px; }
    h2 { color: #106b28; text-align: center; }
    form.filters { margin-bottom: 20px; text-align: center; }
    form.filters input {
      padding: 8px 12px;
      margin: 0 10px 10px 0;
      border: 1px solid #ccc;
      border-radius: 5px;
      width: 180px;
      max-width: 90vw;
    }
    form.filters button {
      padding: 9px 16px;
      background: #106b28;
      color: white;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      font-weight: bold;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      background: white;
      border-radius: 10px;
      overflow: hidden;
      box-shadow: 0 2px 8px rgb(0 0 0 / 0.1);
    }
    th, td {
      padding: 12px 15px;
      border-bottom: 1px solid #eee;
      text-align: left;
      font-size: 14px;
      word-break: break-word;
    }
    th {
      background: #106b28;
      color: white;
      font-weight: 600;
    }
    tr:nth-child(even) {
      background: #f7f9f8;
    }
    .pagination {
      margin-top: 20px;
      text-align: center;
    }
    .pagination a {
      display: inline-block;
      margin: 0 5px;
      padding: 8px 14px;
      background: #eee;
      color: #333;
      border-radius: 6px;
      text-decoration: none;
      font-weight: 600;
    }
    .pagination a.active {
      background: #106b28;
      color: white;
      pointer-events: none;
    }
    .back-link {
      display: block;
      margin-top: 25px;
      color: #106b28;
      text-decoration: none;
      font-weight: bold;
      text-align: center;
    }
    @media (max-width: 600px) {
      th, td { font-size: 12px; padding: 10px 8px; }
      form.filters input { width: 140px; margin-bottom: 8px; }
    }
  </style>
</head>
<body>
  <h2>📝 Admin Activity Logs</h2>

  <form method="get" class="filters" novalidate>
    <input type="text" name="admin" placeholder="Admin username" value="<?= htmlspecialchars($searchAdmin) ?>">
    <input type="text" name="action" placeholder="Action contains" value="<?= htmlspecialchars($searchAction) ?>">
    <button type="submit">Filter</button>
  </form>

  <table>
    <thead>
      <tr>
        <th>ID</th>
        <th>Admin</th>
        <th>Action</th>
        <th>IP Address</th>
        <th>User Agent</th>
        <th>Date</th>
      </tr>
    </thead>
    <tbody>
    <?php if ($result->num_rows === 0): ?>
      <tr><td colspan="6" style="text-align:center;">No logs found.</td></tr>
    <?php else: ?>
      <?php while ($row = $result->fetch_assoc()): ?>
        <tr>
          <td><?= $row['id'] ?></td>
          <td><?= htmlspecialchars($row['username'] ?? '') ?></td>
          <td><?= htmlspecialchars($row['action'] ?? '') ?></td>
          <td><?= htmlspecialchars($row['ip_address'] ?? '') ?></td>
          <td><?= htmlspecialchars($row['user_agent'] ?? '') ?></td>
          <td><?= date('M d, Y H:i:s', strtotime($row['log_time'] ?? '')) ?></td>
        </tr>
      <?php endwhile; ?>
    <?php endif; ?>
    </tbody>
  </table>

  <?php if ($totalPages > 1): ?>
  <div class="pagination" role="navigation" aria-label="Pagination Navigation">
    <?php
    $queryParams = $_GET;
    for ($i = 1; $i <= $totalPages; $i++):
        $queryParams['page'] = $i;
        $url = '?' . http_build_query($queryParams);
    ?>
      <a href="<?= htmlspecialchars($url) ?>" class="<?= ($i === $page) ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>

  <a href="admin_dashboard.php" class="back-link">← Back to Dashboard</a>
</body>
</html>