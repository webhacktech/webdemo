<?php
session_start();
if (!isset($_SESSION['is_admin'])) {
    header("Location: login.php");
    exit;
}
require_once '../config.php';

// Search
$search = $_GET['search'] ?? '';
$search_query = "";
if ($search) {
    $search = "%{$search}%";
    $stmt = $conn->prepare("SELECT id, fullname, email, status, kyc_status, created_at, email_verified, subscription_plan 
                            FROM vendors 
                            WHERE fullname LIKE ? OR email LIKE ? 
                            ORDER BY id DESC");
    $stmt->bind_param("ss", $search, $search);
} else {
    $stmt = $conn->prepare("SELECT id, fullname, email, status, kyc_status, created_at, email_verified, subscription_plan 
                            FROM vendors 
                            ORDER BY id DESC");
}
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Vendors - Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f4f6f9; margin: 0; padding: 20px; }
        h2 { color: #106b28; }
        .top-bar {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .top-bar input[type="text"] {
            padding: 10px;
            width: 250px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        .top-bar button, .bulk-action select {
            padding: 10px;
            border-radius: 5px;
            border: none;
            background: #106b28;
            color: white;
            cursor: pointer;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        th, td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            text-align: left;
        }
        th {
            background-color: #106b28;
            color: white;
        }
        .actions a {
            text-decoration: none;
            margin-right: 8px;
            font-size: 0.9rem;
        }
        .status-active { color: green; font-weight: bold; }
        .status-suspended { color: red; font-weight: bold; }
        .kyc-status {
            padding: 4px 8px;
            border-radius: 5px;
            font-weight: 600;
            display: inline-block;
        }
        .kyc-pending { background: #fff3cd; }
        .kyc-approved { background: #4caf50; color: white; }
        .kyc-rejected { background: #f44336; color: white; }
        .email-verified { color: green; font-weight: bold; }
        .email-unverified { color: red; font-weight: bold; }
        .bulk-action {
            margin-top: 10px;
        }
        .checkbox-col {
            text-align: center;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .plan-premium { color: #106b28; font-weight: bold; }
        .plan-basic { color: #9e9e9e; font-weight: bold; }
    </style>
</head>
<body>

<h2>Vendor Management</h2>

<?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
    <div class="alert-success">Action completed successfully.</div>
<?php endif; ?>

<div class="top-bar">
    <form method="GET">
        <input type="text" name="search" placeholder="Search name/email..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
        <button type="submit">Search</button>
    </form>
</div>

<form method="POST" action="bulk_vendor_action.php" onsubmit="return confirm('Are you sure you want to perform this action?')">
    <table>
        <thead>
            <tr>
                <th class="checkbox-col"><input type="checkbox" id="select-all"></th>
                <th>#</th>
                <th>Vendor Name</th>
                <th>Email</th>
                <th>Email Status</th>
                <th>Status</th>
                <th>KYC</th>
                <th>Plan</th>
                <th>Joined</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($result->num_rows): $i = 1; while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td class="checkbox-col">
                    <input type="checkbox" name="vendor_ids[]" value="<?= $row['id'] ?>">
                </td>
                <td><?= $i++ ?></td>
                <td><?= htmlspecialchars($row['fullname'] ?? '') ?></td>
                <td><?= htmlspecialchars($row['email'] ?? '') ?></td>
                <td>
                    <?php if (!empty($row['email_verified']) && $row['email_verified'] == 1): ?>
                        <span style="color: green;">Verified</span>
                        <a href="verify_vendor_email.php?vendor_id=<?= $row['id'] ?>&action=unverify" onclick="return confirm('Unverify this vendor?')">Unverify</a>
                    <?php else: ?>
                        <span style="color: red;">Not Verified</span>
                        <a href="verify_vendor_email.php?vendor_id=<?= $row['id'] ?>&action=verify" onclick="return confirm('Mark as verified?')">Mark as Verified</a>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($row['status'] === 'active'): ?>
                        <span class="status-active">Active</span>
                    <?php else: ?>
                        <span class="status-suspended">Suspended</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php
                        $kyc = strtolower($row['kyc_status']);
                        echo "<span class='kyc-status kyc-{$kyc}'>" . ucfirst($kyc) . "</span>";
                    ?>
                </td>
                <td>
                    <?php if ($row['subscription_plan'] === 'premium'): ?>
                        <span class="plan-premium">Premium</span>
                    <?php else: ?>
                        <span class="plan-basic">Free</span>
                    <?php endif; ?>
                </td>
                <td><?= date("d M Y", strtotime($row['created_at'])) ?></td>
                <td class="actions">
                    <a href="change_vendor_status.php?id=<?= $row['id'] ?>&action=<?= $row['status'] === 'active' ? 'suspend' : 'activate' ?>" style="color: #ff9800;">
                        <?= $row['status'] === 'active' ? 'Suspend' : 'Activate' ?>
                    </a>
                    <a href="reset_vendor_password.php?id=<?= $row['id'] ?>" style="color: #009688;">Reset Password</a>
                    <a href="view_vendor.php?id=<?= $row['id'] ?>" style="color: #106b28;">View Details</a>
                    <a href="toggle_plan.php?id=<?= $row['id'] ?>&plan=<?= $row['subscription_plan'] === 'premium' ? 'basic' : 'premium' ?>" style="color: #673ab7;">
                        Make <?= $row['subscription_plan'] === 'premium' ? 'Basic' : 'Premium' ?>
                    </a>
                    <a href="impersonate_vendor.php?id=<?= $row['id'] ?>" style="color: #2196f3;">Login as Vendor</a>
                    <a href="delete_vendor.php?id=<?= $row['id'] ?>" style="color: #f44336;" onclick="return confirm('Are you sure?')">Delete</a>
                </td>
            </tr>
        <?php endwhile; else: ?>
            <tr><td colspan="10">No vendors found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>

    <div class="bulk-action">
        <select name="bulk_action" required>
            <option value="">Bulk Actions</option>
            <option value="suspend">Suspend Selected</option>
            <option value="activate">Activate Selected</option>
            <option value="delete">Delete Selected</option>
        </select>
        <button type="submit">Apply</button>
    </div>
</form>

<script>
    document.getElementById("select-all").onclick = function() {
        const checkboxes = document.querySelectorAll('input[name="vendor_ids[]"]');
        for (let box of checkboxes) {
            box.checked = this.checked;
        }
    };
</script>

</body>
</html>