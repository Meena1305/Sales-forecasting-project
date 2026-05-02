<?php
session_start();
include("db_connect.php");

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$loggedInUser = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Guest';
$userRole = $_SESSION['role'] ?? 'User';

$nameParts = explode(' ', $loggedInUser);
$userInitials = strtoupper(substr($nameParts[0], 0, 1));
if (isset($nameParts[1])) {
    $userInitials .= strtoupper(substr($nameParts[1], 0, 1));
}

// ========== GET REAL DATA FROM DATABASE ==========
$revenueQuery = "SELECT ISNULL(SUM(total_amount), 0) as total FROM Orders";
$revenueStmt = sqlsrv_query($conn, $revenueQuery);
$revenueRow = sqlsrv_fetch_array($revenueStmt, SQLSRV_FETCH_ASSOC);
$totalRevenue = $revenueRow['total'] ?? 0;

$ordersQuery = "SELECT COUNT(*) as total FROM Orders";
$ordersStmt = sqlsrv_query($conn, $ordersQuery);
$ordersRow = sqlsrv_fetch_array($ordersStmt, SQLSRV_FETCH_ASSOC);
$totalOrders = $ordersRow['total'] ?? 0;

$productsQuery = "SELECT COUNT(*) as total FROM Products";
$productsStmt = sqlsrv_query($conn, $productsQuery);
$productsRow = sqlsrv_fetch_array($productsStmt, SQLSRV_FETCH_ASSOC);
$totalProducts = $productsRow['total'] ?? 0;

$customersQuery = "SELECT COUNT(DISTINCT customer_name) as total FROM Orders";
$customersStmt = sqlsrv_query($conn, $customersQuery);
$customersRow = sqlsrv_fetch_array($customersStmt, SQLSRV_FETCH_ASSOC);
$totalCustomers = $customersRow['total'] ?? 0;

$unitsQuery = "SELECT ISNULL(SUM(quantity_sold), 0) as total FROM Sales";
$unitsStmt = sqlsrv_query($conn, $unitsQuery);
$unitsRow = sqlsrv_fetch_array($unitsStmt, SQLSRV_FETCH_ASSOC);
$totalUnitsSold = $unitsRow['total'] ?? 0;

$avgOrderValue = $totalOrders > 0 ? round($totalRevenue / $totalOrders, 2) : 0;

$invValueQuery = "SELECT ISNULL(SUM(p.price * i.quantity_available), 0) as total FROM Products p LEFT JOIN Inventory i ON p.id = i.product_id";
$invValueStmt = sqlsrv_query($conn, $invValueQuery);
$invValueRow = sqlsrv_fetch_array($invValueStmt, SQLSRV_FETCH_ASSOC);
$inventoryValue = $invValueRow['total'] ?? 0;

$activeBuyersQuery = "SELECT COUNT(DISTINCT customer_name) as total FROM Orders WHERE order_date >= DATEADD(day, -30, GETDATE())";
$activeStmt = sqlsrv_query($conn, $activeBuyersQuery);
$activeRow = sqlsrv_fetch_array($activeStmt, SQLSRV_FETCH_ASSOC);
$activeBuyers = $activeRow['total'] ?? 0;

$topProducts = [];
$topProductsQuery = "SELECT TOP 5 
    p.name as product_name,
    ISNULL(SUM(s.quantity_sold), 0) as total_sold,
    ISNULL(SUM(o.total_amount), 0) as revenue
FROM Sales s
INNER JOIN Products p ON s.product_id = p.id
INNER JOIN Orders o ON s.order_id = o.id
GROUP BY p.name
ORDER BY revenue DESC";

$topStmt = sqlsrv_query($conn, $topProductsQuery);
if ($topStmt) {
    while ($row = sqlsrv_fetch_array($topStmt, SQLSRV_FETCH_ASSOC)) {
        if ($row['revenue'] > 0) {
            $topProducts[] = $row;
        }
    }
    sqlsrv_free_stmt($topStmt);
}

$monthlySales = [];
$monthlyQuery = "SELECT 
    FORMAT(order_date, 'MMM yyyy') as month,
    ISNULL(SUM(total_amount), 0) as revenue,
    COUNT(*) as order_count
FROM Orders
WHERE order_date IS NOT NULL
GROUP BY FORMAT(order_date, 'MMM yyyy'), YEAR(order_date), MONTH(order_date)
ORDER BY MIN(order_date) ASC";
$monthlyStmt = sqlsrv_query($conn, $monthlyQuery);
if ($monthlyStmt) {
    while ($row = sqlsrv_fetch_array($monthlyStmt, SQLSRV_FETCH_ASSOC)) {
        $monthlySales[] = $row;
    }
    sqlsrv_free_stmt($monthlyStmt);
}

$lowStockProducts = [];
$lowStockQuery = "SELECT TOP 5
    p.name as product_name,
    p.sku,
    ISNULL(i.quantity_available, 0) as stock,
    p.price
FROM Products p
LEFT JOIN Inventory i ON p.id = i.product_id
WHERE ISNULL(i.quantity_available, 0) <= 10
ORDER BY stock ASC";
$lowStmt = sqlsrv_query($conn, $lowStockQuery);
if ($lowStmt) {
    while ($row = sqlsrv_fetch_array($lowStmt, SQLSRV_FETCH_ASSOC)) {
        $lowStockProducts[] = $row;
    }
    sqlsrv_free_stmt($lowStmt);
}

$chartLabels = array_column($monthlySales, 'month');
$chartRevenues = array_column($monthlySales, 'revenue');
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - InsightSphere</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f1f5f9;
            min-height: 100vh;
        }

        .app-container {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 260px;
            background: white;
            border-right: 1px solid #e2e8f0;
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 100;
        }

        .logo {
            padding: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid #e2e8f0;
        }

        .logo-icon {
            width: 36px;
            height: 36px;
            background: #3b82f6;
            color: white;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.2rem;
        }

        .logo-text {
            font-size: 1.2rem;
            font-weight: 600;
            color: #0f172a;
        }

        .nav-section {
            padding: 20px 16px;
            border-bottom: 1px solid #e2e8f0;
        }

        .nav-label {
            font-size: 0.7rem;
            color: #94a3b8;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            color: #64748b;
            text-decoration: none;
            border-radius: 10px;
            margin-bottom: 4px;
            transition: all 0.2s;
        }

        .nav-item:hover {
            background: #f1f5f9;
            color: #0f172a;
        }

        .nav-item.active {
            background: #eff6ff;
            color: #3b82f6;
        }

        .nav-item i {
            font-size: 1.2rem;
        }

        .sidebar-footer {
            margin-top: auto;
            padding: 20px 16px;
            border-top: 1px solid #e2e8f0;
        }

        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 24px;
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .mobile-menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
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
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
        }

        .metric-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
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
            margin-bottom: 4px;
        }

        .metric-value i {
            font-size: 1.2rem;
            color: #3b82f6;
        }

        .metric-subtext {
            font-size: 0.7rem;
            color: #94a3b8;
            margin-bottom: 8px;
        }

        .metric-change {
            font-size: 0.7rem;
        }

        .metric-change.positive {
            color: #10b981;
        }

        .card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            font-weight: 600;
            margin-bottom: 16px;
            font-size: 1rem;
        }

        .reports-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .report-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            background: #f8fafc;
            border-radius: 12px;
            transition: all 0.2s;
            border: 1px solid #e2e8f0;
        }

        .report-item:hover {
            background: #f1f5f9;
            transform: translateX(5px);
        }

        .report-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .report-name {
            font-weight: 600;
            color: #0f172a;
            font-size: 1rem;
        }

        .report-date {
            font-size: 0.75rem;
            color: #64748b;
        }

        .report-actions {
            display: flex;
            gap: 12px;
        }

        .action-btn {
            padding: 8px 16px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-size: 0.8rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
            text-decoration: none;
        }

        .view-btn {
            background: #3b82f6;
            color: white;
        }

        .view-btn:hover {
            background: #2563eb;
            transform: translateY(-2px);
        }

        .download-btn {
            background: #10b981;
            color: white;
        }

        .download-btn:hover {
            background: #059669;
            transform: translateY(-2px);
        }

        .bottom-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }

        .product-rank {
            margin-bottom: 16px;
        }

        .rank-number {
            font-weight: 600;
            color: #3b82f6;
            margin-bottom: 4px;
            font-size: 0.8rem;
        }

        .product-name {
            font-weight: 500;
            color: #0f172a;
        }

        .product-stats {
            font-size: 0.7rem;
            color: #64748b;
        }

        .progress-bar {
            height: 4px;
            background: #3b82f6;
            border-radius: 2px;
            margin-top: 8px;
        }

        .stock-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .stock-item:last-child {
            border-bottom: none;
        }

        .stock-count.critical {
            color: #ef4444;
            font-weight: 600;
        }

        .alert-badge {
            background: #fef3c7;
            color: #d97706;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
        }

        .btn-link {
            color: #3b82f6;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            margin-top: 16px;
            font-size: 0.85rem;
        }

        .no-data-message {
            text-align: center;
            padding: 40px;
            color: #94a3b8;
        }

        .no-data-message i {
            font-size: 48px;
            margin-bottom: 12px;
        }

        .report-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            display: none;
            align-items: center;
            justify-content: center;
        }

        .report-modal.active {
            display: flex;
        }

        .report-modal-content {
            background: white;
            border-radius: 16px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .report-modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f8fafc;
        }

        .report-modal-header h3 {
            margin: 0;
            font-size: 1.2rem;
        }

        .report-modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #64748b;
        }

        .report-modal-body {
            padding: 24px;
            overflow-y: auto;
            flex: 1;
        }

        .report-content {
            font-family: monospace;
            font-size: 13px;
            line-height: 1.8;
            white-space: pre-wrap;
        }

        /* Print/PDF Styles */
        @media print {
            body * {
                visibility: hidden;
            }

            #printReportContainer,
            #printReportContainer * {
                visibility: visible;
            }

            #printReportContainer {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                padding: 20px;
            }

            .no-print {
                display: none;
            }

            button,
            .action-btn,
            .report-actions {
                display: none !important;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                transform: translateX(-100%);
                transition: transform 0.3s;
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .mobile-menu-toggle {
                display: block;
            }

            .metrics-grid {
                grid-template-columns: 1fr;
            }

            .bottom-grid {
                grid-template-columns: 1fr;
            }

            .report-item {
                flex-direction: column;
                gap: 12px;
                text-align: center;
            }

            .report-actions {
                justify-content: center;
            }
        }
    </style>
</head>

<body>
    <div class="app-container">
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
                <a href="sales.php" class="nav-item">
                    <i class='bx bxs-cart'></i>
                    <span>Sales</span>
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
                <a href="reports.php" class="nav-item active">
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

        <div class="main-content">
            <header class="top-bar">
                <div style="display: flex; align-items: center; gap: 16px;">
                    <button class="mobile-menu-toggle" onclick="toggleSidebar()">
                        <i class='bx bx-menu'></i>
                    </button>
                </div>
            </header>

            <div class="metrics-grid">
                <div class="metric-card">
                    <div class="metric-header">Total Revenue</div>
                    <div class="metric-value">
                        <i class='bx bx-rupee'></i> <?php echo number_format($totalRevenue); ?>
                    </div>
                    <div class="metric-subtext">Lifetime sales</div>
                    <div class="metric-change positive">vs last month ↑ 12%</div>
                </div>
                <div class="metric-card">
                    <div class="metric-header">Total Orders</div>
                    <div class="metric-value">
                        <i class='bx bx-receipt'></i> <?php echo number_format($totalOrders); ?>
                    </div>
                    <div class="metric-subtext">Processed orders</div>
                    <div class="metric-change positive">vs last month ↑ 8%</div>
                </div>
                <div class="metric-card">
                    <div class="metric-header">Total Products</div>
                    <div class="metric-value">
                        <i class='bx bx-package'></i> <?php echo number_format($totalProducts); ?>
                    </div>
                    <div class="metric-subtext">In catalog</div>
                    <div class="metric-change positive">vs last month ↑ 5%</div>
                </div>
                <div class="metric-card">
                    <div class="metric-header">Total Customers</div>
                    <div class="metric-value">
                        <i class='bx bx-group'></i> <?php echo number_format($totalCustomers); ?>
                    </div>
                    <div class="metric-subtext">Registered users</div>
                    <div class="metric-change positive">vs last month ↑ 15%</div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">📄 Available Reports</div>
                <div class="reports-list">
                    <div class="report-item">
                        <div class="report-info">
                            <div class="report-name">📊 Sales Report</div>
                            <div class="report-date">Last updated: <?php echo date('d M Y'); ?></div>
                        </div>
                        <div class="report-actions">
                            <button class="action-btn view-btn" onclick="viewReport('sales')">
                                <i class='bx bx-show'></i> View
                            </button>
                            <button class="action-btn download-btn" onclick="downloadAsPDF('sales')">
                                <i class='bx bx-download'></i> Download PDF
                            </button>
                        </div>
                    </div>

                    <div class="report-item">
                        <div class="report-info">
                            <div class="report-name">📦 Inventory Report</div>
                            <div class="report-date">Last updated: <?php echo date('d M Y'); ?></div>
                        </div>
                        <div class="report-actions">
                            <button class="action-btn view-btn" onclick="viewReport('inventory')">
                                <i class='bx bx-show'></i> View
                            </button>
                            <button class="action-btn download-btn" onclick="downloadAsPDF('inventory')">
                                <i class='bx bx-download'></i> Download PDF
                            </button>
                        </div>
                    </div>

                    <div class="report-item">
                        <div class="report-info">
                            <div class="report-name">👥 Customer Report</div>
                            <div class="report-date">Last updated: <?php echo date('d M Y'); ?></div>
                        </div>
                        <div class="report-actions">
                            <button class="action-btn view-btn" onclick="viewReport('customers')">
                                <i class='bx bx-show'></i> View
                            </button>
                            <button class="action-btn download-btn" onclick="downloadAsPDF('customers')">
                                <i class='bx bx-download'></i> Download PDF
                            </button>
                        </div>
                    </div>

                    <div class="report-item">
                        <div class="report-info">
                            <div class="report-name">💰 Financial Report</div>
                            <div class="report-date">Last updated: <?php echo date('d M Y'); ?></div>
                        </div>
                        <div class="report-actions">
                            <button class="action-btn view-btn" onclick="viewReport('financial')">
                                <i class='bx bx-show'></i> View
                            </button>
                            <button class="action-btn download-btn" onclick="downloadAsPDF('financial')">
                                <i class='bx bx-download'></i> Download PDF
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Sales Trend</div>
                <?php if (empty($monthlySales)): ?>
                    <div class="no-data-message">
                        <i class='bx bx-line-chart'></i>
                        <p>No sales data available. Upload CSV to see trends.</p>
                    </div>
                <?php else: ?>
                    <canvas id="salesChart" style="max-height: 300px; width: 100%;"></canvas>
                <?php endif; ?>
            </div>

            <div class="bottom-grid">
                <div class="card">
                    <div class="card-header">Top Performing Products</div>
                    <?php if (empty($topProducts)): ?>
                        <div class="no-data-message">
                            <i class='bx bx-package' style="font-size: 48px;"></i>
                            <p>No product sales data available.</p>
                        </div>
                    <?php else: ?>
                        <?php $maxRevenue = $topProducts[0]['revenue'] ?? 1; ?>
                        <?php foreach ($topProducts as $index => $product): ?>
                            <div class="product-rank">
                                <div class="rank-number">#<?php echo $index + 1; ?></div>
                                <div class="product-name"><?php echo htmlspecialchars($product['product_name']); ?></div>
                                <div class="product-stats">
                                    <?php echo number_format($product['total_sold']); ?> units sold |
                                    ₹<?php echo number_format($product['revenue']); ?> revenue
                                </div>
                                <div class="progress-bar"
                                    style="width: <?php echo min(100, ($product['revenue'] / $maxRevenue) * 100); ?>%"></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <a href="products.php" class="btn-link">View All Products <i class='bx bx-right-arrow-alt'></i></a>
                </div>

                <div class="card">
                    <div class="card-header">
                        Low Stock Alert
                        <span class="alert-badge"><?php echo count($lowStockProducts); ?> items</span>
                    </div>
                    <?php if (count($lowStockProducts) > 0): ?>
                        <?php foreach ($lowStockProducts as $product): ?>
                            <div class="stock-item">
                                <div>
                                    <div class="product-name"><?php echo htmlspecialchars($product['product_name']); ?></div>
                                    <div class="product-stats">SKU: <?php echo htmlspecialchars($product['sku']); ?></div>
                                </div>
                                <div>
                                    <span class="stock-count critical"><?php echo $product['stock']; ?> units left</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-data-message">
                            <i class='bx bx-check-circle' style="font-size: 48px; color: #10b981;"></i>
                            <p>All products have adequate stock levels</p>
                        </div>
                    <?php endif; ?>
                    <a href="inventory.php" class="btn-link">Manage Inventory <i class='bx bx-right-arrow-alt'></i></a>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden Print Container for PDF Generation -->
    <div id="printReportContainer" style="display: none;"></div>

    <!-- View Report Modal -->
    <div id="reportModal" class="report-modal">
        <div class="report-modal-content">
            <div class="report-modal-header">
                <h3 id="modalTitle">Report View</h3>
                <button class="report-modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="report-modal-body">
                <div id="modalContent" class="report-content"></div>
            </div>
        </div>
    </div>

    <script>
        // Initialize Chart
        <?php if (!empty($monthlySales)): ?>
            const ctx = document.getElementById('salesChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($chartLabels); ?>,
                    datasets: [{
                        label: 'Revenue (₹)',
                        data: <?php echo json_encode($chartRevenues); ?>,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    return '₹' + context.parsed.y.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        <?php endif; ?>

        // Report Data
        const reportData = {
            totalRevenue: <?php echo json_encode($totalRevenue); ?>,
            totalOrders: <?php echo json_encode($totalOrders); ?>,
            totalProducts: <?php echo json_encode($totalProducts); ?>,
            totalCustomers: <?php echo json_encode($totalCustomers); ?>,
            totalUnitsSold: <?php echo json_encode($totalUnitsSold); ?>,
            avgOrderValue: <?php echo json_encode($avgOrderValue); ?>,
            inventoryValue: <?php echo json_encode($inventoryValue); ?>,
            activeBuyers: <?php echo json_encode($activeBuyers); ?>,
            topProducts: <?php echo json_encode($topProducts); ?>,
            lowStockProducts: <?php echo json_encode($lowStockProducts); ?>,
            monthlySales: <?php echo json_encode($monthlySales); ?>
        };

        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
        }

        function confirmLogout() {
            return confirm('Are you sure you want to logout?');
        }

        function viewReport(type) {
            const modal = document.getElementById('reportModal');
            const modalTitle = document.getElementById('modalTitle');
            const modalContent = document.getElementById('modalContent');

            let content = '';
            let title = '';

            switch (type) {
                case 'sales':
                    title = '📊 SALES REPORT - ' + new Date().toLocaleDateString();
                    content = generateSalesReportHTML();
                    break;
                case 'inventory':
                    title = '📦 INVENTORY REPORT - ' + new Date().toLocaleDateString();
                    content = generateInventoryReportHTML();
                    break;
                case 'customers':
                    title = '👥 CUSTOMER REPORT - ' + new Date().toLocaleDateString();
                    content = generateCustomerReportHTML();
                    break;
                case 'financial':
                    title = '💰 FINANCIAL REPORT - ' + new Date().toLocaleDateString();
                    content = generateFinancialReportHTML();
                    break;
            }

            modalTitle.innerHTML = title;
            modalContent.innerHTML = content;
            modal.classList.add('active');
        }

        function generateSalesReportHTML() {
            let topProductsHtml = '';
            reportData.topProducts.forEach((p, i) => {
                topProductsHtml += `<p><strong>${i + 1}.</strong> ${p.product_name} - ${p.total_sold} units sold (₹${p.revenue.toLocaleString()})</p>`;
            });

            return `
                <div class="report-content">
                    <p><strong>📅 Generated:</strong> ${new Date().toLocaleString()}</p>
                    <hr>
                    <h4>📈 SUMMARY</h4>
                    <p><strong>Total Revenue:</strong> ₹${reportData.totalRevenue.toLocaleString()}</p>
                    <p><strong>Total Orders:</strong> ${reportData.totalOrders.toLocaleString()}</p>
                    <p><strong>Average Order Value:</strong> ₹${reportData.avgOrderValue.toLocaleString()}</p>
                    <p><strong>Total Units Sold:</strong> ${reportData.totalUnitsSold.toLocaleString()}</p>
                    <hr>
                    <h4>🏆 TOP PRODUCTS</h4>
                    ${topProductsHtml}
                </div>
            `;
        }

        function generateInventoryReportHTML() {
            let lowStockHtml = '';
            if (reportData.lowStockProducts.length > 0) {
                reportData.lowStockProducts.forEach(p => {
                    lowStockHtml += `<p><strong>⚠️ ${p.product_name}</strong> - SKU: ${p.sku} - ${p.stock} units left</p>`;
                });
            } else {
                lowStockHtml = '<p>✅ No low stock items</p>';
            }

            return `
                <div class="report-content">
                    <p><strong>📅 Generated:</strong> ${new Date().toLocaleString()}</p>
                    <hr>
                    <h4>📦 SUMMARY</h4>
                    <p><strong>Total Products:</strong> ${reportData.totalProducts}</p>
                    <p><strong>Inventory Value:</strong> ₹${reportData.inventoryValue.toLocaleString()}</p>
                    <p><strong>Low Stock Items:</strong> ${reportData.lowStockProducts.length}</p>
                    <hr>
                    <h4>⚠️ LOW STOCK ALERTS</h4>
                    ${lowStockHtml}
                </div>
            `;
        }

        function generateCustomerReportHTML() {
            const repeatRate = reportData.totalOrders > 0 ? ((reportData.totalOrders - reportData.totalCustomers) / reportData.totalOrders * 100).toFixed(1) : 0;
            const clv = (reportData.totalRevenue / Math.max(reportData.totalCustomers, 1)).toLocaleString();
            const avgOrders = (reportData.totalOrders / Math.max(reportData.totalCustomers, 1)).toFixed(1);

            return `
                <div class="report-content">
                    <p><strong>📅 Generated:</strong> ${new Date().toLocaleString()}</p>
                    <hr>
                    <h4>👥 SUMMARY</h4>
                    <p><strong>Total Customers:</strong> ${reportData.totalCustomers}</p>
                    <p><strong>Active Buyers (30 days):</strong> ${reportData.activeBuyers}</p>
                    <p><strong>Customer Lifetime Value:</strong> ₹${clv}</p>
                    <p><strong>Repeat Purchase Rate:</strong> ${repeatRate}%</p>
                    <p><strong>Average Orders per Customer:</strong> ${avgOrders}</p>
                </div>
            `;
        }

        function generateFinancialReportHTML() {
            const estimatedProfit = reportData.totalRevenue * 0.4;

            return `
                <div class="report-content">
                    <p><strong>📅 Generated:</strong> ${new Date().toLocaleString()}</p>
                    <hr>
                    <h4>💰 SUMMARY</h4>
                    <p><strong>Gross Revenue:</strong> ₹${reportData.totalRevenue.toLocaleString()}</p>
                    <p><strong>Estimated Profit (40% margin):</strong> ₹${estimatedProfit.toLocaleString()}</p>
                    <p><strong>Inventory Investment:</strong> ₹${reportData.inventoryValue.toLocaleString()}</p>
                    <p><strong>Total Orders:</strong> ${reportData.totalOrders}</p>
                    <p><strong>Average Order Value:</strong> ₹${reportData.avgOrderValue.toLocaleString()}</p>
                </div>
            `;
        }

        function downloadAsPDF(type) {
            // Generate the report content
            let title = '';
            let content = '';
            let fileName = '';

            switch (type) {
                case 'sales':
                    title = 'SALES REPORT';
                    content = generateSalesReportText();
                    fileName = 'sales_report';
                    break;
                case 'inventory':
                    title = 'INVENTORY REPORT';
                    content = generateInventoryReportText();
                    fileName = 'inventory_report';
                    break;
                case 'customers':
                    title = 'CUSTOMER REPORT';
                    content = generateCustomerReportText();
                    fileName = 'customer_report';
                    break;
                case 'financial':
                    title = 'FINANCIAL REPORT';
                    content = generateFinancialReportText();
                    fileName = 'financial_report';
                    break;
            }

            // Create HTML content for printing
            const printHtml = `
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <title>${title}</title>
                    <style>
                        body {
                            font-family: Arial, Helvetica, sans-serif;
                            padding: 40px;
                            margin: 0;
                        }
                        .report-header {
                            text-align: center;
                            margin-bottom: 30px;
                            padding-bottom: 20px;
                            border-bottom: 2px solid #3b82f6;
                        }
                        .report-title {
                            font-size: 24px;
                            font-weight: bold;
                            color: #0f172a;
                            margin-bottom: 10px;
                        }
                        .report-date {
                            color: #64748b;
                            font-size: 12px;
                        }
                        .report-content {
                            font-size: 12px;
                            line-height: 1.6;
                            white-space: pre-wrap;
                            font-family: monospace;
                        }
                        .footer {
                            margin-top: 40px;
                            text-align: center;
                            font-size: 10px;
                            color: #94a3b8;
                            border-top: 1px solid #e2e8f0;
                            padding-top: 20px;
                        }
                        @media print {
                            body {
                                padding: 20px;
                            }
                        }
                    </style>
                </head>
                <body>
                    <div class="report-header">
                        <div class="report-title">${title}</div>
                        <div class="report-date">Generated on: ${new Date().toLocaleString()}</div>
                    </div>
                    <div class="report-content">
                        ${content.replace(/\n/g, '<br>')}
                    </div>
                    <div class="footer">
                        Generated by InsightSphere - Business Intelligence Dashboard
                    </div>
                </body>
                </html>
            `;

            // Create a new window and print to save as PDF
            const printWindow = window.open('', '_blank');
            printWindow.document.write(printHtml);
            printWindow.document.close();

            // Wait for content to load then print
            printWindow.onload = function () {
                printWindow.print();
                // Close the window after print dialog closes (optional)
                printWindow.onafterprint = function () {
                    printWindow.close();
                };
            };
        }

        function generateSalesReportText() {
            let topProductsText = '';
            reportData.topProducts.forEach((p, i) => {
                topProductsText += `${i + 1}. ${p.product_name} - ${p.total_sold} units (₹${p.revenue.toLocaleString()})\n`;
            });

            return `SUMMARY
========================================
Total Revenue: ₹${reportData.totalRevenue.toLocaleString()}
Total Orders: ${reportData.totalOrders.toLocaleString()}
Average Order Value: ₹${reportData.avgOrderValue.toLocaleString()}
Total Units Sold: ${reportData.totalUnitsSold.toLocaleString()}

TOP PRODUCTS
========================================
${topProductsText}`;
        }

        function generateInventoryReportText() {
            let lowStockText = '';
            if (reportData.lowStockProducts.length > 0) {
                reportData.lowStockProducts.forEach(p => {
                    lowStockText += `⚠️ ${p.product_name} - ${p.stock} units left\n`;
                });
            } else {
                lowStockText = 'No low stock items';
            }

            return `SUMMARY
========================================
Total Products: ${reportData.totalProducts}
Inventory Value: ₹${reportData.inventoryValue.toLocaleString()}
Low Stock Items: ${reportData.lowStockProducts.length}

LOW STOCK PRODUCTS
========================================
${lowStockText}`;
        }

        function generateCustomerReportText() {
            const repeatRate = reportData.totalOrders > 0 ? ((reportData.totalOrders - reportData.totalCustomers) / reportData.totalOrders * 100).toFixed(1) : 0;
            const clv = (reportData.totalRevenue / Math.max(reportData.totalCustomers, 1)).toLocaleString();
            const avgOrders = (reportData.totalOrders / Math.max(reportData.totalCustomers, 1)).toFixed(1);

            return `SUMMARY
========================================
Total Customers: ${reportData.totalCustomers}
Active Buyers (30 days): ${reportData.activeBuyers}
Customer Lifetime Value: ₹${clv}
Repeat Purchase Rate: ${repeatRate}%
Average Orders per Customer: ${avgOrders}`;
        }

        function generateFinancialReportText() {
            const estimatedProfit = reportData.totalRevenue * 0.4;

            return `SUMMARY
========================================
Gross Revenue: ₹${reportData.totalRevenue.toLocaleString()}
Estimated Profit (40% margin): ₹${estimatedProfit.toLocaleString()}
Inventory Investment: ₹${reportData.inventoryValue.toLocaleString()}
Total Orders: ${reportData.totalOrders}
Average Order Value: ₹${reportData.avgOrderValue.toLocaleString()}`;
        }

        function closeModal() {
            document.getElementById('reportModal').classList.remove('active');
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });

        window.onclick = function (event) {
            const modal = document.getElementById('reportModal');
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>
</body>

</html>