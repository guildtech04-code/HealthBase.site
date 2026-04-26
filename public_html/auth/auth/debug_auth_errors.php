<?php
// Test to see if there are any errors in auth.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

echo "<h1>Auth.php Error Test</h1>";

echo "<h2>Error Reporting</h2>";
echo "<p>Error reporting is enabled</p>";

echo "<h2>Database Connection Test</h2>";
try {
    require '../config/db_connect.php';
    echo "<p>✅ Database connection successful</p>";
} catch (Exception $e) {
    echo "<p>❌ Database connection failed: " . $e->getMessage() . "</p>";
}

echo "<h2>POST Data Test</h2>";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<pre>";
    print_r($_POST);
    echo "</pre>";
    
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    echo "<h2>Login Processing</h2>";
    echo "<p>Email: $email</p>";
    echo "<p>Password: [HIDDEN]</p>";
    
    // Test database query
    try {
        $stmt = $conn->prepare("SELECT id, email, password, role, status FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if ($user) {
            echo "<p>✅ User found in database</p>";
            echo "<p>User ID: " . $user['id'] . "</p>";
            echo "<p>User Role: " . $user['role'] . "</p>";
            echo "<p>User Status: " . $user['status'] . "</p>";
            
            // Test password verification
            if (password_verify($password, $user['password'])) {
                echo "<p>✅ Password verification successful</p>";
                
                // Set session variables
                $_SESSION['pending_user_id'] = $user['id'];
                $_SESSION['pending_role'] = $user['role'];
                $_SESSION['otp_code'] = 123456;
                $_SESSION['otp_expiry'] = time() + 300;
                
                echo "<h2>About to Redirect</h2>";
                echo "<p>Redirecting to: verify_2fa.php</p>";
                
                // Add a delay to see the debug info
                echo "<p>Redirecting in 3 seconds...</p>";
                echo "<script>setTimeout(function(){ window.location.href='verify_2fa.php'; }, 3000);</script>";
                
            } else {
                echo "<p>❌ Password verification failed</p>";
            }
        } else {
            echo "<p>❌ User not found in database</p>";
        }
    } catch (Exception $e) {
        echo "<p>❌ Database query failed: " . $e->getMessage() . "</p>";
    }
    
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




