<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}
require_once 'db.php';
function getStatusColor($status) {
    $colors = [
        'Pending' => 'bg-yellow-500',
        'Confirmed' => 'bg-green-500',
        'Processing' => 'bg-blue-500',
        'Packed' => 'bg-purple-500',
        'Ready for Courier' => 'bg-cyan-500',
        'Courier Picked' => 'bg-indigo-500',
        'In Transit' => 'bg-orange-500',
        'Out for Delivery' => 'bg-sky-500',
        'Delivered' => 'bg-green-800',
        'Cancelled' => 'bg-red-500',
        'Returned' => 'bg-rose-900',
        'Refunded' => 'bg-gray-500',
        'Failed Delivery' => 'bg-red-800',
        'On Hold' => 'bg-amber-500'
    ];
    return $colors[$status] ?? 'bg-gray-400';
}
$counts = $pdo->query("SELECT delivery_status, COUNT(*) as total FROM orders GROUP BY delivery_status")->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<?php
// ফিল্টার লজিক
$where = "WHERE 1=1";


if (!empty($_GET['status'])) {
    $where .= " AND delivery_status = " . $pdo->quote($_GET['status']);
}


if (!empty($_GET['search'])) {
    $search = trim($_GET['search']);
    $like = "%" . $search . "%";
    $where .= " AND (customer_name LIKE " . $pdo->quote($like) . "
                OR mobile_number LIKE " . $pdo->quote($like) . "
                OR ref_number LIKE " . $pdo->quote($like);
    if (ctype_digit($search)) {
        $where .= " OR id = " . (int)$search;
    }
    $where .= ")";
}


if (!empty($_GET['start_date']) && !empty($_GET['end_date'])) {
    $where .= " AND order_date BETWEEN " . $pdo->quote($_GET['start_date']) . " AND " . $pdo->quote($_GET['end_date']);
}


$stmt = $pdo->query("SELECT * FROM orders $where ORDER BY id DESC");
$orders = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
<form method="GET" class="flex gap-2 mb-4 bg-gray-800 p-4 rounded border border-gray-700">
    <input type="text" name="search" placeholder="নাম বা মোবাইল..." class="p-2 rounded bg-gray-700 text-white text-sm" value="<?php echo $_GET['search'] ?? ''; ?>">
    <input type="date" name="start_date" class="p-2 rounded bg-gray-700 text-white text-sm" value="<?php echo $_GET['start_date'] ?? ''; ?>">
    <input type="date" name="end_date" class="p-2 rounded bg-gray-700 text-white text-sm" value="<?php echo $_GET['end_date'] ?? ''; ?>">
    <button type="submit" class="bg-blue-600 px-4 py-2 rounded text-white text-sm">ফিল্টার</button>
    <a href="admin_orders.php" class="bg-gray-600 px-4 py-2 rounded text-white text-sm">রিসেট</a>
</form>
</head>
<body class="bg-gray-900 text-white p-6">
    <div class="max-w-7xl mx-auto">
        <div class="grid grid-cols-4 gap-4 mb-6">
            <?php foreach(['Pending', 'Confirmed', 'Processing', 'Delivered'] as $s): ?>
                <div class="bg-gray-800 p-4 rounded shadow border border-gray-700">
                    <p class="text-gray-400 text-sm"><?php echo $s; ?></p>
                    <p class="text-2xl font-bold"><?php echo $counts[$s] ?? 0; ?></p>
                </div>
            <?php endforeach; ?>
        </div>


        <div class="flex gap-4 mb-4">
            <form id="exportForm" action="export.php" method="POST">
                <button type="button" onclick="submitExport()" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded">Export Selected</button>
            </form>


            <form id="bulkForm" action="bulk_action.php" method="POST" class="flex gap-2">
                <select name="new_status" class="bg-gray-700 p-2 rounded border border-gray-600">
                    <option value="Confirmed">Confirmed</option>
                    <option value="Processing">Processing</option>
                    <option value="Delivered">Delivered</option>
                    <option value="Cancelled">Cancelled</option>
                </select>
                <button type="button" onclick="submitBulk()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">Update Selected</button>
            </form>
        </div>
    


        <div class="bg-gray-800 rounded-lg shadow overflow-hidden">
            <table class="w-full text-left">
                <thead class="bg-gray-700">
                    <tr>
                        <th class="p-3"><input type="checkbox" id="selectAll"></th>
                        <th class="p-3">Order ID</th>
                        <th class="p-3">Customer</th>
                        <th class="p-3">Status</th>
                        <th class="p-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                    <tr class="border-b border-gray-700">
                        <td class="p-3"><input type="checkbox" class="order-checkbox" value="<?php echo $order['id']; ?>"></td>
        <td class="p-3">#<?php echo $order['id']; ?></td>
        <td class="p-3"><?php echo htmlspecialchars($order['customer_name']); ?></td>
        
        <td class="p-3">
            <span class="px-2 py-1 <?php echo getStatusColor($order['delivery_status']); ?> rounded text-xs text-white">
                <?php echo $order['delivery_status']; ?>
            </span>
        </td>
        
        <td class="p-3">
            <a href="view_order.php?id=<?php echo $order['id']; ?>" class="text-blue-400 hover:underline">View/Edit</a>
        </td>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>


    <script>
        // Select All
        document.getElementById('selectAll').onclick = function() {
            document.querySelectorAll('.order-checkbox').forEach(cb => cb.checked = this.checked);
        }


        // Export Function
        function submitExport() {
            const form = document.getElementById('exportForm');
            const selected = document.querySelectorAll('.order-checkbox:checked');
            if (selected.length === 0) { alert("একটি অর্ডার সিলেক্ট করুন"); return; }
            selected.forEach(cb => {
                let input = document.createElement('input');
                input.type = 'hidden'; input.name = 'order_ids[]'; input.value = cb.value;
                form.appendChild(input);
            });
            form.submit();
        }


        // Bulk Update Function (এই ফাংশনটিই মিসিং ছিল)
        function submitBulk() {
            const form = document.getElementById('bulkForm');
            const selected = document.querySelectorAll('.order-checkbox:checked');
            if (selected.length === 0) { alert("একটি অর্ডার সিলেক্ট করুন"); return; }
            selected.forEach(cb => {
                let input = document.createElement('input');
                input.type = 'hidden'; input.name = 'order_ids[]'; input.value = cb.value;
                form.appendChild(input);
            });
            form.submit();
        }
    </script>
</body>
</html>
