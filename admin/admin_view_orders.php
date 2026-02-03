<?php
session_start();
require '../config.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.php");
    exit;
}

// -------------------- Helper Functions --------------------
function getCurrencySymbol($code) {
    $symbols = [
        'NGN' => '₦', 'USD' => '$', 'GHS' => '₵', 'KES' => 'KSh', 'ZAR' => 'R',
        'EUR' => '€', 'GBP' => '£', 'XOF' => 'CFA', 'XAF' => 'FCFA',
        'TZS' => 'TSh', 'UGX' => 'USh',
    ];
    return $symbols[$code] ?? $code;
}

// Fetch all orders
$orders = $conn->query("SELECT * FROM orders ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>All Orders - Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans p-4 md:p-10">

<div class="max-w-7xl mx-auto bg-white p-6 md:p-10 rounded-xl shadow-md">
    <h2 class="text-2xl font-bold text-green-800 mb-6">📦 All Vendor Orders</h2>

    <div class="mb-6">
        <a href="admin_dashboard.php" class="bg-green-700 text-white px-4 py-2 rounded-md hover:bg-green-800 transition">← Back to Dashboard</a>
    </div>

    <?php if ($orders->num_rows > 0): ?>
        <?php while ($order = $orders->fetch_assoc()): ?>
            <div class="bg-gray-50 rounded-xl p-5 mb-6 shadow-sm border border-gray-200">
                <div class="flex flex-wrap justify-between gap-4 mb-4">
                    <div>
                        <p class="font-semibold"><?= htmlspecialchars($order['customer_name']) ?></p>
                        <p class="text-sm text-gray-600">Email: <?= htmlspecialchars($order['customer_email']) ?></p>
                        <p class="text-sm text-gray-600">Phone: <?= htmlspecialchars($order['customer_phone'] ?? 'N/A') ?></p>
                    </div>
                    <div class="text-sm">
                        <p><strong>Status:</strong> <?= htmlspecialchars($order['status']) ?></p>
                        <p><strong>Delivery:</strong> <?= htmlspecialchars($order['delivery_status'] ?? 'Pending') ?></p>
                        <p><strong>Vendor ID:</strong> <?= (int)$order['vendor_id'] ?></p>
                        <p><strong>Order Ref (SELV):</strong> <?= htmlspecialchars($order['payment_ref'] ?? '') ?></p>
                        <p><strong>Date:</strong> <?= date("M j, Y", strtotime($order['created_at'])) ?></p>
                    </div>
                </div>

                <div class="mb-4">
                    <p class="font-semibold">Ordered Items:</p>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left border border-gray-200 mt-2 rounded-md">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="px-3 py-2">Product</th>
                                    <th class="px-3 py-2">Qty</th>
                                    <th class="px-3 py-2">Price</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $i_stmt = $conn->prepare("SELECT product_name, quantity, price FROM order_items WHERE order_id = ?");
                                $i_stmt->bind_param("i", $order['id']);
                                $i_stmt->execute();
                                $items = $i_stmt->get_result();
                                while($item=$items->fetch_assoc()):
                                ?>
                                    <tr class="border-t border-gray-200">
                                        <td class="px-3 py-2"><?= htmlspecialchars($item['product_name']) ?></td>
                                        <td class="px-3 py-2"><?= (int)$item['quantity'] ?></td>
                                        <td class="px-3 py-2"><?= getCurrencySymbol($order['currency'] ?? 'NGN') ?><?= number_format($item['price'],2) ?></td>
                                    </tr>
                                <?php endwhile; $i_stmt->close(); ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p class="text-gray-600">No orders yet.</p>
    <?php endif; ?>
</div>

</body>
</html>