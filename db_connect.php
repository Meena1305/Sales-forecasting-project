<?php
$serverName = "localhost";
$connectionOptions = [
    "Database" => "user_system",
    "UID" => "sa",
    "PWD" => "123456",
    "CharacterSet" => "UTF-8",
    "ReturnDatesAsStrings" => true  // This helps with date handling
];

// Connect to SQL Server
$conn = sqlsrv_connect($serverName, $connectionOptions);

// Check connection
if ($conn === false) {
    die("<pre>Connection failed: " . print_r(sqlsrv_errors(), true) . "</pre>");
}

// Set connection as global variable
$GLOBALS['conn'] = $conn;
?>