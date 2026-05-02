<?php
// Add this function to your existing save_notification.php
function analyzeAndCreateNotifications($conn) {
    // Get low stock items (assuming you have an inventory table)
    $lowStockQuery = "SELECT ProductName, Stock FROM Inventory WHERE Stock < 10";
    $stmt = sqlsrv_query($conn, $lowStockQuery);
    
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        if ($row['Stock'] == 0) {
            $title = "Out of Stock";
            $message = "{$row['ProductName']} is completely out of stock!";
            $type = "alert";
        } else {
            $title = "Low Stock Warning";
            $message = "{$row['ProductName']} has only {$row['Stock']} units left";
            $type = "stock";
        }
        
        // Insert notification
        $insertQuery = "INSERT INTO Notifications (title, message, type, created_at, is_read) 
                       VALUES (?, ?, ?, GETDATE(), 0)";
        sqlsrv_query($conn, $insertQuery, [$title, $message, $type]);
    }
    
    // Get most sold products
    $topProductsQuery = "SELECT TOP 5 ProductName, SUM(UnitsSold) as TotalSold 
                        FROM SalesData 
                        GROUP BY ProductName 
                        ORDER BY TotalSold DESC";
    $stmt = sqlsrv_query($conn, $topProductsQuery);
    
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $title = "Best Seller Alert";
        $message = "{$row['ProductName']} is trending with {$row['TotalSold']} units sold!";
        $type = "customer";
        
        $insertQuery = "INSERT INTO Notifications (title, message, type, created_at, is_read) 
                       VALUES (?, ?, ?, GETDATE(), 0)";
        sqlsrv_query($conn, $insertQuery, [$title, $message, $type]);
    }
}

// Call this after successful data upload
if ($uploadSuccess) {
    analyzeAndCreateNotifications($conn);
}
?>