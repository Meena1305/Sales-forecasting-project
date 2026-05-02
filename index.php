<?php
session_start();

// Redirect if already logged in
if (isset($_SESSION['username'])) {
    header("Location: indexHome.php");
    exit();
}

include("db_connect.php");

$error = "";
$success = "";

// Handle Login
if (isset($_POST['login'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    if ($username != "" && $password != "") {
        $sql = "SELECT * FROM users WHERE username = ? OR email = ?";
        $params = array($username, $username);
        $stmt = sqlsrv_query($conn, $sql, $params);
        
        if ($stmt === false) {
            die(print_r(sqlsrv_errors(), true));
        }
        
        if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $password_valid = false;
            
            if (password_verify($password, $row['password'])) {
                $password_valid = true;
            } elseif ($password === $row['password']) {
                $password_valid = true;
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $update_sql = "UPDATE users SET password = ? WHERE username = ?";
                sqlsrv_query($conn, $update_sql, array($hashed, $row['username']));
            }
            
            if ($password_valid) {
                $_SESSION['username'] = $row['username'];
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['email'] = $row['email'] ?? '';
                $_SESSION['user_type'] = $row['user_type'] ?? 'local';
                
                // Format username to display name
                $formatted_name = ucwords(str_replace(['_', '.'], ' ', $row['username']));
                $_SESSION['full_name'] = $formatted_name;
                
                // Set role based on username
                $admin_usernames = ['admin', 'amukta', 'administrator', 'Amukta', 'Admin'];
                $_SESSION['role'] = in_array(strtolower($row['username']), $admin_usernames) ? 'Admin' : 'User';
                
                // Log login
                $log_sql = "INSERT INTO login_logs (username, status, login_time) VALUES (?, 'Success', GETDATE())";
                sqlsrv_query($conn, $log_sql, array($row['username']));
                
                header("Location: indexHome.php");
                exit();
            } else {
                $error = "Invalid password!";
                $log_sql = "INSERT INTO login_logs (username, status, login_time) VALUES (?, 'Failed', GETDATE())";
                sqlsrv_query($conn, $log_sql, array($username));
            }
        } else {
            $error = "Invalid username/email!";
            $log_sql = "INSERT INTO login_logs (username, status, login_time) VALUES (?, 'Failed', GETDATE())";
            sqlsrv_query($conn, $log_sql, array($username));
        }
    } else {
        $error = "Please enter username and password";
    }
}

// Handle Registration
if (isset($_POST['register'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role'] ?? 'user';
    
    // Validation
    if (empty($username) || empty($email) || empty($password)) {
        $error = "All fields are required!";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format!";
    } else {
        // Check if username exists
        $check_sql = "SELECT COUNT(*) as count FROM users WHERE username = ?";
        $check_stmt = sqlsrv_query($conn, $check_sql, array($username));
        $check_row = sqlsrv_fetch_array($check_stmt, SQLSRV_FETCH_ASSOC);
        
        if ($check_row['count'] > 0) {
            $error = "Username already exists!";
        } else {
            // Check if email exists
            $check_email_sql = "SELECT COUNT(*) as count FROM users WHERE email = ?";
            $check_email_stmt = sqlsrv_query($conn, $check_email_sql, array($email));
            $email_row = sqlsrv_fetch_array($check_email_stmt, SQLSRV_FETCH_ASSOC);
            
            if ($email_row['count'] > 0) {
                $error = "Email already registered!";
            } else {
                // Hash password and insert
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $sql = "INSERT INTO users (username, email, password, user_type) VALUES (?, ?, ?, 'local')";
                $params = array($username, $email, $hashedPassword);
                $stmt = sqlsrv_query($conn, $sql, $params);
                
                if ($stmt) {
                    $success = "Registration successful! Please login.";
                    // Clear form by resetting variables
                    $username = $email = '';
                } else {
                    $error = "Registration failed. Please try again.";
                }
            }
        }
    }
}

// Handle Forgot Password Request
if (isset($_POST['forgot_password'])) {
    $email = trim($_POST['reset_email']);
    
    if (empty($email)) {
        $error = "Please enter your email address";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format!";
    } else {
        // Check if email exists
        $check_sql = "SELECT username FROM users WHERE email = ?";
        $check_stmt = sqlsrv_query($conn, $check_sql, array($email));
        
        if ($row = sqlsrv_fetch_array($check_stmt, SQLSRV_FETCH_ASSOC)) {
            // Generate reset token
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // First check if reset_token column exists, if not alter table
            $check_column = "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'users' AND COLUMN_NAME = 'reset_token'";
            $col_check = sqlsrv_query($conn, $check_column);
            if (!sqlsrv_fetch_array($col_check, SQLSRV_FETCH_ASSOC)) {
                $alter_sql = "ALTER TABLE users ADD reset_token NVARCHAR(255), reset_expiry DATETIME";
                sqlsrv_query($conn, $alter_sql);
            }
            
            // Store token in database
            $token_sql = "UPDATE users SET reset_token = ?, reset_expiry = ? WHERE email = ?";
            $token_stmt = sqlsrv_query($conn, $token_sql, array($token, $expiry, $email));
            
            if ($token_stmt) {
                $reset_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=" . $token;
                // For demo, show the link
                $success = "Password reset link generated. <a href='$reset_link' style='color: #059669; text-decoration: underline;'>Click here to reset your password</a>";
            } else {
                $error = "Failed to process request. Please try again.";
            }
        } else {
            $error = "No account found with this email address!";
        }
    }
}

// Handle Google/Apple OAuth (simulated for demo)
if (isset($_GET['social_login']) && isset($_GET['provider'])) {
    $provider = $_GET['provider'];
    $social_email = $_GET['email'] ?? '';
    $social_name = $_GET['name'] ?? '';
    
    if ($provider == 'google') {
        $demo_email = $social_email ?: 'user@gmail.com';
        $demo_name = $social_name ?: 'Google User';
        
        $check_sql = "SELECT * FROM users WHERE email = ?";
        $check_stmt = sqlsrv_query($conn, $check_sql, array($demo_email));
        
        if ($row = sqlsrv_fetch_array($check_stmt, SQLSRV_FETCH_ASSOC)) {
            $_SESSION['username'] = $row['username'];
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['email'] = $row['email'];
            $_SESSION['full_name'] = $row['username'];
            $_SESSION['role'] = 'User';
            header("Location: indexHome.php");
            exit();
        } else {
            $username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $demo_name)) . rand(100, 999);
            $hashedPassword = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
            
            $insert_sql = "INSERT INTO users (username, email, password, user_type) VALUES (?, ?, ?, ?)";
            $insert_stmt = sqlsrv_query($conn, $insert_sql, array($username, $demo_email, $hashedPassword, $provider));
            
            if ($insert_stmt) {
                $_SESSION['username'] = $username;
                // Get the inserted ID
                $id_sql = "SELECT SCOPE_IDENTITY() as id";
                $id_stmt = sqlsrv_query($conn, $id_sql);
                $id_row = sqlsrv_fetch_array($id_stmt, SQLSRV_FETCH_ASSOC);
                $_SESSION['user_id'] = $id_row['id'];
                $_SESSION['email'] = $demo_email;
                $_SESSION['full_name'] = $demo_name;
                $_SESSION['role'] = 'User';
                header("Location: indexHome.php");
                exit();
            }
        }
    }
    
    if ($provider == 'apple') {
        $demo_email = $social_email ?: 'user@icloud.com';
        $demo_name = $social_name ?: 'Apple User';
        
        $check_sql = "SELECT * FROM users WHERE email = ?";
        $check_stmt = sqlsrv_query($conn, $check_sql, array($demo_email));
        
        if ($row = sqlsrv_fetch_array($check_stmt, SQLSRV_FETCH_ASSOC)) {
            $_SESSION['username'] = $row['username'];
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['email'] = $row['email'];
            $_SESSION['full_name'] = $row['username'];
            $_SESSION['role'] = 'User';
            header("Location: indexHome.php");
            exit();
        } else {
            $username = 'apple_' . rand(1000, 9999);
            $hashedPassword = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
            
            $insert_sql = "INSERT INTO users (username, email, password, user_type) VALUES (?, ?, ?, ?)";
            $insert_stmt = sqlsrv_query($conn, $insert_sql, array($username, $demo_email, $hashedPassword, $provider));
            
            if ($insert_stmt) {
                $_SESSION['username'] = $username;
                $id_sql = "SELECT SCOPE_IDENTITY() as id";
                $id_stmt = sqlsrv_query($conn, $id_sql);
                $id_row = sqlsrv_fetch_array($id_stmt, SQLSRV_FETCH_ASSOC);
                $_SESSION['user_id'] = $id_row['id'];
                $_SESSION['email'] = $demo_email;
                $_SESSION['full_name'] = $demo_name;
                $_SESSION['role'] = 'User';
                header("Location: indexHome.php");
                exit();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login and Registration Form</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="logo">
        <div class="logo-icon">IS</div>
        <div class="logo-text">InsightSphere</div>
    </div>
    
    <?php if($error != ""): ?>
        <div style="position: fixed; top: 20px; left: 50%; transform: translateX(-50%); background: #fee2e2; color: #dc2626; padding: 10px 20px; border-radius: 8px; z-index: 1000;"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if($success != ""): ?>
        <div style="position: fixed; top: 20px; left: 50%; transform: translateX(-50%); background: #d1fae5; color: #059669; padding: 10px 20px; border-radius: 8px; z-index: 1000;"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <div class="container">
        <div class="form-box login">
            <form action="index.php" method="POST">
                <h1>Login</h1>
                <div class="input-box">
                    <input type="text" name="username" placeholder="Username" required>
                    <i class='bx bxs-user'></i>
                </div>
                <div class="input-box">
                    <input type="password" name="password" placeholder="Password" required>
                    <i class='bx bxs-lock-alt'></i>
                </div>
                <div class="forget-link">
                    <a href="forgot_password.php">Forgot password?</a>
                </div>
                <button type="submit" name="login" class="btn">Login</button>
                <p>or login with social platforms</p>
                <div class="social-icons">
                    <a href="google_login.php"class="google-login" onclick="handleGoogleLogin()">
                        <i class='bx bxl-google'></i>
                        <span>Google</span>
                    </a>
                    <a href="#" class="apple-login" onclick="handleAppleLogin()">
                        <i class='bx bxl-apple'></i>
                        <span>Apple</span>
                    </a>
                </div>
            </form>
        </div>
        <div class="form-box register">
            <form action="index.php" method="POST">
                <h1>Registration</h1>
                <div class="input-box">
                    <input type="text" name="username" placeholder="Username" required>
                    <i class='bx bxs-user'></i>
                </div>
                <div class="input-box">
                    <input type="email" name="email" placeholder="Email" required>
                    <i class='bx bxs-envelope'></i>
                </div>
                <div class="input-box">
                    <input type="password" name="password" placeholder="Password" required>
                    <i class='bx bxs-lock-alt'></i>
                </div>
                <div class="role-select">
                    <label class="role-card">
                        <input type="radio" name="role" value="user" checked> User
                        <i class='bx bxs-user-circle'></i>
                    </label>
                    <label class="role-card">
                        <input type="radio" name="role" value="admin"> Admin
                        <img src="Admin.png" alt="logo" style="width: 20px; height: 20px;">
                    </label>
                </div>
                <button type="submit" name="register" class="btn">Register</button>
                <p>or login with social platforms</p>
                <div class="social-icons">
                    <a href="#" class="google-login" onclick="handleGoogleRegister()">
                        <i class='bx bxl-google'></i>
                        <span>Google</span>
                    </a>
                    <a href="#" class="apple-login" onclick="handleAppleRegister()">
                        <i class='bx bxl-apple'></i>
                        <span>Apple</span>
                    </a>
                </div>
            </form>
        </div>
        <div class="toggle-box">
            <div class="toggle-panel toggle-left">
                <h1>Hello, Welcome!</h1>
                <p>Don't have an account?</p>
                <button class="btn register-btn">Register</button>
            </div>
            <div class="toggle-panel toggle-right">
                <h1>Welcome Back!</h1>
                <p>Already have an account?</p>
                <button class="btn login-btn">Login</button>
            </div>
        </div>
    </div>
    <script src="script.js"></script>
</body>
</html>