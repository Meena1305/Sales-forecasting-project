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

// Update inventory stock based on sales data
$updateSql = "UPDATE Inventory 
              SET quantity_available = (
                  SELECT ISNULL(SUM(s.quantity_sold), 0)
                  FROM Sales s
                  WHERE s.product_id = Inventory.product_id
              )";

$result = sqlsrv_query($conn, $updateSql);

if ($result) {
    echo "Inventory updated successfully!<br><br>";
    
    // Show updated stock
    $checkSql = "SELECT p.name, i.quantity_available 
                 FROM Products p 
                 JOIN Inventory i ON p.id = i.product_id 
                 ORDER BY i.quantity_available DESC";
    $checkStmt = sqlsrv_query($conn, $checkSql);
    
    echo "<table border='1' cellpadding='8' style='border-collapse: collapse;'>";
    echo "<tr style='background: #3b82f6; color: white;'><th>Product Name</th><th>Stock Quantity</th></tr>";
    
    $totalStock = 0;
    while ($row = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC)) {
        echo "<tr>";
        echo "<td>" . $row['name'] . "</td>";
        echo "<td style='text-align: center;'>" . $row['quantity_available'] . "</td>";
        echo "</tr>";
        $totalStock += $row['quantity_available'];
    }
    echo "</table>";
    echo "<br><strong>Total Stock: " . $totalStock . " units</strong>";
    
} else {
    echo "Update failed: " . print_r(sqlsrv_errors(), true);
}

sqlsrv_close($conn);
?>