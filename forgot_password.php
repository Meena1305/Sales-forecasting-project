<?php
session_start();
include("db_connect.php");

$error = "";
$success = "";

// Handle forgot password form submission
if (isset($_POST['send_reset_link'])) {
    $email = trim($_POST['email']);
    
    if (empty($email)) {
        $error = "Please enter your email address";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format!";
    } else {
        // Check if email exists
        $check_sql = "SELECT username FROM users WHERE email = ?";
        $check_stmt = sqlsrv_query($conn, $check_sql, array($email));
        
        if ($check_stmt === false) {
            $error = "Database error: " . print_r(sqlsrv_errors(), true);
        } elseif ($row = sqlsrv_fetch_array($check_stmt, SQLSRV_FETCH_ASSOC)) {
            
            // FIRST: Check if reset_token column exists, if not add it
            $check_column = "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'users' AND COLUMN_NAME = 'reset_token'";
            $col_check = sqlsrv_query($conn, $check_column);
            
            if ($col_check === false) {
                $error = "Failed to check database structure";
            } elseif (!sqlsrv_fetch_array($col_check, SQLSRV_FETCH_ASSOC)) {
                // Column doesn't exist - add it
                $alter_sql = "ALTER TABLE users ADD reset_token NVARCHAR(255), reset_expiry DATETIME";
                $alter_stmt = sqlsrv_query($conn, $alter_sql);
                if ($alter_stmt === false) {
                    $error = "Failed to add reset columns to database. Please run this SQL manually: ALTER TABLE users ADD reset_token NVARCHAR(255), reset_expiry DATETIME";
                    die($error);
                }
            }
            
            // Generate reset token
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Store token in database
            $token_sql = "UPDATE users SET reset_token = ?, reset_expiry = ? WHERE email = ?";
            $token_stmt = sqlsrv_query($conn, $token_sql, array($token, $expiry, $email));
            
            if ($token_stmt === false) {
                $error = "Database update failed: " . print_r(sqlsrv_errors(), true);
            } else {
                $reset_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=" . $token;
                $success = "✅ Password reset link generated! <br><br> 
                            <strong>Click this link to reset your password:</strong><br>
                            <a href='$reset_link' style='color: #059669; text-decoration: underline; word-break: break-all;'>$reset_link</a>
                            <br><br>
                            <small>(In production, this would be sent to your email: " . htmlspecialchars($email) . ")</small>";
            }
        } else {
            $error = "No account found with this email address!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - InsightSphere</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(90deg, #e2e2e2, #c9d6ff);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .forgot-container {
            max-width: 450px;
            width: 100%;
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 24px;
        }
        
        h2 {
            text-align: center;
            margin-bottom: 10px;
            color: #333;
        }
        
        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .input-box {
            position: relative;
            margin-bottom: 20px;
        }
        
        .input-box input {
            width: 100%;
            padding: 12px 40px 12px 40px;
            border: 1px solid #ddd;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .input-box input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }
        
        .input-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }
        
        .btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .alert {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #dc2626;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #059669;
        }
        
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        
        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
        }
        
        .back-link a:hover {
            text-decoration: underline;
        }
        
        .test-accounts {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .test-accounts p {
            font-size: 12px;
            color: #999;
            margin-bottom: 8px;
        }
        
        .test-accounts ul {
            font-size: 12px;
            color: #666;
            list-style: none;
        }
        
        .test-accounts li {
            padding: 3px 0;
        }
    </style>
</head>
<body>
    <div class="forgot-container">
        <div class="logo">
            <div class="logo-icon">IS</div>
        </div>
        
        <?php if($error != ""): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if($success != ""): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
            <div class="back-link">
                <a href="index.php">← Back to Login</a>
            </div>
        <?php else: ?>
            <h2>Find Your Account</h2>
            <p class="subtitle">Please enter your email address to search for your account.</p>
            
            <form action="forgot_password.php" method="POST">
                <div class="input-box">
                    <input type="email" name="email" placeholder="Email Address" required>
                    <i class='bx bxs-envelope'></i>
                </div>
                <button type="submit" name="send_reset_link" class="btn">Send Reset Link</button>
            </form>
            
            <div class="back-link">
                <a href="index.php">← Back to Login</a>
            </div>
            
            <!-- Test accounts info -->
            <div class="test-accounts">
                <p>📧 Test accounts in your database:</p>
                <ul>
                    <?php
                    // Show existing emails to help testing
                    $test_sql = "SELECT email FROM users";
                    $test_stmt = sqlsrv_query($conn, $test_sql);
                    if ($test_stmt) {
                        while ($test_row = sqlsrv_fetch_array($test_stmt, SQLSRV_FETCH_ASSOC)) {
                            echo "<li>• " . htmlspecialchars($test_row['email']) . "</li>";
                        }
                    } else {
                        echo "<li>• (No users found in database)</li>";
                    }
                    ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>