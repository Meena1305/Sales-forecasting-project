<?php
session_start();
include("db_connect.php");

// Create Notifications table if not exists
function createNotificationsTable($conn) {
    $createTable = "IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='Notifications' AND xtype='U')
                    CREATE TABLE Notifications (
                        id INT IDENTITY(1,1) PRIMARY KEY,
                        title NVARCHAR(255),
                        message NVARCHAR(MAX),
                        type NVARCHAR(50),
                        created_at DATETIME DEFAULT GETDATE(),
                        is_read INT DEFAULT 0
                    )";
    return sqlsrv_query($conn, $createTable);
}

// Save notification to database
function saveNotification($conn, $title, $message, $type) {
    // Check if similar notification already exists in last 24 hours
    $checkQuery = "SELECT COUNT(*) as count 
                   FROM Notifications 
                   WHERE title = ? 
                   AND created_at >= DATEADD(day, -1, GETDATE())";
    $checkStmt = sqlsrv_query($conn, $checkQuery, [$title]);
    
    if ($checkStmt) {
        $row = sqlsrv_fetch_array($checkStmt, SQLSRV_FETCH_ASSOC);
        if ($row['count'] > 0) {
            sqlsrv_free_stmt($checkStmt);
            return false;
        }
        sqlsrv_free_stmt($checkStmt);
    }
    
    // Insert new notification
    $query = "INSERT INTO Notifications (title, message, type, created_at, is_read) 
              VALUES (?, ?, ?, GETDATE(), 0)";
    $params = [$title, $message, $type];
    $stmt = sqlsrv_query($conn, $query, $params);
    
    return $stmt !== false;
}

// Main execution when data is uploaded
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['uploadFile'])) {
    
    // Create table if not exists
    createNotificationsTable($conn);
    
    $csvFile = $_FILES['uploadFile']['tmp_name'];
    $handle = fopen($csvFile, "r");
    
    if ($handle !== false) {
        // Skip header row
        $header = fgetcsv($handle);
        
        // Arrays to store data for analysis
        $productSales = [];
        $productStock = [];
        $notificationCount = 0;
        
        while (($data = fgetcsv($handle)) !== false) {
            // For Data.csv format: Order Date, Customer Name, Product Name, Category, Units Sold, Amount, Source, Status, Stock
            if (count($data) >= 9) {
                $productName = trim($data[2]);
                $unitsSold = intval($data[4]);
                $stock = intval($data[8]);
                $amount = floatval($data[5]);
                
                // Track sales for most sold analysis
                if (!isset($productSales[$productName])) {
                    $productSales[$productName] = 0;
                }
                $productSales[$productName] += $unitsSold;
                
                // Track stock levels
                if (!isset($productStock[$productName])) {
                    $productStock[$productName] = $stock;
                }
            }
        }
        fclose($handle);
        
        // 1. Create LOW STOCK and OUT OF STOCK notifications
        foreach ($productStock as $productName => $stock) {
            if ($stock == 0) {
                if (saveNotification($conn, "❌ Out of Stock Alert", "{$productName} is completely out of stock! Please restock immediately.", "alert")) {
                    $notificationCount++;
                }
            } elseif ($stock <= 5) {
                if (saveNotification($conn, "⚠️ Critical Low Stock", "{$productName} has only {$stock} units remaining! Immediate restock needed.", "alert")) {
                    $notificationCount++;
                }
            } elseif ($stock <= 10) {
                if (saveNotification($conn, "📦 Low Stock Warning", "{$productName} has only {$stock} units left. Consider restocking soon.", "stock")) {
                    $notificationCount++;
                }
            }
        }
        
        // 2. Create TOP SELLING PRODUCTS notifications
        if (!empty($productSales)) {
            arsort($productSales); // Sort by sales (highest first)
            $topProducts = array_slice($productSales, 0, 5, true);
            $rank = 1;
            
            foreach ($topProducts as $productName => $totalSold) {
                if ($rank == 1) {
                    $title = "🏆 Top Selling Product";
                    $message = "{$productName} is the BEST SELLER with {$totalSold} units sold!";
                    $type = "customer";
                } else {
                    $title = "📈 Top #{$rank} Selling Product";
                    $message = "{$productName} ranks #{$rank} with {$totalSold} units sold";
                    $type = "info";
                }
                if (saveNotification($conn, $title, $message, $type)) {
                    $notificationCount++;
                }
                $rank++;
            }
        }
        
        // 3. Summary notification
        saveNotification($conn, "✅ Upload Complete", "File processed successfully. Created {$notificationCount} notifications including stock alerts and top products.", "info");
        
        $_SESSION['upload_success'] = "Data uploaded successfully! {$notificationCount} notifications created.";
        
        // Redirect back to dashboard
        header("Location: indexHome.php");
        exit();
    }
}

// If accessed directly without file upload
header("Location: indexHome.php");
exit();
?>