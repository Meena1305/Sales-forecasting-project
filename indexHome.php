<?php
session_start();
include("db_connect.php");

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Get user details from session
$loggedInUser = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Guest';
$userRole = $_SESSION['role'] ?? 'User';

// Get initials for avatar
$nameParts = explode(' ', $loggedInUser);
$userInitials = strtoupper(substr($nameParts[0], 0, 1));
if (isset($nameParts[1])) {
    $userInitials .= strtoupper(substr($nameParts[1], 0, 1));
}

// Get unread notification count
$unreadNotificationCount = 0;
if ($conn) {
    $countQuery = "SELECT COUNT(*) as count FROM Notifications WHERE is_read = 0";
    $countStmt = sqlsrv_query($conn, $countQuery);
    if ($countStmt) {
        $countRow = sqlsrv_fetch_array($countStmt, SQLSRV_FETCH_ASSOC);
        $unreadNotificationCount = $countRow['count'] ?? 0;
        sqlsrv_free_stmt($countStmt);
    }
}

function createNotification($title, $message, $type = 'info')
{
    $notificationData = [
        'title' => $title,
        'message' => $message,
        'type' => $type
    ];

    $ch = curl_init('http://' . $_SERVER['HTTP_HOST'] . '/save_notification.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($notificationData));
    curl_exec($ch);
    curl_close($ch);
}

// Check if file was uploaded
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['uploadFile'])) {
    // ====== READ YOUR UPLOADED FILE ======
    $file = $_FILES['uploadFile'];
    $fileContent = file_get_contents($file['tmp_name']);

    // For CSV files - parse the content
    $rows = array_map('str_getcsv', explode("\n", $fileContent));
    $header = array_shift($rows);

    $products = [];
    $salesData = [];
    $productSales = [];

    foreach ($rows as $row) {
        if (count($row) < 2)
            continue;

        if (count($row) >= 9) {
            // Full format: Order Date, Customer Name, Product Name, Category, Units Sold, Amount, Source, Status, Stock
            $productName = trim($row[2]);
            $unitsSold = intval($row[4]);
            $stock = intval($row[8]);
            $amount = floatval($row[5]);
            $customerName = trim($row[1]);
            $orderDate = trim($row[0]);
            $category = trim($row[3]);

            // Track sales for most sold analysis
            if (!isset($productSales[$productName])) {
                $productSales[$productName] = 0;
            }
            $productSales[$productName] += $unitsSold;

            // Store sales data for later database insertion
            $salesData[] = [
                'order_date' => $orderDate,
                'customer_name' => $customerName,
                'product_name' => $productName,
                'category' => $category,
                'units_sold' => $unitsSold,
                'amount' => $amount,
                'stock' => $stock
            ];

            // Check stock levels for alerts
            if ($stock == 0) {
                createNotification('❌ Out of Stock Alert', $productName . ' is completely out of stock! Please restock immediately.', 'alert');
            } elseif ($stock <= 5) {
                createNotification('⚠️ Low Stock Warning', $productName . ' has only ' . $stock . ' units remaining!', 'stock');
            } elseif ($stock <= 10) {
                createNotification('📦 Stock Running Low', $productName . ' has only ' . $stock . ' units left. Consider restocking soon.', 'stock');
            }

        } else {
            // Simple format: Product Name, Quantity (backward compatibility)
            $productName = trim($row[0]);
            $quantity = intval($row[1]);

            if ($quantity == 0) {
                createNotification('❌ Out of Stock', $productName . ' is out of stock!', 'alert');
            } elseif ($quantity <= 5) {
                createNotification('⚠️ Low Stock', $productName . ' has only ' . $quantity . ' units left!', 'stock');
            }
        }
    }

    // Analyze and create notifications for MOST SOLD PRODUCTS
    if (!empty($productSales)) {
        // Sort products by units sold (descending)
        arsort($productSales);

        // Get top 5 most sold products
        $topProducts = array_slice($productSales, 0, 5, true);
        $rank = 1;

        foreach ($topProducts as $productName => $totalSold) {
            if ($rank == 1) {
                $title = '🏆 Top Selling Product';
                $message = "{$productName} is the BEST SELLER with {$totalSold} units sold!";
                $type = 'customer';
            } else {
                $title = "📈 Top #{$rank} Selling Product";
                $message = "{$productName} ranks #{$rank} with {$totalSold} units sold";
                $type = 'info';
            }
            createNotification($title, $message, $type);
            $rank++;
        }

        // Check for significant sales milestones
        foreach ($productSales as $productName => $totalSold) {
            if ($totalSold > 100) {
                createNotification('🎉 Sales Milestone Reached!', "{$productName} has exceeded 100 units sold! Total: {$totalSold} units", 'customer');
            } elseif ($totalSold > 50) {
                createNotification('⭐ Popular Product Alert', "{$productName} is gaining popularity with {$totalSold} units sold", 'info');
            }
        }
    }

    // Insert sales data into database for historical tracking
    if (!empty($salesData) && $conn) {
        $insertedCount = 0;
        foreach ($salesData as $sale) {
            $insertQuery = "INSERT INTO SalesData (OrderDate, CustomerName, ProductName, Category, UnitsSold, Amount, Stock, CreatedAt) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, GETDATE())";
            $params = [
                $sale['order_date'],
                $sale['customer_name'],
                $sale['product_name'],
                $sale['category'],
                $sale['units_sold'],
                $sale['amount'],
                $sale['stock']
            ];
            $insertStmt = sqlsrv_query($conn, $insertQuery, $params);
            if ($insertStmt) {
                $insertedCount++;
                sqlsrv_free_stmt($insertStmt);
            }
        }

        createNotification('✅ Data Import Complete', "Successfully imported {$insertedCount} sales records. Stock alerts and top products have been updated.", 'info');
    }

    // Summary notification
    $stockAlertCount = 0;
    $topProductCount = count($productSales);
    createNotification('📊 Upload Summary', "File processed: " . count($rows) . " records. Created " . ($stockAlertCount > 0 ? $stockAlertCount : "0") . " stock alerts and identified top products.", 'info');

    echo "<script>alert('File uploaded successfully! Check notifications for stock alerts and top products.'); window.location.href='notifications.php';</script>";
}

// Add this function to get unread notification count
function getUnreadNotificationCount($conn)
{
    if (!$conn)
        return 0;

    $query = "SELECT COUNT(*) as unread_count FROM Notifications WHERE is_read = 0";
    $stmt = sqlsrv_query($conn, $query);
    if ($stmt) {
        $result = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        $count = $result['unread_count'] ?? 0;
        sqlsrv_free_stmt($stmt);
        return $count;
    }
    return 0;
}

// Get unread count for badge display
$unreadNotificationCount = getUnreadNotificationCount($conn);

// Check if connection exists
$conn = $GLOBALS['conn'] ?? null;

// Initialize variables with default values
$totalProducts = 0;
$totalRevenue = 0;
$totalCustomers = 0;
$totalUnits = 0;
$totalOrderCount = 0;

// 1. Get Total Products count
if ($conn) {
    $prodQuery = "SELECT ISNULL(COUNT(*), 0) AS total FROM Products";
    $prodStmt = sqlsrv_query($conn, $prodQuery);
    if ($prodStmt) {
        $prodData = sqlsrv_fetch_array($prodStmt, SQLSRV_FETCH_ASSOC);
        $totalProducts = $prodData['total'] ?? 0;
        sqlsrv_free_stmt($prodStmt);
    }

    // 2. Get Total Sales amount
    $salesQuery = "SELECT ISNULL(SUM(total_amount), 0) AS revenue FROM Orders";
    $salesStmt = sqlsrv_query($conn, $salesQuery);
    if ($salesStmt) {
        $salesData = sqlsrv_fetch_array($salesStmt, SQLSRV_FETCH_ASSOC);
        $totalRevenue = $salesData['revenue'] ?? 0;
        sqlsrv_free_stmt($salesStmt);
    }

    // 3. Get Customer count (from Orders table since Customers table might be empty)
    $custQuery = "SELECT COUNT(DISTINCT customer_name) AS total FROM Orders";
    $custStmt = sqlsrv_query($conn, $custQuery);
    if ($custStmt) {
        $custData = sqlsrv_fetch_array($custStmt, SQLSRV_FETCH_ASSOC);
        $totalCustomers = $custData['total'] ?? 0;
        sqlsrv_free_stmt($custStmt);
    }

    // 4. Get Total Units Sold
    $unitsQuery = "SELECT ISNULL(SUM(quantity_sold), 0) AS total_units FROM Sales";
    $unitsStmt = sqlsrv_query($conn, $unitsQuery);
    if ($unitsStmt) {
        $unitsData = sqlsrv_fetch_array($unitsStmt, SQLSRV_FETCH_ASSOC);
        $totalUnits = $unitsData['total_units'] ?? 0;
        sqlsrv_free_stmt($unitsStmt);
    }

    // 5. Get Total Orders Count
    $totalOrders_query = "SELECT ISNULL(COUNT(*), 0) as order_count FROM Orders";
    $orderStmt = sqlsrv_query($conn, $totalOrders_query);
    if ($orderStmt) {
        $orderData = sqlsrv_fetch_array($orderStmt, SQLSRV_FETCH_ASSOC);
        $totalOrderCount = $orderData['order_count'] ?? 0;
        sqlsrv_free_stmt($orderStmt);
    }
}

$totalVisits = 191886;
$conversionRate = $totalOrderCount > 0 ? round(($totalOrderCount / $totalVisits) * 100, 1) : 0;
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="styleHome.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v6.4.0/css/all.css">
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
                <a href="indexHome.php" class="nav-item active">
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
                    <span class="nav-badge">12</span>
                </a>
                <a href="products.php" class="nav-item">
                    <i class='bx bxs-box'></i>
                    <span>Products</span>
                </a>
                <a href="customers.php" class="nav-item">
                    <i class='bx bxs-user'></i>
                    <span>Customers</span>
                </a>
                <a href="#" class="nav-item" id="uploadFileLink"
                    onclick="document.getElementById('uploadModal').style.display='flex'; return false;">
                    <i class='bx bxs-cloud-upload'></i>
                    <span>Upload file</span>
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
        <div class="main-content">
            <header class="top-bar">
                <div style="display: flex; align-items: center; gap: 16px;">
                    <button class="mobile-menu-toggle" onclick="toggleSidebar()">
                        <i class='bx bx-menu'></i>
                    </button>
                    <div class="search-bar" style="position-relative;">
                        <i class='bx bx-search-alt-2'></i>
                        <input type="text" id="globalSearchInput" placeholder="Search...">

                        <div id="searchResultDropdown"
                            style="position: absolute; top: 100%; left: 0; right: 0; background: white; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.15); z-index: 1000; max-height: 300px; overflow-y: auto; display: none;">
                        </div>
                    </div>
                </div>
                <div class="top-actions">
                    <a href="notifications.php" class="icon-button" style="text-decoration: none; position: relative;">
                        <i class='bx bx-bell' style="font-size: 1.2rem;"></i>
                        <?php if ($unreadNotificationCount > 0): ?>
                            <span class="notification-badge"
                                style="position: absolute; top: -5px; right: -5px; background: #ef4444; color: white; border-radius: 50%; padding: 2px 6px; font-size: 10px; font-weight: bold;">
                                <?php echo min($unreadNotificationCount, 99); ?>
                            </span>
                        <?php endif; ?>
                    </a>
                    <button class="profile-button">
                        <div class="profile-info">
                            <div class="profile-name"><?php echo htmlspecialchars($loggedInUser); ?></div>
                            <div class="profile-role"><?php echo htmlspecialchars($userRole); ?></div>
                        </div>
                        <div class="profile-avatar"><?php echo $userInitials; ?></div>
                    </button>
                </div>
            </header>
            <div class="dashboard">
                <div class="metrics-grid">
                    <!-- Card 1: Active Sales -->
                    <div class="metric-card">
                        <div class="metric-header">
                            Active Sales <i class='bx bxs-info-circle'></i>
                        </div>
                        <div class="metric-value">
                            <span id="active-sales-val">
                                <?php echo number_format($salesData['revenue'] ?? 0); ?>
                            </span>
                        </div>
                        <div class="metric-subtext">
                            <span id="cust-count-val">
                                <?php echo number_format($custData['total'] ?? 0); ?>
                            </span>
                            Customers
                        </div>
                        <div class="metric-change positive">
                            vs last month <i class='bx bx-up-arrow-alt'></i><span id="revenue-change">12</span>%
                        </div>
                        <div class="metric-icon">
                            <div class="bar-chart-icon">
                                <div class="bar" style="height: 35px;"></div>
                                <div class="bar" style="height: 45px;"></div>
                                <div class="bar" style="height: 40px;"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Card 2: Product Revenue -->
                    <div class="metric-card">
                        <div class="metric-header">
                            Product Revenue <i class='bx bxs-info-circle'></i>
                        </div>
                        <div class="metric-value">
                            <i class='bx bx-rupee'></i>
                            <span
                                id="product-revenue-val"><?php echo number_format($salesData['revenue'] ?? 0); ?></span>
                        </div>
                        <div class="metric-change positive">
                            vs last month <i class='bx bx-up-arrow-alt'></i><span id="revenue-change2">9</span>%
                        </div>
                        <div class="metric-icon">
                            <svg class="line-chart-svg" viewBox="0 0 50 50">
                                <polyline points="5,35 15,25 25,30 35,15 45,20" stroke="#7494ec" stroke-width="3"
                                    fill="none" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </div>
                    </div>

                    <!-- Card 3: Product Sold -->
                    <div class="metric-card">
                        <div class="metric-header">
                            Product Sold <i class='bx bxs-info-circle'></i>
                        </div>
                        <div class="metric-value">
                            <span
                                id="product-sold-val"><?php echo number_format($unitsData['total_units'] ?? 0); ?></span>
                        </div>
                        <div class="metric-change positive">
                            vs last month <i class='bx bx-up-arrow-alt'></i><span id="units-change">7</span>%
                        </div>
                        <div class="metric-icon">
                            <div class="gauge-icon">
                                <div class="gauge-bg">
                                    <div class="gauge-fill"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Card 4: Conversion Rate -->
                    <div class="metric-card">
                        <div class="metric-header">
                            Conversion Rate <i class='bx bxs-info-circle'></i>
                        </div>
                        <div class="metric-value">
                            <span
                                id="conversion-rate-val"><?php echo isset($conversionRate) ? $conversionRate : '12.5'; ?></span>
                        </div>
                        <div class="metric-change positive">
                            vs last month <i class='bx bx-up-arrow-alt'></i><span id="conv-change">7</span>%
                        </div>
                        <div class="metric-icon">
                            <div class="mini-bars">
                                <div class="mini-bar" style="height: 35px;"></div>
                                <div class="mini-bar" style="height: 40px;"></div>
                                <div class="mini-bar" style="height: 25px;"></div>
                                <div class="mini-bar" style="height: 45px;"></div>
                                <div class="mini-bar" style="height: 20px;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="content-grid">
                <div class="card">
                    <div class="card-header">
                        Sales Performance <i class='bx bxs-info-circle'></i>
                    </div>
                    <svg class="gauge-arc" viewBox="0 0 200 120">
                        <defs>
                            <linearGradient id="gaugeGradient" x1="0%" y1="0%" x2="100%" y2="0%">
                                <stop offset="0%" style="stop-color: white"></stop>
                                <stop offset="50%" style="stop-color: #ADE8F4"></stop>
                                <stop offset="100%" style="stop-color: #7494ec"></stop>
                            </linearGradient>
                        </defs>
                        <path d="M 20 100 A 80 80 0 0 1 180 100" stroke="#f0f0f0" stroke-width="20" fill="none"
                            stroke-linecap="round" />
                        <path d="M 20 100 A 80 80 0 0 1 148 100" stroke="url(#gaugeGradient)" stroke-width="20"
                            fill="none" stroke-linecap="round" />
                    </svg>
                    <div>
                        <span class="score-value">82</span>
                        <span class="score-badge">+1</span>
                        <span class="score-label">of 100 points</span>
                    </div>
                    <div class="team-message">
                        <h3>You're team is great!</h3>
                        <p>The team is performing well above average, meeting or exceeding targets in several areas</p>
                    </div>
                    <a href="#" class="btn-link">Improve your score</a>
                </div>
                <div class="card">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div class="card-header">
                            Analytics <i class='bx bxs-info-circle'></i>
                        </div>
                        <div class="chart-controls">
                            <button class="btn"><i class='bx bxs-filter-alt'></i>Filter</button>
                            <button class="btn">Last Year<i class='bx bx-chevron-down'></i></button>
                            <button class="btn"><i class='bx bx-expand'></i></button>
                        </div>
                    </div>
                    <div class="analytics-chart">
                        <div class="chart-bars">
                            <div class="chart-bar" style="height: 15%;">
                                <span class="chart-label">Jan</span>
                            </div>
                            <div class="chart-bar" style="height: 20%;">
                                <span class="chart-label">Feb</span>
                            </div>
                            <div class="chart-bar" style="height: 25%;">
                                <span class="chart-label">Mar</span>
                            </div>
                            <div class="chart-bar" style="height: 18%;">
                                <span class="chart-label">Apr</span>
                            </div>
                            <div class="chart-bar" style="height: 22%;">
                                <span class="chart-label">May</span>
                            </div>
                            <div class="chart-bar active" style="height: 85%;">
                                <div class="chart-tooltip">
                                    <div>Jun: 2024</div>
                                    <div>Revenue: <i class='bx bx-rupee'></i>2,766</div>
                                    <div>Conv. Rate: 6.7%</div>
                                </div>
                                <span class="chart-label">Jun</span>
                            </div>
                            <div class="chart-bar" style="height: 12%;">
                                <span class="chart-label">Jul</span>
                            </div>
                            <div class="chart-bar" style="height: 16%;">
                                <span class="chart-label">Aug</span>
                            </div>
                            <div class="chart-bar" style="height: 19%;">
                                <span class="chart-label">Sep</span>
                            </div>
                            <div class="chart-bar" style="height: 14%;">
                                <span class="chart-label">Oct</span>
                            </div>
                            <div class="chart-bar" style="height: 17%;">
                                <span class="chart-label">Nov</span>
                            </div>
                            <div class="chart-bar" style="height: 21%;">
                                <span class="chart-label">Dec</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bottom-grid">
                <div class="card">
                    <div class="card-header">
                        Visit by Time <i class='bx bxs-info-circle'></i>
                    </div>
                    <div class="heatmap">
                        <div class="heatmap-grid">
                            <div></div>
                            <div style="text-align: center; color: #999; font-size: 12px;">Mon</div>
                            <div style="text-align: center; color: #999; font-size: 12px;">Tue</div>
                            <div style="text-align: center; color: #999; font-size: 12px;">Wed</div>
                            <div style="text-align: center; color: #999; font-size: 12px;">Thu</div>
                            <div style="text-align: center; color: #999; font-size: 12px;">Fri</div>
                            <div style="text-align: center; color: #999; font-size: 12px;">Sat</div>
                            <div style="text-align: center; color: #999; font-size: 12px;">Sun</div>
                            <div class="heatmap-label">12 AM - 8 AM</div>
                            <div class="heatmap-cell medium"></div>
                            <div class="heatmap-cell empty"></div>
                            <div class="heatmap-cell low"></div>
                            <div class="heatmap-cell low"></div>
                            <div class="heatmap-cell empty"></div>
                            <div class="heatmap-cell low"></div>
                            <div class="heatmap-cell empty"></div>

                            <div class="heatmap-label">8 AM - 4 PM</div>
                            <div class="heatmap-cell high"></div>
                            <div class="heatmap-cell low"></div>
                            <div class="heatmap-cell high"></div>
                            <div class="heatmap-cell medium"></div>
                            <div class="heatmap-cell low"></div>
                            <div class="heatmap-cell medium"></div>
                            <div class="heatmap-cell high"></div>

                            <div class="heatmap-label">4 PM - 12 AM</div>
                            <div class="heatmap-cell low"></div>
                            <div class="heatmap-cell medium"></div>
                            <div class="heatmap-cell empty"></div>
                            <div class="heatmap-cell empty"></div>
                            <div class="heatmap-cell very-high"></div>
                            <div class="heatmap-cell empty"></div>
                            <div class="heatmap-cell medium"></div>
                        </div>
                        <div class="heatmap-legend">
                            <span>0</span>
                            <div class="legend-scale">
                                <div class="legend-box" style="background: #fed7d7;"></div>
                                <div class="legend-box" style="background: #fc8181;"></div>
                                <div class="legend-box" style="background: #f56565;"></div>
                                <div class="legend-box" style="background: #e53e3e;"></div>
                            </div>
                            <span>10,000+</span>
                        </div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header">
                        Total Visit <i class='bx bxs-info-circle'></i>
                    </div>
                    <div class="visits-content">
                        <div class="total-visits">
                            <div class="total-number">191,886</div>
                            <div class="total-change">
                                vs last month <i class='bx bx-up-arrow-alt'></i>8.5%
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 40px;">
                            <div class="visit-stats" style="flex: 1;">
                                <div class="stat-item">
                                    <div class="stat-label">
                                        <span class="stat-dot mobile"></span>
                                        Mobile
                                    </div>
                                    <div class="stat-value">115,132</div>
                                </div>

                                <div class="stat-item">
                                    <div class="stat-label">
                                        <span class="stat-dot website"></span>
                                        Website
                                    </div>
                                    <div class="stat-value">76,754</div>
                                </div>
                            </div>
                            <div class="donut-chart">
                                <svg viewBox="0 0 200 200">
                                    <circle cx="100" cy="100" r="80" fill="none" stroke="#fed7d7" stroke-width="40" />
                                    <circle cx="100" cy="100" r="80" fill="none" stroke="#ff6b35" stroke-width="40"
                                        stroke-dasharray="301.59 502.65" transform="rotate(-90 100 100)" />
                                    <text x="100" y="95" text-anchor="middle" font-size="32" font-weight="600"
                                        fill="#1a1a1a">60%</text>
                                    <text x="100" y="120" text-anchor="middle" font-size="14" fill="#999">40%</text>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="scriptHome.js"></script>

    <script>
        function closeModal() {
            document.getElementById('uploadModal').style.display = 'none';
        }
        // Close modal when clicking outside
        window.onclick = function (event) {
            const modal = document.getElementById('uploadModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>

    <script>
        // Auto-refresh notification count every 30 seconds
        setInterval(function () {
            fetch('get_notification_count.php')
                .then(response => response.json())
                .then(data => {
                    const badge = document.querySelector('.notification-badge');
                    if (data.count > 0) {
                        if (badge) {
                            badge.textContent = data.count > 99 ? '99+' : data.count;
                            badge.style.display = 'inline-block';
                        } else {
                            // Create badge if it doesn't exist
                            const bellButton = document.querySelector('.icon-button');
                            if (bellButton) {
                                const newBadge = document.createElement('span');
                                newBadge.className = 'notification-badge';
                                newBadge.textContent = data.count > 99 ? '99+' : data.count;
                                bellButton.appendChild(newBadge);
                            }
                        }
                    } else if (badge) {
                        badge.style.display = 'none';
                    }
                })
                .catch(error => console.error('Error fetching notifications:', error));
        }, 30000);
    </script>
</body>

</html>