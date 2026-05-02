<?php
session_start();

// Redirect if already logged in
if (isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

include("db_connect.php");

$error = '';

if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = "Please enter username and password";
    } else {
        // Query using your exact table structure
        $sql = "SELECT * FROM users WHERE username = ?";
        $params = array($username);
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt === false) {
            $error = "Database error. Please try again.";
            error_log(print_r(sqlsrv_errors(), true));
        } elseif ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            // Check password (supports both hashed and plain text)
            $password_valid = false;
            
            if (password_verify($password, $row['password'])) {
                $password_valid = true;
            } elseif ($password === $row['password']) {
                $password_valid = true;
                // Update to hashed password for security
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $update_sql = "UPDATE users SET password = ? WHERE username = ?";
                sqlsrv_query($conn, $update_sql, array($hashed, $username));
            }
            
            if ($password_valid) {
                // Store user information in session (using your column names)
                $_SESSION['username'] = $row['username'];
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['email'] = $row['email'] ?? '';
                
                // Since you don't have full_name column, use username as display name
                // Format username to look like a name (e.g., "amukta_rao" -> "Amukta Rao")
                $formatted_name = ucwords(str_replace(['_', '.'], ' ', $row['username']));
                $_SESSION['full_name'] = $formatted_name;
                
                // Set role based on username (since you don't have role column)
                // You can customize which usernames are admins
                $admin_usernames = ['admin', 'amukta', 'administrator', 'Amukta', 'Admin'];
                if (in_array(strtolower($row['username']), $admin_usernames)) {
                    $_SESSION['role'] = 'Admin';
                } else {
                    $_SESSION['role'] = 'User';
                }
                
                // Log successful login to login_logs table
                $ip = $_SERVER['REMOTE_ADDR'];
                $log_sql = "INSERT INTO login_logs (username, status, login_time) VALUES (?, 'Success', GETDATE())";
                $log_stmt = sqlsrv_query($conn, $log_sql, array($row['username']));
                
                header("Location: indexHome.php");
                exit();
            } else {
                $error = "Invalid password!";
                // Log failed attempt
                $log_sql = "INSERT INTO login_logs (username, status, login_time) VALUES (?, 'Failed', GETDATE())";
                $log_stmt = sqlsrv_query($conn, $log_sql, array($username));
            }
        } else {
            $error = "Username not found!";
            // Log failed attempt
            $log_sql = "INSERT INTO login_logs (username, status, login_time) VALUES (?, 'Failed', GETDATE())";
            $log_stmt = sqlsrv_query($conn, $log_sql, array($username));
        }
    }
}

// Check for logout success message
$logout_message = '';
if (isset($_GET['message']) && $_GET['message'] == 'logout_success') {
    $logout_message = 'Logged out successfully!';
}
?>