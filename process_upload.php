<?php
header('Content-Type: application/json');
session_start();

$serverName = "localhost";
$connectionOptions = [
    "Database" => "user_system",
    "UID" => "sa",
    "PWD" => "123456"
];

$conn = sqlsrv_connect($serverName, $connectionOptions);

if (!$conn) {
    echo json_encode(['status' => 'error', 'message' => 'Connection failed']);
    exit;
}

// ========== ADD NOTIFICATION FUNCTION ==========
function createNotification($title, $message, $type = 'info') {
    $notificationData = [
        'title' => $title,
        'message' => $message,
        'type' => $type  // 'info', 'alert', 'stock', 'customer'
    ];
    
    // Use cURL to call save_notification.php
    $ch = curl_init('http://' . $_SERVER['HTTP_HOST'] . '/save_notification.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($notificationData));
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); // Don't wait too long
    curl_exec($ch);
    curl_close($ch);
}

// Check if file was uploaded
if (!isset($_FILES['uploadedFile']) || $_FILES['uploadedFile']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['status' => 'error', 'message' => 'No file uploaded']);
    exit;
}

$file = $_FILES['uploadedFile']['tmp_name'];

// Read CSV file
$rows = [];
if (($handle = fopen($file, "r")) !== false) {
    $headers = fgetcsv($handle); // Skip headers
    
    while (($data = fgetcsv($handle)) !== false) {
        if (count($data) >= 9 && !empty(trim($data[0]))) {
            $rows[] = $data;
        }
    }
    fclose($handle);
}

if (empty($rows)) {
    echo json_encode(['status' => 'error', 'message' => 'No data found']);
    exit;
}

// Clear existing data
sqlsrv_query($conn, "DELETE FROM Sales");
sqlsrv_query($conn, "DELETE FROM Orders");
sqlsrv_query($conn, "DELETE FROM Inventory");
sqlsrv_query($conn, "DELETE FROM Products");

// First, collect unique products with their latest stock
$products = [];
foreach ($rows as $row) {
    $productName = trim($row[2]);
    $category = trim($row[3]);
    $amount = floatval($row[5]);
    $unitsSold = intval($row[4]);
    $stock = intval($row[8]);
    $price = $unitsSold > 0 ? round($amount / $unitsSold, 2) : 0;
    
    if (!isset($products[$productName])) {
        $products[$productName] = [
            'category' => $category,
            'price' => $price,
            'stock' => $stock
        ];
    }
}

// Insert products and inventory
$productIds = [];
foreach ($products as $productName => $product) {
    $sku = 'SKU_' . rand(10000, 99999);
    
    // Insert into Products
    $insertProduct = "INSERT INTO Products (name, sku, category, price) OUTPUT INSERTED.id VALUES (?, ?, ?, ?)";
    $stmt = sqlsrv_query($conn, $insertProduct, [$productName, $sku, $product['category'], $product['price']]);
    
    if ($stmt === false) {
        echo json_encode(['status' => 'error', 'message' => 'Product insert failed: ' . print_r(sqlsrv_errors(), true)]);
        exit;
    }
    
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    $productId = $row['id'];
    $productIds[$productName] = $productId;
    sqlsrv_free_stmt($stmt);
    
    // Insert into Inventory
    $insertInv = "INSERT INTO Inventory (product_id, quantity_available) VALUES (?, ?)";
    $stmt = sqlsrv_query($conn, $insertInv, [$productId, $product['stock']]);
    if ($stmt === false) {
        echo json_encode(['status' => 'error', 'message' => 'Inventory insert failed: ' . print_r(sqlsrv_errors(), true)]);
        exit;
    }
    sqlsrv_free_stmt($stmt);
}

// Insert orders and sales
foreach ($rows as $row) {
    // Parse date
    $dateParts = explode('-', trim($row[0]));
    $formattedDate = $dateParts[2] . '-' . $dateParts[1] . '-' . $dateParts[0];
    
    $customerName = trim($row[1]);
    $productName = trim($row[2]);
    $unitsSold = intval($row[4]);
    $amount = floatval($row[5]);
    $status = trim($row[7]);
    
    if (!isset($productIds[$productName])) {
        continue;
    }
    
    $productId = $productIds[$productName];
    
    // Insert Order
    $insertOrder = "INSERT INTO Orders (customer_name, order_date, total_amount, status) OUTPUT INSERTED.id VALUES (?, ?, ?, ?)";
    $stmt = sqlsrv_query($conn, $insertOrder, [$customerName, $formattedDate, $amount, $status]);
    
    if ($stmt === false) {
        echo json_encode(['status' => 'error', 'message' => 'Order insert failed: ' . print_r(sqlsrv_errors(), true)]);
        exit;
    }
    
    $orderRow = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    $orderId = $orderRow['id'];
    sqlsrv_free_stmt($stmt);
    
    // Insert Sale
    $insertSale = "INSERT INTO Sales (order_id, product_id, quantity_sold) VALUES (?, ?, ?)";
    $stmt = sqlsrv_query($conn, $insertSale, [$orderId, $productId, $unitsSold]);
    if ($stmt === false) {
        echo json_encode(['status' => 'error', 'message' => 'Sale insert failed: ' . print_r(sqlsrv_errors(), true)]);
        exit;
    }
    sqlsrv_free_stmt($stmt);
}

// Get counts
$countQuery = sqlsrv_query($conn, "SELECT COUNT(*) as count FROM Orders");
$countRow = sqlsrv_fetch_array($countQuery, SQLSRV_FETCH_ASSOC);
$totalOrders = $countRow['count'];

$revenueQuery = sqlsrv_query($conn, "SELECT ISNULL(SUM(total_amount), 0) as total FROM Orders");
$revenueRow = sqlsrv_fetch_array($revenueQuery, SQLSRV_FETCH_ASSOC);
$totalRevenue = $revenueRow['total'];

$unitsQuery = sqlsrv_query($conn, "SELECT ISNULL(SUM(quantity_sold), 0) as total FROM Sales");
$unitsRow = sqlsrv_fetch_array($unitsQuery, SQLSRV_FETCH_ASSOC);
$totalUnits = $unitsRow['total'];

$customersQuery = sqlsrv_query($conn, "SELECT COUNT(DISTINCT customer_name) as count FROM Orders");
$customersRow = sqlsrv_fetch_array($customersQuery, SQLSRV_FETCH_ASSOC);
$totalCustomers = $customersRow['count'];

$productsQuery = sqlsrv_query($conn, "SELECT COUNT(*) as count FROM Products");
$productsRow = sqlsrv_fetch_array($productsQuery, SQLSRV_FETCH_ASSOC);
$totalProducts = $productsRow['count'];

$stockQuery = sqlsrv_query($conn, "SELECT ISNULL(SUM(quantity_available), 0) as total FROM Inventory");
$stockRow = sqlsrv_fetch_array($stockQuery, SQLSRV_FETCH_ASSOC);
$totalStock = $stockRow['total'];

sqlsrv_close($conn);
$_SESSION['data_updated'] = true;

echo json_encode([
    'status' => 'success',
    'message' => "Processed " . count($rows) . " records",
    'active_sales' => $totalOrders,
    'customer_count' => $totalCustomers,
    'product_revenue' => '₹' . number_format($totalRevenue),
    'product_sold' => $totalUnits,
    'conversion_rate' => $totalOrders > 0 ? round(($totalOrders / 191886) * 100, 1) . '%' : '0%',
    'revenue_change' => '+12.5%',
    'units_change' => '+8.3%',
    'conv_change' => '+5.2%',
    'performance_score' => min(100, $totalOrders),
    'score_change' => '+7',
    'performance_message' => "✅ Imported $totalOrders orders with $totalUnits units sold",
    'total_visits' => '191,886',
    'visits_change' => 8.5,
    'mobile_visits' => '115,132',
    'website_visits' => '76,754',
    'mobile_percent' => 60,
    'total_products' => $totalProducts,
    'total_orders' => $totalOrders,
    'total_revenue' => $totalRevenue,
    'total_stock' => $totalStock,
    'low_stock_count' => 0,
    'out_of_stock_count' => 0
]);
?>