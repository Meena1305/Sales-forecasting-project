<?php
header('Content-Type: application/json');
include("db_connect.php");

$page = isset($_GET['page']) ? $_GET['page'] : '';

$response = ["status" => "error", "message" => "Invalid page"];

switch($page) {
    case 'orders':
        $query = "SELECT TOP 50 order_id, customer_name, FORMAT(order_date, 'yyyy-MM-dd') as order_date, total_amount, status FROM Orders ORDER BY order_date DESC";
        $stmt = sqlsrv_query($conn, $query);
        $records = [];
        while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $records[] = $row;
        }
        $response = ["status" => "success", "title" => "Orders", "records" => $records];
        break;
        
    case 'products':
        $query = "SELECT TOP 50 product_id, name, price, category, sku FROM Products ORDER BY product_id DESC";
        $stmt = sqlsrv_query($conn, $query);
        $records = [];
        while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $records[] = $row;
        }
        $response = ["status" => "success", "title" => "Products", "records" => $records];
        break;
        
    case 'customers':
        $query = "SELECT DISTINCT customer_name, COUNT(*) as order_count, SUM(total_amount) as total_spent FROM Orders GROUP BY customer_name ORDER BY total_spent DESC";
        $stmt = sqlsrv_query($conn, $query);
        $records = [];
        while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $records[] = $row;
        }
        $response = ["status" => "success", "title" => "Customers", "records" => $records];
        break;
        
    case 'inventory':
        $query = "SELECT TOP 50 product_id, name, sku, price, category FROM Products ORDER BY product_id";
        $stmt = sqlsrv_query($conn, $query);
        $records = [];
        while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $records[] = $row;
        }
        $response = ["status" => "success", "title" => "Inventory", "records" => $records];
        break;
        
    case 'reports':
        $query = "SELECT 
            COUNT(*) as total_orders,
            ISNULL(SUM(total_amount), 0) as total_revenue,
            AVG(total_amount) as avg_order_value
            FROM Orders";
        $stmt = sqlsrv_query($conn, $query);
        $summary = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        $response = ["status" => "success", "title" => "Reports", "records" => [$summary]];
        break;
        
    case 'sales':
        $query = "SELECT TOP 50 s.sale_id, o.customer_name, p.name as product_name, s.quantity_sold, s.sale_date 
                  FROM Sales s 
                  JOIN Orders o ON s.order_id = o.order_id 
                  JOIN Products p ON s.product_id = p.product_id 
                  ORDER BY s.sale_date DESC";
        $stmt = sqlsrv_query($conn, $query);
        $records = [];
        while($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $records[] = $row;
        }
        $response = ["status" => "success", "title" => "Sales", "records" => $records];
        break;
        
    default:
        $response = ["status" => "error", "message" => "Page not found"];
}

echo json_encode($response);
?>