<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.php");
    exit;
}

// Handle Approve / Reject / Delete actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    if ($_GET['action'] === 'approve') {
        $conn->query("UPDATE product_reviews SET status='approved' WHERE id=$id");
    } elseif ($_GET['action'] === 'reject') {
        $conn->query("UPDATE product_reviews SET status='rejected' WHERE id=$id");
    } elseif ($_GET['action'] === 'delete') {
        $conn->query("DELETE FROM product_reviews WHERE id=$id");
    }
    $_SESSION['success_message'] = "Review updated successfully.";
    header("Location: admin_reviews.php");
    exit;
}

// Fetch reviews from product_reviews
$sql = "SELECT r.id, r.rating, r.review, r.status, r.created_at, 
               r.customer_name, r.customer_email,
               p.name AS product_name, v.store_name AS vendor_name
        FROM product_reviews r
        JOIN products p ON r.product_id = p.id
        JOIN vendors v ON r.vendor_id = v.id
        ORDER BY r.created_at DESC";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin - Manage Reviews</title>
<style>
body { font-family: Arial, sans-serif; margin:0; padding:0; background:#f4f6f8;}
.container { max-width:1200px; margin:auto; padding:20px; }
h1 { color:#106b28; }
table { width:100%; border-collapse: collapse; margin-top:20px; }
th, td { border:1px solid #ddd; padding:8px; text-align:left; }
th { background:#106b28; color:#fff; }
tr:nth-child(even) { background:#f9f9f9; }
a.button { padding:5px 10px; border-radius:5px; text-decoration:none; color:white; margin-right:5px;}
a.approve { background:green; }
a.reject { background:red; }
a.delete { background:#555; }
.success { padding:10px; background:#d1fae5; color:#065f46; border-radius:5px; margin-bottom:15px;}
@media(max-width:768px){
    table, thead, tbody, th, td, tr { display:block; }
    th { position:sticky; top:0; }
    td { padding-left:50%; position:relative; }
    td:before { position:absolute; left:10px; width:45%; white-space:nowrap; font-weight:bold; }
    td:nth-of-type(1):before { content:"ID"; }
    td:nth-of-type(2):before { content:"Customer"; }
    td:nth-of-type(3):before { content:"Email"; }
    td:nth-of-type(4):before { content:"Product"; }
    td:nth-of-type(5):before { content:"Vendor"; }
    td:nth-of-type(6):before { content:"Rating"; }
    td:nth-of-type(7):before { content:"Review"; }
    td:nth-of-type(8):before { content:"Status"; }
    td:nth-of-type(9):before { content:"Date"; }
    td:nth-of-type(10):before { content:"Actions"; }
}
</style>
</head>
<body>
<div class="container">
    <h1>Manage Reviews</h1>

    <?php if(isset($_SESSION['success_message'])): ?>
        <div class="success"><?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Customer</th>
                <th>Email</th>
                <th>Product</th>
                <th>Vendor</th>
                <th>Rating</th>
                <th>Review</th>
                <th>Status</th>
                <th>Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if($result && $result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= $row['id'] ?></td>
                    <td><?= htmlspecialchars($row['customer_name']) ?></td>
                    <td><?= htmlspecialchars($row['customer_email']) ?></td>
                    <td><?= htmlspecialchars($row['product_name']) ?></td>
                    <td><?= htmlspecialchars($row['vendor_name']) ?></td>
                    <td><?= $row['rating'] ?></td>
                    <td><?= htmlspecialchars($row['review']) ?></td>
                    <td><?= ucfirst($row['status']) ?></td>
                    <td><?= $row['created_at'] ?></td>
                    <td>
                        <a href="?action=approve&id=<?= $row['id'] ?>" class="button approve">Approve</a>
                        <a href="?action=reject&id=<?= $row['id'] ?>" class="button reject">Reject</a>
                        <a href="?action=delete&id=<?= $row['id'] ?>" class="button delete" onclick="return confirm('Are you sure?')">Delete</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="10">No reviews found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>