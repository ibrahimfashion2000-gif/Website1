<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}
require_once 'db.php';
$id = $_GET['id'] ?? null;
if (!$id) die("Order ID missing!");

$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$id]);
$order = $stmt->fetch();
$areas = $pdo->query("SELECT * FROM delivery_areas")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $stmt = $pdo->prepare("UPDATE orders SET order_date = ?, delivery_status = ?, delivery_charge = ?, customer_note = ? WHERE id = ?");
    $stmt->execute([$_POST['order_date'], $_POST['delivery_status'], $_POST['delivery_charge'], $_POST['customer_note'], $id]);
    
    $pdo->prepare("INSERT INTO order_logs (order_id, action) VALUES (?, ?)")
        ->execute([$id, "Updated Status to " . $_POST['delivery_status'] . " & Charge to " . $_POST['delivery_charge']]);
    
    header("Location: view_order.php?id=$id");
    exit;
}

$order_logs = $pdo->prepare("SELECT * FROM order_logs WHERE order_id = ? ORDER BY changed_at DESC");
$order_logs->execute([$id]);
$logs = $order_logs->fetchAll();
?>

<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <title>Edit Order #<?php echo $id; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white p-6">
    <div class="max-w-4xl mx-auto bg-gray-800 p-8 rounded-lg shadow-lg border border-gray-700">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-blue-400">Order Edit #<?php echo $id; ?></h2>
            <button onclick="document.getElementById('editModal').classList.remove('hidden')" class="bg-blue-600 px-4 py-2 rounded hover:bg-blue-700 font-bold">
                এডিট করুন ✏️
            </button>
        </div>

        <div id="editModal" class="fixed inset-0 bg-black bg-opacity-70 hidden flex items-center justify-center p-4 z-50">
            <div class="bg-gray-800 p-6 rounded-lg w-full max-w-lg border border-gray-600 shadow-2xl">
                <h3 class="text-xl font-bold mb-4 text-blue-400">অর্ডার আপডেট করুন</h3>
                <form method="POST">
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <input type="date" name="order_date" value="<?php echo $order['order_date']; ?>" class="bg-gray-700 p-2 rounded">
                        <select name="delivery_status" class="bg-gray-700 p-2 rounded">
                            <?php 
                            $statuses = ['Pending', 'Confirmed', 'Processing', 'Packed', 'Ready for Courier', 'Courier Picked', 'In Transit', 'Out for Delivery', 'Delivered', 'Cancelled', 'Returned', 'Refunded', 'Failed Delivery', 'On Hold'];
                            foreach($statuses as $s) echo "<option ".($order['delivery_status']==$s?'selected':'').">$s</option>"; 
                            ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <select id="area_select" onchange="document.getElementById('delivery_charge').value = this.value" class="w-full bg-gray-700 p-2 rounded mb-2">
                            <option value="0">এলাকা সিলেক্ট করুন</option>
                            <?php foreach ($areas as $area): ?>
                                <option value="<?php echo $area['charge']; ?>"><?php echo $area['area_name']; ?> (<?php echo $area['charge']; ?> ৳)</option>
                            <?php endforeach; ?>
                        </select>
                        <input type="number" name="delivery_charge" id="delivery_charge" value="<?php echo $order['delivery_charge'] ?? 0; ?>" class="w-full bg-gray-700 p-2 rounded" placeholder="Delivery Charge">
                    </div>

                    <textarea name="customer_note" rows="3" class="w-full bg-gray-700 p-2 rounded mb-4"><?php echo htmlspecialchars($order['customer_note'] ?? ''); ?></textarea>

                    <div class="flex justify-end gap-2">
                        <button type="button" onclick="document.getElementById('editModal').classList.add('hidden')" class="bg-gray-600 px-4 py-2 rounded">বাতিল</button>
                        <button type="submit" class="bg-green-600 px-6 py-2 rounded font-bold">সেভ করুন</button>
                    </div>
                </form>
            </div>
        </div>

        <h3 class="mt-8 text-lg font-bold border-b border-gray-700 pb-2">Activity Log</h3>
        <ul class="mt-4 space-y-2">
            <?php foreach ($logs as $log): ?>
                <li class="text-sm bg-gray-700 p-2 rounded flex justify-between">
                    <span><?php echo htmlspecialchars($log['action']); ?></span>
                    <span class="text-gray-400"><?php echo $log['changed_at']; ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</body>
</html>