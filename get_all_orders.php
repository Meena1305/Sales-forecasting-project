<?php
// Suppress warnings for this file only - prevents undefined variable notices
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);

header('Content-Type: application/json');
session_start();

$response = ['success' => false, 'orders' => [], 'error' => null];

$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Default to using sample data
$useSampleData = true;
$dbConnection = null;

// Try to connect to database
if (file_exists("db_connect.php")) {
    require_once("db_connect.php");
    
    // Check if connection variable exists and is valid
    if (isset($conn) && !is_null($conn) && $conn !== false) {
        $dbConnection = $conn;
        $useSampleData = false;
    }
}

// If we have a valid database connection, fetch real data
if (!$useSampleData && $dbConnection !== null) {
    try {
        // Build the query using prepared statements
        $query = "SELECT TOP 50 
            o.order_id,
            ISNULL(c.name, 'Guest') as customer_name,
            o.total_amount,
            o.order_date,
            o.status
        FROM Orders o
        LEFT JOIN Customers c ON o.customer_id = c.customer_id
        WHERE YEAR(o.order_date) = ?";
        
        $params = array($year);
        
        if ($filter != 'all') {
            $days = 30;
            if ($filter == 'last7') $days = 7;
            if ($filter == 'last30') $days = 30;
            if ($filter == 'last90') $days = 90;
            if ($filter == 'lastYear') $days = 365;
            $query .= " AND o.order_date >= DATEADD(day, -?, GETDATE())";
            $params[] = $days;
        }
        
        $query .= " ORDER BY o.order_date DESC";
        
        $stmt = sqlsrv_query($dbConnection, $query, $params);
        
        if ($stmt !== false) {
            while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $orderDate = $row['order_date'];
                $dateStr = '';
                
                if (is_object($orderDate) && method_exists($orderDate, 'format')) {
                    $dateStr = $orderDate->format('Y-m-d H:i:s');
                } else {
                    $dateStr = date('Y-m-d H:i:s', strtotime($orderDate));
                }
                
                $response['orders'][] = [
                    'order_id' => $row['order_id'],
                    'customer_name' => $row['customer_name'],
                    'total_amount' => (float)$row['total_amount'],
                    'order_date' => $dateStr,
                    'status' => $row['status']
                ];
            }
            $response['success'] = true;
            sqlsrv_free_stmt($stmt);
        } else {
            // Query failed, use sample data
            $response['orders'] = getSampleOrdersData($filter);
            $response['success'] = true;
            $response['warning'] = 'Database query failed, using sample data';
        }
        
        // Close connection if it exists
        if ($dbConnection !== null) {
            @sqlsrv_close($dbConnection);
        }
        
    } catch (Exception $e) {
        // Error occurred, use sample data
        $response['orders'] = getSampleOrdersData($filter);
        $response['success'] = true;
        $response['warning'] = 'Database error: ' . $e->getMessage();
    }
} else {
    // No database connection, use sample data
    $response['orders'] = getSampleOrdersData($filter);
    $response['success'] = true;
    $response['warning'] = 'No database connection available, showing sample data';
}

// Send response
echo json_encode($response);
exit;

/**
 * Get sample orders for demonstration
 */
function getSampleOrdersData($filter = 'all') {
    $allOrders = [
        ['order_id' => 1001, 'customer_name' => 'John Doe', 'total_amount' => 299.99, 'order_date' => '2024-06-15 10:30:00', 'status' => 'Completed'],
        ['order_id' => 1002, 'customer_name' => 'Jane Smith', 'total_amount' => 149.50, 'order_date' => '2024-06-14 14:20:00', 'status' => 'Completed'],
        ['order_id' => 1003, 'customer_name' => 'Mike Johnson', 'total_amount' => 89.99, 'order_date' => '2024-06-14 09:15:00', 'status' => 'Processing'],
        ['order_id' => 1004, 'customer_name' => 'Sarah Williams', 'total_amount' => 459.00, 'order_date' => '2024-06-13 16:45:00', 'status' => 'Shipped'],
        ['order_id' => 1005, 'customer_name' => 'David Brown', 'total_amount' => 34.99, 'order_date' => '2024-06-13 11:00:00', 'status' => 'Completed'],
        ['order_id' => 1006, 'customer_name' => 'Emily Davis', 'total_amount' => 199.99, 'order_date' => '2024-06-12 13:30:00', 'status' => 'Pending'],
        ['order_id' => 1007, 'customer_name' => 'Chris Wilson', 'total_amount' => 74.50, 'order_date' => '2024-06-12 08:45:00', 'status' => 'Completed'],
        ['order_id' => 1008, 'customer_name' => 'Jessica Martinez', 'total_amount' => 129.99, 'order_date' => '2024-06-11 15:20:00', 'status' => 'Processing'],
        ['order_id' => 1009, 'customer_name' => 'Ryan Taylor', 'total_amount' => 549.00, 'order_date' => '2024-06-11 10:00:00', 'status' => 'Shipped'],
        ['order_id' => 1010, 'customer_name' => 'Amanda Anderson', 'total_amount' => 45.99, 'order_date' => '2024-06-10 12:15:00', 'status' => 'Completed'],
        ['order_id' => 1011, 'customer_name' => 'Robert Lee', 'total_amount' => 89.99, 'order_date' => '2024-06-09 09:30:00', 'status' => 'Completed'],
        ['order_id' => 1012, 'customer_name' => 'Maria Garcia', 'total_amount' => 234.50, 'order_date' => '2024-06-08 14:45:00', 'status' => 'Shipped'],
        ['order_id' => 1013, 'customer_name' => 'James Wilson', 'total_amount' => 67.99, 'order_date' => '2024-06-07 11:20:00', 'status' => 'Completed'],
        ['order_id' => 1014, 'customer_name' => 'Patricia Brown', 'total_amount' => 189.00, 'order_date' => '2024-06-06 16:10:00', 'status' => 'Processing'],
        ['order_id' => 1015, 'customer_name' => 'Michael Chen', 'total_amount' => 299.99, 'order_date' => '2024-06-05 13:40:00', 'status' => 'Completed']
    ];
    
    // Apply filter if needed
    if ($filter != 'all') {
        $days = 30;
        if ($filter == 'last7') $days = 7;
        if ($filter == 'last30') $days = 30;
        if ($filter == 'last90') $days = 90;
        if ($filter == 'lastYear') $days = 365;
        
        $cutoff = new DateTime("-$days days");
        $filtered = [];
        foreach ($allOrders as $order) {
            $orderDate = new DateTime($order['order_date']);
            if ($orderDate >= $cutoff) {
                $filtered[] = $order;
            }
        }
        return $filtered;
    }
    
    return $allOrders;
}
?>