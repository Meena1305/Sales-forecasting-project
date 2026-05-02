<?php
$serverName = "localhost";
$connectionOptions = [
    "Database" => "user_system",
    "UID" => "sa",
    "PWD" => "123456"
];

$conn = sqlsrv_connect($serverName, $connectionOptions);

if (!$conn) {
    die("Connection failed: " . print_r(sqlsrv_errors(), true));
}

// Delete all data from tables
sqlsrv_query($conn, "DELETE FROM Sales");
sqlsrv_query($conn, "DELETE FROM Orders");
sqlsrv_query($conn, "DELETE FROM Inventory");
sqlsrv_query($conn, "DELETE FROM Products");

echo "All data cleared successfully!<br>";

// Verify
$queries = [
    'Products' => "SELECT COUNT(*) as count FROM Products",
    'Orders' => "SELECT COUNT(*) as count FROM Orders",
    'Sales' => "SELECT COUNT(*) as count FROM Sales"
];

foreach ($queries as $name => $sql) {
    $stmt = sqlsrv_query($conn, $sql);
    $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    echo "$name: " . ($row['count'] ?? 0) . " records<br>";
}

sqlsrv_close($conn);
?>