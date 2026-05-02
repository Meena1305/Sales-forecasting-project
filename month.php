<?php
session_start();
include("db_connect.php");

// Get month from URL parameter
$monthParam = isset($_GET['month']) ? $_GET['month'] : date('M');

$monthNames = [
    'Jan' => 1, 'Feb' => 2, 'Mar' => 3, 'Apr' => 4, 'May' => 5, 'Jun' => 6,
    'Jul' => 7, 'Aug' => 8, 'Sep' => 9, 'Oct' => 10, 'Nov' => 11, 'Dec' => 12
];

$monthNumber = isset($monthNames[$monthParam]) ? $monthNames[$monthParam] : date('n');
$monthFullName = date('F', mktime(0, 0, 0, $monthNumber, 1));
$currentYear = date('Y');

// Get selected year from parameter or default to current year
$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : $currentYear;

// 1. Total revenue for this month
$revenueQuery = "SELECT COALESCE(SUM(total_amount), 0) as revenue 
                 FROM Orders 
                 WHERE MONTH(order_date) = ? AND YEAR(order_date) = ?";
$revenueStmt = sqlsrv_query($conn, $revenueQuery, array($monthNumber, $selectedYear));
if ($revenueStmt === false) {
    $totalRevenue = 0;
} else {
    $revenueData = sqlsrv_fetch_array($revenueStmt, SQLSRV_FETCH_ASSOC);
    $totalRevenue = $revenueData['revenue'] ?? 0;
}

// 2. Total orders for this month
$ordersQuery = "SELECT COUNT(*) as total 
                FROM Orders 
                WHERE MONTH(order_date) = ? AND YEAR(order_date) = ?";
$ordersStmt = sqlsrv_query($conn, $ordersQuery, array($monthNumber, $selectedYear));
if ($ordersStmt === false) {
    $totalOrders = 0;
} else {
    $ordersData = sqlsrv_fetch_array($ordersStmt, SQLSRV_FETCH_ASSOC);
    $totalOrders = $ordersData['total'] ?? 0;
}

// 3. Total customers for this month (from Orders table since you have customer_name)
$customersQuery = "SELECT COUNT(DISTINCT customer_name) as total 
                   FROM Orders 
                   WHERE MONTH(order_date) = ? AND YEAR(order_date) = ?";
$customersStmt = sqlsrv_query($conn, $customersQuery, array($monthNumber, $selectedYear));
if ($customersStmt === false) {
    $totalCustomers = 0;
} else {
    $customersData = sqlsrv_fetch_array($customersStmt, SQLSRV_FETCH_ASSOC);
    $totalCustomers = $customersData['total'] ?? 0;
}

// 4. Get previous year same month for comparison
$prevYearRevenueQuery = "SELECT COALESCE(SUM(total_amount), 0) as revenue 
                         FROM Orders 
                         WHERE MONTH(order_date) = ? AND YEAR(order_date) = ?";
$prevStmt = sqlsrv_query($conn, $prevYearRevenueQuery, array($monthNumber, $selectedYear - 1));
if ($prevStmt === false) {
    $prevRevenue = 0;
} else {
    $prevData = sqlsrv_fetch_array($prevStmt, SQLSRV_FETCH_ASSOC);
    $prevRevenue = $prevData['revenue'] ?? 0;
}
$growth = $prevRevenue > 0 ? round((($totalRevenue - $prevRevenue) / $prevRevenue) * 100, 1) : 0;

// 5. Get top products for this month (using your schema: Products table has id, name)
$topProductsQuery = "SELECT TOP 5 
                        p.name as product_name,
                        SUM(s.quantity_sold) as quantity,
                        SUM(p.price * s.quantity_sold) as revenue
                     FROM Sales s
                     JOIN Products p ON s.product_id = p.id
                     JOIN Orders o ON s.order_id = o.id
                     WHERE MONTH(o.order_date) = ? AND YEAR(o.order_date) = ?
                     GROUP BY p.name
                     ORDER BY revenue DESC";
$topStmt = sqlsrv_query($conn, $topProductsQuery, array($monthNumber, $selectedYear));
$topProducts = [];
if ($topStmt !== false) {
    while ($row = sqlsrv_fetch_array($topStmt, SQLSRV_FETCH_ASSOC)) {
        $topProducts[] = $row;
    }
}

// 6. Get daily sales for this month
$dailySalesQuery = "SELECT 
                        DAY(order_date) as day,
                        SUM(total_amount) as revenue,
                        COUNT(*) as orders
                    FROM Orders
                    WHERE MONTH(order_date) = ? AND YEAR(order_date) = ?
                    GROUP BY DAY(order_date)
                    ORDER BY day";
$dailyStmt = sqlsrv_query($conn, $dailySalesQuery, array($monthNumber, $selectedYear));
$dailySales = [];
if ($dailyStmt !== false) {
    while ($row = sqlsrv_fetch_array($dailyStmt, SQLSRV_FETCH_ASSOC)) {
        $dailySales[] = $row;
    }
}

// 7. Get recent orders for this month
$ordersListQuery = "SELECT TOP 10 
                        o.id as order_id,
                        o.order_date,
                        o.customer_name,
                        o.total_amount,
                        o.status
                    FROM Orders o
                    WHERE MONTH(o.order_date) = ? AND YEAR(o.order_date) = ?
                    ORDER BY o.order_date DESC";
$ordersListStmt = sqlsrv_query($conn, $ordersListQuery, array($monthNumber, $selectedYear));
$recentOrders = [];
if ($ordersListStmt !== false) {
    while ($row = sqlsrv_fetch_array($ordersListStmt, SQLSRV_FETCH_ASSOC)) {
        $recentOrders[] = $row;
    }
}

// 8. Get available years for this month
$yearsQuery = "SELECT DISTINCT YEAR(order_date) as year 
               FROM Orders 
               WHERE MONTH(order_date) = ?
               ORDER BY year DESC";
$yearsStmt = sqlsrv_query($conn, $yearsQuery, array($monthNumber));
$availableYears = [];
if ($yearsStmt !== false) {
    while ($row = sqlsrv_fetch_array($yearsStmt, SQLSRV_FETCH_ASSOC)) {
        $availableYears[] = $row['year'];
    }
}
if (empty($availableYears)) {
    $availableYears = [$currentYear];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $monthFullName; ?> <?php echo $selectedYear; ?> - InsightSphere</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f7fa;
            color: #1a2634;
        }
        
        .page-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 40px;
        }
        
        /* Header */
        .page-header {
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            border-radius: 24px;
            padding: 40px;
            color: white;
            margin-bottom: 30px;
        }
        
        .page-header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .page-header h1 i {
            font-size: 2.5rem;
        }
        
        .page-header p {
            opacity: 0.9;
            margin-top: 10px;
        }
        
        /* Year Selector */
        .year-selector {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        .year-btn {
            padding: 8px 24px;
            border: 1px solid #e2e8f0;
            background: white;
            border-radius: 30px;
            cursor: pointer;
            text-decoration: none;
            color: #64748b;
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        
        .year-btn.active {
            background: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }
        
        .year-btn:hover {
            border-color: #3b82f6;
            color: #3b82f6;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            border: 1px solid #e2e8f0;
            transition: all 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
        }
        
        .stat-card .icon {
            font-size: 2rem;
            color: #3b82f6;
            margin-bottom: 15px;
        }
        
        .stat-card h3 {
            font-size: 0.75rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        
        .stat-card .value {
            font-size: 2rem;
            font-weight: 700;
            color: #0f172a;
        }
        
        .stat-card .change {
            font-size: 0.75rem;
            margin-top: 10px;
        }
        
        .change.positive { color: #10b981; }
        .change.negative { color: #ef4444; }
        
        /* Two Column Layout */
        .two-columns {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
            margin-bottom: 30px;
        }
        
        /* Cards */
        .card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            border: 1px solid #e2e8f0;
        }
        
        .card h3 {
            font-size: 1rem;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .card h3 i {
            color: #3b82f6;
        }
        
        /* Tables */
        .products-table, .orders-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .products-table th, .orders-table th,
        .products-table td, .orders-table td {
            padding: 12px 0;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .products-table th, .orders-table th {
            font-size: 0.7rem;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .products-table td, .orders-table td {
            font-size: 0.85rem;
            color: #1e293b;
        }
        
        .products-table tr:last-child td,
        .orders-table tr:last-child td {
            border-bottom: none;
        }
        
        .status-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .status-Completed, .status-completed { background: #ecfdf5; color: #10b981; }
        .status-Pending, .status-pending { background: #fef3c7; color: #d97706; }
        .status-Cancelled, .status-cancelled { background: #fef2f2; color: #ef4444; }
        .status-Shipped, .status-shipped { background: #dbeafe; color: #3b82f6; }
        
        /* Back Button */
        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: white;
            color: #3b82f6;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 30px;
            margin-bottom: 20px;
            border: 1px solid #e2e8f0;
            transition: all 0.2s;
        }
        
        .back-button:hover {
            background: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #94a3b8;
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .two-columns {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .page-container {
                padding: 20px;
            }
            .page-header {
                padding: 25px;
            }
            .page-header h1 {
                font-size: 1.5rem;
            }
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .stat-card .value {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="page-container">
        <!-- Back Button -->
        <a href="indexHome.php" class="back-button">
            <i class='bx bx-arrow-back'></i> Back to Dashboard
        </a>
        
        <!-- Header -->
        <div class="page-header">
            <h1>
                <i class='bx bx-calendar'></i>
                <?php echo $monthFullName; ?> <?php echo $selectedYear; ?>
            </h1>
            <p>Complete sales analytics and performance report for <?php echo $monthFullName; ?> <?php echo $selectedYear; ?></p>
        </div>
        
        <!-- Year Selector -->
        <div class="year-selector">
            <?php foreach ($availableYears as $year): ?>
                <a href="?month=<?php echo $monthParam; ?>&year=<?php echo $year; ?>" 
                   class="year-btn <?php echo $year == $selectedYear ? 'active' : ''; ?>">
                    <?php echo $year; ?>
                </a>
            <?php endforeach; ?>
        </div>
        
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="icon"><i class='bx bx-rupee'></i></div>
                <h3>Total Revenue</h3>
                <div class="value">₹<?php echo number_format($totalRevenue); ?></div>
                <div class="change <?php echo $growth >= 0 ? 'positive' : 'negative'; ?>">
                    <?php echo $growth >= 0 ? '↑' : '↓'; ?> <?php echo abs($growth); ?>% vs last year
                </div>
            </div>
            
            <div class="stat-card">
                <div class="icon"><i class='bx bx-shopping-bag'></i></div>
                <h3>Total Orders</h3>
                <div class="value"><?php echo number_format($totalOrders); ?></div>
            </div>
            
            <div class="stat-card">
                <div class="icon"><i class='bx bx-user'></i></div>
                <h3>Unique Customers</h3>
                <div class="value"><?php echo number_format($totalCustomers); ?></div>
            </div>
            
            <div class="stat-card">
                <div class="icon"><i class='bx bx-trending-up'></i></div>
                <h3>Average Order Value</h3>
                <div class="value">₹<?php echo $totalOrders > 0 ? number_format($totalRevenue / $totalOrders, 0) : 0; ?></div>
            </div>
        </div>
        
        <!-- Daily Sales Chart -->
        <div class="card" style="margin-bottom: 30px;">
            <h3><i class='bx bx-line-chart'></i> Daily Sales Trend</h3>
            <canvas id="dailyChart" height="100"></canvas>
        </div>
        
        <!-- Two Columns -->
        <div class="two-columns">
            <!-- Top Products -->
            <div class="card">
                <h3><i class='bx bx-trophy'></i> Top 5 Products</h3>
                <?php if (empty($topProducts)): ?>
                    <div class="no-data">No products sold this month</div>
                <?php else: ?>
                    <table class="products-table">
                        <thead>
                            <tr><th>Product</th><th>Quantity</th><th>Revenue</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topProducts as $product): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                <td><?php echo number_format($product['quantity']); ?> units</td>
                                <td>₹<?php echo number_format($product['revenue']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <!-- Recent Orders -->
            <div class="card">
                <h3><i class='bx bx-receipt'></i> Recent Orders</h3>
                <?php if (empty($recentOrders)): ?>
                    <div class="no-data">No orders this month</div>
                <?php else: ?>
                    <table class="orders-table">
                        <thead>
                            <tr><th>Order ID</th><th>Date</th><th>Customer</th><th>Amount</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentOrders as $order): ?>
                            <tr>
                                <td>#<?php echo $order['order_id']; ?></td>
                                <td><?php 
                                    if ($order['order_date'] instanceof DateTime) {
                                        echo $order['order_date']->format('d M Y');
                                    } else {
                                        echo date('d M Y', strtotime($order['order_date']));
                                    }
                                ?></td>
                                <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                <td>₹<?php echo number_format($order['total_amount']); ?></td>
                                <td><span class="status-badge status-<?php echo strtolower($order['status']); ?>"><?php echo $order['status']; ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Summary Section -->
        <div class="card">
            <h3><i class='bx bx-info-circle'></i> Monthly Summary</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                <div>
                    <div style="font-size: 0.7rem; color: #64748b;">Best Day Revenue</div>
                    <div style="font-size: 1.1rem; font-weight: 600; margin-top: 5px;">
                        <?php 
                        $bestDay = !empty($dailySales) ? max(array_column($dailySales, 'revenue')) : 0;
                        echo $bestDay > 0 ? '₹' . number_format($bestDay) : 'No data';
                        ?>
                    </div>
                </div>
                <div>
                    <div style="font-size: 0.7rem; color: #64748b;">Average Daily Revenue</div>
                    <div style="font-size: 1.1rem; font-weight: 600; margin-top: 5px;">
                        <?php 
                        $avgDaily = !empty($dailySales) ? $totalRevenue / count($dailySales) : 0;
                        echo $avgDaily > 0 ? '₹' . number_format($avgDaily, 0) : 'No data';
                        ?>
                    </div>
                </div>
                <div>
                    <div style="font-size: 0.7rem; color: #64748b;">Orders Per Day</div>
                    <div style="font-size: 1.1rem; font-weight: 600; margin-top: 5px;">
                        <?php 
                        $avgOrders = !empty($dailySales) ? $totalOrders / count($dailySales) : 0;
                        echo $avgOrders > 0 ? round($avgOrders, 1) : 'No data';
                        ?>
                    </div>
                </div>
                <div>
                    <div style="font-size: 0.7rem; color: #64748b;">Total Items Sold</div>
                    <div style="font-size: 1.1rem; font-weight: 600; margin-top: 5px;">
                        <?php 
                        $totalItems = 0;
                        foreach ($topProducts as $product) {
                            $totalItems += $product['quantity'];
                        }
                        echo number_format($totalItems);
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Daily Sales Chart
        const dailyData = <?php 
            $days = [];
            $revenues = [];
            for ($d = 1; $d <= 31; $d++) {
                $found = false;
                foreach ($dailySales as $sale) {
                    if ($sale['day'] == $d) {
                        $revenues[] = $sale['revenue'];
                        $found = true;
                        break;
                    }
                }
                if (!$found) $revenues[] = 0;
                $days[] = $d;
            }
            echo json_encode($revenues);
        ?>;
        
        const daysLabels = <?php echo json_encode($days); ?>;
        
        const ctx = document.getElementById('dailyChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: daysLabels,
                datasets: [{
                    label: 'Daily Revenue (₹)',
                    data: dailyData,
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#3b82f6',
                    pointBorderColor: 'white',
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return '₹' + context.raw.toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₹' + value.toLocaleString();
                            }
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Day of Month'
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>