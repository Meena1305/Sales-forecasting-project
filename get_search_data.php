<?php
session_start();
include("db_connect.php");

$response = ['status' => 'success', 'products' => [], 'customers' => [], 'orders' => []];

// Get products
$prodQuery = "SELECT TOP 10 product_id as id, product_name as name, sku, stock_quantity as stock FROM Products";
$prodStmt = sqlsrv_query($conn, $prodQuery);
while ($row = sqlsrv_fetch_array($prodStmt, SQLSRV_FETCH_ASSOC)) {
    $response['products'][] = $row;
}

// Get customers
$custQuery = "SELECT TOP 10 customer_id as id, CONCAT(first_name, ' ', last_name) as name, email, 
              (SELECT COUNT(*) FROM Orders WHERE customer_id = Customers.customer_id) as orders 
              FROM Customers";
$custStmt = sqlsrv_query($conn, $custQuery);
while ($row = sqlsrv_fetch_array($custStmt, SQLSRV_FETCH_ASSOC)) {
    $response['customers'][] = $row;
}

// Get recent orders
$orderQuery = "SELECT TOP 10 order_id, total_amount as amount, status FROM Orders ORDER BY order_date DESC";
$orderStmt = sqlsrv_query($conn, $orderQuery);
while ($row = sqlsrv_fetch_array($orderStmt, SQLSRV_FETCH_ASSOC)) {
    $response['orders'][] = $row;
}

echo json_encode($response);
?>