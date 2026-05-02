<?php
session_start();
include("db_connect.php");

$response = ['status' => 'success', 'low_stock_products' => []];

// Query to get low stock products from Inventory table
$query = "SELECT 
    p.name as name, 
    ISNULL(i.quantity_available, 0) as stock,
    10 as reorder_level
FROM Products p
LEFT JOIN Inventory i ON p.id = i.product_id
WHERE ISNULL(i.quantity_available, 0) <= 10
ORDER BY ISNULL(i.quantity_available, 0) ASC";

$stmt = sqlsrv_query($conn, $query);

if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $response['low_stock_products'][] = $row;
    }
    sqlsrv_free_stmt($stmt);
}

echo json_encode($response);
?>