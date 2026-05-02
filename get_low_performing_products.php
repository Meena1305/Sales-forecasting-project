<?php
session_start();
include("db_connect.php");

$response = ['status' => 'success', 'products' => []];

// Query to get products with low sales
$query = "SELECT TOP 5 
    p.name as name, 
    ISNULL(SUM(s.quantity_sold), 0) as sales_count
FROM Products p
LEFT JOIN Sales s ON p.id = s.product_id
LEFT JOIN Orders o ON s.order_id = o.id AND o.order_date >= DATEADD(month, -1, GETDATE())
GROUP BY p.name
HAVING ISNULL(SUM(s.quantity_sold), 0) < 5
ORDER BY sales_count ASC";

$stmt = sqlsrv_query($conn, $query);

if ($stmt) {
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $response['products'][] = $row;
    }
    sqlsrv_free_stmt($stmt);
}

echo json_encode($response);
?>