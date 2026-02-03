<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.php");
    exit();
}

// Filters
$search = $_GET['search'] ?? '';

$sql = "
SELECT r.id, r.affiliate_id, r.product_id, r.amount, r.created_at, 
       a.email AS affiliate_email, p.name AS product_name
FROM affiliate_referrals r
LEFT JOIN affiliates a ON r.affiliate_id = a.id
LEFT JOIN products p ON r.product_id = p.id
";

if (!empty($search)) {
    $sql .= " WHERE a.email LIKE '%$search%' OR p.name LIKE '%$search%'";
}

$sql .= " ORDER BY r.created_at DESC";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Affiliate Referrals - Admin Dashboard</title>
    <link rel="stylesheet" href="admin_styles.css"> <!-- Link to your admin CSS -->
    <style>
        body {
            font-family: Arial, sans-serif;
        }

        .container {
            padding: 30px;
        }

        .referral-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .referral-table th, .referral-table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }

        .referral-table th {
            background-color: #f5f5f5;
        }

        .search-bar {
            margin-bottom: 20px;
        }

        .back-btn {
            display: inline-block;
            margin-top: 20px;
            padding: 8px 16px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }

        .back-btn:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>

<div class="container">
    <h1>Affiliate Referral Transactions</h1>

    <form method="get" class="search-bar">
        <input type="text" name="search" placeholder="Search by email or product..." value="<?= htmlspecialchars($search) ?>" />
        <button type="submit">Search</button>
    </form>

    <table class="referral-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Affiliate Email</th>
                <th>Product Name</th>
                <th>Commission Earned</th>
                <th>Referral Date</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result->num_rows > 0): 
                $i = 1;
                while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><?= htmlspecialchars($row['affiliate_email']) ?></td>
                        <td><?= htmlspecialchars($row['product_name']) ?></td>
                        <td>₦<?= number_format($row['amount'], 2) ?></td>
                        <td><?= date("Y-m-d H:i", strtotime($row['created_at'])) ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="5">No referrals found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <a href="admin_dashboard.php" class="back-btn">← Back to Dashboard</a>
</div>

</body>
</html>