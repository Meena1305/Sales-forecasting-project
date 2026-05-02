<?php
session_start();
header('Content-Type: application/json');

$serverName = "localhost";
$connectionOptions = [
    "Database" => "user_system",
    "UID" => "sa",
    "PWD" => "123456"
];

$conn = sqlsrv_connect($serverName, $connectionOptions);

if (!$conn) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

// Get statistics from database
$stats = [];

// Get total customers from Orders table
$customerQuery = "SELECT COUNT(DISTINCT customer_name) as total FROM Orders";
$customerStmt = sqlsrv_query($conn, $customerQuery);
if ($customerStmt) {
    $row = sqlsrv_fetch_array($customerStmt, SQLSRV_FETCH_ASSOC);
    $stats['customer_count'] = $row['total'] ?? 0;
}

// Get active buyers (customers with orders in last 30 days)
$activeQuery = "SELECT COUNT(DISTINCT customer_name) as total FROM Orders WHERE order_date >= DATEADD(day, -30, GETDATE())";
$activeStmt = sqlsrv_query($conn, $activeQuery);
if ($activeStmt) {
    $row = sqlsrv_fetch_array($activeStmt, SQLSRV_FETCH_ASSOC);
    $stats['active_buyers'] = $row['total'] ?? 0;
}

// Get total revenue
$revenueQuery = "SELECT ISNULL(SUM(total_amount), 0) as total FROM Orders";
$revenueStmt = sqlsrv_query($conn, $revenueQuery);
if ($revenueStmt) {
    $row = sqlsrv_fetch_array($revenueStmt, SQLSRV_FETCH_ASSOC);
    $totalRevenue = $row['total'] ?? 0;
    $stats['product_revenue'] = '₹' . number_format($totalRevenue);
}

// Get total units sold
$unitsQuery = "SELECT ISNULL(SUM(quantity_sold), 0) as total FROM Sales";
$unitsStmt = sqlsrv_query($conn, $unitsQuery);
if ($unitsStmt) {
    $row = sqlsrv_fetch_array($unitsStmt, SQLSRV_FETCH_ASSOC);
    $stats['product_sold'] = $row['total'] ?? 0;
}

// Get total orders
$ordersQuery = "SELECT COUNT(*) as total FROM Orders";
$ordersStmt = sqlsrv_query($conn, $ordersQuery);
if ($ordersStmt) {
    $row = sqlsrv_fetch_array($ordersStmt, SQLSRV_FETCH_ASSOC);
    $stats['active_sales'] = $row['total'] ?? 0;
}

// Calculate conversion rate - FIXED
$totalVisits = 191886;
$totalOrders = $stats['active_sales'];
if ($totalOrders > 0 && $totalVisits > 0) {
    $conversionRate = round(($totalOrders / $totalVisits) * 100, 1);
} else {
    $conversionRate = 0;
}
$stats['conversion_rate'] = $conversionRate;

// Get monthly sales for chart
$monthlySales = [];
$monthlyQuery = "SELECT 
    FORMAT(order_date, 'yyyy-MM') as month,
    ISNULL(SUM(total_amount), 0) as amount,
    COUNT(*) as orders
    FROM Orders 
    WHERE order_date >= DATEADD(month, -6, GETDATE())
    GROUP BY FORMAT(order_date, 'yyyy-MM')
    ORDER BY month ASC";
$monthlyStmt = sqlsrv_query($conn, $monthlyQuery);
if ($monthlyStmt) {
    while ($row = sqlsrv_fetch_array($monthlyStmt, SQLSRV_FETCH_ASSOC)) {
        $monthlySales[] = $row;
    }
}

$stats['monthly_sales'] = $monthlySales;
$stats['status'] = 'success';
$stats['revenue_change'] = '+12.5%';
$stats['units_change'] = '+8.3%';
$stats['conv_change'] = '+5.2%';

// Fix performance score calculation
$stats['performance_score'] = $conversionRate > 0 ? min(100, round($conversionRate * 1.5)) : 68;
$stats['score_change'] = '+7';
$stats['performance_message'] = $stats['active_sales'] > 0 ? 'Great performance! ' . $stats['active_sales'] . ' records processed successfully.' : 'Upload data to see performance metrics.';
$stats['total_visits'] = number_format($totalVisits);
$stats['visits_change'] = 8.5;
$stats['mobile_visits'] = number_format(115132);
$stats['website_visits'] = number_format(76754);
$stats['mobile_percent'] = 60;

sqlsrv_close($conn);

echo json_encode($stats);
?>