<?php
session_start();
include("db_connect.php");

$response = ['status' => 'success', 'count' => 0];

// Get unique customer count from Orders table (since Customers table might be empty)
$query = "SELECT COUNT(DISTINCT customer_name) as count FROM Orders";
$stmt = sqlsrv_query($conn, $query);

if ($stmt) {
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    $response['count'] = $row['count'] ?? 0;
    sqlsrv_free_stmt($stmt);
}

echo json_encode($response);
?>