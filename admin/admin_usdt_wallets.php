<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../config.php';

// Admin auth check
if (!isset($_SESSION['is_admin'])) {
    header("Location: login.php");
    exit();
}

// Fetch all submitted wallets
$sql = "
    SELECT w.id, w.vendor_id, w.wallet_address, w.status, w.submitted_at,
           v.store_name, v.email
    FROM vendor_wallets w
    JOIN vendors v ON w.vendor_id = v.id
    ORDER BY w.submitted_at DESC
";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin - USDT Wallet Approvals</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    body {
      font-family: 'Segoe UI', sans-serif;
      background: #f4f6f8;
      margin: 0;
      padding: 20px;
    }
    .container {
      max-width: 1000px;
      margin: auto;
      background: #fff;
      padding: 25px;
      border-radius: 12px;
      box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    }
    h2 {
      text-align: center;
      color: #106b28;
      margin-bottom: 20px;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 10px;
      font-size: 14px;
    }
    th, td {
      padding: 12px;
      border: 1px solid #e0e0e0;
      text-align: left;
    }
    th {
      background: #e8f5e9;
      color: #106b28;
    }
    tr:nth-child(even) {
      background: #f9f9f9;
    }
    .status-approved {
      color: green;
      font-weight: bold;
    }
    .status-pending {
      color: orange;
      font-weight: bold;
    }
    .status-rejected {
      color: red;
      font-weight: bold;
    }
    .btn {
      padding: 6px 12px;
      border: none;
      border-radius: 4px;
      font-size: 13px;
      cursor: pointer;
      text-decoration: none;
    }
    .btn-approve {
      background-color: #106b28;
      color: white;
    }
    .btn-reject {
      background-color: #b22222;
      color: white;
      margin-left: 6px;
    }
    .note {
      font-size: 0.9em;
      color: #666;
      margin-top: 15px;
      text-align: center;
    }

    @media (max-width: 768px) {
      table, thead, tbody, th, td, tr {
        display: block;
      }
      thead { display: none; }
      tr {
        margin-bottom: 15px;
        background: #fff;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        padding: 12px;
        border-radius: 8px;
      }
      td {
        padding: 10px 10px 10px 50%;
        position: relative;
        border: none;
        border-bottom: 1px solid #eee;
      }
      td::before {
        content: attr(data-label);
        position: absolute;
        left: 12px;
        top: 10px;
        font-weight: bold;
        color: #106b28;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <h2>🦷 Vendor USDT Wallet Submissions</h2>

<?php if (!empty($_SESSION['wallet_msg'])): ?>
  <div style="text-align:center; background:#e6fbe9; color:#106b28; padding:10px; margin-bottom:15px; border-radius:6px;">
    <?= $_SESSION['wallet_msg'] ?>
  </div>
  <?php unset($_SESSION['wallet_msg']); ?>
<?php endif; ?>

    <?php if ($result->num_rows === 0): ?>
      <p style="text-align:center;">No wallet submissions yet.</p>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Vendor</th>
            <th>Email</th>
            <th>Wallet Address</th>
            <th>Status</th>
            <th>Submitted</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
              <td data-label="Vendor"><?= htmlspecialchars($row['store_name']) ?></td>
              <td data-label="Email"><?= htmlspecialchars($row['email']) ?></td>
              <td data-label="Wallet"><?= htmlspecialchars($row['wallet_address']) ?></td>
              <td data-label="Status" class="status-<?= $row['status'] ?>"><?= ucfirst($row['status']) ?></td>
              <td data-label="Submitted"><?= date('M j, Y h:i A', strtotime($row['submitted_at'])) ?></td>
              <td data-label="Action">
                <?php if ($row['status'] === 'pending'): ?>
                  <a class="btn btn-approve" href="approve_usdt_wallet.php?id=<?= $row['id'] ?>" onclick="return confirm('Approve this wallet?')">Approve</a>
                  <a class="btn btn-reject" href="reject_usdt_wallet.php?id=<?= $row['id'] ?>" onclick="return confirm('Reject this wallet?')">Reject</a>
                <?php else: ?>
                  <span style="color:#666;">—</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <div class="note">Vendors must have their wallet approved before requesting USDT withdrawals.</div>
  </div>
</body>
</html>