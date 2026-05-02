<?php
session_start();
header('Content-Type: application/json');
include("db_connect.php");

if (!isset($_SESSION['username'])) {
    echo json_encode(['count' => 0]);
    exit();
}

$count = 0;
if ($conn) {
    $query = "SELECT COUNT(*) as count FROM Notifications WHERE is_read = 0";
    $stmt = sqlsrv_query($conn, $query);
    if ($stmt) {
        $result = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        $count = $result['count'] ?? 0;
        sqlsrv_free_stmt($stmt);
    }
}

echo json_encode(['count' => $count]);
?>