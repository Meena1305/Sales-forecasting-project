<?php
session_start();
include("db_connect.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (isset($_SESSION['username'])) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $log_sql = "INSERT INTO login_logs (username, status, ip_address) VALUES (?, ?, ?)";
    $log_params = array($_SESSION['username'], 'Logout', $ip);

    $result = sqlsrv_query($conn, $log_sql, $log_params);

    if ($result === false) {
        die(print_r(sqlsrv_errors(), true)); // 👈 this will show actual error
    }

    session_unset();
    session_destroy();

    header("Location: index.php");
    exit();
}

header("Location: index.php");
exit();
?>