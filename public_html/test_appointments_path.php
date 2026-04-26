<?php
// Test file to check appointments page paths
echo "<h1>Testing Appointments Page Paths</h1>";

echo "<h2>File Structure Check:</h2>";
echo "<ul>";
echo "<li>appointments/appointments.php: " . (file_exists('appointments/appointments.php') ? '✅ Exists' : '❌ Missing') . "</li>";
echo "<li>appointments/appointments.css: " . (file_exists('appointments/appointments.css') ? '✅ Exists' : '❌ Missing') . "</li>";
echo "<li>appointments/appointments.js: " . (file_exists('appointments/appointments.js') ? '✅ Exists' : '❌ Missing') . "</li>";
echo "<li>config/db_connect.php: " . (file_exists('config/db_connect.php') ? '✅ Exists' : '❌ Missing') . "</li>";
echo "<li>includes/sidebar.php: " . (file_exists('includes/sidebar.php') ? '✅ Exists' : '❌ Missing') . "</li>";
echo "<li>dashboard/doctor_dashboard.php: " . (file_exists('dashboard/doctor_dashboard.php') ? '✅ Exists' : '❌ Missing') . "</li>";
echo "<li>auth/login.php: " . (file_exists('auth/login.php') ? '✅ Exists' : '❌ Missing') . "</li>";
echo "</ul>";

echo "<h2>Direct Links:</h2>";
echo "<ul>";
echo "<li><a href='appointments/appointments.php'>Appointments Page</a></li>";
echo "<li><a href='dashboard/doctor_dashboard.php'>Doctor Dashboard</a></li>";
echo "<li><a href='auth/login.php'>Login Page</a></li>";
echo "</ul>";

echo "<h2>Current Directory:</h2>";
echo "<p>" . getcwd() . "</p>";

echo "<h2>Server Info:</h2>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
?>
