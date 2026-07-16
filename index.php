<?php
require_once 'db.php';
require_once 'config.php';
require_once 'includes/profit_calculator.php'; 
?>
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// সিকিউরিটি চেক
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

require_once 'db.php';

/* =========================================================
   ধরে নেওয়া টেবিল/কলামের নাম — আপনার DB তে না মিললে বদলে নিন:
   - orders(id, customer_name, delivery_status, total_amount, created_at)
   - products(id, name, category, stock_quantity)   [category, stock_quantity না থাকলে সেই সেকশন খালি দেখাবে]
   - order_items(id, order_id, product_id, quantity, price)  [Sales by Category এর জন্য দরকার]
   - expenses(id, amount, created_at)   [না থাকলে 0 দেখাবে]
   - income(id, amount, created_at)     [না থাকলে 0 দেখাবে]
   ========================================================= */

function safeScalar($pdo, $sql, $default = 0) {
    try {
        $val = $pdo->query($sql)->fetchColumn();
        return $val !== false && $val !== null ? $val : $default;
    } catch (PDOException $e) {
        return $default;
    }
}

try {
    $totalOrders     = (int) safeScalar($pdo, "SELECT COUNT(*) FROM orders");
    $totalCustomers  = (int) safeScalar($pdo, "SELECT COUNT(DISTINCT customer_name) FROM orders");
    $totalRevenue    = (float) safeScalar($pdo, "SELECT SUM(total_amount) FROM orders WHERE delivery_status = 'Delivered'");
    $todayOrders     = (int) safeScalar($pdo, "SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()");

    $stmt = $pdo->prepare("SELECT delivery_status, COUNT(*) as count FROM orders GROUP BY delivery_status");
    $stmt->execute();
    $status_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $statusCounts = ['pending'=>0,'confirmed'=>0,'hold'=>0,'cancelled'=>0,'delivered'=>0,'return'=>0];
    foreach ($status_data as $row) {
        $key = strtolower($row['delivery_status']);
        if (array_key_exists($key, $statusCounts)) $statusCounts[$key] = (int)$row['count'];
    }

    $recentOrdersStmt = $pdo->query("SELECT id, customer_name, delivery_status, total_amount, created_at FROM orders ORDER BY id DESC LIMIT 5");
    $recentOrders = $recentOrdersStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $totalOrders = $totalCustomers = $totalRevenue = $todayOrders = 0;
    $statusCounts = ['pending'=>0,'confirmed'=>0,'hold'=>0,'cancelled'=>0,'delivered'=>0,'return'=>0];
    $status_data = [];
    $recentOrders = [];
}

// প্রোডাক্ট
$totalProducts = (int) safeScalar($pdo, "SELECT COUNT(*) FROM products");

// ইনকাম / এক্সপেন্স / ফাইনাল প্রফিট (আলাদা টেবিল, না থাকলে 0)
$totalExpenses = (float) safeScalar($pdo, "SELECT SUM(amount) FROM expenses");
$totalIncome   = (float) safeScalar($pdo, "SELECT SUM(amount) FROM income");
$finalProfit   = $totalIncome - $totalExpenses;

// লো স্টক প্রোডাক্ট (কলাম না থাকলে খালি লিস্ট)
$lowStockProducts = [];
try {
    $stmt = $pdo->prepare("SELECT name, stock_quantity FROM products WHERE stock_quantity <= 5 ORDER BY stock_quantity ASC LIMIT 6");
    $stmt->execute();
    $lowStockProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $lowStockProducts = [];
}

// টপ কাস্টমার (শুধু orders টেবিল লাগে)
$topCustomers = [];
try {
    $stmt = $pdo->prepare("SELECT customer_name, COUNT(*) as orders_count, SUM(total_amount) as total_spent 
                            FROM orders GROUP BY customer_name ORDER BY total_spent DESC LIMIT 5");
    $stmt->execute();
    $topCustomers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $topCustomers = [];
}

// সেলস বাই ক্যাটাগরি (order_items + products লাগে)
$categoryLabels = [];
$categoryData = [];
try {
    $stmt = $pdo->prepare("SELECT p.category, SUM(oi.quantity * oi.price) as total 
                            FROM order_items oi 
                            JOIN products p ON p.id = oi.product_id 
                            GROUP BY p.category ORDER BY total DESC LIMIT 6");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $categoryLabels[] = $r['category'] ? $r['category'] : 'Uncategorized';
        $categoryData[] = (float)$r['total'];
    }
} catch (PDOException $e) {
    $categoryLabels = [];
    $categoryData = [];
}

// রেভিনিউ ওভারভিউ — ৪টা রেঞ্জ প্রি-লোড করা হচ্ছে, JS এ ফিল্টার বাটনে সুইচ হবে
function buildRevenueSeries($pdo) {
    $out = ['week'=>['labels'=>[],'data'=>[]], 'month'=>['labels'=>[],'data'=>[]], 'year'=>['labels'=>[],'data'=>[]], 'today'=>['labels'=>[],'data'=>[]]];

    $hourly = array_fill(0, 24, 0);
    try {
        $stmt = $pdo->query("SELECT HOUR(created_at) as h, SUM(total_amount) as total FROM orders WHERE DATE(created_at) = CURDATE() GROUP BY HOUR(created_at)");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) { $hourly[(int)$r['h']] = (float)$r['total']; }
    } catch (PDOException $e) {}
    $out['today']['labels'] = array_map(fn($h) => sprintf('%02d:00', $h), range(0,23));
    $out['today']['data'] = $hourly;

    $week = array_fill(0, 7, 0);
    $weekLabels = [];
    for ($i = 6; $i >= 0; $i--) { $weekLabels[] = date('D', strtotime("-$i day")); }
    try {
        $stmt = $pdo->query("SELECT DATE(created_at) as d, SUM(total_amount) as total FROM orders WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(created_at)");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            for ($i = 6; $i >= 0; $i--) {
                if ($r['d'] === date('Y-m-d', strtotime("-$i day"))) $week[6-$i] = (float)$r['total'];
            }
        }
    } catch (PDOException $e) {}
    $out['week']['labels'] = $weekLabels;
    $out['week']['data'] = $week;

    $month = array_fill(0, 12, 0);
    $monthLabels = [];
    for ($i = 11; $i >= 0; $i--) { $monthLabels[] = date('M', strtotime("-$i month")); }
    try {
        $stmt = $pdo->query("SELECT DATE_FORMAT(created_at,'%Y-%m') as m, SUM(total_amount) as total FROM orders WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH) GROUP BY m");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            for ($i = 11; $i >= 0; $i--) {
                if ($r['m'] === date('Y-m', strtotime("-$i month"))) $month[11-$i] = (float)$r['total'];
            }
        }
    } catch (PDOException $e) {}
    $out['month']['labels'] = $monthLabels;
    $out['month']['data'] = $month;

    $year = array_fill(0, 5, 0);
    $yearLabels = [];
    for ($i = 4; $i >= 0; $i--) { $yearLabels[] = date('Y', strtotime("-$i year")); }
    try {
        $stmt = $pdo->query("SELECT YEAR(created_at) as y, SUM(total_amount) as total FROM orders WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 4 YEAR) GROUP BY y");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            for ($i = 4; $i >= 0; $i--) {
                if ((int)$r['y'] === (int)date('Y', strtotime("-$i year"))) $year[4-$i] = (float)$r['total'];
            }
        }
    } catch (PDOException $e) {}
    $out['year']['labels'] = $yearLabels;
    $out['year']['data'] = $year;

    return $out;
}
$revenueSeries = buildRevenueSeries($pdo);

$statusLabels = ['Pending','Confirmed','Hold','Cancelled','Delivered','Return'];
$statusValues = array_values($statusCounts);
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<title>Dashboard</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<style>
  :root{
    --bg:#f3f5fb; --card:#ffffff; --text:#1f2430; --muted:#8a90a3; --border:#eceff5;
    --purple:#6c5ce7; --purple-soft:#efeafe;
    --pink:#ff5c9e; --pink-soft:#ffe6f0;
    --green:#22c55e; --green-soft:#e6faec;
    --orange:#ff9d3d; --orange-soft:#fff2e2;
    --blue:#2f7bf5; --blue-soft:#e8f1ff;
    --teal:#17b6b1; --teal-soft:#e2f9f8;
    --red:#ef4444; --red-soft:#fde8e8;
    --radius:16px; --shadow:0 4px 20px rgba(30,34,60,0.06);
  }
  *{box-sizing:border-box;}
  html,body{margin:0; padding:0;}
  body{ font-family:"Segoe UI","Noto Sans Bengali",sans-serif; background:var(--bg); color:var(--text); }
  .layout{display:flex; min-height:100vh; width:100%;}

  .sidebar{ width:230px; background:#fff; border-right:1px solid var(--border); padding:22px 16px; flex-shrink:0; }
  .brand{font-weight:700; font-size:18px; color:var(--purple); margin-bottom:22px; padding-left:8px;}
  .nav-item{ display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:10px; color:#5b6070; font-size:14px; margin-bottom:2px; }
  .nav-item.active{ background:var(--purple-soft); color:var(--purple); font-weight:600; }

  .main{ flex:1; padding:26px 28px; min-width:0; }
  .topbar{ display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
  .topbar h1{ font-size:22px; margin:0; }
  .topbar .today{ background:#fff; border:1px solid var(--border); border-radius:10px; padding:8px 14px; font-size:13px; color:var(--muted); }

  .stat-grid{ display:grid; grid-template-columns:repeat(auto-fit, minmax(170px, 1fr)); gap:14px; margin-bottom:20px; }
  .stat-card{ background:var(--card); border-radius:var(--radius); padding:16px; box-shadow:var(--shadow); }
  .stat-icon{ width:34px; height:34px; border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:16px; margin-bottom:8px; }
  .stat-label{ font-size:12.5px; color:var(--muted); margin-bottom:3px; white-space:nowrap; }
  .stat-value{ font-size:19px; font-weight:700; }

  .c-purple .stat-icon{background:var(--purple-soft); color:var(--purple);}
  .c-pink .stat-icon{background:var(--pink-soft); color:var(--pink);}
  .c-green .stat-icon{background:var(--green-soft); color:var(--green);}
  .c-orange .stat-icon{background:var(--orange-soft); color:var(--orange);}
  .c-blue .stat-icon{background:var(--blue-soft); color:var(--blue);}
  .c-teal .stat-icon{background:var(--teal-soft); color:var(--teal);}
  .c-red .stat-icon{background:var(--red-soft); color:var(--red);}

  .grid-2{ display:grid; grid-template-columns:1.6fr 1fr; gap:14px; margin-bottom:14px; }
  .grid-3{ display:grid; grid-template-columns:repeat(3, 1fr); gap:14px; margin-bottom:14px; }
  @media (max-width:1000px){ .grid-2, .grid-3{ grid-template-columns:1fr; } }

  .panel{ background:var(--card); border-radius:var(--radius); padding:18px; box-shadow:var(--shadow); min-width:0; }
  .panel-head{ display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; flex-wrap:wrap; gap:8px; }
  .panel-head h3{ margin:0; font-size:14.5px; }

  .filter-tabs{ display:flex; gap:4px; background:var(--bg); padding:3px; border-radius:8px; }
  .filter-tabs button{ border:none; background:transparent; padding:5px 10px; font-size:12px; border-radius:6px; color:var(--muted); cursor:pointer; }
  .filter-tabs button.active{ background:#fff; color:var(--purple); font-weight:600; box-shadow:0 1px 3px rgba(0,0,0,0.08); }

  /* চার্টের হাইট এখন সবসময় ফিক্সড থাকবে — আগের বাগ এখানেই ছিল */
  .chart-box{ position:relative; height:280px; }

  table{ width:100%; border-collapse:collapse; font-size:13px; }
  th{ text-align:left; color:var(--muted); font-weight:600; padding:8px 6px; border-bottom:1px solid var(--border); }
  td{ padding:9px 6px; border-bottom:1px solid var(--border); }
  .badge{ padding:3px 10px; border-radius:20px; font-size:11px; font-weight:600; white-space:nowrap; }
  .badge.pending{background:var(--orange-soft); color:var(--orange);}
  .badge.delivered{background:var(--green-soft); color:var(--green);}
  .badge.cancelled{background:var(--pink-soft); color:var(--pink);}
  .badge.confirmed, .badge.hold{background:var(--blue-soft); color:var(--blue);}
  .badge.return, .badge.default{background:#f0f0f4; color:#666;}

  .list-row{ display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid var(--border); font-size:13px; }
  .list-row:last-child{ border-bottom:none; }
  .list-name{ font-weight:600; }
  .list-sub{ color:var(--muted); font-size:11.5px; }
  .stock-badge{ padding:2px 9px; border-radius:20px; font-size:11px; font-weight:600; background:var(--red-soft); color:var(--red); }
  .empty-note{ text-align:center; color:var(--muted); font-size:13px; padding:24px 0; }
</style>
</head>
<body>
<div class="layout">

  <aside class="sidebar">
    <div class="brand">⚡ YourBrand</div>
    <div class="nav-item active">🏠 Dashboard</div>
    <div class="nav-item">📦 Orders</div>
    <div class="nav-item">🛒 Products</div>
    <div class="nav-item">👥 Customers</div>
    <div class="nav-item">📊 Reports</div>
    <div class="nav-item">⚙️ Settings</div>
  </aside>

  <main class="main">
    <div class="topbar">
      <h1>Dashboard</h1>
      <div class="today">📅 <?php echo date('d M, Y'); ?></div>
    </div>

    <div class="stat-grid">
      <div class="stat-card c-purple"><div class="stat-icon">🛍️</div><div class="stat-label">Today Orders</div><div class="stat-value"><?php echo $todayOrders; ?></div></div>
      <div class="stat-card c-orange"><div class="stat-icon">⏳</div><div class="stat-label">Pending Orders</div><div class="stat-value"><?php echo $statusCounts['pending']; ?></div></div>
      <div class="stat-card c-blue"><div class="stat-icon">✅</div><div class="stat-label">Confirmed Orders</div><div class="stat-value"><?php echo $statusCounts['confirmed']; ?></div></div>
      <div class="stat-card c-blue"><div class="stat-icon">🕒</div><div class="stat-label">Hold Orders</div><div class="stat-value"><?php echo $statusCounts['hold']; ?></div></div>
      <div class="stat-card c-pink"><div class="stat-icon">❌</div><div class="stat-label">Cancelled Orders</div><div class="stat-value"><?php echo $statusCounts['cancelled']; ?></div></div>
      <div class="stat-card c-green"><div class="stat-icon">📬</div><div class="stat-label">Delivered Orders</div><div class="stat-value"><?php echo $statusCounts['delivered']; ?></div></div>
      <div class="stat-card c-teal"><div class="stat-icon">↩️</div><div class="stat-label">Return Orders</div><div class="stat-value"><?php echo $statusCounts['return']; ?></div></div>
      <div class="stat-card c-purple"><div class="stat-icon">📦</div><div class="stat-label">Total Orders</div><div class="stat-value">৳<?php echo number_format($totalRevenue,0); ?></div></div>
      <div class="stat-card c-teal"><div class="stat-icon">🧾</div><div class="stat-label">Total Products</div><div class="stat-value"><?php echo $totalProducts; ?></div></div>
      <div class="stat-card c-orange"><div class="stat-icon">💸</div><div class="stat-label">Total Expenses</div><div class="stat-value">৳<?php echo number_format($totalExpenses, 0); ?></div></div>
      <div class="stat-card c-blue"><div class="stat-icon">💵</div><div class="stat-label">Income</div><div class="stat-value">৳<?php echo number_format($totalIncome,0); ?></div></div>
      <div class="stat-card c-green"><div class="stat-icon">📈</div><div class="stat-label">Final Profit</div><div class="stat-value">৳<?php echo number_format($finalProfit, 0); ?></div></div>
    </div>

    <div class="grid-2">
      <div class="panel">
        <div class="panel-head">
          <h3>Revenue Overview</h3>
          <div class="filter-tabs">
            <button data-range="today">Today</button>
            <button data-range="week" class="active">Weekly</button>
            <button data-range="month">Monthly</button>
            <button data-range="year">Yearly</button>
          </div>
        </div>
        <div class="chart-box"><canvas id="revenueChart"></canvas></div>
      </div>
      <div class="panel">
        <div class="panel-head"><h3>Orders by Status</h3></div>
        <div class="chart-box"><canvas id="statusChart"></canvas></div>
      </div>
    </div>

    <div class="grid-3">
      <div class="panel">
        <div class="panel-head"><h3>Sales by Category</h3></div>
        <?php if (count($categoryData) > 0): ?>
          <div class="chart-box"><canvas id="categoryChart"></canvas></div>
        <?php else: ?>
          <div class="empty-note">কোনো ক্যাটাগরি ডেটা পাওয়া যায়নি।<br>(order_items / products.category টেবিল চেক করুন)</div>
        <?php endif; ?>
      </div>

      <div class="panel">
        <div class="panel-head"><h3>Top Customers</h3></div>
        <?php if (count($topCustomers) > 0): ?>
          <?php foreach ($topCustomers as $c): ?>
            <div class="list-row">
              <div>
                <div class="list-name"><?php echo htmlspecialchars($c['customer_name']); ?></div>
                <div class="list-sub"><?php echo (int)$c['orders_count']; ?> orders</div>
              </div>
              <div><strong>৳<?php echo number_format($c['total_spent'],0); ?></strong></div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="empty-note">কোনো কাস্টমার ডেটা নেই।</div>
        <?php endif; ?>
      </div>

      <div class="panel">
        <div class="panel-head"><h3>Low Stock Products</h3></div>
        <?php if (count($lowStockProducts) > 0): ?>
          <?php foreach ($lowStockProducts as $p): ?>
            <div class="list-row">
              <div class="list-name"><?php echo htmlspecialchars($p['name']); ?></div>
              <div class="stock-badge"><?php echo $p['stock_quantity'] == 0 ? 'Out of Stock' : $p['stock_quantity'].' left'; ?></div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="empty-note">কোনো Low Stock প্রোডাক্ট নেই।<br>(products.stock_quantity কলাম চেক করুন)</div>
        <?php endif; ?>
      </div>
    </div>

    <div class="panel">
      <div class="panel-head">
        <h3>Recent Orders</h3>
        <a href="admin_orders.php" style="font-size:13px; color:var(--purple); text-decoration:none;">View All →</a>
      </div>
      <table>
        <thead><tr><th>Order ID</th><th>Customer</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
        <tbody>
        <?php if (count($recentOrders) > 0): ?>
          <?php foreach ($recentOrders as $order): ?>
            <?php
              $statusClass = strtolower($order['delivery_status']);
              if (!in_array($statusClass, ['pending','confirmed','hold','cancelled','delivered','return'])) $statusClass = 'default';
            ?>
            <tr>
              <td>#<?php echo str_pad($order['id'], 4, '0', STR_PAD_LEFT); ?></td>
              <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
              <td>৳<?php echo number_format($order['total_amount'],0); ?></td>
              <td><span class="badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($order['delivery_status']); ?></span></td>
              <td><?php echo date('d M Y, h:i A', strtotime($order['created_at'])); ?></td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="5" class="empty-note">No recent orders</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

  </main>
</div>

<script>
const revenueSeries = <?php echo json_encode($revenueSeries); ?>;

const revenueChart = new Chart(document.getElementById('revenueChart'), {
  type: 'line',
  data: {
    labels: revenueSeries.week.labels,
    datasets: [{
      label: 'Revenue',
      data: revenueSeries.week.data,
      borderColor: '#6c5ce7',
      backgroundColor: 'rgba(108,92,231,0.08)',
      tension: 0.35,
      fill: true,
      pointRadius: 3,
      pointBackgroundColor: '#6c5ce7'
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      y: { beginAtZero: true, grid: { color: '#f0f1f7' } },
      x: { grid: { display: false } }
    }
  }
});

document.querySelectorAll('.filter-tabs button').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.filter-tabs button').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    const range = btn.dataset.range;
    revenueChart.data.labels = revenueSeries[range].labels;
    revenueChart.data.datasets[0].data = revenueSeries[range].data;
    revenueChart.update();
  });
});

new Chart(document.getElementById('statusChart'), {
  type: 'doughnut',
  data: {
    labels: <?php echo json_encode($statusLabels); ?>,
    datasets: [{
      data: <?php echo json_encode($statusValues); ?>,
      backgroundColor: ['#ff9d3d', '#2f7bf5', '#6c5ce7', '#ff5c9e', '#22c55e', '#17b6b1'],
      borderWidth: 0
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '65%',
    plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } }
  }
});

<?php if (count($categoryData) > 0): ?>
new Chart(document.getElementById('categoryChart'), {
  type: 'doughnut',
  data: {
    labels: <?php echo json_encode($categoryLabels); ?>,
    datasets: [{
      data: <?php echo json_encode($categoryData); ?>,
      backgroundColor: ['#2f7bf5', '#22c55e', '#6c5ce7', '#ff9d3d', '#ff5c9e', '#17b6b1'],
      borderWidth: 0
    }]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '60%',
    plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } }
  }
});
<?php endif; ?>
</script>
</body>
</html>