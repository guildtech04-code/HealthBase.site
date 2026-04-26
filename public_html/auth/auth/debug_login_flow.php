<?php
// Debug the actual login flow
session_start();

echo "<h1>Login Flow Debug</h1>";

echo "<h2>Current Request Information</h2>";
echo "<p><strong>REQUEST_URI:</strong> " . $_SERVER['REQUEST_URI'] . "</p>";
echo "<p><strong>REQUEST_METHOD:</strong> " . $_SERVER['REQUEST_METHOD'] . "</p>";
echo "<p><strong>HTTP_HOST:</strong> " . $_SERVER['HTTP_HOST'] . "</p>";

echo "<h2>POST Data</h2>";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<pre>";
    print_r($_POST);
    echo "</pre>";
    
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    echo "<h2>Login Processing</h2>";
    echo "<p>Email: $email</p>";
    echo "<p>Password: [HIDDEN]</p>";
    
    // Simulate successful login
    echo "<p>✅ Simulating successful login...</p>";
    
    // Set session variables
    $_SESSION['pending_user_id'] = 1;
    $_SESSION['pending_role'] = 'user';
    $_SESSION['otp_code'] = 123456;
    $_SESSION['otp_expiry'] = time() + 300;
    
    echo "<h2>About to Redirect</h2>";
    echo "<p>Current URL: " . $_SERVER['REQUEST_URI'] . "</p>";
    echo "<p>Redirecting to: verify_2fa.php</p>";
    
    // Calculate the full URL
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $current_path = dirname($_SERVER['REQUEST_URI']);
    $redirect_url = $protocol . "://" . $host . $current_path . "/verify_2fa.php";
    
    echo "<p>Full redirect URL: $redirect_url</p>";
    
    // Add a delay to see the debug info
    echo "<p>Redirecting in 3 seconds...</p>";
    echo "<script>setTimeout(function(){ window.location.href='verify_2fa.php'; }, 3000);</script>";
    
} else {
    echo "<h2>No POST Data</h2>";
    echo "<p>This page should only be accessed via POST from the login form.</p>";
    echo "<p><a href='login.php'>Go to Login</a></p>";
}

echo "<h2>Session Data</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";
?>




