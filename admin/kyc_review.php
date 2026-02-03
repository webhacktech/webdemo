<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require '../config.php';
require 'admin_auth.php';

$admin_id = $_SESSION['admin_id'] ?? 0;
$log_stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action, created_at) VALUES (?, 'Viewed KYC review page', NOW())");
if ($log_stmt) {
    $log_stmt->bind_param("i", $admin_id);
    $log_stmt->execute();
    $log_stmt->close();
}

// Handle POST for rejection comment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_kyc_id'])) {
    $reject_id = intval($_POST['reject_kyc_id']);
    $comment = trim($_POST['admin_comment'] ?? '');

    // Update KYC status to rejected and save comment
    $update_stmt = $conn->prepare("UPDATE vendors SET kyc_status = 'rejected' WHERE id = ?");
    $update_stmt->bind_param("i", $reject_id);
    $update_stmt->execute();
    $update_stmt->close();

    // Insert or update comment
    $check_comment = $conn->prepare("SELECT id FROM kyc_comments WHERE vendor_id = ?");
    $check_comment->bind_param("i", $reject_id);
    $check_comment->execute();
    $check_comment->store_result();
    if ($check_comment->num_rows > 0) {
        // update
        $check_comment->bind_result($comment_id);
        $check_comment->fetch();
        $update_comment = $conn->prepare("UPDATE kyc_comments SET comment = ?, updated_at = NOW() WHERE id = ?");
        $update_comment->bind_param("si", $comment, $comment_id);
        $update_comment->execute();
        $update_comment->close();
    } else {
        // insert
        $insert_comment = $conn->prepare("INSERT INTO kyc_comments (vendor_id, comment, created_at) VALUES (?, ?, NOW())");
        $insert_comment->bind_param("is", $reject_id, $comment);
        $insert_comment->execute();
        $insert_comment->close();
    }
    $check_comment->close();

    header("Location: kyc_review.php");
    exit;
}

// Filters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';

$conditions = ["kyc_document IS NOT NULL"];
$params = [];
$types = '';

if (!empty($search)) {
    $conditions[] = "LOWER(store_name) LIKE ?";
    $params[] = '%' . strtolower($search) . '%';
    $types .= 's';
}
if (in_array($status_filter, ['pending', 'approved', 'rejected'])) {
    $conditions[] = "kyc_status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
$query = "SELECT v.id, v.store_name, v.email, v.kyc_status, v.kyc_document_type, v.kyc_document, v.settlement_bank_name, v.settlement_account_name, v.settlement_account_number, kc.comment
          FROM vendors v
          LEFT JOIN kyc_comments kc ON v.id = kc.vendor_id
          $where
          ORDER BY v.id DESC";
$stmt = $conn->prepare($query);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>KYC Review - Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f2f3f7;
            margin: 0;
            padding: 20px;
        }
        h2 {
            color: #106b28;
            text-align: center;
            margin-bottom: 20px;
        }
        .filters {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
            margin-bottom: 30px;
        }
        .filters input, .filters select, .filters button {
            padding: 10px;
            font-size: 14px;
            border-radius: 6px;
            border: 1px solid #ccc;
        }
        button {
            background-color: #106b28;
            color: #fff;
            border: none;
            cursor: pointer;
        }

        .kyc-card {
            background: #fff;
            border: 1.8px solid #ccc;
            border-radius: 10px;
            padding: 20px 25px;
            margin-bottom: 25px;
            box-shadow: 0 3px 8px rgba(0,0,0,0.06);
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }
        .kyc-card h3 {
            margin: 0 0 15px;
            color: #106b28;
            font-weight: 700;
            font-size: 1.4em;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .status-badge {
            padding: 5px 14px;
            font-size: 13px;
            font-weight: 600;
            border-radius: 20px;
            color: #fff;
            white-space: nowrap;
        }
        .status-pending { background: #ffc107; color: #3a3a00; }
        .status-approved { background: #28a745; }
        .status-rejected { background: #dc3545; }

        .kyc-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 18px 40px;
        }
        .detail-label {
            font-weight: 600;
            color: #555;
            margin-bottom: 3px;
            display: block;
        }
        .detail-value {
            color: #222;
            font-size: 0.95em;
            word-wrap: break-word;
        }

        .view-doc {
            color: #106b28;
            text-decoration: underline;
            font-weight: 600;
            cursor: pointer;
        }
        .view-doc:hover {
            text-decoration: none;
        }

        .action-buttons {
            margin-top: 22px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .approve-btn, .reject-btn {
            padding: 10px 18px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 0.9em;
            border: none;
            cursor: pointer;
            flex: 1 1 120px;
            transition: background-color 0.2s ease;
        }
        .approve-btn {
            background-color: #28a745;
            color: #fff;
        }
        .approve-btn:hover {
            background-color: #218838;
        }
        .reject-btn {
            background-color: #dc3545;
            color: #fff;
        }
        .reject-btn:hover {
            background-color: #b02a37;
        }

        /* Admin comment box */
        .comment-section {
            margin-top: 25px;
            border-top: 1.5px solid #eee;
            padding-top: 15px;
        }
        .comment-section label {
            display: block;
            font-weight: 600;
            margin-bottom: 6px;
            color: #444;
        }
        .comment-section textarea {
            width: 100%;
            min-height: 80px;
            padding: 10px 12px;
            border-radius: 6px;
            border: 1.5px solid #ccc;
            font-size: 14px;
            resize: vertical;
        }

        /* Responsive */
        @media (max-width: 600px) {
            .kyc-details {
                grid-template-columns: 1fr;
            }
            .action-buttons {
                flex-direction: column;
            }
            .approve-btn, .reject-btn {
                flex: 1 1 auto;
            }
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

<h2>ðŸ“‹ KYC Review</h2>

<form method="get" class="filters">
    <input type="text" name="search" placeholder="Search store..." value="<?= htmlspecialchars($search) ?>">
    <select name="status">
        <option value="">-- All Status --</option>
        <option value="pending" <?= $status_filter == 'pending' ? 'selected' : '' ?>>Pending</option>
        <option value="approved" <?= $status_filter == 'approved' ? 'selected' : '' ?>>Approved</option>
        <option value="rejected" <?= $status_filter == 'rejected' ? 'selected' : '' ?>>Rejected</option>
    </select>
    <button type="submit">Filter</button>
</form>

<?php if ($result->num_rows > 0): ?>
    <?php while ($row = $result->fetch_assoc()): ?>
        <div class="kyc-card">
            <h3>
                <?= htmlspecialchars($row['store_name']) ?>
                <span class="status-badge status-<?= $row['kyc_status'] ?>">
                    <?= ucfirst($row['kyc_status']) ?>
                </span>
            </h3>
            <div class="kyc-details">
                <div>
                    <span class="detail-label">Email</span>
                    <span class="detail-value"><?= htmlspecialchars($row['email']) ?></span>
                </div>
                <div>
                    <span class="detail-label">Bank Name</span>
                    <span class="detail-value"><?= htmlspecialchars($row['settlement_bank_name']) ?></span>
                </div>
                <div>
                    <span class="detail-label">Account Name</span>
                    <span class="detail-value"><?= htmlspecialchars($row['settlement_account_name']) ?></span>
                </div>
                <div>
                    <span class="detail-label">Account Number</span>
                    <span class="detail-value"><?= htmlspecialchars($row['settlement_account_number']) ?></span>
                </div>
                <div>
                    <span class="detail-label">Document Type</span>
                    <span class="detail-value"><?= htmlspecialchars($row['kyc_document_type']) ?></span>
                </div>
                <div>
                    <span class="detail-label">Uploaded Document</span><br>
                    <?php if (!empty($row['kyc_document'])): ?>
                        <a href="../<?= htmlspecialchars($row['kyc_document']) ?>" target="_blank" class="view-doc">View Document</a>
                    <?php else: ?>
                        <span class="detail-value">N/A</span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($row['kyc_status'] === 'pending'): ?>
                <form method="POST" class="comment-section" onsubmit="return confirm('Reject with comment?');">
                    <label for="admin_comment_<?= $row['id'] ?>">Reject Comment (required if rejecting):</label>
                    <textarea id="admin_comment_<?= $row['id'] ?>" name="admin_comment" placeholder="Enter rejection reason..."></textarea>

                    <div class="action-buttons" style="margin-top: 10px;">
                        <button type="submit" name="reject_kyc_id" value="<?= $row['id'] ?>" class="reject-btn">Reject KYC</button>
                        <a href="kyc_action.php?id=<?= $row['id'] ?>&action=approve" class="approve-btn" onclick="return confirm('Approve this KYC?')">Approve KYC</a>
                    </div>
                </form>
            <?php else: ?>
                <?php if (!empty($row['comment'])): ?>
                    <div class="comment-section" style="background: #fff4f4; border-left: 5px solid #dc3545; margin-top: 20px; padding: 12px 16px; border-radius: 6px;">
                        <strong>Rejection Comment:</strong>
                        <p><?= nl2br(htmlspecialchars($row['comment'])) ?></p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endwhile; ?>
<?php else: ?>
    <p style="text-align:center; font-size:1.1em; color:#666;">No KYC submissions found.</p>
<?php endif; ?>

</body>
</html>