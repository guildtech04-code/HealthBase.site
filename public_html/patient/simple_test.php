<?php
// Simple test for patient_tickets.php
echo "<h1>Patient Tickets Test Page</h1>";
echo "<p>This is a test to verify the patient_tickets.php file is accessible.</p>";
echo "<p>Current time: " . date('Y-m-d H:i:s') . "</p>";
echo "<p>PHP version: " . phpversion() . "</p>";
echo "<p>File exists: " . (file_exists(__FILE__) ? 'Yes' : 'No') . "</p>";
echo "<p>Directory: " . __DIR__ . "</p>";
?>
