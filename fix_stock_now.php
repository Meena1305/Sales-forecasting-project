<?php
$serverName = "localhost";
$connectionOptions = ["Database" => "user_system", "UID" => "sa", "PWD" => "123456"];
$conn = sqlsrv_connect($serverName, $connectionOptions);

if (!$conn) {
    die("Connection failed");
}

echo "<h2>Fixing Stock from CSV Data</h2>";

// First, clear existing inventory
sqlsrv_query($conn, "DELETE FROM Inventory");
echo "Cleared existing inventory.<br>";

// Now read the CSV file directly and update stock
$csvFile = 'Data.csv'; // Make sure this file is in the same directory

if (!file_exists($csvFile)) {
    die("CSV file not found: " . $csvFile);
}

$productStock = [];

if (($handle = fopen($csvFile, "r")) !== false) {
    $headers = fgetcsv($handle);
    while (($data = fgetcsv($handle)) !== false) {
        if (count($data) >= 9) {
            $productName = trim($data[2]);
            $stock = intval($data[8]); // Stock column
            $category = trim($data[3]);
            $price = floatval($data[5]) / intval($data[4]); // Calculate unit price
            
            if (!isset($productStock[$productName])) {
                $productStock[$productName] = [
                    'category' => $category,
                    'price' => $price,
                    'stock' => $stock
                ];
            }
        }
    }
    fclose($handle);
}

echo "<h3>Products and Stock from CSV:</h3>";
echo "<table border='1' cellpadding='8'>";
echo "<tr style='background: #3b82f6; color: white;'><th>Product Name</th><th>Category</th><th>Price</th><th>Stock</th></tr>";

foreach ($productStock as $name => $data) {
    echo "<tr>";
    echo "<td>" . $name . "</td>";
    echo "<td>" . $data['category'] . "</td>";
    echo "<td>₹" . number_format($data['price'], 2) . "</td>";
    echo "<td style='text-align: center;'>" . $data['stock'] . "</td>";
    echo "</tr>";
    
    // Update product price if needed
    $updatePrice = "UPDATE Products SET price = ? WHERE name = ?";
    $priceStmt = sqlsrv_query($conn, $updatePrice, [$data['price'], $name]);
    
    // Update inventory
    $checkInv = "SELECT id FROM Inventory WHERE product_id = (SELECT id FROM Products WHERE name = ?)";
    $checkStmt = sqlsrv_query($conn, $checkInv, [$name]);
    $exists = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC);
    
    if ($exists) {
        $updateInv = "UPDATE Inventory SET quantity_available = ? WHERE product_id = (SELECT id FROM Products WHERE name = ?)";
        sqlsrv_query($conn, $updateInv, [$data['stock'], $name]);
    } else {
        $insertInv = "INSERT INTO Inventory (product_id, quantity_available) SELECT id, ? FROM Products WHERE name = ?";
        sqlsrv_query($conn, $insertInv, [$data['stock'], $name]);
    }
}
echo "</table>";

// Get total stock
$totalStockQuery = sqlsrv_query($conn, "SELECT SUM(quantity_available) as total FROM Inventory");
$totalStockRow = sqlsrv_fetch_array($totalStockQuery, SQLSRV_FETCH_ASSOC);
echo "<br><strong>Total Stock in Database: " . ($totalStockRow['total'] ?? 0) . " units</strong>";

sqlsrv_close($conn);
?>