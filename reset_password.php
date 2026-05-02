<?php
session_start();
include("db_connect.php");

$error = "";
$success = "";
$token_valid = false;
$email = "";
$username = "";

// Check if token is provided
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    // Verify token
    $check_sql = "SELECT email, username FROM users WHERE reset_token = ? AND reset_expiry > GETDATE()";
    $check_stmt = sqlsrv_query($conn, $check_sql, array($token));
    
    if ($check_stmt && $row = sqlsrv_fetch_array($check_stmt, SQLSRV_FETCH_ASSOC)) {
        $token_valid = true;
        $email = $row['email'];
        $username = $row['username'];
    } else {
        $error = "Invalid or expired reset link. Please request a new one.";
    }
}

// Handle password reset
if (isset($_POST['reset_password'])) {
    $token = $_POST['token'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters!";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match!";
    } else {
        // Verify token again
        $check_sql = "SELECT email FROM users WHERE reset_token = ? AND reset_expiry > GETDATE()";
        $check_stmt = sqlsrv_query($conn, $check_sql, array($token));
        
        if ($check_stmt && $row = sqlsrv_fetch_array($check_stmt, SQLSRV_FETCH_ASSOC)) {
            $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
            $update_sql = "UPDATE users SET password = ?, reset_token = NULL, reset_expiry = NULL WHERE reset_token = ?";
            $update_stmt = sqlsrv_query($conn, $update_sql, array($hashedPassword, $token));
            
            if ($update_stmt) {
                $success = true;
            } else {
                $error = "Failed to reset password. Please try again.";
            }
        } else {
            $error = "Invalid or expired reset link.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - InsightSphere</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .reset-container {
            max-width: 450px;
            width: 100%;
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
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
        
        .success-message {
            text-align: center;
        }
        
        .success-message i {
            font-size: 60px;
            color: #10b981;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="logo">
            <div class="logo-icon">IS</div>
        </div>
        
        <?php if($error != ""): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <div class="back-link">
                <a href="index.php">← Back to Login</a>
            </div>
        <?php elseif(isset($success) && $success === true): ?>
            <div class="success-message">
                <i class='bx bx-check-circle'></i>
                <h2>Password Reset Successful!</h2>
                <p style="color: #666; margin: 15px 0;">Your password has been changed successfully.</p>
                <a href="index.php" class="btn" style="display: inline-block; text-decoration: none; margin-top: 10px;">Login Now</a>
            </div>
        <?php elseif($token_valid): ?>
            <h2>Reset Password</h2>
            <p class="subtitle">Create a new password for your account</p>
            
            <form action="reset_password.php" method="POST">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($_GET['token']); ?>">
                <div class="input-box">
                    <input type="password" name="new_password" placeholder="New Password" required>
                    <i class='bx bxs-lock-alt'></i>
                </div>
                <div class="input-box">
                    <input type="password" name="confirm_password" placeholder="Confirm Password" required>
                    <i class='bx bxs-check-circle'></i>
                </div>
                <button type="submit" name="reset_password" class="btn">Reset Password</button>
            </form>
            
            <div class="back-link">
                <a href="index.php">← Back to Login</a>
            </div>
        <?php else: ?>
            <div class="alert alert-error">No reset token provided or invalid link. Please request a password reset from the login page.</div>
            <div class="back-link">
                <a href="index.php">← Back to Login</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>