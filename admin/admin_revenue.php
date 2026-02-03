<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.php");
    exit;
}

// ===== Date Filter =====
$from = $_GET['from'] ?? '';
$to   = $_GET['to'] ?? '';
$whereClause = "WHERE t.status = 'successful'";
$params = [];
$types = "";

if (!empty($from) && !empty($to)) {
    $whereClause .= " AND DATE(t.created_at) BETWEEN ? AND ?";
    $params[] = $from;
    $params[] = $to;
    $types .= "ss";
}

// ===== Fetch Transactions =====
$sql = "
    SELECT 
        v.id AS vendor_id,
        v.store_name,
        v.subscription_plan,
        t.amount_paid,
        t.platform_fee,
        t.vendor_credited,
        t.created_at
    FROM vendors v
    LEFT JOIN transactions t ON v.id = t.vendor_id AND t.status='successful'
    $whereClause
    ORDER BY v.store_name, t.created_at
";

$stmt = $conn->prepare($sql);
if ($types) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$transactions = [];
while ($row = $result->fetch_assoc()) {
    $transactions[$row['vendor_id']][] = $row;
}
$stmt->close();

// ===== Totals =====
$grandTotalSales = 0;
$grandTotalFees = 0;
$grandTotalEarnings = 0;

function nf($num) {
    return "₦" . number_format($num, 2);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sellevo – Revenue Report</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans">

<!-- Topbar -->
<div class="flex justify-between items-center bg-green-800 text-white p-4">
    <span class="text-xl font-bold">Sellevo Admin</span>
    <div class="flex items-center gap-4">
        <button class="md:hidden" onclick="toggleSidebar()">☰</button>
        <a href="logout.php" class="hover:underline">Logout</a>
    </div>
</div>

<!-- Sidebar -->
<div id="sidebar" class="fixed top-0 left-0 w-56 bg-gray-800 text-gray-300 h-full pt-16 overflow-auto transform -translate-x-56 md:translate-x-0 transition-transform duration-300 z-50">
    <a href="admin_dashboard.php" class="block px-4 py-3 hover:bg-gray-700 hover:text-white">📊 Dashboard</a>
    <a href="admin_revenue.php" class="block px-4 py-3 bg-gray-700 text-white">💰 Revenue Report</a>
    <a href="admin_transactions.php" class="block px-4 py-3 hover:bg-gray-700 hover:text-white">📈 Transactions</a>
    <a href="admin_vendors.php" class="block px-4 py-3 hover:bg-gray-700 hover:text-white">🏪 Vendors</a>
    <a href="admin_withdrawals.php" class="block px-4 py-3 hover:bg-gray-700 hover:text-white">💵 Withdrawals</a>
    <a href="site_settings.php" class="block px-4 py-3 hover:bg-gray-700 hover:text-white">⚙️ Settings</a>
</div>

<!-- Main Content -->
<div class="md:ml-56 p-4">
    <h2 class="text-2xl font-bold mb-4">💰 Revenue & Commission Report</h2>

    <!-- Filter -->
    <form method="GET" class="bg-white p-4 rounded shadow flex flex-wrap gap-2 items-center mb-4">
        <label class="flex flex-col">
            From: <input type="date" name="from" value="<?= htmlspecialchars($from) ?>" class="border rounded p-1">
        </label>
        <label class="flex flex-col">
            To: <input type="date" name="to" value="<?= htmlspecialchars($to) ?>" class="border rounded p-1">
        </label>
        <button type="submit" class="bg-green-800 text-white px-3 py-1 rounded hover:bg-green-900">Filter</button>
        <a href="?from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&export=csv" class="bg-green-800 text-white px-3 py-1 rounded hover:bg-green-900">⬇ Export CSV</a>
    </form>

    <!-- Transactions Table -->
    <div class="overflow-x-auto bg-white rounded shadow">
        <table class="min-w-full border-collapse">
            <thead class="bg-gray-100">
                <tr>
                    <th class="border px-3 py-2 text-left">Vendor</th>
                    <th class="border px-3 py-2 text-left">Plan</th>
                    <th class="border px-3 py-2 text-left">Date</th>
                    <th class="border px-3 py-2 text-right">Sale Amount</th>
                    <th class="border px-3 py-2 text-right">Platform Fee</th>
                    <th class="border px-3 py-2 text-right">Vendor Earnings</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($transactions): ?>
                    <?php foreach ($transactions as $vendorId => $txns): 
                        $vendorTotalSales = 0;
                        $vendorTotalFees = 0;
                        $vendorTotalEarnings = 0;
                    ?>
                        <!-- Vendor Header / Toggle -->
                        <tr class="bg-gray-200 cursor-pointer" onclick="toggleVendor('vendor-<?= $vendorId ?>')">
                            <td colspan="6" class="px-3 py-2 font-bold flex justify-between items-center">
                                <span><?= htmlspecialchars($txns[0]['store_name']) ?> (<?= ucfirst($txns[0]['subscription_plan']) ?>)</span>
                                <span id="icon-vendor-<?= $vendorId ?>">▶</span>
                            </td>
                        </tr>

                        <!-- Transactions (collapsed by default) -->
                        <tbody id="vendor-<?= $vendorId ?>" class="hidden">
                        <?php foreach ($txns as $txn): 
                            $vendorTotalSales += $txn['amount_paid'];
                            $vendorTotalFees += $txn['platform_fee'];
                            $vendorTotalEarnings += $txn['vendor_credited'];
                            $grandTotalSales += $txn['amount_paid'];
                            $grandTotalFees += $txn['platform_fee'];
                            $grandTotalEarnings += $txn['vendor_credited'];
                        ?>
                        <tr class="bg-gray-50">
                            <td class="border px-3 py-2"><?= htmlspecialchars($txn['store_name']) ?></td>
                            <td class="border px-3 py-2"><?= ucfirst($txn['subscription_plan']) ?></td>
                            <td class="border px-3 py-2"><?= date('Y-m-d', strtotime($txn['created_at'])) ?></td>
                            <td class="border px-3 py-2 text-right"><?= nf($txn['amount_paid']) ?></td>
                            <td class="border px-3 py-2 text-right"><?= nf($txn['platform_fee']) ?></td>
                            <td class="border px-3 py-2 text-right"><?= nf($txn['vendor_credited']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <!-- Vendor Subtotal -->
                        <tr class="bg-gray-100 font-bold">
                            <td colspan="3" class="px-3 py-2">Subtotal for <?= htmlspecialchars($txns[0]['store_name']) ?></td>
                            <td class="px-3 py-2 text-right"><?= nf($vendorTotalSales) ?></td>
                            <td class="px-3 py-2 text-right"><?= nf($vendorTotalFees) ?></td>
                            <td class="px-3 py-2 text-right"><?= nf($vendorTotalEarnings) ?></td>
                        </tr>
                        </tbody>
                    <?php endforeach; ?>
                    <!-- Grand Total -->
                    <tr class="bg-green-100 font-bold">
                        <td colspan="3" class="px-3 py-2">GRAND TOTAL</td>
                        <td class="px-3 py-2 text-right"><?= nf($grandTotalSales) ?></td>
                        <td class="px-3 py-2 text-right"><?= nf($grandTotalFees) ?></td>
                        <td class="px-3 py-2 text-right"><?= nf($grandTotalEarnings) ?></td>
                    </tr>
                <?php else: ?>
                    <tr><td colspan="6" class="px-3 py-2 text-center">No transactions found for this period.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('-translate-x-56');
}

function toggleVendor(id) {
    const tbody = document.getElementById(id);
    const icon = document.getElementById('icon-' + id);
    if(tbody.classList.contains('hidden')) {
        tbody.classList.remove('hidden');
        icon.innerText = '▼';
    } else {
        tbody.classList.add('hidden');
        icon.innerText = '▶';
    }
}
</script>
</body>
</html>