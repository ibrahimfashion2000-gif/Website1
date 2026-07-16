<?php
require_once 'db.php';

// যদি কোনো আইডি সিলেক্ট করা না থাকে, তবে সব এক্সপোর্ট হবে
$where = "1=1";
if (!empty($_POST['order_ids'])) {
    $ids = implode(',', array_map('intval', $_POST['order_ids']));
    $where = "id IN ($ids)";
}

$stmt = $pdo->query("SELECT * FROM orders WHERE $where ORDER BY id DESC");
$orders = $stmt->fetchAll();

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=selected_orders.xls");

echo "Order ID\tCustomer Name\tMobile\tStatus\tTotal\n";
foreach ($orders as $row) {
    echo $row['id'] . "\t" . $row['customer_name'] . "\t" . $row['mobile_number'] . "\t" . $row['delivery_status'] . "\t" . $row['total_amount'] . "\n";
}
exit;
?>