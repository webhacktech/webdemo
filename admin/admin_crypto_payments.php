<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require '../config.php';

// Check admin login - add your auth check here
// Example:
// if (!isset($_SESSION['admin_logged_in'])) {
//    header('Location: admin_login.php');
//    exit;
// }

// Pagination
$limit = 15;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Filters (optional)
$status_filter = $_GET['status'] ?? 'pending';
$vendor_filter = $_GET['vendor'] ?? '';

// Build query with filters
$where = "WHERE payment_method = 'crypto' ";
$params = [];
$types = "";

if ($status_filter && in_array($status_filter, ['pending', 'paid', 'rejected'])) {
    $where .= "AND o.status = ? ";
    $types .= "s";
    $params[] = $status_filter;
}

if ($vendor_filter && is_numeric($vendor_filter)) {
    $where .= "AND o.vendor_id = ? ";
    $types .= "i";
    $params[] = intval($vendor_filter);
}

// Get total count
$count_sql = "SELECT COUNT(*) FROM orders o $where";
$count_stmt = $conn->prepare($count_sql);
if ($types) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$count_stmt->bind_result($total_rows);
$count_stmt->fetch();
$count_stmt->close();

// Fetch paginated orders
$sql = "SELECT o.*, v.store_name FROM orders o 
        LEFT JOIN vendors v ON o.vendor_id = v.id
        $where ORDER BY o.created_at DESC LIMIT ?, ?";
$count_params = $params;
$count_types = $types;
$count_types .= "ii";
$count_params[] = $offset;
$count_params[] = $limit;

$stmt = $conn->prepare($sql);
if ($count_types) {
    // bind params dynamically
    $tmp = [];
    foreach ($count_params as $k => &$param) {
        $tmp[$k] = &$param;
    }
    array_unshift($tmp, $count_types);
    call_user_func_array([$stmt, 'bind_param'], $tmp);
}

$stmt->execute();
$result = $stmt->get_result();

?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin - Crypto Payments Approval</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
      body { font-family: Arial, sans-serif; background: #f9f9f9; margin:0; padding: 20px; }
      .container { max-width: 900px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 12px rgba(0,0,0,0.1); }
      h1 { color: #106b28; }
      table { width: 100%; border-collapse: collapse; margin-top: 20px; }
      th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
      th { background: #106b28; color: white; }
      tr:nth-child(even) { background: #f9f9f9; }
      .btn { padding: 8px 14px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; }
      .approve { background: #28a745; color: white; }
      .reject { background: #dc3545; color: white; }
      .status-pending { color: #ff9800; font-weight: bold; }
      .status-paid { color: #28a745; font-weight: bold; }
      .status-rejected { color: #dc3545; font-weight: bold; }
      @media (max-width: 600px) {
        table, thead, tbody, th, td, tr { display: block; }
        tr { margin-bottom: 15px; }
        th { position: absolute; top: -9999px; left: -9999px; }
        td {
          border: none;
          position: relative;
          padding-left: 50%;
          white-space: normal;
          text-align: right;
          word-wrap: break-word;
        }
        td::before {
          position: absolute;
          top: 12px;
          left: 12px;
          width: 45%;
          padding-right: 10px;
          white-space: nowrap;
          font-weight: bold;
          text-align: left;
          content: attr(data-label);
        }
        .btn { width: 48%; margin: 1% 1% 1% 0; display: inline-block; }
      }
      .pagination { margin-top: 20px; text-align: center; }
      .pagination a {
        margin: 0 5px;
        padding: 8px 12px;
        text-decoration: none;
        background: #106b28;
        color: white;
        border-radius: 4px;
        font-weight: bold;
      }
      .pagination a.disabled {
        background: #ccc;
        cursor: default;
      }
    </style>
</head>
<body>
<div class="container">
    <h1>Crypto Payments Approval</h1>

    <form method="GET" style="margin-bottom: 20px;">
        <label>Status:
            <select name="status">
                <option value="" <?= $status_filter == '' ? 'selected' : '' ?>>All</option>
                <option value="pending" <?= $status_filter == 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="paid" <?= $status_filter == 'paid' ? 'selected' : '' ?>>Approved</option>
                <option value="rejected" <?= $status_filter == 'rejected' ? 'selected' : '' ?>>Rejected</option>
            </select>
        </label>
        <label style="margin-left: 15px;">Vendor ID:
            <input type="number" name="vendor" value="<?= htmlspecialchars($vendor_filter) ?>" placeholder="Filter by vendor ID" style="width: 120px;">
        </label>
        <button type="submit" style="margin-left: 15px; padding: 6px 12px; background: #106b28; color: #fff; border:none; border-radius: 5px;">Filter</button>
    </form>

    <table>
        <thead>
            <tr>
                <th>Order ID</th>
                <th>Vendor</th>
                <th>Customer</th>
                <th>Email</th>
                <th>Amount (₦)</th>
                <th>Payment Ref</th>
                <th>Status</th>
                <th>Proof</th>
                <th>Submitted At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td data-label="Order ID"><?= $row['id'] ?></td>
                <td data-label="Vendor"><?= htmlspecialchars($row['store_name'] ?: 'N/A') ?></td>
                <td data-label="Customer"><?= htmlspecialchars($row['customer_name']) ?></td>
                <td data-label="Email"><?= htmlspecialchars($row['customer_email']) ?></td>
                <td data-label="Amount">₦<?= number_format($row['total_amount'], 2) ?></td>
                <td data-label="Payment Ref"><?= htmlspecialchars($row['payment_ref']) ?></td>
                <td data-label="Status">
                    <span class="status-<?= htmlspecialchars($row['status']) ?>"><?= ucfirst($row['status']) ?></span>
                </td>
                <td data-label="Proof">
                    <?php if ($row['proof_image']): ?>
                        <a href="uploads/crypto_proofs/<?= htmlspecialchars($row['proof_image']) ?>" target="_blank">View</a>
                    <?php else: ?>
                        N/A
                    <?php endif; ?>
                </td>
                <td data-label="Submitted At"><?= date('Y-m-d H:i', strtotime($row['created_at'])) ?></td>
                <td data-label="Actions">
                    <?php if ($row['status'] === 'pending'): ?>
                        <form action="approve_crypto_payment.php" method="POST" style="display:inline-block;">
                            <input type="hidden" name="order_id" value="<?= $row['id'] ?>">
                            <button type="submit" class="btn approve" onclick="return confirm('Approve this payment?')">Approve</button>
                        </form>
                        <form action="reject_crypto_payment.php" method="POST" style="display:inline-block; margin-left:5px;">
                            <input type="hidden" name="order_id" value="<?= $row['id'] ?>">
                            <button type="submit" class="btn reject" onclick="return confirm('Reject this payment?')">Reject</button>
                        </form>
                    <?php else: ?>
                        &mdash;
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <div class="pagination">
        <?php 
        $total_pages = ceil($total_rows / $limit);
        if ($page > 1) {
            $prev_page = $page - 1;
            echo '<a href="?page='.$prev_page.'&status='.urlencode($status_filter).'&vendor='.urlencode($vendor_filter).'">Prev</a>';
        } else {
            echo '<a class="disabled">Prev</a>';
        }

        echo " Page $page of $total_pages ";

        if ($page < $total_pages) {
            $next_page = $page + 1;
            echo '<a href="?page='.$next_page.'&status='.urlencode($status_filter).'&vendor='.urlencode($vendor_filter).'">Next</a>';
        } else {
            echo '<a class="disabled">Next</a>';
        }
        ?>
    </div>
</div>
</body>
</html>