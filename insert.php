<?php
$sql = "INSERT INTO users (username, email, password) VALUES (?, ?, ?)";
$params = [$username, $email, $password];

$stmt = sqlsrv_query($conn, $sql, $params);

print_r(sqlsrv_errors());
?>