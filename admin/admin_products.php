<?php
session_start();
require_once '../config.php';
require_once 'admin_auth.php';

$admin_id = $_SESSION['admin_id'] ?? 0;
$limit = 20;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

$conditions = [];
$params = [];
$types = '';

if ($status_filter) {
    $conditions[] = "p.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if (!empty($search)) {
    $conditions[] = "(p.product_name LIKE ? OR v.store_name LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $types .= 'ss';
}

$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$query = "
    SELECT p.id, p.product_name, p.slug, p.image_url, p.status, p.created_at,
           v.store_name, v.slug AS store_slug
    FROM products p
    LEFT JOIN vendors v ON p.vendor_id = v.id
    $where
    ORDER BY p.created_at DESC
    LIMIT ? OFFSET ?
";

$params[] = $limit;
$params[] = $offset;
$types .= 'ii';

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin - Products</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: 'Segoe UI', sans-serif; margin: 0; padding: 20px; background: #f4f6f9; }
        h2 { color: #106b28; margin-bottom: 20px; }

        .filter-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }

        input[type="text"], select {
            padding: 10px;
            font-size: 14px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        button, .btn {
            padding: 10px 15px;
            background: #106b28;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            cursor: pointer;
        }

        .product-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
        }

        .product-card {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
        }

        .product-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        .product-info {
            padding: 15px;
        }

        .product-info h3 {
            margin: 0 0 10px;
            font-size: 18px;
            color: #333;
        }

        .product-info p {
            margin: 4px 0;
            font-size: 14px;
            color: #555;
        }

        .actions {
            margin-top: 10px;
        }

        .actions a {
            margin-right: 8px;
            text-decoration: none;
            font-size: 13px;
            color: #106b28;
        }

        .pagination {
            margin-top: 30px;
            text-align: center;
        }

        .pagination a {
            margin: 0 4px;
            text-decoration: none;
            color: #106b28;
        }

        .bulk-actions {
            margin-top: 20px;
        }

        .checkbox-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
        }
    </style>
</head>
<body>

<h2>🛒 Product Management</h2>

<form method="get" class="filter-bar">
    <input type="text" name="search" placeholder="Search product or store..." value="<?= htmlspecialchars($search) ?>">
    <select name="status">
        <option value="">All Statuses</option>
        <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
        <option value="suspended" <?= $status_filter === 'suspended' ? 'selected' : '' ?>>Suspended</option>
    </select>
    <button type="submit">🔍 Filter</button>
</form>

<form method="post" action="bulk_product_action.php" onsubmit="return confirm('Apply bulk action to selected products?')">
    <div class="bulk-actions checkbox-wrap">
        <select name="action" required>
            <option value="">Bulk Actions</option>
            <option value="suspend">Suspend Selected</option>
            <option value="activate">Activate Selected</option>
            <option value="delete">Delete Selected</option>
        </select>
        <button type="submit">Apply</button>
    </div>

    <div class="product-list">
        <?php while ($row = $result->fetch_assoc()): ?>
            <?php
            $image_file = $row['image_url'] ?? '';
            $image_path = (!empty($image_file) && file_exists("../upload/" . basename($image_file)))
                ? "../upload/" . basename($image_file)
                : "https://via.placeholder.com/300x200?text=No+Image";
            ?>
            <div class="product-card">
                <img src="<?= htmlspecialchars($image_path) ?>" alt="<?= htmlspecialchars($row['product_name']) ?>">
                <div class="product-info">
                    <div class="checkbox-wrap">
                        <input type="checkbox" name="product_ids[]" value="<?= $row['id'] ?>">
                        <h3><?= htmlspecialchars($row['product_name']) ?></h3>
                    </div>
                    <p><strong>Vendor:</strong> <?= htmlspecialchars($row['store_name']) ?></p>
                    <p><strong>Status:</strong> <?= ucfirst($row['status']) ?></p>
                    <p><strong>Date:</strong> <?= date('M j, Y', strtotime($row['created_at'])) ?></p>
                    <div class="actions">
                        <a href="../product_view.php?slug=<?= urlencode($row['slug']) ?>" target="_blank">🔎 Preview</a>
                        <a href="product_status_action.php?id=<?= $row['id'] ?>&action=<?= $row['status'] === 'active' ? 'suspend' : 'activate' ?>">
                            <?= $row['status'] === 'active' ? '🚫 Suspend' : '✅ Activate' ?>
                        </a>
                        <a href="product_delete.php?id=<?= $row['id'] ?>" onclick="return confirm('Delete this product?')">🗑️ Delete</a>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
</form>

<?php
// Pagination logic
$count_query = "
    SELECT COUNT(*)
    FROM products p
    LEFT JOIN vendors v ON p.vendor_id = v.id
    $where
";
$count_stmt = $conn->prepare($count_query);
if ($conditions) {
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
            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" <?= $i == $page ? 'style="font-weight:bold;text-decoration:underline;"' : '' ?>><?= $i ?></a>
        <?php endfor; ?>
    </div>
<?php endif; ?>

</body>
</html>