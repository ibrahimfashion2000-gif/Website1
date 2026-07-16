<?php
require_once 'db.php';
require_once 'config.php';
require_once 'includes/profit_calculator.php'; // এই ফাইলটি আমরা includes ফোল্ডারে রেখেছি
?>
<?php
// ১. ডাটাবেজ থেকে স্ট্যাটাস অনুযায়ী অর্ডারের সংখ্যা তুলে আনা
require_once 'db.php';

try {
    $stmt = $pdo->prepare("SELECT delivery_status, COUNT(*) as count FROM orders GROUP BY delivery_status");
    $stmt->execute();
    $status_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $status_data = [];
}

// চার্টের জন্য ডাটা প্রস্তুত করা
$labels = [];
$counts = [];
foreach ($status_data as $row) {
    $labels[] = $row['delivery_status'] ? $row['delivery_status'] : 'Unknown';
    $counts[] = (int)$row['count'];
}
?>

<div style="position: relative; z-index: 10000; margin: 20px;">
    <a href="add_product.php" class="btn btn-primary" style="padding: 10px 20px; font-size: 18px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
        + Add New Product
    </a>
</div>

<div class="row" style="margin-bottom: 20px;">
    <div class="col-md-12">
        <a href="add_product.php" class="btn btn-primary" style="padding: 10px 25px; font-weight: bold; border-radius: 5px;">+ Add New Product</a>
    </div>
</div>
<!-- HTML অংশ: চার্ট দেখানোর জন্য ক্যানভাস এবং CDN লিঙ্ক -->
<div style="width: 400px; margin: 20px auto;">
    <h3 style="text-align: center;">Order Status Dashboard</h3>
    <canvas id="orderStatusChart"></canvas>
</div>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const ctx = document.getElementById('orderStatusChart').getContext('2d');
    const orderStatusChart = new Chart(ctx, {
        type: 'pie', // চার্টের ধরণ
        data: {
            labels: <?php echo json_encode($labels); ?>,
            datasets: [{
                label: 'Orders Count',
                data: <?php echo json_encode($counts); ?>,
                backgroundColor: [
                    '#FF6384', // Pending / Red
                    '#36A2EB', // Processing / Blue
                    '#FFCE56', // Shipped / Yellow
                    '#4BC0C0', // Delivered / Green
                    '#9966FF'  // Other / Purple
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom',
                }
            }
        }
    });
</script>