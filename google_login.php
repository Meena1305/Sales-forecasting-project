<?php
// google-login.php
session_start();

// Your Google OAuth credentials
$client_id = "YOUR_GOOGLE_CLIENT_ID";
$redirect_uri = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/google-callback.php";

// Google OAuth URL
$google_auth_url = "https://accounts.google.com/o/oauth2/v2/auth?" . http_build_query([
    'client_id' => $client_id,
    'redirect_uri' => $redirect_uri,
    'response_type' => 'code',
    'scope' => 'email profile',
    'access_type' => 'online'
]);

// Redirect to Google
header("Location: " . $google_auth_url);
exit();
?>