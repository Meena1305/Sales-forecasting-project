<?php
// google-callback.php
session_start();
include("db_connect.php");

$client_id = "YOUR_GOOGLE_CLIENT_ID";
$client_secret = "YOUR_GOOGLE_CLIENT_SECRET";
$redirect_uri = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/google-callback.php";

if (isset($_GET['code'])) {
    // Exchange code for access token
    $token_url = "https://oauth2.googleapis.com/token";
    
    $post_data = [
        'code' => $_GET['code'],
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'redirect_uri' => $redirect_uri,
        'grant_type' => 'authorization_code'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $token_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $token_data = json_decode($response, true);
    
    if (isset($token_data['access_token'])) {
        // Get user info
        $userinfo_url = "https://www.googleapis.com/oauth2/v2/userinfo?access_token=" . $token_data['access_token'];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $userinfo_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $user_response = curl_exec($ch);
        curl_close($ch);
        
        $user_data = json_decode($user_response, true);
        
        if (isset($user_data['email'])) {
            $email = $user_data['email'];
            $name = $user_data['name'];
            $google_id = $user_data['id'];
            
            // Check if user exists
            $check_sql = "SELECT * FROM users WHERE email = ? OR google_id = ?";
            $check_stmt = sqlsrv_query($conn, $check_sql, array($email, $google_id));
            
            if ($row = sqlsrv_fetch_array($check_stmt, SQLSRV_FETCH_ASSOC)) {
                // Existing user - log them in
                $_SESSION['username'] = $row['username'];
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['email'] = $row['email'];
                $_SESSION['full_name'] = $row['username'];
                $_SESSION['role'] = 'User';
            } else {
                // New user - create account
                $username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', explode('@', $email)[0])) . rand(100, 999);
                $hashedPassword = password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
                
                $insert_sql = "INSERT INTO users (username, email, password, user_type, google_id) VALUES (?, ?, ?, 'google', ?)";
                $insert_stmt = sqlsrv_query($conn, $insert_sql, array($username, $email, $hashedPassword, $google_id));
                
                if ($insert_stmt) {
                    $_SESSION['username'] = $username;
                    $id_sql = "SELECT SCOPE_IDENTITY() as id";
                    $id_stmt = sqlsrv_query($conn, $id_sql);
                    $id_row = sqlsrv_fetch_array($id_stmt, SQLSRV_FETCH_ASSOC);
                    $_SESSION['user_id'] = $id_row['id'];
                    $_SESSION['email'] = $email;
                    $_SESSION['full_name'] = $name;
                    $_SESSION['role'] = 'User';
                }
            }
            
            header("Location: indexHome.php");
            exit();
        }
    }
}

// If something fails, go back to login
header("Location: index.php");
exit();
?>