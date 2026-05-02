<?php
session_start();
include("db_connect.php");

// Check if data was recently updated
$dataUpdated = isset($_SESSION['data_updated']) && $_SESSION['data_updated'] === true;
$showUpdateNotification = $dataUpdated || isset($_GET['updated']);

// Clear the flag after showing notification
if ($showUpdateNotification) {
    $_SESSION['data_updated'] = false;
}

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Fetch orders from database
$orders = [];
$totalOrders = 0;
$totalRevenue = 0;
$avgOrderValue = 0;
$uniqueCustomers = 0;
$pendingCount = 0;

$dbConnected = ($conn !== false);

if ($dbConnected) {
    // Get all orders with product info
    $ordersQuery = "SELECT 
    o.id as order_id,
    o.customer_name,
    o.order_date,
    o.total_amount,
    o.status,
    STUFF((SELECT ', ' + p.name FROM Sales s JOIN Products p ON s.product_id = p.id WHERE s.order_id = o.id FOR XML PATH('')), 1, 2, '') as product_names,
    ISNULL((SELECT SUM(s.quantity_sold) FROM Sales s WHERE s.order_id = o.id), 0) as total_quantity
    FROM Orders o
    ORDER BY o.order_date DESC";

    $ordersStmt = sqlsrv_query($conn, $ordersQuery);
    if ($ordersStmt !== false) {
        while ($row = sqlsrv_fetch_array($ordersStmt, SQLSRV_FETCH_ASSOC)) {
            // Format date
            if ($row['order_date'] instanceof DateTime) {
                $row['order_date'] = $row['order_date']->format('Y-m-d');
            }
            $orders[] = $row;
        }
        sqlsrv_free_stmt($ordersStmt);
    }

    // Calculate stats from database
    $totalOrders = count($orders);
    $totalRevenue = array_sum(array_column($orders, 'total_amount'));
    $avgOrderValue = $totalOrders > 0 ? round($totalRevenue / $totalOrders, 2) : 0;
    $uniqueCustomers = count(array_unique(array_column($orders, 'customer_name')));
    $pendingCount = count(array_filter($orders, function ($o) {
        return isset($o['status']) && $o['status'] == 'Pending';
    }));
}

// Fallback: If no orders in DB, show message
if (empty($orders)) {
    $orders = [];
    $totalOrders = 0;
    $totalRevenue = 0;
    $avgOrderValue = 0;
    $uniqueCustomers = 0;
    $pendingCount = 0;
}

$stats = [
    'total_orders' => $totalOrders,
    'total_revenue' => $totalRevenue,
    'avg_order_value' => $avgOrderValue,
    'unique_customers' => $uniqueCustomers
];

function formatOrderDate($date)
{
    if (empty($date))
        return 'N/A';
    return date('d M Y', strtotime($date));
}
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders - InsightSphere</title>
    <link rel="stylesheet" href="orders.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        /* Status badge colors */
        .status-badge.pending {
            background: #fef3c7;
            color: #d97706;
        }

        .status-badge.processing {
            background: #dbeafe;
            color: #2563eb;
        }

        .status-badge.shipped {
            background: #e0e7ff;
            color: #4f46e5;
        }

        .status-badge.completed {
            background: #d1fae5;
            color: #059669;
        }

        .status-badge.cancelled {
            background: #fee2e2;
            color: #dc2626;
        }

        /* Filter section */
        .status-filter {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            padding: 15px;
            background: #f8fafc;
            border-radius: 12px;
            margin-bottom: 20px;
        }

        .filter-option {
            padding: 8px 16px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            background: white;
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 500;
            color: #64748b;
            transition: all 0.2s;
        }

        .filter-option:hover {
            background: #e2e8f0;
        }

        .filter-option.active {
            background: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }

        .count-badge {
            background: rgba(0, 0, 0, 0.1);
            border-radius: 20px;
            padding: 2px 8px;
            font-size: 0.7rem;
            margin-left: 8px;
        }

        .filter-option.active .count-badge {
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }

        /* Animation */
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .notification {
            animation: slideIn 0.3s ease;
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
                <a href="orders.php" class="nav-item active">
                    <i class='bx bxs-file-pdf'></i>
                    <span>Orders</span>
                    <span class="nav-badge"><?php echo $pendingCount; ?></span>
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
                    <div class="search-bar">
                        <i class='bx bx-search-alt-2'></i>
                        <input type="text" id="searchInput" placeholder="Search by Order ID, Customer, or Product...">
                    </div>
                </div>
            </header>

            <!-- Metrics Grid -->
            <div class="metrics-grid">
                <div class="metric-card">
                    <div class="metric-header">Total Orders</div>
                    <div class="metric-value"><i
                            class='bx bx-receipt'></i><span><?php echo number_format($stats['total_orders']); ?></span>
                    </div>
                    <div class="metric-subtext">Pending Orders: <?php echo $pendingCount; ?></div>
                    <div class="metric-change positive">vs last month <i class='bx bx-up-arrow-alt'></i>8%</div>
                </div>

                <div class="metric-card">
                    <div class="metric-header">Total Revenue</div>
                    <div class="metric-value"><i
                            class='bx bx-rupee'></i><span><?php echo number_format($stats['total_revenue']); ?></span>
                    </div>
                    <div class="metric-change positive">vs last month <i class='bx bx-up-arrow-alt'></i>12%</div>
                </div>

                <div class="metric-card">
                    <div class="metric-header">Avg Order Value</div>
                    <div class="metric-value"><i
                            class='bx bx-rupee'></i><span><?php echo number_format($stats['avg_order_value']); ?></span>
                    </div>
                    <div class="metric-change positive">vs last month <i class='bx bx-up-arrow-alt'></i>5%</div>
                </div>

                <div class="metric-card">
                    <div class="metric-header">Unique Customers</div>
                    <div class="metric-value"><i
                            class='bx bx-group'></i><span><?php echo number_format($stats['unique_customers']); ?></span>
                    </div>
                    <div class="metric-change positive">vs last month <i class='bx bx-up-arrow-alt'></i>15%</div>
                </div>
            </div>

            <!-- Orders Table -->
            <div class="content-grid" style="grid-template-columns: 1fr;">
                <div class="card">
                    <div
                        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
                        <div class="card-header">Orders List</div>
                        <div class="chart-controls">
                            <button class="btn" id="filterBtn"><i class='bx bxs-filter-alt'></i>Filter by
                                Status</button>
                            <button class="btn" id="exportBtn"><i class='bx bx-download'></i>Export CSV</button>
                        </div>
                    </div>

                    <!-- Status Filter Buttons -->
                    <div class="status-filter" id="statusFilter" style="display: none;">
                        <button class="filter-option active" data-status="all">
                            All Orders <span class="count-badge"><?php echo count($orders); ?></span>
                        </button>
                        <button class="filter-option" data-status="Pending">
                            Pending <span class="count-badge"><?php echo count(array_filter($orders, function ($o) {
                                return $o['status'] == 'Pending';
                            })); ?></span>
                        </button>
                        <button class="filter-option" data-status="Processing">
                            Processing <span class="count-badge"><?php echo count(array_filter($orders, function ($o) {
                                return $o['status'] == 'Processing';
                            })); ?></span>
                        </button>
                        <button class="filter-option" data-status="Shipped">
                            Shipped <span class="count-badge"><?php echo count(array_filter($orders, function ($o) {
                                return $o['status'] == 'Shipped';
                            })); ?></span>
                        </button>
                        <button class="filter-option" data-status="Completed">
                            Completed <span class="count-badge"><?php echo count(array_filter($orders, function ($o) {
                                return $o['status'] == 'Completed';
                            })); ?></span>
                        </button>
                        <button class="filter-option" data-status="Cancelled">
                            Cancelled <span class="count-badge"><?php echo count(array_filter($orders, function ($o) {
                                return $o['status'] == 'Cancelled';
                            })); ?></span>
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="orders-table">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Date</th>
                                    <th>Customer</th>
                                    <th>Products</th>
                                    <th>Qty</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="ordersTableBody">
                                <!-- Orders will be loaded via JavaScript -->
                            </tbody>
                        </table>
                    </div>

                    <div class="table-footer">
                        <div class="showing-info">Showing <span id="showingStart">0</span> - <span
                                id="showingEnd">0</span> of <span id="totalCount">0</span> orders</div>
                        <div class="pagination">
                            <button class="page-btn" id="prevPage" disabled><i class='bx bx-chevron-left'></i></button>
                            <div class="page-numbers" id="pageNumbers"></div>
                            <button class="page-btn" id="nextPage" disabled><i class='bx bx-chevron-right'></i></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Order Details Modal -->
    <div id="orderModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Order Details</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body" id="modalBody"></div>
        </div>
    </div>

    <script>
        // All orders data from PHP - Contains ALL status types
        const allOrdersData = <?php
        $ordersJson = [];
        foreach ($orders as $order) {
            $ordersJson[] = [
                'order_id' => $order['order_id'],
                'customer_name' => $order['customer_name'],
                'order_date' => formatOrderDate($order['order_date']),
                'total_amount' => floatval($order['total_amount']),
                'status' => $order['status'],
                'product_names' => $order['product_names'],
                'total_quantity' => intval($order['total_quantity'])
            ];
        }
        echo json_encode($ordersJson);
        ?>;

        console.log('Orders loaded:', allOrdersData.length);
        console.log('Status breakdown:', allOrdersData.reduce((acc, order) => {
            acc[order.status] = (acc[order.status] || 0) + 1;
            return acc;
        }, {}));

        // Variables
        let currentPage = 1;
        const rowsPerPage = 10;
        let currentFilter = 'all';
        let currentSearch = '';
        let filteredOrders = [];

        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
        }

        function confirmLogout() {
            return confirm('Are you sure you want to logout?');
        }

        // Filter button toggle
        const filterBtn = document.getElementById('filterBtn');
        const statusFilter = document.getElementById('statusFilter');

        if (filterBtn) {
            filterBtn.addEventListener('click', function (e) {
                e.preventDefault();
                if (statusFilter.style.display === 'none' || statusFilter.style.display === '') {
                    statusFilter.style.display = 'flex';
                } else {
                    statusFilter.style.display = 'none';
                }
            });
        }

        // Filter option clicks
        const filterOptions = document.querySelectorAll('.filter-option');
        filterOptions.forEach(function (btn) {
            btn.addEventListener('click', function () {
                filterOptions.forEach(function (b) {
                    b.classList.remove('active');
                });
                this.classList.add('active');
                currentFilter = this.getAttribute('data-status');
                if (statusFilter) statusFilter.style.display = 'none';
                applyFilters();
                goToPage(1);
            });
        });

        // Search functionality
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('keyup', function () {
                currentSearch = this.value.toLowerCase();
                applyFilters();
                goToPage(1);
            });
        }

        function applyFilters() {
            filteredOrders = allOrdersData.filter(order => {
                const matchesStatus = currentFilter === 'all' || order.status.toLowerCase() === currentFilter.toLowerCase();
                const matchesSearch = currentSearch === '' ||
                    order.order_id.toString().includes(currentSearch) ||
                    order.customer_name.toLowerCase().includes(currentSearch) ||
                    order.product_names.toLowerCase().includes(currentSearch);
                return matchesStatus && matchesSearch;
            });

            updateTableDisplay();
            updatePaginationInfo();
        }

        function updateTableDisplay() {
            const tbody = document.getElementById('ordersTableBody');
            if (!tbody) return;

            const start = (currentPage - 1) * rowsPerPage;
            const end = start + rowsPerPage;
            const pageOrders = filteredOrders.slice(start, end);

            tbody.innerHTML = '';

            if (pageOrders.length === 0) {
                const row = tbody.insertRow();
                const cell = row.insertCell(0);
                cell.colSpan = 8;
                cell.textContent = 'No orders found';
                cell.style.textAlign = 'center';
                cell.style.padding = '40px';
                return;
            }

            pageOrders.forEach(order => {
                const row = tbody.insertRow();
                row.innerHTML = `
                    <td class="order-id">#${order.order_id}</td>
                    <td>${order.order_date}</td>
                    <td>${escapeHtml(order.customer_name)}</td>
                    <td>${escapeHtml(order.product_names)}</td>
                    <td>${order.total_quantity}</td>
                    <td class="amount">₹${order.total_amount.toFixed(2)}</td>
                    <td><span class="status-badge ${order.status.toLowerCase()}">${order.status}</span></td>
                    <td>
                        <div class="action-buttons">
                            <button class="action-btn view" onclick="viewOrder(${order.order_id})"><i class='bx bx-show'></i></button>
                            <button class="action-btn edit" onclick="editOrder(${order.order_id})"><i class='bx bx-edit-alt'></i></button>
                        </div>
                    </td>
                `;
            });
        }

        function updatePaginationInfo() {
            const totalRows = filteredOrders.length;
            const totalPages = Math.ceil(totalRows / rowsPerPage);
            const showingStart = totalRows > 0 ? (currentPage - 1) * rowsPerPage + 1 : 0;
            const showingEnd = Math.min(currentPage * rowsPerPage, totalRows);

            document.getElementById('showingStart').innerText = showingStart;
            document.getElementById('showingEnd').innerText = showingEnd;
            document.getElementById('totalCount').innerText = totalRows;

            const prevBtn = document.getElementById('prevPage');
            const nextBtn = document.getElementById('nextPage');
            if (prevBtn) prevBtn.disabled = currentPage === 1;
            if (nextBtn) nextBtn.disabled = currentPage === totalPages || totalPages === 0;

            const pageNumbers = document.getElementById('pageNumbers');
            if (pageNumbers) {
                pageNumbers.innerHTML = '';
                const maxPages = Math.min(totalPages, 5);
                for (let i = 1; i <= maxPages; i++) {
                    const pageBtn = document.createElement('button');
                    pageBtn.className = 'page-number';
                    if (i === currentPage) pageBtn.classList.add('active');
                    pageBtn.innerText = i;
                    pageBtn.onclick = (function (page) {
                        return function () { goToPage(page); };
                    })(i);
                    pageNumbers.appendChild(pageBtn);
                }
            }
        }

        function goToPage(page) {
            currentPage = page;
            updateTableDisplay();
            updatePaginationInfo();
        }

        const prevPageBtn = document.getElementById('prevPage');
        const nextPageBtn = document.getElementById('nextPage');

        if (prevPageBtn) {
            prevPageBtn.addEventListener('click', function () {
                if (currentPage > 1) goToPage(currentPage - 1);
            });
        }

        if (nextPageBtn) {
            nextPageBtn.addEventListener('click', function () {
                const totalPages = Math.ceil(filteredOrders.length / rowsPerPage);
                if (currentPage < totalPages) goToPage(currentPage + 1);
            });
        }

        // Export to CSV
        const exportBtn = document.getElementById('exportBtn');

        if (exportBtn) {
            exportBtn.addEventListener('click', function () {
                if (filteredOrders.length === 0) {
                    alert('No data to export');
                    return;
                }

                const headers = ['Order ID', 'Date', 'Customer', 'Products', 'Quantity', 'Amount (₹)', 'Status'];
                const csvRows = [headers];

                filteredOrders.forEach(order => {
                    csvRows.push([
                        order.order_id,
                        order.order_date,
                        order.customer_name,
                        order.product_names,
                        order.total_quantity,
                        order.total_amount.toFixed(2),
                        order.status
                    ]);
                });

                const csvContent = csvRows.map(row =>
                    row.map(cell => `"${String(cell).replace(/"/g, '""')}"`).join(',')
                ).join('\n');

                const blob = new Blob(["\uFEFF" + csvContent], { type: 'text/csv;charset=utf-8;' });
                const link = document.createElement('a');
                const url = URL.createObjectURL(blob);
                link.href = url;
                link.setAttribute('download', `orders_export_${new Date().toISOString().slice(0, 19).replace(/:/g, '-')}.csv`);
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(url);

                showNotification(`Exported ${filteredOrders.length} orders to CSV`, 'success');
            });
        }

        // Modal functions
        function viewOrder(orderId) {
            const modal = document.getElementById('orderModal');
            const modalBody = document.getElementById('modalBody');

            const order = allOrdersData.find(o => o.order_id == orderId);

            if (order && modalBody) {
                modalBody.innerHTML = `
                    <div class="order-detail-row"><div class="detail-label">Order ID:</div><div class="detail-value">#${order.order_id}</div></div>
                    <div class="order-detail-row"><div class="detail-label">Order Date:</div><div class="detail-value">${order.order_date}</div></div>
                    <div class="order-detail-row"><div class="detail-label">Customer:</div><div class="detail-value">${escapeHtml(order.customer_name)}</div></div>
                    <div class="order-detail-row"><div class="detail-label">Products:</div><div class="detail-value">${escapeHtml(order.product_names)}</div></div>
                    <div class="order-detail-row"><div class="detail-label">Total Quantity:</div><div class="detail-value">${order.total_quantity}</div></div>
                    <div class="order-detail-row"><div class="detail-label">Total Amount:</div><div class="detail-value">₹${order.total_amount.toFixed(2)}</div></div>
                    <div class="order-detail-row"><div class="detail-label">Status:</div><div class="detail-value"><span class="status-badge ${order.status.toLowerCase()}">${order.status}</span></div></div>
                `;
            }

            if (modal) modal.style.display = 'flex';
        }

        function editOrder(orderId) {
            alert('Edit functionality for order #' + orderId + ' will be implemented soon.');
        }

        function closeModal() {
            const modal = document.getElementById('orderModal');
            if (modal) modal.style.display = 'none';
        }

        window.onclick = function (event) {
            const modal = document.getElementById('orderModal');
            if (event.target === modal && modal) {
                modal.style.display = 'none';
            }
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function showNotification(msg, type) {
            const notif = document.createElement('div');
            notif.className = 'notification';
            notif.textContent = msg;
            notif.style.cssText = `position:fixed;top:20px;right:20px;padding:12px 20px;border-radius:8px;color:white;font-weight:500;z-index:10001;background:${type === 'success' ? '#10b981' : '#ef4444'}`;
            document.body.appendChild(notif);
            setTimeout(() => notif.remove(), 3000);
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function () {
            applyFilters();
            goToPage(1);
        });
    </script>
</body>

</html>