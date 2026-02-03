<?php
session_start();
require_once '../config.php';
require_once 'admin_auth.php';
require_once '../email_config.php'; // for sendEmail()

$admin_id = $_SESSION['admin_id'] ?? 0;
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Log page visit
$log = $conn->prepare("INSERT INTO admin_logs (admin_id, action, created_at) VALUES (?, 'Viewed referral withdrawals', NOW())");
$log->bind_param("i", $admin_id);
$log->execute();
$log->close();

// Filter logic
$conditions = ["rw.status IS NOT NULL"];
$params = [];
$types = "";

if (!empty($search)) {
    $conditions[] = "v.store_name LIKE ?";
    $params[] = "%" . $search . "%";
    $types .= "s";
}
if (!empty($status)) {
    $conditions[] = "rw.status = ?";
    $params[] = $status;
    $types .= "s";
}
$where = "WHERE " . implode(" AND ", $conditions);

// Query
$query = "
    SELECT rw.*, v.store_name 
    FROM referral_withdrawals rw
    LEFT JOIN vendors v ON rw.vendor_id = v.id
    $where
    ORDER BY rw.requested_at DESC
    LIMIT ? OFFSET ?
";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin - Referral Withdrawals</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f5f5f5; padding: 20px; }
        h2 { color: #106b28; margin-bottom: 20px; }

        .filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }
        input[type="text"], select, textarea {
            padding: 8px;
            font-size: 14px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        button {
            padding: 8px 14px;
            background: #106b28;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        table {
            width: 100%;
            background: white;
            border-collapse: collapse;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
        }

        th, td {
            padding: 10px;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }

        th {
            background: #106b28;
            color: white;
        }

        .actions form {
            display: inline;
        }

        .pagination {
            margin-top: 20px;
            text-align: center;
        }

        .pagination a {
            padding: 6px 10px;
            margin: 0 4px;
            background: #eee;
            border-radius: 4px;
            text-decoration: none;
        }

        .pagination a.active {
            background: #106b28;
            color: white;
        }

        .comment-box {
            margin-top: 5px;
        }

        .comment-box textarea {
            width: 100%;
            height: 50px;
            resize: vertical;
        }

        .status-approved { color: green; font-weight: bold; }
        .status-rejected { color: red; font-weight: bold; }
        .status-pending { color: orange; font-weight: bold; }
    </style>
</head>
<body>

<h2>💸 Referral Withdrawal Requests</h2>

<form method="get" class="filter-bar">
    <input type="text" name="search" placeholder="Search vendor store..." value="<?= htmlspecialchars($search) ?>">
    <select name="status">
        <option value="">All Statuses</option>
        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
        <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Approved</option>
        <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
    </select>
    <button type="submit">Filter</button>
</form>

<table>
    <thead>
        <tr>
            <th>Store Name</th>
            <th>Amount (₦)</th>
            <th>Status</th>
            <th>Requested</th>
            <th>Reviewed</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php while($row = $result->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($row['store_name']) ?></td>
            <td>₦<?= number_format($row['amount'], 2) ?></td>
            <td class="status-<?= $row['status'] ?>"><?= ucfirst($row['status']) ?></td>
            <td><?= date('M j, Y H:i', strtotime($row['requested_at'])) ?></td>
            <td><?= $row['reviewed_at'] ? date('M j, Y H:i', strtotime($row['reviewed_at'])) : '—' ?></td>
            <td class="actions">
                <?php if ($row['status'] === 'pending'): ?>
                    <form method="post" action="referral_withdrawal_action.php" style="display:inline;">
                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                        <input type="hidden" name="action" value="approve">
                        <button type="submit">✅ Approve</button>
                    </form>
                    <form method="post" action="referral_withdrawal_action.php">
                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                        <input type="hidden" name="action" value="reject">
                        <div class="comment-box">
                            <textarea name="comment" placeholder="Reason for rejection..." required></textarea>
                            <button type="submit" style="background:red;">❌ Reject</button>
                        </div>
                    </form>
                <?php else: ?>
                    <?php
                        $commentQ = $conn->prepare("SELECT comment FROM referral_withdrawal_comments WHERE withdrawal_id = ? ORDER BY created_at DESC LIMIT 1");
                        $commentQ->bind_param("i", $row['id']);
                        $commentQ->execute();
                        $commentQ->bind_result($comment);
                        $commentQ->fetch();
                        if ($comment): ?>
                            <div><strong>Comment:</strong> <?= htmlspecialchars($comment) ?></div>
                    <?php endif; $commentQ->close(); ?>
                <?php endif; ?>
            </td>
        </tr>
    <?php endwhile; ?>
    </tbody>
</table>

<?php
// Pagination
$count_query = "SELECT COUNT(*) FROM referral_withdrawals rw LEFT JOIN vendors v ON rw.vendor_id = v.id $where";
$count_stmt = $conn->prepare($count_query);
if (!empty($conditions)) {
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
        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" class="<?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>

</body>
</html>