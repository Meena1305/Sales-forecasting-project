<?php
session_start();
header('Content-Type: application/json');

$serverName = "localhost";
$connectionOptions = [
    "Database" => "user_system",
    "UID" => "sa",
    "PWD" => "123456"
];

$conn = sqlsrv_connect($serverName, $connectionOptions);

if (!$conn) {
    echo json_encode(['status' => 'error', 'message' => 'DB Connection failed: ' . print_r(sqlsrv_errors(), true)]);
    exit;
}

// Check if file was uploaded
if (!isset($_FILES['uploadedFile']) || $_FILES['uploadedFile']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['status' => 'error', 'message' => 'No file uploaded']);
    exit;
}

$file = $_FILES['uploadedFile']['tmp_name'];

// Read CSV
$csvData = array_map('str_getcsv', file($file));
if (count($csvData) < 2) {
    echo json_encode(['status' => 'error', 'message' => 'Empty CSV']);
    exit;
}

$headers = array_shift($csvData);
echo json_encode([
    'status' => 'debug',
    'total_rows' => count($csvData),
    'sample_row' => $csvData[0] ?? [],
    'headers' => $headers
]);
?>