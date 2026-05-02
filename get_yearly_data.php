<?php
header('Content-Type: application/json');
session_start();
include("db_connect.php");

$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Get monthly data
$monthlyData = [];
for ($m = 1; $m <= 12; $m++) {
    $monthQuery = "SELECT COALESCE(SUM(total_amount), 0) as revenue 
                   FROM Orders 
                   WHERE YEAR(order_date) = ? AND MONTH(order_date) = ?";
    $monthStmt = sqlsrv_query($conn, $monthQuery, array($year, $m));
    if ($monthStmt !== false) {
        $monthData = sqlsrv_fetch_array($monthStmt, SQLSRV_FETCH_ASSOC);
        $monthlyData[] = $monthData['revenue'] ?? 0;
    } else {
        $monthlyData[] = 0;
    }
}

// Get total revenue
$revenueQuery = "SELECT COALESCE(SUM(total_amount), 0) as total FROM Orders WHERE YEAR(order_date) = ?";
$revenueStmt = sqlsrv_query($conn, $revenueQuery, array($year));
$revenueData = sqlsrv_fetch_array($revenueStmt, SQLSRV_FETCH_ASSOC);
$totalRevenue = $revenueData['total'] ?? 0;

// Get total orders
$ordersQuery = "SELECT COUNT(*) as total FROM Orders WHERE YEAR(order_date) = ?";
$ordersStmt = sqlsrv_query($conn, $ordersQuery, array($year));
$ordersData = sqlsrv_fetch_array($ordersStmt, SQLSRV_FETCH_ASSOC);
$totalOrders = $ordersData['total'] ?? 0;

// Get previous year revenue for comparison
$prevYear = $year - 1;
$prevRevenueQuery = "SELECT COALESCE(SUM(total_amount), 0) as total FROM Orders WHERE YEAR(order_date) = ?";
$prevStmt = sqlsrv_query($conn, $prevRevenueQuery, array($prevYear));
$prevData = sqlsrv_fetch_array($prevStmt, SQLSRV_FETCH_ASSOC);
$prevRevenue = $prevData['total'] ?? 0;
$growthRate = $prevRevenue > 0 ? round((($totalRevenue - $prevRevenue) / $prevRevenue) * 100, 1) : 0;

// Calculate average order value
$avgOrder = $totalOrders > 0 ? round($totalRevenue / $totalOrders, 0) : 0;

echo json_encode([
    'success' => true,
    'year' => $year,
    'prev_year' => $prevYear,
    'monthly_data' => $monthlyData,
    'total_revenue' => $totalRevenue,
    'total_orders' => $totalOrders,
    'avg_order' => $avgOrder,
    'growth_rate' => $growthRate
]);
?>