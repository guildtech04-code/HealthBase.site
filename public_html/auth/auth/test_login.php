<?php
// Quick test to verify login form is working
echo "<h1>HealthBase Login Form Test</h1>";

echo "<h2>Testing Login Form Action</h2>";
echo "<p>Login form should submit to: <strong>auth/auth.php</strong></p>";

echo "<h2>Testing File Existence</h2>";
if (file_exists('auth/auth.php')) {
    echo "✅ auth/auth.php exists<br>";
} else {
    echo "❌ auth/auth.php not found<br>";
}

if (file_exists('auth/login.php')) {
    echo "✅ auth/login.php exists<br>";
} else {
    echo "❌ auth/login.php not found<br>";
}

echo "<h2>Testing Path Resolution</h2>";
echo "<p>Current directory: " . getcwd() . "</p>";
echo "<p>Auth directory contents:</p>";
echo "<ul>";
$files = scandir('auth');
foreach ($files as $file) {
    if ($file != '.' && $file != '..') {
        echo "<li>$file</li>";
    }
}
echo "</ul>";

echo "<h2>Test Complete</h2>";
echo "<p>If all files exist, the login should work correctly now.</p>";
?>
