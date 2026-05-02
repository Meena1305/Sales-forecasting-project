<?php
session_start();
include("db_connect.php");

$error = '';
$success = '';

if (isset($_POST['register'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validation
    $errors = [];
    
    if (empty($username)) {
        $errors[] = "Username is required";
    } elseif (strlen($username) < 3) {
        $errors[] = "Username must be at least 3 characters";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    if (empty($errors)) {
        // Check if username already exists
        $check_sql = "SELECT COUNT(*) as count FROM users WHERE username = ?";
        $check_stmt = sqlsrv_query($conn, $check_sql, array($username));
        
        if ($check_stmt) {
            $check_row = sqlsrv_fetch_array($check_stmt, SQLSRV_FETCH_ASSOC);
            if ($check_row['count'] > 0) {
                $error = "Username already exists! Please choose another.";
            } else {
                // Check if email already exists
                $check_email_sql = "SELECT COUNT(*) as count FROM users WHERE email = ?";
                $check_email_stmt = sqlsrv_query($conn, $check_email_sql, array($email));
                
                if ($check_email_stmt) {
                    $email_row = sqlsrv_fetch_array($check_email_stmt, SQLSRV_FETCH_ASSOC);
                    if ($email_row['count'] > 0) {
                        $error = "Email already registered! Please use another email.";
                    } else {
                        // Hash password
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        
                        // Insert new user
                        $sql = "INSERT INTO users (username, email, password) VALUES (?, ?, ?)";
                        $params = array($username, $email, $hashedPassword);
                        $stmt = sqlsrv_query($conn, $sql, $params);
                        
                        if ($stmt) {
                            // Redirect to login page with success message
                            header("Location: login.php?registered=success");
                            exit();
                        } else {
                            $error = "Registration failed. Please try again.";
                            error_log(print_r(sqlsrv_errors(), true));
                        }
                    }
                }
            }
        } else {
            $error = "Database error. Please try again.";
        }
    } else {
        $error = implode("<br>", $errors);
    }
}
?>