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

// ========== GET CUSTOMERS FROM DATABASE ==========
$customers_data = [];
$total_customers = 0;
$active_buyers = 0;
$total_spent = 0;
$total_orders_all = 0;

if ($conn) {
    // Query to get customer statistics from Orders table
    $customerQuery = "SELECT 
        customer_name,
        COUNT(*) as total_orders,
        ISNULL(SUM(total_amount), 0) as total_spent,
        MAX(order_date) as last_active_date
    FROM Orders 
    GROUP BY customer_name
    ORDER BY total_spent DESC";

    $customerStmt = sqlsrv_query($conn, $customerQuery);

    if ($customerStmt) {
        while ($row = sqlsrv_fetch_array($customerStmt, SQLSRV_FETCH_ASSOC)) {
            $customerName = $row['customer_name'];
            $orders = intval($row['total_orders']);
            $spent = floatval($row['total_spent']);
            $lastActive = $row['last_active_date'];

            // Format last active date
            if ($lastActive instanceof DateTime) {
                $lastActive = $lastActive->format('d M Y');
            } elseif ($lastActive) {
                $lastActive = date('d M Y', strtotime($lastActive));
            } else {
                $lastActive = 'N/A';
            }

            // Determine customer tier
            if ($spent > 30000) {
                $tier = 'Gold';
                $tierIcon = '🥇';
            } elseif ($spent > 10000) {
                $tier = 'Silver';
                $tierIcon = '🥈';
            } else {
                $tier = 'Bronze';
                $tierIcon = '🥉';
            }

            // Generate email and phone
            $email = strtolower(str_replace(' ', '.', $customerName)) . '@example.com';
            $phone = '(' . rand(200, 999) . ') 555-' . rand(1000, 9999);

            $customers_data[] = [
                'name' => $customerName,
                'email' => $email,
                'phone' => $phone,
                'orders' => $orders,
                'spent' => $spent,
                'tier' => $tier,
                'tier_icon' => $tierIcon,
                'last_active' => $lastActive
            ];

            $total_customers++;
            $total_spent += $spent;
            $total_orders_all += $orders;

            // Check if customer was active in last 30 days
            if ($row['last_active_date']) {
                $lastActiveDate = $row['last_active_date'];
                if ($lastActiveDate instanceof DateTime) {
                    $lastActiveDate = $lastActiveDate->format('Y-m-d');
                }
                $thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));
                if ($lastActiveDate >= $thirtyDaysAgo) {
                    $active_buyers++;
                }
            }
        }
        sqlsrv_free_stmt($customerStmt);
    }
}

// Calculate metrics
$avg_order_value = $total_orders_all > 0 ? round($total_spent / $total_orders_all, 2) : 0;
$lifetime_value = round($total_spent / 1000000, 1); // Convert to millions

// Handle search functionality
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$filtered_customers = $customers_data;

if ($search_query !== '') {
    $filtered_customers = array_filter($customers_data, function ($customer) use ($search_query) {
        return stripos($customer['name'], $search_query) !== false ||
            stripos($customer['email'], $search_query) !== false ||
            stripos($customer['phone'], $search_query) !== false;
    });
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>InsightSphere - Customers</title>
    <link rel="stylesheet" href="customers.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>

<body>
    <div class="app-container">
        <!-- Sidebar - Exactly matching dashboard design -->
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
                    <span class="nav-badge">12</span>
                </a>
                <a href="products.php" class="nav-item">
                    <i class='bx bxs-box'></i>
                    <span>Products</span>
                </a>
                <a href="customers.php" class="nav-item active">
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
            <header class="top-bar">
                <div style="display: flex; align-items: center; gap: 16px;">
                    <button class="mobile-menu-toggle" onclick="toggleSidebar()">
                        <i class='bx bx-menu'></i>
                    </button>
                </div>
            </header>

            <!-- Page Title -->
            <div class="page-header">
                <h1>Customers</h1>
            </div>

            <!-- Stats Cards - Matching dashboard metric cards -->
            <div class="metrics-grid">
                <div class="metric-card">
                    <div class="metric-header">
                        TOTAL CUSTOMERS <i class='bx bxs-info-circle'></i>
                    </div>
                    <div class="metric-value">
                        <?php echo number_format($total_customers); ?>
                    </div>
                    <div class="metric-change positive">
                        vs last month <i class='bx bx-up-arrow-alt'></i>12%
                    </div>
                    <div class="metric-icon">
                        <div class="bar-chart-icon">
                            <div class="bar" style="height: 35px;"></div>
                            <div class="bar" style="height: 45px;"></div>
                            <div class="bar" style="height: 40px;"></div>
                        </div>
                    </div>
                </div>

                <div class="metric-card">
                    <div class="metric-header">
                        ACTIVE BUYERS <i class='bx bxs-info-circle'></i>
                    </div>
                    <div class="metric-value">
                        <?php echo number_format($active_buyers); ?>
                    </div>
                    <div class="metric-change positive">
                        vs last month <i class='bx bx-up-arrow-alt'></i>8%
                    </div>
                    <div class="metric-icon">
                        <svg class="line-chart-svg" viewBox="0 0 50 50">
                            <polyline points="5,35 15,25 25,30 35,15 45,20" stroke="#7494ec" stroke-width="3"
                                fill="none" stroke-linecap="round" />
                        </svg>
                    </div>
                </div>

                <div class="metric-card">
                    <div class="metric-header">
                        AVG. ORDER VALUE <i class='bx bxs-info-circle'></i>
                    </div>
                    <div class="metric-value">
                        <i class='bx bx-rupee'></i> <?php echo number_format($avg_order_value, 2); ?>
                    </div>
                    <div class="metric-change positive">
                        vs last month <i class='bx bx-up-arrow-alt'></i>5%
                    </div>
                    <div class="metric-icon">
                        <div class="gauge-icon">
                            <div class="gauge-bg">
                                <div class="gauge-fill"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="metric-card">
                    <div class="metric-header">
                        LIFETIME VALUE <i class='bx bxs-info-circle'></i>
                    </div>
                    <div class="metric-value">
                        <i class='bx bx-rupee'></i> <?php echo number_format($lifetime_value, 1); ?>M
                    </div>
                    <div class="metric-change positive">
                        vs last month <i class='bx bx-up-arrow-alt'></i>9%
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

            <!-- Customers Table Section -->
            <div class="card">
                <div class="card-header"
                    style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                    <span>All Customers</span>
                    <div class="search-area">
                        <form method="GET" action="" class="search-form">
                            <div class="search-bar" style="margin: 0;">
                                <i class='bx bx-search-alt-2'></i>
                                <input type="text" name="search" placeholder="Search customers..."
                                    value="<?php echo htmlspecialchars($search_query); ?>">
                            </div>
                            <button type="submit" class="btn">Search</button>
                            <?php if ($search_query !== ''): ?>
                                <a href="customers.php" class="btn" style="background: #fef2f2; color: #dc2626;">Clear</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <div class="table-wrapper">
                    <table class="customers-table">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Email</th>
                                <th>Total Orders</th>
                                <th>Total Spent</th>
                                <th>Tier</th>
                                <th>Last Active</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($filtered_customers)): ?>
                                <tr>
                                    <td colspan="6" class="no-results" style="text-align: center; padding: 40px;">
                                        No customers found
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($filtered_customers as $customer): ?>
                                    <tr>
                                        <td class="customer-cell">
                                            <div class="customer-avatar">
                                                <?php
                                                $name_parts = explode(' ', $customer['name']);
                                                $initials = strtoupper($name_parts[0][0] . ($name_parts[1][0] ?? ''));
                                                echo $initials;
                                                ?>
                                            </div>
                                            <?php echo htmlspecialchars($customer['name']); ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($customer['email']); ?>
                                        </td>
                                        <td>
                                            <?php echo $customer['orders']; ?>
                                        </td>
                                        <td><i class='bx bx-rupee'></i>
                                            <?php echo number_format($customer['spent'], 2); ?>
                                        </td>
                                        <td>
                                            <?php if ($customer['tier'] == 'Gold'): ?>
                                                <span class="tier-badge gold">🥇 Gold</span>
                                            <?php elseif ($customer['tier'] == 'Silver'): ?>
                                                <span class="tier-badge silver">🥈 Silver</span>
                                            <?php else: ?>
                                                <span class="tier-badge bronze">🥉 Bronze</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo $customer['last_active']; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Bottom Grid - Visit by Time and Total Visit -->
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

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('active');
        }

        function confirmLogout() {
            return confirm('Are you sure you want to logout?');
        }

        // See Details buttons
        document.querySelectorAll('.see-details').forEach(btn => {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                alert('Detailed analytics coming soon');
            });
        });
    </script>
</body>

</html>