<?php
// Debug version of auth.php to see what's happening
session_start();

echo "<h1>Debug Auth.php</h1>";

echo "<h2>POST Data</h2>";
echo "<pre>";
print_r($_POST);
echo "</pre>";

echo "<h2>Session Data</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h2>Server Data</h2>";
echo "<p>REQUEST_URI: " . $_SERVER['REQUEST_URI'] . "</p>";
echo "<p>SCRIPT_NAME: " . $_SERVER['SCRIPT_NAME'] . "</p>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h2>Processing Login...</h2>";
    
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    echo "<p>Email: $email</p>";
    echo "<p>Password: [HIDDEN]</p>";
    
    // Simulate successful login
    echo "<p>✅ Simulating successful login...</p>";
    
    // Set session variables
    $_SESSION['pending_user_id'] = 1;
    $_SESSION['pending_role'] = 'user';
    $_SESSION['otp_code'] = 123456;
    $_SESSION['otp_expiry'] = time() + 300;
    
    echo "<h2>Redirect Test</h2>";
    echo "<p>About to redirect to: verify_2fa.php</p>";
    echo "<p>Full URL should be: " . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . "/verify_2fa.php</p>";
    
    // Add a delay to see the debug info
    echo "<p>Redirecting in 3 seconds...</p>";
    echo "<script>setTimeout(function(){ window.location.href='verify_2fa.php'; }, 3000);</script>";
    
} else {
    echo "<h2>No POST Data</h2>";
    echo "<p>This page should only be accessed via POST from the login form.</p>";
    echo "<p><a href='login.php'>Go to Login</a></p>";
}
?>




