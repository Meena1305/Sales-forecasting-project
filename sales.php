<?php
// sales.php - Sales Page (Fixed Donut Chart)
session_start();
error_reporting(0);
ini_set('display_errors', 0);

include("db_connect.php");

if (!$conn) {
    die("Database connection failed: " . print_r(sqlsrv_errors(), true));
}

// Get Total Revenue
$totalSalesQuery = "SELECT ISNULL(SUM(total_amount), 0) AS total FROM Orders";
$totalSalesStmt = sqlsrv_query($conn, $totalSalesQuery);
$totalSales = 0;
if ($totalSalesStmt) {
    $totalSalesData = sqlsrv_fetch_array($totalSalesStmt, SQLSRV_FETCH_ASSOC);
    $totalSales = $totalSalesData['total'] ?? 0;
    sqlsrv_free_stmt($totalSalesStmt);
}

// Get Total Orders
$totalOrdersQuery = "SELECT ISNULL(COUNT(*), 0) AS count FROM Orders";
$totalOrdersStmt = sqlsrv_query($conn, $totalOrdersQuery);
$totalOrders = 0;
if ($totalOrdersStmt) {
    $totalOrdersData = sqlsrv_fetch_array($totalOrdersStmt, SQLSRV_FETCH_ASSOC);
    $totalOrders = $totalOrdersData['count'] ?? 0;
    sqlsrv_free_stmt($totalOrdersStmt);
}

// Get Units Sold
$unitsQuery = "SELECT ISNULL(SUM(quantity_sold), 0) AS total_units FROM Sales";
$unitsStmt = sqlsrv_query($conn, $unitsQuery);
$unitsSold = 0;
if ($unitsStmt) {
    $unitsData = sqlsrv_fetch_array($unitsStmt, SQLSRV_FETCH_ASSOC);
    $unitsSold = $unitsData['total_units'] ?? 0;
    sqlsrv_free_stmt($unitsStmt);
}

// Get Top 5 Products
$topProducts = [];
$topProductsQuery = "
    SELECT TOP 5
        p.name AS product_name,
        ISNULL(SUM(s.quantity_sold), 0) AS total_sold,
        ISNULL(SUM(o.total_amount), 0) AS revenue
    FROM Sales s
    INNER JOIN Products p ON s.product_id = p.id
    INNER JOIN Orders o ON s.order_id = o.id
    GROUP BY p.name
    ORDER BY revenue DESC
";

$topProductsStmt = sqlsrv_query($conn, $topProductsQuery);
$top5Revenue = 0;

if ($topProductsStmt) {
    while ($row = sqlsrv_fetch_array($topProductsStmt, SQLSRV_FETCH_ASSOC)) {
        if ($row['total_sold'] > 0 && $row['revenue'] > 0) {
            $topProducts[] = [
                'product_name' => $row['product_name'],
                'total_sold' => intval($row['total_sold']),
                'revenue' => floatval($row['revenue'])
            ];
            $top5Revenue += floatval($row['revenue']);
        }
    }
    sqlsrv_free_stmt($topProductsStmt);
}

// If no products found, try alternative query
if (empty($topProducts)) {
    $simpleQuery = "
        SELECT TOP 5
            p.name AS product_name,
            ISNULL(SUM(s.quantity_sold), 0) AS total_sold,
            ISNULL(SUM(s.quantity_sold * p.price), 0) AS estimated_revenue
        FROM Sales s
        INNER JOIN Products p ON s.product_id = p.id
        GROUP BY p.name
        ORDER BY estimated_revenue DESC
    ";
    
    $simpleStmt = sqlsrv_query($conn, $simpleQuery);
    if ($simpleStmt) {
        while ($row = sqlsrv_fetch_array($simpleStmt, SQLSRV_FETCH_ASSOC)) {
            if ($row['total_sold'] > 0) {
                $topProducts[] = [
                    'product_name' => $row['product_name'],
                    'total_sold' => intval($row['total_sold']),
                    'revenue' => floatval($row['estimated_revenue'])
                ];
                $top5Revenue += floatval($row['estimated_revenue']);
            }
        }
        sqlsrv_free_stmt($simpleStmt);
    }
}

// Calculate percentage for donut chart (Top 5 Revenue / Total Revenue)
$donutPercentage = 0;
if ($totalSales > 0 && $top5Revenue > 0) {
    $donutPercentage = round(($top5Revenue / $totalSales) * 100, 1);
}

// Calculate stroke-dasharray for donut chart
$circumference = 502.65; // 2 * PI * 80 (radius)
$strokeDasharray = ($donutPercentage / 100) * $circumference;

// Get Conversion Rate
$totalVisits = 191886;
$conversionRate = $totalOrders > 0 ? round(($totalOrders / $totalVisits) * 100, 1) : 0;
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Dashboard - InsightSphere</title>
    <link rel="stylesheet" href="sales.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        .product-list {
            max-height: 320px;
            overflow-y: auto;
        }
        .product-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        .product-item:last-child {
            border-bottom: none;
        }
        .product-info {
            flex: 1;
        }
        .product-name {
            font-weight: 600;
            color: #0f172a;
            font-size: 0.9rem;
        }
        .product-stats {
            font-size: 0.7rem;
            color: #64748b;
            margin-top: 4px;
        }
        .product-revenue {
            font-weight: 700;
            color: #3b82f6;
            font-size: 1rem;
        }
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #94a3b8;
        }
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            display: block;
        }
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        .metric-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .metric-header {
            font-size: 0.7rem;
            color: #64748b;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        .metric-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: #0f172a;
        }
        .metric-change {
            font-size: 0.7rem;
            margin-top: 8px;
        }
        .metric-change.positive {
            color: #10b981;
        }
        .card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .card-header {
            font-weight: 600;
            margin-bottom: 16px;
            font-size: 1rem;
        }
        .see-details {
            margin-top: 20px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #3b82f6;
            text-decoration: none;
            font-size: 0.85rem;
        }
        .see-details:hover {
            text-decoration: underline;
        }
        .donut-label {
            font-size: 0.7rem;
            text-align: center;
            margin-top: 8px;
            color: #64748b;
        }
        @media (max-width: 768px) {
            .metrics-grid {
                grid-template-columns: 1fr;
            }
            .bottom-grid {
                grid-template-columns: 1fr !important;
            }
        }
    </style>
</head>

<body>
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="logo">
                <div class="logo-icon">IS</div>
                <div class="logo-text">InsightSphere</div>
            </div>
            <nav class="nav-section">
                <div class="nav-label">Main Menu</div>
                <a href="indexHome.php" class="nav-item">
                    <i class='bx bx-line-chart'></i>
                    <span>Dashboard</span>
                </a>
                <a href="analytics.php" class="nav-item">
                    <i class='bx bx-bar-chart-alt'></i>
                    <span>Analytics</span>
                </a>
                <a href="sales.php" class="nav-item active">
                    <i class='bx bxs-cart'></i>
                    <span>Sales</span>
                    <span class="nav-badge"><?php echo $totalOrders; ?></span>
                </a>
                <a href="products.php" class="nav-item">
                    <i class='bx bxs-box'></i>
                    <span>Products</span>
                </a>
                <a href="customers.php" class="nav-item">
                    <i class='bx bxs-user'></i>
                    <span>Customers</span>
                </a>
            </nav>
            <nav class="nav-section">
                <div class="nav-label">Management</div>
                <a href="orders.php" class="nav-item">
                    <i class='bx bxs-file-pdf'></i>
                    <span>Orders</span>
                </a>
                <a href="inventory.php" class="nav-item">
                    <i class='bx bxs-building-house'></i>
                    <span>Inventory</span>
                </a>
                <a href="reports.php" class="nav-item">
                    <i class='bx bxs-pie-chart'></i>
                    <span>Reports</span>
                </a>
            </nav>
            <div class="sidebar-footer">
                <a href="logout.php" class="nav-item" onclick="return confirmLogout()">
                    <i class='bx bx-log-in'></i>
                    <span>Logout</span>
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="main-content">
            <h1>Sales Dashboard</h1>

            <!-- Metrics Cards -->
            <div class="metrics-grid">
                <div class="metric-card">
                    <div class="metric-header">TOTAL REVENUE</div>
                    <div class="metric-value">₹<?php echo number_format($totalSales); ?></div>
                    <div class="metric-change positive">↑ +12.5% vs last month</div>
                </div>
                <div class="metric-card">
                    <div class="metric-header">TOTAL ORDERS</div>
                    <div class="metric-value"><?php echo number_format($totalOrders); ?></div>
                    <div class="metric-change positive">↑ +8% vs last month</div>
                </div>
                <div class="metric-card">
                    <div class="metric-header">UNITS SOLD</div>
                    <div class="metric-value"><?php echo number_format($unitsSold); ?></div>
                    <div class="metric-change positive">↑ +8.3% vs last month</div>
                </div>
                <div class="metric-card">
                    <div class="metric-header">CONVERSION RATE</div>
                    <div class="metric-value"><?php echo $conversionRate; ?>%</div>
                    <div class="metric-change positive">↑ +5.2% vs last month</div>
                </div>
            </div>

            <!-- Bottom Grid - 2 Columns -->
            <div class="bottom-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <!-- Sales by Time Heatmap -->
                <div class="card">
                    <div class="card-header">
                        Sales by Time <i class='bx bxs-info-circle'></i>
                    </div>
                    <div class="heatmap">
                        <div class="heatmap-grid" style="display: grid; grid-template-columns: repeat(8, 1fr); gap: 4px;">
                            <div></div>
                            <div style="text-align: center; color: #64748b; font-size: 11px;">Mon</div>
                            <div style="text-align: center; color: #64748b; font-size: 11px;">Tue</div>
                            <div style="text-align: center; color: #64748b; font-size: 11px;">Wed</div>
                            <div style="text-align: center; color: #64748b; font-size: 11px;">Thu</div>
                            <div style="text-align: center; color: #64748b; font-size: 11px;">Fri</div>
                            <div style="text-align: center; color: #64748b; font-size: 11px;">Sat</div>
                            <div style="text-align: center; color: #64748b; font-size: 11px;">Sun</div>

                            <div class="heatmap-label" style="font-size: 0.7rem;">Morning</div>
                            <div class="heatmap-cell" style="background: #fc8181; height: 30px; border-radius: 4px;"></div>
                            <div class="heatmap-cell" style="background: #fed7d7; height: 30px; border-radius: 4px;"></div>
                            <div class="heatmap-cell" style="background: #fc8181; height: 30px; border-radius: 4px;"></div>
                            <div class="heatmap-cell" style="background: #fed7d7; height: 30px; border-radius: 4px;"></div>
                            <div class="heatmap-cell" style="background: #f56565; height: 30px; border-radius: 4px;"></div>
                            <div class="heatmap-cell" style="background: #e53e3e; height: 30px; border-radius: 4px;"></div>
                            <div class="heatmap-cell" style="background: #fc8181; height: 30px; border-radius: 4px;"></div>

                            <div class="heatmap-label" style="font-size: 0.7rem;">Afternoon</div>
                            <div class="heatmap-cell" style="background: #f56565; height: 30px; border-radius: 4px;"></div>
                            <div class="heatmap-cell" style="background: #f56565; height: 30px; border-radius: 4px;"></div>
                            <div class="heatmap-cell" style="background: #e53e3e; height: 30px; border-radius: 4px;"></div>
                            <div class="heatmap-cell" style="background: #e53e3e; height: 30px; border-radius: 4px;"></div>
                            <div class="heatmap-cell" style="background: #f56565; height: 30px; border-radius: 4px;"></div>
                            <div class="heatmap-cell" style="background: #fc8181; height: 30px; border-radius: 4px;"></div>
                            <div class="heatmap-cell" style="background: #fed7d7; height: 30px; border-radius: 4px;"></div>

                            <div class="heatmap-label" style="font-size: 0.7rem;">Evening</div>
                            <div class="heatmap-cell" style="background: #e53e3e; height: 30px; border-radius: 4px;"></div>
                            <div class="heatmap-cell" style="background: #e53e3e; height: 30px; border-radius: 4px;"></div>
                            <div class="heatmap-cell" style="background: #f56565; height: 30px; border-radius: 4px;"></div>
                            <div class="heatmap-cell" style="background: #f56565; height: 30px; border-radius: 4px;"></div>
                            <div class="heatmap-cell" style="background: #fc8181; height: 30px; border-radius: 4px;"></div>
                            <div class="heatmap-cell" style="background: #fed7d7; height: 30px; border-radius: 4px;"></div>
                            <div class="heatmap-cell" style="background: #fed7d7; height: 30px; border-radius: 4px;"></div>
                        </div>
                        <div class="heatmap-legend" style="display: flex; align-items: center; gap: 8px; margin-top: 12px;">
                            <span>Low Sales</span>
                            <div class="legend-scale" style="display: flex; gap: 2px;">
                                <div style="background: #fed7d7; width: 20px; height: 20px; border-radius: 4px;"></div>
                                <div style="background: #fc8181; width: 20px; height: 20px; border-radius: 4px;"></div>
                                <div style="background: #f56565; width: 20px; height: 20px; border-radius: 4px;"></div>
                                <div style="background: #e53e3e; width: 20px; height: 20px; border-radius: 4px;"></div>
                            </div>
                            <span>High Sales</span>
                        </div>
                    </div>
                </div>

                <!-- Top 5 Selling Products -->
                <div class="card">
                    <div class="card-header">
                        Top 5 Selling Products <i class='bx bxs-info-circle'></i>
                    </div>
                    <div class="visits-content">
                        <div class="total-visits">
                            <div class="total-number" style="font-size: 1rem; font-weight: 600;">🏆 Top Performers</div>
                            <div class="total-change" style="font-size: 0.7rem; color: #10b981;">
                                Products by revenue <i class='bx bx-up-arrow-alt'></i> all time
                            </div>
                        </div>
                        <div style="display: flex; align-items: flex-start; gap: 30px; flex-wrap: wrap;">
                            <div class="visit-stats" style="flex: 1;">
                                <?php if (empty($topProducts)): ?>
                                    <div class="empty-state">
                                        <i class='bx bx-package'></i>
                                        No sales data available<br>
                                        <small>Upload data to see top products</small>
                                    </div>
                                <?php else: ?>
                                    <div class="product-list">
                                        <?php 
                                        $rankEmojis = ['🥇', '🥈', '🥉', '4️⃣', '5️⃣'];
                                        foreach ($topProducts as $index => $product): 
                                        ?>
                                            <div class="product-item">
                                                <div class="product-info">
                                                    <span style="font-size: 1.2rem; margin-right: 10px;"><?php echo $rankEmojis[$index]; ?></span>
                                                    <span class="product-name"><?php echo htmlspecialchars($product['product_name']); ?></span>
                                                    <div class="product-stats">
                                                        📦 Sold: <?php echo number_format($product['total_sold']); ?> units
                                                    </div>
                                                </div>
                                                <div class="product-revenue">
                                                    💰 ₹<?php echo number_format($product['revenue']); ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div style="text-align: center; flex-shrink: 0;">
                                <div class="donut-chart" style="width: 120px; height: 120px;">
                                    <svg viewBox="0 0 200 200">
                                        <circle cx="100" cy="100" r="80" fill="none" stroke="#e2e8f0" stroke-width="40" />
                                        <circle cx="100" cy="100" r="80" fill="none" stroke="#3b82f6" stroke-width="40"
                                            stroke-dasharray="<?php echo max(0, $strokeDasharray); ?> <?php echo $circumference; ?>" 
                                            stroke-linecap="round"
                                            transform="rotate(-90 100 100)" />
                                        <text x="100" y="95" text-anchor="middle" font-size="28" font-weight="600" fill="#0f172a"><?php echo $donutPercentage; ?>%</text>
                                        <text x="100" y="120" text-anchor="middle" font-size="10" fill="#64748b">of revenue</text>
                                    </svg>
                                </div>
                                <div class="donut-label">
                                    Top 5 Products
                                </div>
                            </div>
                        </div>
                        <a href="products.php" class="see-details">
                            View All Products <i class='bx bx-right-arrow-alt'></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('active');
        }

        function confirmLogout() {
            return confirm('Are you sure you want to logout?');
        }
    </script>
</body>

</html>