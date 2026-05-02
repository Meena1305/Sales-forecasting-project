<?php
session_start();
include("db_connect.php");

// Create test notifications directly
function addTestNotification($conn, $title, $message, $type) {
    $query = "INSERT INTO Notifications (title, message, type, created_at, is_read) 
              VALUES (?, ?, ?, GETDATE(), 0)";
    $params = array($title, $message, $type);
    $stmt = sqlsrv_query($conn, $query, $params);
    return $stmt !== false;
}

// Create Notifications table if not exists
$createTable = "IF NOT EXISTS (SELECT * FROM sysobjects WHERE name='Notifications' AND xtype='U')
                CREATE TABLE Notifications (
                    id INT IDENTITY(1,1) PRIMARY KEY,
                    title NVARCHAR(255),
                    message NVARCHAR(MAX),
                    type NVARCHAR(50),
                    created_at DATETIME DEFAULT GETDATE(),
                    is_read INT DEFAULT 0
                )";
sqlsrv_query($conn, $createTable);

// Add test notifications
addTestNotification($conn, "🏆 Test Top Product", "Wireless Headphones is the best seller with 150 units sold!", "customer");
addTestNotification($conn, "⚠️ Low Stock Alert", "Smart Speaker has only 3 units left!", "stock");
addTestNotification($conn, "❌ Out of Stock", "Gaming Mouse is out of stock!", "alert");
addTestNotification($conn, "✅ Upload Complete", "Your data has been uploaded successfully!", "info");

echo "<h2>Test Notifications Created!</h2>";
echo "<p>4 test notifications have been added to the database.</p>";
echo "<a href='notifications.php'>View Notifications →</a>";
?>