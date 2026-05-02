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

// Get filter values from URL
$selectedCategory = isset($_GET['category']) ? $_GET['category'] : 'all';
$selectedStock = isset($_GET['stock']) ? $_GET['stock'] : 'all';
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';

// Build WHERE clause based on filters
$whereClauses = [];
$params = [];

if ($selectedCategory !== 'all') {
    $whereClauses[] = "p.category = ?";
    $params[] = $selectedCategory;
}

if ($selectedStock !== 'all') {
    if ($selectedStock === 'in-stock') {
        $whereClauses[] = "ISNULL(i.quantity_available, 0) > 10";
    } elseif ($selectedStock === 'low-stock') {
        $whereClauses[] = "ISNULL(i.quantity_available, 0) BETWEEN 1 AND 10";
    } elseif ($selectedStock === 'out-of-stock') {
        $whereClauses[] = "ISNULL(i.quantity_available, 0) = 0";
    }
}

if (!empty($searchTerm)) {
    $whereClauses[] = "(p.name LIKE ? OR p.sku LIKE ?)";
    $params[] = "%$searchTerm%";
    $params[] = "%$searchTerm%";
}

$whereSQL = '';
if (!empty($whereClauses)) {
    $whereSQL = "WHERE " . implode(" AND ", $whereClauses);
}

// Count total filtered products for pagination
$countQuery = "SELECT COUNT(*) as total 
FROM Products p
LEFT JOIN Inventory i ON p.id = i.product_id
$whereSQL";
$countStmt = sqlsrv_query($conn, $countQuery, $params);
$totalFilteredProducts = 0;
if ($countStmt) {
    $countRow = sqlsrv_fetch_array($countStmt, SQLSRV_FETCH_ASSOC);
    $totalFilteredProducts = $countRow['total'];
    sqlsrv_free_stmt($countStmt);
}

// Pagination
$itemsPerPage = 10;
$currentPage = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$totalPages = $totalFilteredProducts > 0 ? ceil($totalFilteredProducts / $itemsPerPage) : 1;
$offset = ($currentPage - 1) * $itemsPerPage;

// Fetch filtered and paginated inventory
$inventory = [];
$inventoryQuery = "SELECT 
    p.id as product_id,
    p.sku,
    p.name as product_name,
    ISNULL(p.category, 'Uncategorized') as category,
    p.price,
    ISNULL(i.quantity_available, 0) as quantity_available
FROM Products p
LEFT JOIN Inventory i ON p.id = i.product_id
$whereSQL
ORDER BY p.name ASC
OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";

$params[] = $offset;
$params[] = $itemsPerPage;

$inventoryStmt = sqlsrv_query($conn, $inventoryQuery, $params);

if ($inventoryStmt !== false) {
    while ($row = sqlsrv_fetch_array($inventoryStmt, SQLSRV_FETCH_ASSOC)) {
        $qty = intval($row['quantity_available']);

        $category = trim($row['category']);

        if ($qty <= 0) {
            $stock_status = 'Out of Stock';
            $status_color = 'danger';
            $stock_filter = 'out-of-stock';
        } elseif ($qty <= 10) {
            $stock_status = 'Low Stock';
            $status_color = 'warning';
            $stock_filter = 'low-stock';
        } else {
            $stock_status = 'In Stock';
            $status_color = 'success';
            $stock_filter = 'in-stock';
        }

        $inventory[] = [
            'product_id' => $row['product_id'],
            'sku' => $row['sku'] ?? 'SKU_' . rand(1000, 9999),
            'product_name' => $row['product_name'],
            'category' => $category,
            'price' => floatval($row['price']),
            'quantity_available' => $qty,
            'stock_status' => $stock_status,
            'status_color' => $status_color,
            'stock_filter' => $stock_filter
        ];
    }
    sqlsrv_free_stmt($inventoryStmt);
}

// Get all unique categories for filter dropdown
$allCategories = [];
$catQuery = "SELECT DISTINCT category FROM Products WHERE category IS NOT NULL AND category != '' ORDER BY category";
$catStmt = sqlsrv_query($conn, $catQuery);
if ($catStmt) {
    while ($catRow = sqlsrv_fetch_array($catStmt, SQLSRV_FETCH_ASSOC)) {
        $allCategories[] = $catRow['category'];
    }
    sqlsrv_free_stmt($catStmt);
}

// Calculate statistics for ALL products (not just filtered)
$totalProductsQuery = "SELECT COUNT(*) as total FROM Products";
$totalStmt = sqlsrv_query($conn, $totalProductsQuery);
$totalProducts = 0;
if ($totalStmt) {
    $totalRow = sqlsrv_fetch_array($totalStmt, SQLSRV_FETCH_ASSOC);
    $totalProducts = $totalRow['total'];
    sqlsrv_free_stmt($totalStmt);
}

$totalUnitsQuery = "SELECT ISNULL(SUM(i.quantity_available), 0) as total FROM Inventory i";
$totalUnitsStmt = sqlsrv_query($conn, $totalUnitsQuery);
$totalUnits = 0;
if ($totalUnitsStmt) {
    $totalUnitsRow = sqlsrv_fetch_array($totalUnitsStmt, SQLSRV_FETCH_ASSOC);
    $totalUnits = $totalUnitsRow['total'];
    sqlsrv_free_stmt($totalUnitsStmt);
}

$inventoryValueQuery = "SELECT ISNULL(SUM(p.price * i.quantity_available), 0) as total FROM Products p LEFT JOIN Inventory i ON p.id = i.product_id";
$invValueStmt = sqlsrv_query($conn, $inventoryValueQuery);
$inventoryValue = 0;
if ($invValueStmt) {
    $invValueRow = sqlsrv_fetch_array($invValueStmt, SQLSRV_FETCH_ASSOC);
    $inventoryValue = $invValueRow['total'];
    sqlsrv_free_stmt($invValueStmt);
}

$lowStockCountQuery = "SELECT COUNT(*) as total FROM Products p LEFT JOIN Inventory i ON p.id = i.product_id WHERE ISNULL(i.quantity_available, 0) <= 10";
$lowStockStmt = sqlsrv_query($conn, $lowStockCountQuery);
$lowStockCount = 0;
if ($lowStockStmt) {
    $lowStockRow = sqlsrv_fetch_array($lowStockStmt, SQLSRV_FETCH_ASSOC);
    $lowStockCount = $lowStockRow['total'];
    sqlsrv_free_stmt($lowStockStmt);
}
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory - InsightSphere</title>
    <link rel="stylesheet" href="inventory.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
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
                <a href="inventory.php" class="nav-item active">
                    <i class='bx bxs-building-house'></i>
                    <span>Inventory</span>
                    <?php if ($lowStockCount > 0): ?>
                        <span class="nav-badge"><?php echo $lowStockCount; ?></span>
                    <?php endif; ?>
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
                        <input type="text" id="searchInput" placeholder="Search products by name..."
                            value="<?php echo htmlspecialchars($searchTerm); ?>">
                    </div>
                </div>
            </header>

            <!-- Stats Cards -->
            <div class="metrics-grid">
                <div class="metric-card">
                    <div class="metric-header">TOTAL PRODUCTS</div>
                    <div class="metric-value"><?php echo $totalProducts; ?></div>
                    <div class="metric-subtext">Active in catalog</div>
                    <div class="metric-change positive">vs last month <i class='bx bx-up-arrow-alt'></i>5%</div>
                </div>
                <div class="metric-card">
                    <div class="metric-header">TOTAL STOCK</div>
                    <div class="metric-value"><?php echo number_format($totalUnits); ?></div>
                    <div class="metric-change positive">vs last month <i class='bx bx-up-arrow-alt'></i>12%</div>
                </div>
                <div class="metric-card">
                    <div class="metric-header">INVENTORY VALUE</div>
                    <div class="metric-value"><i class='bx bx-rupee'></i> <?php echo number_format($inventoryValue); ?>
                    </div>
                    <div class="metric-change positive">vs last month <i class='bx bx-up-arrow-alt'></i>8%</div>
                </div>
                <div class="metric-card">
                    <div class="metric-header">LOW STOCK ITEMS</div>
                    <div class="metric-value"><?php echo $lowStockCount; ?></div>
                    <div class="metric-subtext">Need attention</div>
                    <div class="metric-change negative">vs last month <i class='bx bx-down-arrow-alt'></i>3%</div>
                </div>
            </div>

            <!-- Filters -->
            <form method="GET" action="inventory.php" id="filterForm">
                <div class="filters-bar">
                    <div class="filters-left">
                        <select id="categoryFilter" name="category" class="filter-select">
                            <option value="all" <?php echo $selectedCategory == 'all' ? 'selected' : ''; ?>>All
                                Categories</option>
                            <?php foreach ($allCategories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category); ?>" <?php echo $selectedCategory == $category ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <select id="stockFilter" name="stock" class="filter-select">
                            <option value="all" <?php echo $selectedStock == 'all' ? 'selected' : ''; ?>>All Stock Status
                            </option>
                            <option value="in-stock" <?php echo $selectedStock == 'in-stock' ? 'selected' : ''; ?>>In
                                Stock (10+)</option>
                            <option value="low-stock" <?php echo $selectedStock == 'low-stock' ? 'selected' : ''; ?>>Low
                                Stock (1-10)</option>
                            <option value="out-of-stock" <?php echo $selectedStock == 'out-of-stock' ? 'selected' : ''; ?>>Out of Stock (0)</option>
                        </select>

                        <input type="hidden" name="search" id="searchHidden"
                            value="<?php echo htmlspecialchars($searchTerm); ?>">
                    </div>

                    <div class="filters-right">
                        <button type="submit" class="btn-apply">
                            <i class='bx bx-filter-alt'></i> Apply Filters
                        </button>
                        <a href="inventory.php" class="btn-clear">
                            <i class='bx bx-refresh'></i> Clear Filters
                        </a>
                        <button type="button" class="btn-export" onclick="exportToCSV()">
                            <i class='bx bx-download'></i> Export CSV
                        </button>
                    </div>
                </div>
            </form>

            <!-- Products Table -->
            <div class="card">
                <div class="card-header">
                    Product Catalog <span
                        style="font-size: 0.7rem; color: #94a3b8;">(<?php echo $totalFilteredProducts; ?> products
                        found)</span>
                </div>
                <div class="table-container">
                    <table class="products-table">
                        <thead>
                            <tr>
                                <th>SKU</th>
                                <th>PRODUCT NAME</th>
                                <th>CATEGORY</th>
                                <th>PRICE</th>
                                <th>STOCK QUANTITY</th>
                                <th>STATUS</th>
                                <th>ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody id="inventoryTableBody">
                            <?php if (empty($inventory)): ?>
                                <tr>
                                    <td colspan="7" style="text-align:center; padding:40px;">No products found matching your
                                        filters.</td>
                                <?php else: ?>
                                    <?php foreach ($inventory as $item): ?>
                                    <tr data-category="<?php echo htmlspecialchars($item['category']); ?>"
                                        data-stock="<?php echo $item['stock_filter']; ?>">
                                        <td class="sku"><?php echo htmlspecialchars($item['sku']); ?></td>
                                        <td class="product-name"><?php echo htmlspecialchars($item['product_name']); ?></td>
                                        <td class="category-cell"><?php echo htmlspecialchars($item['category']); ?></td>
                                        <td class="price">₹<?php echo number_format($item['price'], 2); ?></td>
                                        <td class="stock-qty"><?php echo $item['quantity_available']; ?></td>
                                        <td class="status-cell">
                                            <span class="stock-badge <?php echo $item['status_color']; ?>">
                                                <?php echo $item['stock_status']; ?>
                                            </span>
                                        </td>
                                        <td class="actions">
                                            <button class="action-btn edit"
                                                onclick="editProduct(<?php echo $item['product_id']; ?>)">
                                                <i class='bx bx-edit'></i>
                                            </button>
                                            <button class="action-btn delete"
                                                onclick="deleteProduct(<?php echo $item['product_id']; ?>, '<?php echo htmlspecialchars($item['product_name']); ?>')">
                                                <i class='bx bx-trash'></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($currentPage > 1): ?>
                            <a href="?page=<?php echo $currentPage - 1; ?>&category=<?php echo urlencode($selectedCategory); ?>&stock=<?php echo urlencode($selectedStock); ?>&search=<?php echo urlencode($searchTerm); ?>"
                                class="page-link"><i class='bx bx-chevron-left'></i> Previous</a>
                        <?php endif; ?>

                        <?php
                        $startPage = max(1, $currentPage - 2);
                        $endPage = min($totalPages, $currentPage + 2);

                        if ($startPage > 1) {
                            echo '<a href="?page=1&category=' . urlencode($selectedCategory) . '&stock=' . urlencode($selectedStock) . '&search=' . urlencode($searchTerm) . '" class="page-link">1</a>';
                            if ($startPage > 2)
                                echo '<span class="page-link">...</span>';
                        }

                        for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <a href="?page=<?php echo $i; ?>&category=<?php echo urlencode($selectedCategory); ?>&stock=<?php echo urlencode($selectedStock); ?>&search=<?php echo urlencode($searchTerm); ?>"
                                class="page-link <?php echo $i == $currentPage ? 'active' : ''; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>

                        <?php if ($endPage < $totalPages): ?>
                            <?php if ($endPage < $totalPages - 1)
                                echo '<span class="page-link">...</span>'; ?>
                            <a href="?page=<?php echo $totalPages; ?>&category=<?php echo urlencode($selectedCategory); ?>&stock=<?php echo urlencode($selectedStock); ?>&search=<?php echo urlencode($searchTerm); ?>"
                                class="page-link"><?php echo $totalPages; ?></a>
                        <?php endif; ?>

                        <?php if ($currentPage < $totalPages): ?>
                            <a href="?page=<?php echo $currentPage + 1; ?>&category=<?php echo urlencode($selectedCategory); ?>&stock=<?php echo urlencode($selectedStock); ?>&search=<?php echo urlencode($searchTerm); ?>"
                                class="page-link">Next <i class='bx bx-chevron-right'></i></a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div style="text-align: center; margin-top: 15px; font-size: 0.75rem; color: #64748b;">
                    Showing <?php echo count($inventory); ?> of <?php echo $totalFilteredProducts; ?> products
                </div>
            </div>
        </div>
    </div>

    <style>
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 24px;
        }

        .metric-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            position: relative;
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

        .metric-change.negative {
            color: #ef4444;
        }

        .filters-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            gap: 20px;
            flex-wrap: wrap;
            background: white;
            padding: 16px 20px;
            border-radius: 16px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .filter-select {
            padding: 10px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: white;
            font-size: 0.85rem;
            color: #0f172a;
            cursor: pointer;
            transition: all 0.2s;
            min-width: 160px;
        }

        .filter-select:hover {
            border-color: #3b82f6;
        }

        .filter-select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .btn-apply,
        .btn-clear,
        .btn-export {
            padding: 10px 20px;
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            border: none;
            text-decoration: none;
        }

        .btn-apply {
            background: #3b82f6;
            color: white;
        }

        .btn-apply:hover {
            background: #2563eb;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        .btn-clear {
            background: white;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }

        .btn-clear:hover {
            background: #f8fafc;
            color: #0f172a;
            border-color: #cbd5e1;
        }

        .btn-export {
            background: #f1f5f9;
            color: #0f172a;
            border: 1px solid #e2e8f0;
        }

        .btn-export:hover {
            background: #e2e8f0;
            transform: translateY(-1px);
        }

        /* Remove the old export-btn styles */
        .export-btn {
            display: none;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .filters-bar {
                flex-direction: column;
                align-items: stretch;
            }

            .filters-left {
                flex-direction: column;
                width: 100%;
            }

            .filters-right {
                flex-direction: column;
                width: 100%;
            }

            .filter-select {
                width: 100%;
            }

            .btn-apply,
            .btn-clear,
            .btn-export {
                width: 100%;
                justify-content: center;
            }
        }

        .card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            font-weight: 600;
            margin-bottom: 16px;
            font-size: 1rem;
        }

        .table-container {
            overflow-x: auto;
        }

        .products-table {
            width: 100%;
            border-collapse: collapse;
        }

        .products-table th,
        .products-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        .products-table th {
            background: #f8fafc;
            font-weight: 600;
            color: #64748b;
            font-size: 0.7rem;
        }

        .stock-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
        }

        .stock-badge.danger {
            background: #fee2e2;
            color: #dc2626;
        }

        .stock-badge.warning {
            background: #fef3c7;
            color: #d97706;
        }

        .stock-badge.success {
            background: #d1fae5;
            color: #059669;
        }

        .action-btn {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1.1rem;
            color: #64748b;
            padding: 4px 8px;
        }

        .action-btn:hover {
            color: #3b82f6;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .page-link {
            padding: 6px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            text-decoration: none;
            color: #64748b;
            font-size: 0.8rem;
        }

        .page-link.active {
            background: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }

        .page-link:hover {
            background: #f1f5f9;
        }

        .search-bar {
            display: flex;
            align-items: center;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 8px 16px;
            gap: 8px;
        }

        .search-bar input {
            border: none;
            outline: none;
            font-size: 0.85rem;
            width: 250px;
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .top-actions {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .icon-button {
            background: none;
            border: none;
            font-size: 1.2rem;
            cursor: pointer;
            position: relative;
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ef4444;
            color: white;
            font-size: 0.6rem;
            padding: 2px 5px;
            border-radius: 10px;
        }

        .profile-button {
            display: flex;
            align-items: center;
            gap: 12px;
            background: none;
            border: none;
            cursor: pointer;
            padding: 6px 12px;
            border-radius: 40px;
        }

        .profile-button:hover {
            background: #f1f5f9;
        }

        .profile-info {
            text-align: right;
        }

        .profile-name {
            font-size: 0.8rem;
            font-weight: 500;
            color: #0f172a;
        }

        .profile-role {
            font-size: 0.65rem;
            color: #64748b;
        }

        .profile-avatar {
            width: 36px;
            height: 36px;
            background: #3b82f6;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        .mobile-menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
        }

        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                transform: translateX(-100%);
                transition: transform 0.3s;
                z-index: 1000;
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .mobile-menu-toggle {
                display: block;
            }

            .metrics-grid {
                grid-template-columns: 1fr;
            }

            .filters-bar {
                flex-direction: column;
            }

            .export-btn {
                margin-left: 0;
            }
        }
    </style>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
        }

        function confirmLogout() {
            return confirm('Are you sure you want to logout?');
        }

        function editProduct(id) {
            window.location.href = 'edit_product.php?id=' + id;
        }

        function deleteProduct(id, name) {
            if (confirm('Are you sure you want to delete "' + name + '"?')) {
                showNotification('Product "' + name + '" deleted', 'success');
                setTimeout(() => location.reload(), 1000);
            }
        }

        // Handle search input with Enter key
        document.getElementById('searchInput').addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                document.getElementById('searchHidden').value = this.value;
                document.getElementById('filterForm').submit();
            }
        });

        function exportToCSV() {
            const rows = document.querySelectorAll('#inventoryTableBody tr');
            let csv = [['SKU', 'Product Name', 'Category', 'Price', 'Stock Quantity', 'Status']];

            rows.forEach(row => {
                if (!row.querySelector('td[colspan]')) {
                    const cells = row.querySelectorAll('td');
                    csv.push([
                        cells[0]?.innerText || '',
                        cells[1]?.innerText || '',
                        cells[2]?.innerText || '',
                        cells[3]?.innerText || '',
                        cells[4]?.innerText || '',
                        cells[5]?.innerText || ''
                    ]);
                }
            });

            if (csv.length === 1) {
                showNotification('No products to export!', 'error');
                return;
            }

            const csvContent = csv.map(row => row.join(',')).join('\n');
            const blob = new Blob(["\uFEFF" + csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = 'inventory_export.csv';
            link.click();
            URL.revokeObjectURL(link.href);
            showNotification('Export complete!', 'success');
        }

        function showNotification(msg, type) {
            const notif = document.createElement('div');
            notif.className = 'notification';
            notif.textContent = msg;
            notif.style.cssText = `position:fixed;top:20px;right:20px;padding:12px 20px;border-radius:8px;color:white;z-index:10001;background:${type === 'success' ? '#10b981' : '#ef4444'}`;
            document.body.appendChild(notif);
            setTimeout(() => notif.remove(), 3000);
        }
    </script>
</body>

</html>