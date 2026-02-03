<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.php");
    exit();
}

// Handle deletion FIRST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'delete' && isset($_POST['ids'])) {
    $ids = array_map('intval', $_POST['ids']);
    $ids_str = implode(',', $ids);
    $conn->query("DELETE FROM affiliate_withdrawal_requests WHERE id IN ($ids_str)");
    header("Location: admin_affiliate_withdrawals.php");
    exit;
}

// Handle approve/reject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['ids'])) {
    $ids = array_map('intval', $_POST['ids']);
    $action = $_POST['action'] === 'approve' ? 'approved' : 'rejected';
    $ids_str = implode(',', $ids);
    $conn->query("UPDATE affiliate_withdrawal_requests SET status='$action' WHERE id IN ($ids_str)");
    header("Location: admin_affiliate_withdrawals.php");
    exit;
}

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment;filename=withdrawals.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Affiliate Email', 'Amount', 'Method', 'Details', 'Status', 'Date']);

    $query = "
        SELECT r.*, a.email 
        FROM affiliate_withdrawal_requests r 
        JOIN affiliates a ON r.affiliate_id = a.id 
        ORDER BY r.created_at DESC";
    $result = $conn->query($query);

    while ($row = $result->fetch_assoc()) {
        $details = $row['method'] === 'bank'
    ? "Bank: " . ($row['bank_name'] ?? '') . ", Acct No: " . ($row['account_number'] ?? '') . ", Acct Name: " . ($row['account_name'] ?? '')
    : ($row['method'] === 'usdt' ? "USDT Wallet: " . ($row['crypto_address'] ?? '') : "N/A");

fputcsv($output, [
    $row['email'],
    $row['amount'],
    $row['method'],
    $details,
    $row['status'],
    $row['created_at']
]);
    }

    fclose($output);
    exit;
}

// Handle filters
$where = [];
if (!empty($_GET['email'])) {
    $email = $conn->real_escape_string($_GET['email']);
    $where[] = "a.email LIKE '%$email%'";
}
if (!empty($_GET['status']) && in_array($_GET['status'], ['pending', 'approved', 'rejected'])) {
    $status = $conn->real_escape_string($_GET['status']);
    $where[] = "r.status = '$status'";
}
$where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$query = "
    SELECT r.*, a.email 
    FROM affiliate_withdrawal_requests r 
    JOIN affiliates a ON r.affiliate_id = a.id 
    $where_clause
    ORDER BY r.created_at DESC";

$result = $conn->query($query);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Affiliate Withdrawals - Admin</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f9f9f9; }
        h2 { margin-bottom: 20px; }
        table { border-collapse: collapse; width: 100%; background: #fff; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background: #333; color: #fff; }
        .actions form { display: inline; }
        .btn { padding: 6px 12px; border: none; background: #444; color: #fff; cursor: pointer; }
        .btn:hover { opacity: 0.85; }
        .filter-form { margin-bottom: 20px; }
        .filter-form input, .filter-form select { padding: 6px; margin-right: 10px; }
        .bulk-actions { margin-bottom: 10px; }
        .back-btn { margin-top: 20px; display: inline-block; }
    </style>
</head>
<body>
    <h2>Affiliate Withdrawal Requests</h2>

    <form class="filter-form" method="get">
        <input type="text" name="email" placeholder="Search by email" value="<?= htmlspecialchars($_GET['email'] ?? '') ?>">
        <select name="status">
            <option value="">All Statuses</option>
            <option value="pending" <?= ($_GET['status'] ?? '') == 'pending' ? 'selected' : '' ?>>Pending</option>
            <option value="approved" <?= ($_GET['status'] ?? '') == 'approved' ? 'selected' : '' ?>>Approved</option>
            <option value="rejected" <?= ($_GET['status'] ?? '') == 'rejected' ? 'selected' : '' ?>>Rejected</option>
        </select>
        <button class="btn" type="submit">Filter</button>
        <a href="admin_affiliate_withdrawals.php" class="btn">Reset</a>
        <a href="?export=csv" class="btn" style="background: green;">Export CSV</a>
    </form>

    <form method="post">
        <div class="bulk-actions">
            <button class="btn" type="submit" name="action" value="approve">Bulk Approve</button>
            <button class="btn" type="submit" name="action" value="reject" style="background:red;">Bulk Reject</button>
        </div>

        <table>
            <thead>
                <tr>
                    <th><input type="checkbox" onclick="toggleCheckboxes(this)"></th>
                    <th>Email</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Details</th>
                    <th>Status</th>
                    <th>Requested At</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><input type="checkbox" name="ids[]" value="<?= $row['id'] ?>"></td>
                            <td><?= htmlspecialchars($row['email']) ?></td>
                            <td>₦<?= number_format($row['amount'], 2) ?></td>
                            <td><?= htmlspecialchars($row['method']) ?></td>
                            <td>
<?php
$method = $row['method'];
if ($method === 'bank') {
    echo "Bank: " . htmlspecialchars($row['bank_name'] ?? '') . "<br>" .
         "Acct No: " . htmlspecialchars($row['account_number'] ?? '') . "<br>" .
         "Acct Name: " . htmlspecialchars($row['account_name'] ?? '');
} elseif ($method === 'usdt') {
    echo "USDT Wallet: " . htmlspecialchars($row['crypto_address'] ?? '');
} else {
    echo "N/A";
}
?>
</td>
                            <td><?= ucfirst($row['status']) ?></td>
                            <td><?= date('Y-m-d H:i', strtotime($row['created_at'])) ?></td>
                            <td class="actions">
                                <?php if ($row['status'] === 'pending'): ?>
                                    <form method="post" style="display:inline">
                                        <input type="hidden" name="ids[]" value="<?= $row['id'] ?>">
                                        <button class="btn" name="action" value="approve">Approve</button>
                                    </form>
                                    <form method="post" style="display:inline">
                                        <input type="hidden" name="ids[]" value="<?= $row['id'] ?>">
                                        <button class="btn" name="action" value="reject" style="background:red;">Reject</button>
                                    </form>
                                    <form method="post" style="display:inline" onsubmit="return confirm('Are you sure you want to delete this withdrawal request?');">
    <input type="hidden" name="ids[]" value="<?= $row['id'] ?>">
    <button class="btn" name="action" value="delete" style="background:#cc0000;">Delete</button>
</form>
                                <?php else: ?>
                                    <?= ucfirst($row['status']) ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="8">No withdrawal requests found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </form>

    <a href="admin_dashboard.php" class="btn back-btn">← Back to Dashboard</a>

    <script>
        function toggleCheckboxes(master) {
            const checkboxes = document.querySelectorAll('input[type="checkbox"][name="ids[]"]');
            checkboxes.forEach(cb => cb.checked = master.checked);
        }
    </script>
</body>
</html>