<?php
echo "<h1>Auth Directory Test</h1>";
echo "<p>This file is in the auth directory.</p>";
echo "<p>Current time: " . date('Y-m-d H:i:s') . "</p>";
echo "<p>Current directory: " . getcwd() . "</p>";
echo "<p>File exists: " . (file_exists(__FILE__) ? "Yes" : "No") . "</p>";

echo "<h2>Test Links</h2>";
echo "<p><a href='login.php'>Go to Login</a></p>";
echo "<p><a href='auth.php'>Go to Auth Handler</a></p>";
echo "<p><a href='verify_2fa.php'>Go to 2FA</a></p>";
?>




