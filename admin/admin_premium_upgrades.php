<?php
session_start();
require '../config.php';

if (empty($_SESSION['is_admin'])) {
    header("Location: login.php");
    exit();
}

// ✅ Handle Approve / Reject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['txn_id'], $_POST['action'])) {
    $txn_id = intval($_POST['txn_id']);
    $action = $_POST['action'];

    if ($action === 'approve') {
        $stmt = $conn->prepare("UPDATE transactions SET status='approved' WHERE id=? AND method='crypto'");
        $stmt->bind_param("i", $txn_id);
        $stmt->execute();
        $stmt->close();

        $_SESSION['message'] = "✅ Payment approved.";
    } elseif ($action === 'reject') {
        $stmt = $conn->prepare("UPDATE transactions SET status='rejected' WHERE id=? AND method='crypto'");
        $stmt->bind_param("i", $txn_id);
        $stmt->execute();
        $stmt->close();

        $_SESSION['message'] = "❌ Payment rejected.";
    }

    header("Location: admin_premium_upgrades.php");
    exit();
}

// ✅ Fetch upgrades
$sql = "
    SELECT t.id, t.vendor_id, t.amount, t.currency, t.method, t.status, t.created_at, 
           v.store_name,
           pp.proof_url
    FROM transactions t
    JOIN vendors v ON t.vendor_id = v.id
    LEFT JOIN premium_payments pp ON pp.vendor_id = t.vendor_id AND t.method='crypto'
    WHERE t.type='premium_upgrade'
    ORDER BY t.created_at DESC
";
$result = $conn->query($sql);

// ✅ Summary Stats
$totalRevenue = 0;
$totalVendors = 0;
$methodBreakdown = ["paystack" => 0, "flutterwave" => 0, "crypto" => 0];
$vendors = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $vendor_id = $row['vendor_id'];
        $method = strtolower($row['method']);

        // Revenue in Naira
        if ($method === "crypto") {
            $totalRevenue += 5000; // fixed rate
            $methodBreakdown["crypto"] += 5000;
        } else {
            $totalRevenue += $row['amount'];
            $methodBreakdown[$method] += $row['amount'];
        }

        $vendors[$vendor_id] = true;
    }
    $totalVendors = count($vendors);
}

// Reset pointer for table display
$result->data_seek(0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin – Premium Upgrades</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
  <div class="max-w-7xl mx-auto p-6">
    <!-- Back button -->
    <div class="mb-6">
      <a href="admin_dashboard.php" class="inline-flex items-center px-4 py-2 bg-green-700 text-white text-sm font-semibold rounded-lg shadow hover:bg-green-800 transition">
        ← Back to Dashboard
      </a>
    </div>

    <h1 class="text-3xl font-bold text-green-800 mb-6">📊 Premium Upgrades</h1>

    <!-- ✅ Summary Stats -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
      <div class="bg-white p-6 rounded-xl shadow hover:shadow-lg transition">
        <h3 class="text-gray-500 text-sm font-medium">Total Premium Revenue</h3>
        <p class="text-2xl font-bold text-green-700 mt-2">₦<?= number_format($totalRevenue, 2) ?></p>
      </div>
      <div class="bg-white p-6 rounded-xl shadow hover:shadow-lg transition">
        <h3 class="text-gray-500 text-sm font-medium">Total Premium Vendors</h3>
        <p class="text-2xl font-bold text-green-700 mt-2"><?= $totalVendors ?></p>
      </div>
      <div class="bg-white p-6 rounded-xl shadow hover:shadow-lg transition">
        <h3 class="text-gray-500 text-sm font-medium">By Payment Method</h3>
        <ul class="mt-2 text-sm text-gray-700">
          <li>Paystack: ₦<?= number_format($methodBreakdown['paystack'], 2) ?></li>
          <li>Flutterwave: ₦<?= number_format($methodBreakdown['flutterwave'], 2) ?></li>
          <li>Crypto: ₦<?= number_format($methodBreakdown['crypto'], 2) ?></li>
        </ul>
      </div>
    </div>

    <?php if (!empty($_SESSION['message'])): ?>
      <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg shadow">
        <?= htmlspecialchars($_SESSION['message']) ?>
      </div>
      <?php unset($_SESSION['message']); ?>
    <?php endif; ?>

    <div class="overflow-x-auto bg-white shadow-lg rounded-xl">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-green-700 text-white">
          <tr>
            <th class="px-6 py-3 text-left text-sm font-semibold">Vendor</th>
            <th class="px-6 py-3 text-left text-sm font-semibold">Amount</th>
            <th class="px-6 py-3 text-left text-sm font-semibold">Method</th>
            <th class="px-6 py-3 text-left text-sm font-semibold">Status</th>
            <th class="px-6 py-3 text-left text-sm font-semibold">Date</th>
            <th class="px-6 py-3 text-left text-sm font-semibold">Proof</th>
            <th class="px-6 py-3 text-left text-sm font-semibold">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
              <?php
                $status = $row['status'];
                if (in_array($row['method'], ['paystack', 'flutterwave']) && $status === 'completed') {
                    $status = 'approved';
                }
              ?>
              <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 text-sm text-gray-800 font-medium"><?= htmlspecialchars($row['store_name']) ?></td>
                <td class="px-6 py-4 text-sm text-gray-700">
                  <?= htmlspecialchars($row['currency']) ?> <?= number_format($row['amount'], 2) ?>
                </td>
                <td class="px-6 py-4 text-sm text-gray-700 capitalize"><?= htmlspecialchars($row['method']) ?></td>
                <td class="px-6 py-4 text-sm">
                  <?php if ($status === 'approved'): ?>
                    <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs font-medium">Approved</span>
                  <?php elseif ($status === 'pending'): ?>
                    <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded text-xs font-medium">Pending</span>
                  <?php elseif ($status === 'rejected'): ?>
                    <span class="bg-red-100 text-red-800 px-2 py-1 rounded text-xs font-medium">Rejected</span>
                  <?php endif; ?>
                </td>
                <td class="px-6 py-4 text-sm text-gray-600"><?= htmlspecialchars($row['created_at']) ?></td>
                <td class="px-6 py-4 text-sm">
                  <?php if ($row['method'] === 'crypto' && !empty($row['proof_url'])): ?>
                    <a href="../<?= htmlspecialchars($row['proof_url']) ?>" target="_blank" class="text-blue-600 hover:underline">View Proof</a>
                  <?php else: ?>
                    <span class="text-gray-400">N/A</span>
                  <?php endif; ?>
                </td>
                <td class="px-6 py-4 text-sm">
                  <?php if ($row['method'] === 'crypto' && $status === 'pending'): ?>
                    <form method="POST" class="flex gap-2">
                      <input type="hidden" name="txn_id" value="<?= $row['id'] ?>">
                      <button type="submit" name="action" value="approve" class="bg-green-600 text-white px-3 py-1 rounded text-xs hover:bg-green-700">Approve</button>
                      <button type="submit" name="action" value="reject" class="bg-red-600 text-white px-3 py-1 rounded text-xs hover:bg-red-700">Reject</button>
                    </form>
                  <?php else: ?>
                    <span class="text-gray-400">-</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="7" class="px-6 py-4 text-center text-gray-500">No premium upgrades found.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>