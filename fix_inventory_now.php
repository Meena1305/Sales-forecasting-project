<?php
$serverName = "localhost";
$connectionOptions = ["Database" => "user_system", "UID" => "sa", "PWD" => "123456"];
$conn = sqlsrv_connect($serverName, $connectionOptions);

if (!$conn) {
    die("Connection failed");
}

// First, check if Inventory table has records
$checkInv = sqlsrv_query($conn, "SELECT COUNT(*) as count FROM Inventory");
$invCount = sqlsrv_fetch_array($checkInv, SQLSRV_FETCH_ASSOC);

if ($invCount['count'] == 0) {
    // Create inventory records for all products
    echo "No inventory records found. Creating inventory for all products...<br>";
    $createInv = sqlsrv_query($conn, "INSERT INTO Inventory (product_id, quantity_available) SELECT id, 0 FROM Products");
    if ($createInv) echo "Inventory records created.<br>";
}

// Now update stock based on sales
$updateSql = "UPDATE i 
              SET quantity_available = (
                  SELECT ISNULL(SUM(s.quantity_sold), 0)
                  FROM Sales s
                  WHERE s.product_id = i.product_id
              )
              FROM Inventory i";

$result = sqlsrv_query($conn, $updateSql);

if ($result) {
    echo "Inventory updated successfully!<br><br>";
    
    // Show updated stock
    $checkSql = "SELECT p.name, i.quantity_available 
                 FROM Products p 
                 JOIN Inventory i ON p.id = i.product_id 
                 WHERE i.quantity_available > 0
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