<?php
// get_dashboard_data.php - Returns stored dashboard data

session_start();
header('Content-Type: application/json');

if (isset($_SESSION['dashboard_data'])) {
    $data = $_SESSION['dashboard_data'];
    
    echo json_encode([
        'status' => 'success',
        'active_sales' => $data['active_sales'],
        'customer_count' => $data['customer_count'],
        'product_revenue' => '₹' . number_format($data['total_revenue']),
        'product_sold' => $data['total_units'],
        'conversion_rate' => $data['conversion_rate'] . '%',
        'revenue_change' => '+12.5',
        'units_change' => '+8.3',
        'conv_change' => '+5.2',
        'performance_score' => $data['conversion_rate'],
        'last_updated' => date('Y-m-d H:i:s', $data['timestamp'])
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'No data uploaded yet'
    ]);
}
?>