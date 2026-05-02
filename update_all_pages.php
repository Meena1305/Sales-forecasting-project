<?php
// update_all_pages.php
// Stores a flag that data has been updated

session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['data_updated'] = true;
    $_SESSION['update_timestamp'] = time();
    
    echo json_encode(['status' => 'success']);
    exit;
}

// GET request - check if data was updated
if (isset($_SESSION['data_updated']) && $_SESSION['data_updated'] === true) {
    echo json_encode(['updated' => true, 'timestamp' => $_SESSION['update_timestamp']]);
    // Clear the flag after checking
    $_SESSION['data_updated'] = false;
} else {
    echo json_encode(['updated' => false]);
}
?>