<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require '../config.php';

// ✅ Force Nigeria timezone
date_default_timezone_set('Africa/Lagos');

if (!isset($_SESSION['is_admin'])) {
    header("Location: login.php");
    exit();
}

$search = trim($_GET['search'] ?? '');
$method_filter = $_GET['method'] ?? '';
$status_filter = $_GET['status'] ?? '';
$limit = 20;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$conditions = [];
$params = [];
$types = '';

// Filters for search (vendor store name or email)
if ($search !== '') {
    $conditions[] = "(v.store_name LIKE ? OR v.email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

// Filters for method/status based on transactions table
$valid_methods = ['paystack', 'flutterwave', 'crypto', 'manual'];
if ($method_filter !== '' && in_array(strtolower($method_filter), $valid_methods)) {
    $conditions[] = "t.method = ?";
    $params[] = strtolower($method_filter);
    $types .= 's';
}

$valid_statuses = ['successful', 'failed', 'pending'];
if ($status_filter !== '' && in_array(strtolower($status_filter), $valid_statuses)) {
    $conditions[] = "t.status = ?";
    $params[] = strtolower($status_filter);
    $types .= 's';
}

$where = $conditions ? "WHERE " . implode(' AND ', $conditions) : "";

// Count total records
$count_sql = "
    SELECT COUNT(*)
    FROM transactions t
    JOIN vendors v ON t.vendor_id = v.id
    $where
";

$count_stmt = $conn->prepare($count_sql);
if ($types !== '') {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$count_stmt->bind_result($total_records);
$count_stmt->fetch();
$count_stmt->close();

$total_pages = $total_records > 0 ? ceil($total_records / $limit) : 1;

// Fetch transactions data with vendor info
$sql = "
    SELECT 
        t.id, t.vendor_id, v.store_name, v.email, 
        t.amount AS tx_amount, t.currency AS tx_currency, 
        t.status AS tx_status, t.method AS tx_method, 
        t.created_at, t.reference AS tx_reference
    FROM transactions t
    JOIN vendors v ON t.vendor_id = v.id
    $where
    ORDER BY t.created_at DESC
    LIMIT ? OFFSET ?
";

$params[] = $limit;
$params[] = $offset;
$types .= 'ii';

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Currency symbol helper
function getCurrencySymbol($code) {
    $code = strtoupper((string)$code);
    $symbols = [
        'NGN' => '₦', 'USD' => '$', 'GHS' => '₵', 'KES' => 'KSh', 'ZAR' => 'R',
        'EUR' => '€', 'GBP' => '£', 'XOF' => 'CFA', 'XAF' => 'FCFA',
        'TZS' => 'TSh', 'UGX' => 'USh',
    ];
    return $symbols[$code] ?? ($code !== '' ? $code : '');
}

// Match transaction to order (try exact and partial ref matches)
function getOrderReference($conn, $vendor_id, $transaction_ref) {
    $transaction_ref = (string)$transaction_ref;

    if ($transaction_ref === '') {
        return null;
    }

    // 1) Exact match on payment_ref, transaction_ref, or flutterwave_ref
    $stmt = $conn->prepare("
        SELECT payment_ref, transaction_ref, flutterwave_ref, total_amount, currency, status, payment_method, tracking_code
        FROM orders
        WHERE vendor_id = ? AND (payment_ref = ? OR transaction_ref = ? OR flutterwave_ref = ?)
        LIMIT 1
    ");
    if ($stmt) {
        $stmt->bind_param("isss", $vendor_id, $transaction_ref, $transaction_ref, $transaction_ref);
        $stmt->execute();
        $stmt->bind_result($payment_ref, $transaction_ref_db, $flutterwave_ref, $total_amount, $currency, $status, $payment_method, $tracking_code);
        if ($stmt->fetch()) {
            $stmt->close();
            return [
                'payment_ref' => $payment_ref ?? '',
                'transaction_ref_db' => $transaction_ref_db ?? '',
                'flutterwave_ref' => $flutterwave_ref ?? '',
                'amount' => $total_amount,
                'currency' => $currency,
                'status' => $status,
                'method' => $payment_method,
                'tracking_code' => $tracking_code
            ];
        }
        $stmt->close();
    }

    // fallback partial match
    $stmt = $conn->prepare("
        SELECT payment_ref, transaction_ref, flutterwave_ref, total_amount, currency, status, payment_method, tracking_code
        FROM orders
        WHERE vendor_id = ? AND (transaction_ref LIKE CONCAT('%', ?, '%') OR payment_ref LIKE CONCAT('%', ?, '%') OR flutterwave_ref LIKE CONCAT('%', ?, '%'))
        LIMIT 1
    ");
    if ($stmt) {
        $stmt->bind_param("isss", $vendor_id, $transaction_ref, $transaction_ref, $transaction_ref);
        $stmt->execute();
        $stmt->bind_result($payment_ref, $transaction_ref_db, $flutterwave_ref, $total_amount, $currency, $status, $payment_method, $tracking_code);
        if ($stmt->fetch()) {
            $stmt->close();
            return [
                'payment_ref' => $payment_ref ?? '',
                'transaction_ref_db' => $transaction_ref_db ?? '',
                'flutterwave_ref' => $flutterwave_ref ?? '',
                'amount' => $total_amount,
                'currency' => $currency,
                'status' => $status,
                'method' => $payment_method,
                'tracking_code' => $tracking_code
            ];
        }
        $stmt->close();
    }

    return null;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Admin - Transactions</title>
<style>
  body { font-family: Arial, sans-serif; padding: 20px; background: #f9f9f9; }
  h2 { color: #106b28; }
  form.filters { margin-bottom: 15px; }
  input[type="text"], select { padding: 8px; margin-right: 8px; border: 1px solid #ccc; border-radius: 4px; }
  button { background: #106b28; color: white; border: none; padding: 8px 14px; border-radius: 4px; cursor: pointer; }
  table { width: 100%; border-collapse: collapse; background: white; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
  th, td { padding: 12px; border: 1px solid #ddd; font-size: 14px; }
  th { background: #e6f2e6; color: #106b28; text-align: left; }
  tr:nth-child(even) { background: #f9f9f9; }
  tr:hover { background: #dff0d8; }
  .status-successful { color: #228B22; font-weight: bold; }
  .status-failed { color: #b22222; font-weight: bold; }
  .status-pending { color: #b8860b; font-weight: bold; }
  .pagination { margin-top: 15px; }
  .pagination a { margin: 0 5px; text-decoration: none; padding: 6px 12px; border-radius: 4px; background: #106b28; color: white; }
  .pagination a.current { font-weight: bold; background: #064816; }
  @media(max-width:768px) {
    table, thead, tbody, th, td, tr { display: block; }
    th { display: none; }
    tr { margin-bottom: 15px; background: white; padding: 12px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
    td { padding-left: 50%; position: relative; border: none; border-bottom: 1px solid #eee; }
    td::before { position: absolute; top: 12px; left: 12px; width: 45%; padding-right: 10px; white-space: nowrap; font-weight: bold; color: #106b28; }
    td[data-label="Vendor"]::before { content: "Vendor"; }
    td[data-label="Email"]::before { content: "Email"; }
    td[data-label="Amount"]::before { content: "Amount"; }
    td[data-label="Currency"]::before { content: "Currency"; }
    td[data-label="Status"]::before { content: "Status"; }
    td[data-label="Method"]::before { content: "Method"; }
    td[data-label="Reference"]::before { content: "Reference"; }
    td[data-label="Date"]::before { content: "Date"; }
  }
</style>
</head>
<body>
    
<div style="max-width:800px;margin:0 auto 20px;text-align:left;">
    <a href="admin_dashboard.php" 
       style="display:inline-block;width:100%;max-width:200px;padding:8px 12px;background:#106b28;color:#fff;border-radius:6px;text-decoration:none;font-weight:600;margin-bottom:10px;text-align:center;">
        ← Back to Dashboard
    </a>
</div>

<h2>📂 Platform Transactions</h2>

<form method="get" class="filters">
  <input type="text" name="search" placeholder="Search vendor or email..." value="<?= htmlspecialchars($search) ?>" />
  <select name="method">
    <option value="">All Methods</option>
    <option value="paystack" <?= strtolower($method_filter) == 'paystack' ? 'selected' : '' ?>>Paystack</option>
    <option value="flutterwave" <?= strtolower($method_filter) == 'flutterwave' ? 'selected' : '' ?>>Flutterwave</option>
    <option value="crypto" <?= strtolower($method_filter) == 'crypto' ? 'selected' : '' ?>>Crypto</option>
    <option value="manual" <?= strtolower($method_filter) == 'manual' ? 'selected' : '' ?>>Manual</option>
  </select>
  <select name="status">
    <option value="">All Statuses</option>
    <option value="successful" <?= strtolower($status_filter) == 'successful' ? 'selected' : '' ?>>Successful</option>
    <option value="failed" <?= strtolower($status_filter) == 'failed' ? 'selected' : '' ?>>Failed</option>
    <option value="pending" <?= strtolower($status_filter) == 'pending' ? 'selected' : '' ?>>Pending</option>
  </select>
  <button type="submit">Filter</button>
</form>

<table>
  <thead>
    <tr>
      <th>Vendor</th>
      <th>Email</th>
      <th>Amount</th>
      <th>Currency</th>
      <th>Status</th>
      <th>Method</th>
      <th>Reference</th>
      <th>Date</th>
    </tr>
  </thead>
  <tbody>
  <?php if ($result->num_rows === 0): ?>
    <tr><td colspan="8" style="text-align:center;">No transactions found.</td></tr>
  <?php else: ?>
    <?php while ($row = $result->fetch_assoc()): ?>
      <?php
          $orderData = getOrderReference($conn, (int)$row['vendor_id'], $row['tx_reference']);

          if ($orderData) {
              $display_amount = is_numeric($orderData['amount']) ? $orderData['amount'] : (is_numeric($row['tx_amount']) ? $row['tx_amount'] : 0);
              $display_currency = $orderData['currency'] ?: $row['tx_currency'];
              $display_status = $orderData['status'] ?: $row['tx_status'];
              $display_method = !empty($orderData['method']) ? $orderData['method'] : (!empty($row['tx_method']) ? $row['tx_method'] : 'Unknown');
              $display_tracking = $orderData['tracking_code'] ?? '';

              // Flutterwave ref formatting
              if (strtolower($display_method) === 'flutterwave' && !empty($orderData['flutterwave_ref'])) {
                  $final_ref = $orderData['flutterwave_ref'];
                  if (!empty($orderData['payment_ref'])) {
                      $final_ref .= " (" . $orderData['payment_ref'] . ")";
                  }
              } else {
                  $final_ref = !empty($orderData['payment_ref']) ? $orderData['payment_ref'] : ($orderData['transaction_ref_db'] ?? $row['tx_reference']);
              }

          } else {
              $display_amount = is_numeric($row['tx_amount']) ? $row['tx_amount'] : 0;
              $display_currency = $row['tx_currency'];
              $display_status = $row['tx_status'];
              $display_method = $row['tx_method'] ?: 'Unknown';
              $display_tracking = '';
              $final_ref = $row['tx_reference'];
          }

          $amount_str = getCurrencySymbol($display_currency) . number_format((float)$display_amount, 2);
      ?>
      <tr>
        <td data-label="Vendor"><?= htmlspecialchars($row['store_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
        <td data-label="Email"><?= htmlspecialchars($row['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
        <td data-label="Amount"><?= $amount_str ?></td>
        <td data-label="Currency"><?= htmlspecialchars(strtoupper((string)($display_currency ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
        <td data-label="Status" class="status-<?= htmlspecialchars($display_status ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <?= ucfirst(htmlspecialchars($display_status ?? '', ENT_QUOTES, 'UTF-8')) ?>
        </td>
        <td data-label="Method"><?= htmlspecialchars(ucfirst((string)($display_method ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
        <td data-label="Reference"><?= htmlspecialchars($final_ref ?? '', ENT_QUOTES, 'UTF-8') ?></td>
        <td data-label="Date"><?= date('M j, Y h:i A', strtotime($row['created_at'] ?? 'now')) ?></td>
      </tr>
    <?php endwhile; ?>
  <?php endif; ?>
</tbody>
</table>

<?php if ($total_pages > 1): ?>
  <div class="pagination" role="navigation" aria-label="Pagination">
    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
      <a href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>" class="<?= $p == $page ? 'current' : '' ?>"><?= $p ?></a>
    <?php endfor; ?>
  </div>
<?php endif; ?>

</body>
</html>