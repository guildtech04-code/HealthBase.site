<?php
// Test 2FA verification flow
echo "<h1>HealthBase 2FA Verification Test</h1>";

echo "<h2>File Existence Check</h2>";
if (file_exists('auth/verify_2fa.php')) {
    echo "✅ auth/verify_2fa.php exists<br>";
} else {
    echo "❌ auth/verify_2fa.php not found<br>";
}

if (file_exists('auth/auth.php')) {
    echo "✅ auth/auth.php exists<br>";
} else {
    echo "❌ auth/auth.php not found<br>";
}

echo "<h2>Path Resolution Test</h2>";
echo "<p>Current directory: " . getcwd() . "</p>";

echo "<h2>Auth Directory Contents</h2>";
echo "<ul>";
$files = scandir('auth');
foreach ($files as $file) {
    if ($file != '.' && $file != '..') {
        echo "<li>$file</li>";
    }
}
echo "</ul>";

echo "<h2>Expected Flow</h2>";
echo "<ol>";
echo "<li>User submits login form to auth/auth.php</li>";
echo "<li>auth.php processes login and redirects to verify_2fa.php</li>";
echo "<li>verify_2fa.php displays 2FA form</li>";
echo "<li>After successful 2FA, redirects to appropriate dashboard</li>";
echo "</ol>";

echo "<h2>Test URLs</h2>";
echo "<p>Login: <a href='auth/login.php'>auth/login.php</a></p>";
echo "<p>2FA Verification: <a href='auth/verify_2fa.php'>auth/verify_2fa.php</a></p>";

echo "<h2>Test Complete</h2>";
echo "<p>If all files exist, the 2FA flow should work correctly.</p>";
?>
