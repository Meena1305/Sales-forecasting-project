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

// Get sort parameter
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'revenue_desc';
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';

// Fetch products with sales data from database
$productsQuery = "SELECT 
    p.id as product_id,
    p.name as product_name,
    ISNULL(p.category, 'Uncategorized') as category,
    p.price,
    ISNULL(SUM(s.quantity_sold), 0) as units_sold,
    ISNULL(SUM(s.quantity_sold * p.price), 0) as total_revenue
FROM Products p
LEFT JOIN Sales s ON p.id = s.product_id
GROUP BY p.id, p.name, p.category, p.price";

// Apply search filter
if (!empty($searchQuery)) {
    $productsQuery = "SELECT * FROM ($productsQuery) as sub WHERE product_name LIKE '%$searchQuery%'";
}

// Apply sorting
switch ($sortBy) {
    case 'revenue_desc':
        $productsQuery .= " ORDER BY total_revenue DESC";
        break;
    case 'revenue_asc':
        $productsQuery .= " ORDER BY total_revenue ASC";
        break;
    case 'units_desc':
        $productsQuery .= " ORDER BY units_sold DESC";
        break;
    case 'units_asc':
        $productsQuery .= " ORDER BY units_sold ASC";
        break;
    case 'name_asc':
        $productsQuery .= " ORDER BY product_name ASC";
        break;
    case 'name_desc':
        $productsQuery .= " ORDER BY product_name DESC";
        break;
    default:
        $productsQuery .= " ORDER BY total_revenue DESC";
}

$productsStmt = sqlsrv_query($conn, $productsQuery);
$products = [];
if ($productsStmt !== false) {
    while ($row = sqlsrv_fetch_array($productsStmt, SQLSRV_FETCH_ASSOC)) {
        $products[] = [
            'product_id' => $row['product_id'],
            'product_name' => $row['product_name'],
            'category' => $row['category'],
            'price' => floatval($row['price']),
            'units_sold' => intval($row['units_sold']),
            'total_revenue' => floatval($row['total_revenue'])
        ];
    }
    sqlsrv_free_stmt($productsStmt);
}

// Calculate totals
$totalProducts = count($products);
$totalUnitsSold = array_sum(array_column($products, 'units_sold'));
$totalRevenue = array_sum(array_column($products, 'total_revenue'));
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Products - InsightSphere</title>
    <link rel="stylesheet" href="styleHome.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        .products-container {
            padding: 24px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .page-header h1 {
            font-size: 24px;
            font-weight: 600;
            color: #0f172a;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: #f1f5f9;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            color: #64748b;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }

        .back-btn:hover {
            background: #e2e8f0;
            color: #1e293b;
        }

        /* Stats Grid - Matching dashboard */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .stat-card .stat-header {
            font-size: 12px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
        }

        .stat-card .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #0f172a;
        }

        .stat-card .stat-value i {
            font-size: 28px;
            color: #3b82f6;
            margin-right: 8px;
        }

        /* Filters Bar */
        .filters-bar {
            background: white;
            border-radius: 16px;
            padding: 16px 20px;
            margin-bottom: 24px;
            display: flex;
            gap: 16px;
            align-items: center;
            flex-wrap: wrap;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .search-box {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 10px;
            background: #f1f5f9;
            padding: 10px 16px;
            border-radius: 12px;
        }

        .search-box i {
            color: #94a3b8;
            font-size: 18px;
        }

        .search-box input {
            flex: 1;
            border: none;
            background: none;
            outline: none;
            font-size: 14px;
        }

        .sort-select {
            padding: 10px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: white;
            font-size: 14px;
            color: #1e293b;
            cursor: pointer;
        }

        .apply-btn {
            padding: 10px 20px;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .apply-btn:hover {
            background: #2563eb;
        }

        .apply-btn.clear {
            background: #ef4444;
        }

        .apply-btn.clear:hover {
            background: #dc2626;
        }

        /* Products Table */
        .products-table-container {
            background: white;
            border-radius: 16px;
            overflow-x: auto;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .products-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }

        .products-table th {
            text-align: left;
            padding: 16px 20px;
            background: #f8fafc;
            font-weight: 600;
            font-size: 12px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #e2e8f0;
        }

        .products-table td {
            padding: 14px 20px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
            color: #1e293b;
        }

        .products-table tr:hover {
            background: #f8fafc;
        }

        .rank-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            background: #f1f5f9;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
        }

        .rank-number.top-1 {
            background: #fef3c7;
            color: #d97706;
        }

        .rank-number.top-2 {
            background: #e0e7ff;
            color: #4f46e5;
        }

        .rank-number.top-3 {
            background: #fed7aa;
            color: #ea580c;
        }

        .product-name {
            font-weight: 600;
            color: #0f172a;
        }

        .revenue-value {
            font-weight: 700;
            color: #10b981;
        }

        .no-data {
            text-align: center;
            padding: 60px;
            color: #94a3b8;
        }

        .no-data i {
            font-size: 48px;
            margin-bottom: 16px;
            display: block;
        }

        @media (max-width: 768px) {
            .products-container {
                padding: 16px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .filters-bar {
                flex-direction: column;
                align-items: stretch;
            }

            .apply-btn {
                justify-content: center;
            }
        }
    </style>
</head>

<body>
    <div class="products-container">
        <!-- Page Header -->
        <div class="page-header">
            <h1>All Products</h1>
            <a href="sales.php" class="back-btn">
                <i class='bx bx-arrow-back'></i> Back to Sales
            </a>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">Total Products</div>
                <div class="stat-value">
                    <i class='bx bx-package'></i> <?php echo number_format($totalProducts); ?>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-header">Total Units Sold</div>
                <div class="stat-value">
                    <i class='bx bx-cart'></i> <?php echo number_format($totalUnitsSold); ?>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-header">Total Revenue</div>
                <div class="stat-value">
                    <i class='bx bx-rupee'></i> <?php echo number_format($totalRevenue); ?>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <form method="GET" action="" class="filters-bar">
            <div class="search-box">
                <i class='bx bx-search'></i>
                <input type="text" name="search" placeholder="Search product..."
                    value="<?php echo htmlspecialchars($searchQuery); ?>">
            </div>
            <select name="sort" class="sort-select">
                <option value="revenue_desc" <?php echo $sortBy == 'revenue_desc' ? 'selected' : ''; ?>>Highest Revenue
                </option>
                <option value="revenue_asc" <?php echo $sortBy == 'revenue_asc' ? 'selected' : ''; ?>>Lowest Revenue
                </option>
                <option value="units_desc" <?php echo $sortBy == 'units_desc' ? 'selected' : ''; ?>>Most Units Sold
                </option>
                <option value="units_asc" <?php echo $sortBy == 'units_asc' ? 'selected' : ''; ?>>Least Units Sold
                </option>
                <option value="name_asc" <?php echo $sortBy == 'name_asc' ? 'selected' : ''; ?>>Name (A-Z)</option>
                <option value="name_desc" <?php echo $sortBy == 'name_desc' ? 'selected' : ''; ?>>Name (Z-A)</option>
            </select>
            <button type="submit" class="apply-btn">
                <i class='bx bx-check'></i> Apply
            </button>
            <?php if (!empty($searchQuery) || $sortBy != 'revenue_desc'): ?>
                <a href="seeAllProducts.php" class="apply-btn clear">
                    <i class='bx bx-refresh'></i> Clear
                </a>
            <?php endif; ?>
        </form>

        <!-- Products Table -->
        <div class="products-table-container">
            <table class="products-table">
                <thead>
                    <tr>
                        <th style="width: 60px;">#</th>
                        <th>Product Name</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Units Sold</th>
                        <th>Total Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="6" class="no-data">
                                <i class='bx bx-package'></i>
                                No products found. Upload a CSV file to see products.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($products as $index => $product): ?>
                            <tr>
                                <td>
                                    <span
                                        class="rank-number <?php echo $index == 0 ? 'top-1' : ($index == 1 ? 'top-2' : ($index == 2 ? 'top-3' : '')); ?>">
                                        <?php echo $index + 1; ?>
                                    </span>
                                </td>
                                <td class="product-name"><?php echo htmlspecialchars($product['product_name']); ?></td>
                                <td><?php echo htmlspecialchars($product['category']); ?></td>
                                <td>₹<?php echo number_format($product['price'], 2); ?></td>
                                <td><?php echo number_format($product['units_sold']); ?></td>
                                <td class="revenue-value">₹<?php echo number_format($product['total_revenue'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    </div>
    </div>

    <script src="scriptHome.js"></script>
</body>

</html>