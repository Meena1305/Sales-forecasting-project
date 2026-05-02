<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include("db_connect.php");

echo "<h2>Database Debug Information</h2>";

// Check if tables exist and have data
$tables = ['Products', 'Orders', 'Sales', 'Inventory', 'Customers'];
foreach ($tables as $table) {
    $query = "SELECT COUNT(*) as count FROM $table";
    $stmt = sqlsrv_query($conn, $query);
    if ($stmt) {
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        echo "<p>Table <strong>$table</strong>: " . $row['count'] . " records</p>";
    } else {
        echo "<p style='color:red'>Table <strong>$table</strong>: Error - " . print_r(sqlsrv_errors(), true) . "</p>";
    }
}

echo "<h2>CSV Test Upload</h2>";
echo "<form method='POST' enctype='multipart/form-data'>";
echo "<input type='file' name='test_csv' accept='.csv' required>";
echo "<button type='submit'>Test Upload</button>";
echo "</form>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['test_csv'])) {
    $file = $_FILES['test_csv']['tmp_name'];
    echo "<h3>CSV Content (First 5 rows):</h3>";
    
    if (($handle = fopen($file, "r")) !== FALSE) {
        $rowNum = 0;
        while (($data = fgetcsv($handle)) !== FALSE && $rowNum < 5) {
            echo "<pre>Row " . ($rowNum + 1) . ": ";
            print_r($data);
            echo "</pre>";
            $rowNum++;
        }
        fclose($handle);
    }
}
?>