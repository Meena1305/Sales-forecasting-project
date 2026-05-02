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

// Check if database connection exists
$dbConnected = ($conn !== false);

// Fetch products from database
$products = [];
$productsQuery = "SELECT 
    p.id as product_id,
    p.name as product_name,
    p.price,
    ISNULL(p.category, 'Uncategorized') as category_name,
    ISNULL(p.sku, 'N/A') as sku,
    ISNULL(i.quantity_available, 0) as stock_quantity
FROM Products p
LEFT JOIN Inventory i ON p.id = i.product_id
ORDER BY p.id DESC";

$productsStmt = sqlsrv_query($conn, $productsQuery);
if ($productsStmt !== false) {
    while ($row = sqlsrv_fetch_array($productsStmt, SQLSRV_FETCH_ASSOC)) {
        $products[] = $row;
    }
    sqlsrv_free_stmt($productsStmt);
}

// Get categories from products
$categories = [];
$catQuery = "SELECT DISTINCT category as category_name FROM Products WHERE category IS NOT NULL ORDER BY category";
$catStmt = sqlsrv_query($conn, $catQuery);
if ($catStmt !== false) {
    $counter = 1;
    while ($cat = sqlsrv_fetch_array($catStmt, SQLSRV_FETCH_ASSOC)) {
        $categories[] = ['category_id' => $counter++, 'category_name' => $cat['category_name']];
    }
    sqlsrv_free_stmt($catStmt);
}

// If no categories, use defaults
if (empty($categories)) {
    $categories = [
        ['category_id' => 1, 'category_name' => 'Electronics'],
        ['category_id' => 2, 'category_name' => 'Accessories'],
        ['category_id' => 3, 'category_name' => 'Furniture'],
        ['category_id' => 4, 'category_name' => 'Office'],
        ['category_id' => 5, 'category_name' => 'Smart Home'],
    ];
}

// Calculate statistics from products
$totalProducts = count($products);
$totalStock = array_sum(array_column($products, 'stock_quantity'));
$totalStockValue = array_sum(array_map(function ($p) {
    return $p['price'] * $p['stock_quantity'];
}, $products));
$lowStockProducts = count(array_filter($products, function ($p) {
    return $p['stock_quantity'] > 0 && $p['stock_quantity'] <= 10;
}));
$outOfStockProducts = count(array_filter($products, function ($p) {
    return $p['stock_quantity'] <= 0;
}));

// Calculate stock value
$totalStockValue = 0;
foreach ($products as $product) {
    $totalStockValue += $product['price'] * $product['stock_quantity'];
}

function getStockBadgeClass($quantity)
{
    if ($quantity <= 0)
        return 'out-of-stock';
    if ($quantity <= 10)
        return 'low-stock';
    return 'in-stock';
}

function getStockBadgeText($quantity)
{
    if ($quantity <= 0)
        return 'Out of Stock';
    if ($quantity <= 10)
        return 'Low Stock';
    return 'In Stock';
}

if (isset($row['created_at'])) {
    if ($row['created_at'] instanceof DateTime) {
        $createdAt = $row['created_at']->format('Y-m-d');
    } else {
        $createdAt = date('Y-m-d', strtotime($row['created_at']));
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - InsightSphere</title>
    <link rel="stylesheet" href="products.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
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
                <a href="sales.php" class="nav-item">
                    <i class='bx bxs-cart'></i>
                    <span>Sales</span>
                </a>
                <a href="products.php" class="nav-item active">
                    <i class='bx bxs-box'></i>
                    <span>Products</span>
                    <span class="nav-badge"><?php echo $totalProducts; ?></span>
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
        <main class="main-content">
            <button class="mobile-menu-toggle" id="mobileMenuToggle" onclick="toggleSidebar()">
                <i class='bx bx-menu'></i>
            </button>

            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h1>Products</h1>
                    <p>Manage your product inventory and catalog</p>
                </div>
                <button class="add-product-btn" onclick="openAddProductModal()">
                    <i class='bx bx-plus'></i>
                    Add New Product
                </button>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon blue">
                        <i class='bx bx-package'></i>
                    </div>
                    <div class="stat-info">
                        <span class="stat-label">Total Products</span>
                        <span class="stat-value"><?php echo $totalProducts; ?></span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class='bx bx-cube'></i>
                    </div>
                    <div class="stat-info">
                        <span class="stat-label">Total Stock</span>
                        <span class="stat-value"><?php echo number_format($totalStock); ?></span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon orange">
                        <i class='bx bx-rupee'></i>
                    </div>
                    <div class="stat-info">
                        <span class="stat-label">Stock Value</span>
                        <span class="stat-value">₹<?php echo number_format($totalStockValue); ?></span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon red">
                        <i class='bx bx-error-circle'></i>
                    </div>
                    <div class="stat-info">
                        <span class="stat-label">Low Stock Items</span>
                        <span class="stat-value"><?php echo $lowStockProducts + $outOfStockProducts; ?></span>
                    </div>
                </div>
            </div>

            <!-- Filters and Search -->
            <div class="filters-bar">
                <div class="search-box">
                    <i class='bx bx-search'></i>
                    <input type="text" id="searchInput" placeholder="Search products by name...">
                </div>
                <div class="filter-group">
                    <select id="categoryFilter" class="filter-select">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo htmlspecialchars($category['category_name']); ?>">
                                <?php echo htmlspecialchars($category['category_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select id="stockFilter" class="filter-select">
                        <option value="">All Stock Status</option>
                        <option value="in-stock">In Stock</option>
                        <option value="low-stock">Low Stock (≤10)</option>
                        <option value="out-of-stock">Out of Stock</option>
                    </select>
                </div>
            </div>

            <!-- Products Table -->
            <div class="products-section">
                <div class="section-header">
                    <h2>Product Catalog <span
                            style="font-size: 0.7rem; color: #94a3b8; font-weight: normal;">(<?php echo $totalProducts; ?>
                            products)</span></h2>
                    <div class="table-controls">
                        <button class="export-btn" onclick="exportToCSV()">
                            <i class='bx bx-export'></i> Export CSV
                        </button>
                    </div>
                </div>
                <div class="table-container">
                    <table class="products-table" id="productsTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Product Name</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="productsTableBody">
                            <?php foreach ($products as $product): ?>
                                <tr data-category="<?php echo htmlspecialchars($product['category_name']); ?>"
                                    data-stock="<?php echo getStockBadgeClass($product['stock_quantity']); ?>">
                                    <td>#<?php echo $product['product_id']; ?></td>
                                    <td class="product-name"><?php echo htmlspecialchars($product['product_name']); ?></td>
                                    <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                                    <td>₹<?php echo number_format($product['price'], 2); ?></td>
                                    <td><?php echo number_format($product['stock_quantity']); ?></td>
                                    <td>
                                        <span
                                            class="stock-badge <?php echo getStockBadgeClass($product['stock_quantity']); ?>">
                                            <?php echo getStockBadgeText($product['stock_quantity']); ?>
                                        </span>
                                    </td>
                                    <td class="actions">
                                        <button class="action-btn edit"
                                            onclick="editProduct(<?php echo $product['product_id']; ?>)">
                                            <i class='bx bx-edit'></i>
                                        </button>
                                        <button class="action-btn delete"
                                            onclick="deleteProduct(<?php echo $product['product_id']; ?>, '<?php echo htmlspecialchars($product['product_name']); ?>')">
                                            <i class='bx bx-trash'></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Add/Edit Product Modal -->
    <div id="productModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Add New Product</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <form id="productForm">
                <input type="hidden" id="productId">
                <div class="form-group">
                    <label>Product Name</label>
                    <input type="text" id="productName" class="form-input" placeholder="Enter product name" required>
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <select id="productCategory" class="form-input" required>
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo htmlspecialchars($category['category_name']); ?>">
                                <?php echo htmlspecialchars($category['category_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Price (₹)</label>
                        <input type="number" id="productPrice" class="form-input" step="0.01" placeholder="0.00"
                            required>
                    </div>
                    <div class="form-group">
                        <label>Stock Quantity</label>
                        <input type="number" id="productStock" class="form-input" placeholder="0" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn-save">Save Product</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Toggle sidebar for mobile
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
        }

        function confirmLogout() {
            return confirm('Are you sure you want to logout?');
        }

        // Filter functionality
        const searchInput = document.getElementById('searchInput');
        const categoryFilter = document.getElementById('categoryFilter');
        const stockFilter = document.getElementById('stockFilter');
        const tableBody = document.getElementById('productsTableBody');
        const rows = tableBody ? tableBody.getElementsByTagName('tr') : [];

        function filterTable() {
            const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
            const category = categoryFilter ? categoryFilter.value : '';
            const stock = stockFilter ? stockFilter.value : '';
            let visibleCount = 0;

            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const productName = row.cells[1]?.textContent.toLowerCase() || '';
                const rowCategory = row.getAttribute('data-category') || '';
                const rowStock = row.getAttribute('data-stock') || '';

                let show = true;

                if (searchTerm && !productName.includes(searchTerm)) {
                    show = false;
                }
                if (category && rowCategory !== category) {
                    show = false;
                }
                if (stock && rowStock !== stock) {
                    show = false;
                }

                row.style.display = show ? '' : 'none';
                if (show) visibleCount++;
            }

            // Update the product count in header
            const countSpan = document.querySelector('.section-header h2 span');
            if (countSpan) {
                countSpan.textContent = `(${visibleCount} products)`;
            }
        }

        if (searchInput) searchInput.addEventListener('keyup', filterTable);
        if (categoryFilter) categoryFilter.addEventListener('change', filterTable);
        if (stockFilter) stockFilter.addEventListener('change', filterTable);

        // Modal functions
        const modal = document.getElementById('productModal');

        function openAddProductModal() {
            document.getElementById('modalTitle').textContent = 'Add New Product';
            document.getElementById('productForm').reset();
            document.getElementById('productId').value = '';
            modal.style.display = 'flex';
        }

        function closeModal() {
            modal.style.display = 'none';
        }

        function editProduct(id) {
            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const productId = row.cells[0]?.textContent.replace('#', '');
                if (productId == id) {
                    document.getElementById('modalTitle').textContent = 'Edit Product';
                    document.getElementById('productId').value = id;
                    document.getElementById('productName').value = row.cells[1]?.textContent || '';
                    document.getElementById('productCategory').value = row.cells[2]?.textContent || '';
                    document.getElementById('productPrice').value = row.cells[3]?.textContent.replace('₹', '') || '';
                    document.getElementById('productStock').value = row.cells[4]?.textContent || '';
                    break;
                }
            }
            modal.style.display = 'flex';
        }

        function deleteProduct(id, name) {
            if (confirm(`Are you sure you want to delete "${name}"?`)) {
                // Find and remove the row
                for (let i = 0; i < rows.length; i++) {
                    const row = rows[i];
                    const productId = row.cells[0]?.textContent.replace('#', '');
                    if (productId == id) {
                        row.remove();
                        alert(`Product "${name}" has been deleted.`);
                        filterTable(); // Refresh the count
                        break;
                    }
                }
            }
        }

        // Form submission
        document.getElementById('productForm')?.addEventListener('submit', function (e) {
            e.preventDefault();
            const id = document.getElementById('productId').value;
            const productName = document.getElementById('productName').value;
            const category = document.getElementById('productCategory').value;
            const price = document.getElementById('productPrice').value;
            const stock = document.getElementById('productStock').value;

            if (id) {
                // Update existing product in the table
                for (let i = 0; i < rows.length; i++) {
                    const row = rows[i];
                    const productId = row.cells[0]?.textContent.replace('#', '');
                    if (productId == id) {
                        row.cells[1].textContent = productName;
                        row.cells[2].textContent = category;
                        row.cells[3].textContent = '₹' + parseFloat(price).toLocaleString('en-IN', { minimumFractionDigits: 2 });
                        row.cells[4].textContent = parseInt(stock);
                        row.setAttribute('data-category', category);
                        let stockClass = '';
                        let stockText = '';
                        if (stock <= 0) {
                            stockClass = 'out-of-stock';
                            stockText = 'Out of Stock';
                        } else if (stock <= 10) {
                            stockClass = 'low-stock';
                            stockText = 'Low Stock';
                        } else {
                            stockClass = 'in-stock';
                            stockText = 'In Stock';
                        }
                        row.setAttribute('data-stock', stockClass);
                        row.cells[5].innerHTML = `<span class="stock-badge ${stockClass}">${stockText}</span>`;
                        alert(`Product "${productName}" has been updated.`);
                        break;
                    }
                }
            } else {
                // Add new product
                const newId = Math.floor(Math.random() * 1000) + 200;
                let stockClass = '';
                let stockText = '';
                if (stock <= 0) {
                    stockClass = 'out-of-stock';
                    stockText = 'Out of Stock';
                } else if (stock <= 10) {
                    stockClass = 'low-stock';
                    stockText = 'Low Stock';
                } else {
                    stockClass = 'in-stock';
                    stockText = 'In Stock';
                }

                const newRow = `
                    <tr data-category="${category}" data-stock="${stockClass}">
                        <td>#${newId}</td>
                        <td class="product-name">${productName}</td>
                        <td>${category}</td>
                        <td>₹${parseFloat(price).toLocaleString('en-IN', { minimumFractionDigits: 2 })}</td>
                        <td>${parseInt(stock)}</td>
                        <td><span class="stock-badge ${stockClass}">${stockText}</span></td>
                        <td class="actions">
                            <button class="action-btn edit" onclick="editProduct(${newId})"><i class='bx bx-edit'></i></button>
                            <button class="action-btn delete" onclick="deleteProduct(${newId}, '${productName}')"><i class='bx bx-trash'></i></button>
                        </td>
                    </tr>
                `;
                tableBody.insertAdjacentHTML('beforeend', newRow);
                alert(`Product "${productName}" has been added.`);
                filterTable();
            }
            closeModal();
        });

        // Close modal when clicking outside
        window.onclick = function (event) {
            if (event.target === modal) {
                closeModal();
            }
        }

        // Export to CSV
        function exportToCSV() {
            const table = document.getElementById('productsTable');
            let csv = [];
            // Add headers
            let headers = [];
            for (let j = 0; j < table.rows[0].cells.length - 1; j++) {
                headers.push(table.rows[0].cells[j].innerText);
            }
            csv.push(headers.join(','));

            // Add data rows (only visible rows)
            for (let i = 1; i < table.rows.length; i++) {
                if (table.rows[i].style.display !== 'none') {
                    let row = [];
                    for (let j = 0; j < table.rows[i].cells.length - 1; j++) {
                        let cell = table.rows[i].cells[j];
                        let text = cell.innerText.replace(/,/g, '');
                        row.push(text);
                    }
                    csv.push(row.join(','));
                }
            }

            const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'products_export.csv';
            a.click();
            URL.revokeObjectURL(url);
        }
    </script>
</body>

</html>