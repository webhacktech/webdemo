<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.php");
    exit();
}

// Handle Bulk Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_selected']) && !empty($_POST['selected_affiliates'])) {
    $idsToDelete = implode(',', array_map('intval', $_POST['selected_affiliates']));
    $conn->query("DELETE FROM affiliates WHERE id IN ($idsToDelete)");
    header("Location: admin_affiliates.php");
    exit();
}

// Handle Approve/Block/Unblock/Delete
if (isset($_GET['action'], $_GET['id'])) {
    $id = (int) $_GET['id'];
    if ($_GET['action'] === 'approve') {
        $conn->query("UPDATE affiliates SET email_verified = 1 WHERE id = $id");
    } elseif ($_GET['action'] === 'block') {
        $conn->query("UPDATE affiliates SET status = 'blocked' WHERE id = $id");
    } elseif ($_GET['action'] === 'unblock') {
        $conn->query("UPDATE affiliates SET status = 'active' WHERE id = $id");
    } elseif ($_GET['action'] === 'delete') {
        $conn->query("DELETE FROM affiliates WHERE id = $id");
    }
    header("Location: admin_affiliates.php");
    exit();
}

// Search/Filter logic
$search = $_GET['search'] ?? '';
$filter_status = $_GET['status'] ?? '';

$query = "SELECT * FROM affiliates WHERE 1";
if ($search !== '') {
    $escaped = $conn->real_escape_string($search);
    $query .= " AND (email LIKE '%$escaped%' OR referral_code LIKE '%$escaped%')";
}
if ($filter_status !== '') {
    $escaped = $conn->real_escape_string($filter_status);
    $query .= " AND status = '$escaped'";
}
$query .= " ORDER BY id DESC";
$stmt = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Affiliate Users - Admin Dashboard</title>
    <link rel="stylesheet" href="admin_styles.css">
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            padding: 20px;
            background: #f7f7f7;
        }
        h1 {
            margin-bottom: 10px;
        }
        .top-controls {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            align-items: center;
            margin-bottom: 15px;
        }
        .back-btn {
            padding: 8px 15px;
            background: #333;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }
        .search-filter-form input,
        .search-filter-form select {
            padding: 6px;
            margin-right: 8px;
            border-radius: 4px;
            border: 1px solid #ccc;
        }
        .search-filter-form button {
            padding: 6px 12px;
            border: none;
            background: #007bff;
            color: white;
            border-radius: 4px;
            cursor: pointer;
        }
        .affiliate-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border: 1px solid #ddd;
        }
        .affiliate-table th,
        .affiliate-table td {
            border: 1px solid #ddd;
            padding: 10px;
        }
        .affiliate-table th {
            background-color: #f5f5f5;
        }
        .badge {
            padding: 3px 8px;
            border-radius: 5px;
            font-size: 12px;
            color: #fff;
        }
        .badge.active { background-color: #28a745; }
        .badge.blocked { background-color: #dc3545; }
        .badge.unverified { background-color: #ffc107; color: #000; }
        .action-links a {
            margin-right: 8px;
            text-decoration: none;
            font-size: 13px;
        }
        .action-links a.approve { color: green; }
        .action-links a.block { color: red; }
        .action-links a.unblock { color: orange; }
        .action-links a.delete { color: #dc3545; }
        .delete-btn {
            padding: 6px 12px;
            background-color: #dc3545;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
    </style>
</head>
<body>

<div class="admin-container">
    <h1>Affiliate Users</h1>

    <div class="top-controls">
        <a href="admin_dashboard.php" class="back-btn">← Back to Admin Dashboard</a>

        <form method="get" class="search-filter-form">
            <input type="text" name="search" placeholder="Search email or code" value="<?= htmlspecialchars($search) ?>">
            <select name="status">
                <option value="">All Status</option>
                <option value="active" <?= $filter_status === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="blocked" <?= $filter_status === 'blocked' ? 'selected' : '' ?>>Blocked</option>
            </select>
            <button type="submit">Filter</button>
        </form>

        <form method="post" onsubmit="return confirm('Are you sure you want to delete selected affiliates?');">
            <button type="submit" name="delete_selected" class="delete-btn">Delete Selected</button>
        </form>
    </div>

    <form method="post">
        <table class="affiliate-table">
            <thead>
                <tr>
                    <th><input type="checkbox" onclick="toggleAll(this)"></th>
                    <th>#</th>
                    <th>Email</th>
                    <th>Referral Code</th>
                    <th>Commission</th>
                    <th>Joined</th>
                    <th>Status</th>
                    <th>Verified</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $count = 1;
            while ($row = $stmt->fetch_assoc()):
                $status = $row['status'] ?? 'active';
                $verified = $row['email_verified'] ?? 0;
                $commission = isset($row['commission_earned']) ? number_format($row['commission_earned'], 2) : '0.00';
            ?>
                <tr>
                    <td><input type="checkbox" name="selected_affiliates[]" value="<?= $row['id'] ?>"></td>
                    <td><?= $count++ ?></td>
                    <td><?= htmlspecialchars($row['email']) ?></td>
                    <td><?= htmlspecialchars($row['referral_code']) ?></td>
                    <td>₦<?= $commission ?></td>
                    <td><?= date('Y-m-d H:i', strtotime($row['joined_at'] ?? $row['created_at'] ?? 'now')) ?></td>
                    <td>
                        <span class="badge <?= $status === 'blocked' ? 'blocked' : 'active' ?>">
                            <?= ucfirst($status) ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge <?= $verified ? 'active' : 'unverified' ?>">
                            <?= $verified ? 'Verified' : 'Unverified' ?>
                        </span>
                    </td>
                    <td class="action-links">
                        <?php if (!$verified): ?>
                            <a href="?action=approve&id=<?= $row['id'] ?>" class="approve">Approve</a>
                        <?php endif; ?>

                        <?php if ($status === 'blocked'): ?>
                            <a href="?action=unblock&id=<?= $row['id'] ?>" class="unblock">Unblock</a>
                        <?php else: ?>
                            <a href="?action=block&id=<?= $row['id'] ?>" class="block">Block</a>
                        <?php endif; ?>

                        <a href="?action=delete&id=<?= $row['id'] ?>" class="delete" onclick="return confirm('Delete this affiliate?')">Delete</a>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </form>
</div>

<script>
function toggleAll(source) {
    const checkboxes = document.querySelectorAll('input[type="checkbox"][name=\"selected_affiliates[]\"]');
    checkboxes.forEach(cb => cb.checked = source.checked);
}
</script>

</body>
</html>