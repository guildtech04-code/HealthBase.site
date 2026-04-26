<?php
// Debug version of verify_2fa.php to check what's happening
session_start();

echo "<h1>Debug: 2FA Verification</h1>";

echo "<h2>Session Check</h2>";
if (isset($_SESSION['pending_user_id'])) {
    echo "✅ Session pending_user_id exists: " . $_SESSION['pending_user_id'] . "<br>";
} else {
    echo "❌ Session pending_user_id NOT set<br>";
}

if (isset($_SESSION['pending_role'])) {
    echo "✅ Session pending_role exists: " . $_SESSION['pending_role'] . "<br>";
} else {
    echo "❌ Session pending_role NOT set<br>";
}

if (isset($_SESSION['otp_code'])) {
    echo "✅ Session otp_code exists: " . $_SESSION['otp_code'] . "<br>";
} else {
    echo "❌ Session otp_code NOT set<br>";
}

echo "<h2>Current URL</h2>";
echo "<p>Current URL: " . $_SERVER['REQUEST_URI'] . "</p>";

echo "<h2>Expected Behavior</h2>";
echo "<p>If session variables are not set, this should redirect to login.php</p>";

echo "<h2>Test Links</h2>";
echo "<p><a href='login.php'>Go to Login</a></p>";
echo "<p><a href='auth.php'>Go to Auth Handler</a></p>";

// Check if we should redirect
if (!isset($_SESSION['pending_user_id'])) {
    echo "<h2>Redirecting...</h2>";
    echo "<p>Redirecting to login.php?error=session</p>";
    header("Location: login.php?error=session");
    exit();
}
?>




