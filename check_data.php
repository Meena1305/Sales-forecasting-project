<?php
session_start();
include("db_connect.php");

echo "<h2>Database Diagnostic Tool</h2>";

if (!$conn) {
    echo "<p style='color:red'>Database connection failed!</p>";
    print_r(sqlsrv_errors());
    exit;
}

echo "<p style='color:green'>Database connected successfully!</p>";

// Check Products table
$productsQuery = "SELECT * FROM Products";
$productsStmt = sqlsrv_query($conn, $productsQuery);
echo "<h3>Products Table:</h3>";
if ($productsStmt === false) {
    echo "<p style='color:red'>Error: " . print_r(sqlsrv_errors(), true) . "</p>";
} else {
    $productCount = 0;
    while ($row = sqlsrv_fetch_array($productsStmt, SQLSRV_FETCH_ASSOC)) {
        $productCount++;
        echo "<pre>";
        print_r($row);
        echo "</pre>";
    }
    echo "<p>Total Products: $productCount</p>";
    sqlsrv_free_stmt($productsStmt);
}

// Check Inventory table
$inventoryQuery = "SELECT * FROM Inventory";
$inventoryStmt = sqlsrv_query($conn, $inventoryQuery);
echo "<h3>Inventory Table:</h3>";
if ($inventoryStmt === false) {
    echo "<p style='color:red'>Error: " . print_r(sqlsrv_errors(), true) . "</p>";
} else {
    $inventoryCount = 0;
    while ($row = sqlsrv_fetch_array($inventoryStmt, SQLSRV_FETCH_ASSOC)) {
        $inventoryCount++;
        echo "<pre>";
        print_r($row);
        echo "</pre>";
    }
    echo "<p>Total Inventory Records: $inventoryCount</p>";
    sqlsrv_free_stmt($inventoryStmt);
}

// Check Orders table
$ordersQuery = "SELECT * FROM Orders";
$ordersStmt = sqlsrv_query($conn, $ordersQuery);
echo "<h3>Orders Table:</h3>";
if ($ordersStmt === false) {
    echo "<p style='color:red'>Error: " . print_r(sqlsrv_errors(), true) . "</p>";
} else {
    $orderCount = 0;
    while ($row = sqlsrv_fetch_array($ordersStmt, SQLSRV_FETCH_ASSOC)) {
        $orderCount++;
        echo "<pre>";
        print_r($row);
        echo "</pre>";
    }
    echo "<p>Total Orders: $orderCount</p>";
    sqlsrv_free_stmt($ordersStmt);
}

// Check Sales table
$salesQuery = "SELECT * FROM Sales";
$salesStmt = sqlsrv_query($conn, $salesQuery);
echo "<h3>Sales Table:</h3>";
if ($salesStmt === false) {
    echo "<p style='color:red'>Error: " . print_r(sqlsrv_errors(), true) . "</p>";
} else {
    $salesCount = 0;
    while ($row = sqlsrv_fetch_array($salesStmt, SQLSRV_FETCH_ASSOC)) {
        $salesCount++;
        echo "<pre>";
        print_r($row);
        echo "</pre>";
    }
    echo "<p>Total Sales Records: $salesCount</p>";
    sqlsrv_free_stmt($salesStmt);
}

sqlsrv_close($conn);
?>