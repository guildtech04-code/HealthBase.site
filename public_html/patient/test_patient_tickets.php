<?php
// test_patient_tickets.php - Test patient tickets page
session_start();

// Simulate a logged-in user for testing
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'testpatient';
    $_SESSION['email'] = 'test@patient.com';
    $_SESSION['role'] = 'user';
}

echo "<h1>Patient Tickets Test</h1>";

// Test if the file exists
if (file_exists('patient_tickets.php')) {
    echo "✅ patient_tickets.php file exists<br>";
} else {
    echo "❌ patient_tickets.php file not found<br>";
}

// Test database connection
echo "<h2>Database Test</h2>";
try {
    include("../config/db_connect.php");
    echo "✅ Database connection successful<br>";
    
    // Test if support_tickets table exists
    $test_query = $conn->prepare("SHOW TABLES LIKE 'support_tickets'");
    $test_query->execute();
    $result = $test_query->get_result();
    
    if ($result->num_rows > 0) {
        echo "✅ support_tickets table exists<br>";
    } else {
        echo "❌ support_tickets table not found<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
}

echo "<h2>File Access Test</h2>";
echo "<p>Test accessing the patient tickets page:</p>";
echo "<a href='patient_tickets.php' style='background: #0ea5e9; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Test Patient Tickets</a><br><br>";

echo "<h2>Direct Link Test</h2>";
echo "<p>Test direct access to patient tickets:</p>";
echo "<a href='/patient/patient_tickets.php' style='background: #10b981; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Direct Link to Patient Tickets</a><br><br>";

echo "<h2>Debug Information</h2>";
echo "<p><strong>Current Directory:</strong> " . getcwd() . "</p>";
echo "<p><strong>Script Name:</strong> " . $_SERVER['SCRIPT_NAME'] . "</p>";
echo "<p><strong>Request URI:</strong> " . $_SERVER['REQUEST_URI'] . "</p>";
echo "<p><strong>Document Root:</strong> " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
?>
