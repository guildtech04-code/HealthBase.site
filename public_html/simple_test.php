<?php
echo "<h1>Simple Test File</h1>";
echo "<p>This is a simple test file to check if PHP files are working.</p>";
echo "<p>Current time: " . date('Y-m-d H:i:s') . "</p>";
echo "<p>Current directory: " . getcwd() . "</p>";
echo "<p>File exists: " . (file_exists(__FILE__) ? "Yes" : "No") . "</p>";
?>




