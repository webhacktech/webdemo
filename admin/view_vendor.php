<?php
session_start();
if (!isset($_SESSION['is_admin'])) {
    header("Location: login.php");
    exit;
}

require_once '../config.php';

$vendor_id = $_GET['id'] ?? 0;
if (!$vendor_id) {
    echo "Invalid vendor ID.";
    exit;
}

// Get vendor details
$stmt = $conn->prepare("SELECT * FROM vendors WHERE id = ?");
$stmt->bind_param("i", $vendor_id);
$stmt->execute();
$vendor = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$vendor) {
    echo "Vendor not found.";
    exit;
}

// Get referred vendors
$referral_code = strtolower(str_replace(' ', '', $vendor['store_name']));
$referred_stmt = $conn->prepare("SELECT * FROM vendors WHERE LOWER(REPLACE(referrer_code, ' ', '')) = ?");
$referred_stmt->bind_param("s", $referral_code);
$referred_stmt->execute();
$referred_vendors = $referred_stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Vendor Details</title>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f4f6f8;
            padding: 30px;
        }
        .container {
            max-width: 900px;
            margin: auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        h2 { color: #106b28; }
        .info-group {
            margin-bottom: 20px;
        }
        .info-label {
            font-weight: bold;
            color: #555;
        }
        .value {
            color: #222;
            margin-top: 5px;
        }
        table {
            width: 100%;
            margin-top: 30px;
            border-collapse: collapse;
            background: #fff;
        }
        th, td {
            padding: 12px;
            border-bottom: 1px solid #eee;
            text-align: left;
        }
        th {
            background: #106b28;
            color: white;
        }
        tr:hover {
            background-color: #f1f1f1;
        }
        .back-link {
            display: inline-block;
            margin-top: 30px;
            text-decoration: none;
            color: #106b28;
            font-weight: bold;
        }
        .kyc-status {
            padding: 4px 8px;
            border-radius: 5px;
            font-weight: 600;
        }
        .kyc-pending { background: #ffeb3b; }
        .kyc-approved { background: #4caf50; color: white; }
        .kyc-rejected { background: #f44336; color: white; }
    </style>
</head>
<body>
<div class="container">
    <h2>👤 Vendor Details</h2>

    <div class="info-group">
        <div class="info-label">Full Name:</div>
        <div class="value"><?= htmlspecialchars($vendor['fullname']) ?></div>
    </div>

    <div class="info-group">
        <div class="info-label">Email:</div>
        <div class="value"><?= htmlspecialchars($vendor['email']) ?></div>
    </div>
    
    <div class="info-group">
    <div class="info-label">Email Verified:</div>
    <div class="value">
        <?php if ($vendor['email_verified'] == 1): ?>
            ✅ Verified
        <?php else: ?>
            ❌ Not Verified
        <?php endif; ?>
    </div>
</div>
    <?php if ($vendor['email_verified'] == 0): ?>
    <form method="post" action="verify_email_toggle.php" style="margin-top: 10px;">
        <input type="hidden" name="vendor_id" value="<?= $vendor['id'] ?>">
        <input type="hidden" name="action" value="verify">
        <button type="submit" style="padding: 6px 12px; background: #00b96b; color: white; border: none; border-radius: 4px; cursor: pointer;">
            ✅ Mark as Verified
        </button>
    </form>
<?php else: ?>
    <form method="post" action="verify_email_toggle.php" style="margin-top: 10px;">
        <input type="hidden" name="vendor_id" value="<?= $vendor['id'] ?>">
        <input type="hidden" name="action" value="unverify">
        <button type="submit" style="padding: 6px 12px; background: #f44336; color: white; border: none; border-radius: 4px; cursor: pointer;">
            ❌ Mark as Unverified
        </button>
    </form>
<?php endif; ?>
    <div class="info-group">
        <div class="info-label">Store Name:</div>
        <div class="value"><?= htmlspecialchars($vendor['store_name']) ?></div>
    </div>

    <div class="info-group">
        <div class="info-label">Referral Code:</div>
        <div class="value">
            <?= $referral_code ? $referral_code : 'N/A' ?>
        </div>
    </div>

    <div class="info-group">
        <div class="info-label">Subscription Plan:</div>
        <div class="value"><?= ucfirst($vendor['subscription_plan']) ?></div>
    </div>

    <div class="info-group">
        <div class="info-label">KYC Status:</div>
        <div class="value">
            <span class="kyc-status kyc-<?= strtolower($vendor['kyc_status']) ?>">
                <?= ucfirst($vendor['kyc_status']) ?>
            </span>
        </div>
    </div>

    <div class="info-group">
        <div class="info-label">Account Status:</div>
        <div class="value"><?= ucfirst($vendor['status']) ?></div>
    </div>

    <div class="info-group">
        <div class="info-label">Joined:</div>
        <div class="value"><?= date("d M Y", strtotime($vendor['created_at'])) ?></div>
    </div>

    <hr style="margin-top: 40px;">

    <h3>👥 Referred Vendors</h3>
    <?php if ($referred_vendors->num_rows > 0): ?>
    <table>
        <thead>
        <tr>
            <th>#</th>
            <th>Full Name</th>
            <th>Email</th>
            <th>Plan</th>
            <th>Joined</th>
        </tr>
        </thead>
        <tbody>
        <?php $i = 1; while ($ref = $referred_vendors->fetch_assoc()): ?>
        <tr>
            <td><?= $i++ ?></td>
            <td><?= htmlspecialchars($ref['fullname']) ?></td>
            <td><?= htmlspecialchars($ref['email']) ?></td>
            <td><?= ucfirst($ref['subscription_plan']) ?></td>
            <td><?= date("d M Y", strtotime($ref['created_at'])) ?></td>
        </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
    <?php else: ?>
        <p style="color: #888;">No referred vendors found.</p>
    <?php endif; ?>

    <a href="admin_vendors.php" class="back-link">← Back to Vendor List</a>
</div>
</body>
</html>