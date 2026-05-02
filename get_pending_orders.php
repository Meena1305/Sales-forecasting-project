<?php
session_start();
include("db_connect.php");

$response = ['status' => 'success', 'count' => 0];

// Count pending orders from Orders table
$query = "SELECT COUNT(*) as count FROM Orders WHERE status = 'Pending'";
$stmt = sqlsrv_query($conn, $query);

if ($stmt) {
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    $response['count'] = $row['count'] ?? 0;
    sqlsrv_free_stmt($stmt);
}

echo json_encode($response);
?>