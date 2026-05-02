<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (isset($input['csv_data'])) {
        // Create a simplified version to save
        $data_to_save = [
            'active_sales' => $input['csv_data']['active_sales'],
            'customer_count' => $input['csv_data']['customer_count'],
            'product_revenue' => $input['csv_data']['product_revenue'],
            'product_sold' => $input['csv_data']['product_sold'],
            'conversion_rate' => $input['csv_data']['conversion_rate']
        ];
        
        file_put_contents('uploaded_data.json', json_encode($data_to_save));
        echo json_encode(['status' => 'success']);
        exit;
    }
}

echo json_encode(['status' => 'error', 'message' => 'No data provided']);
?>