<?php
// test_patient_dashboard.php - Test patient dashboard functionality
session_start();

// Simulate a logged-in user for testing
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; // Test user ID
    $_SESSION['username'] = 'testpatient';
    $_SESSION['email'] = 'test@patient.com';
    $_SESSION['role'] = 'user';
}

echo "<h1>Patient Dashboard Test</h1>";

// Test favicon
echo "<h2>Favicon Test</h2>";
if (file_exists('../assets/icons/favicon.ico')) {
    echo "✅ Favicon found at: ../assets/icons/favicon.ico<br>";
} else {
    echo "❌ Favicon not found<br>";
}

// Test logo
echo "<h2>Logo Test</h2>";
if (file_exists('../assets/images/Logo.png')) {
    echo "✅ Logo found at: ../assets/images/Logo.png<br>";
} else {
    echo "❌ Logo not found<br>";
}

// Test CSS
echo "<h2>CSS Test</h2>";
if (file_exists('css/patient_dashboard.css')) {
    echo "✅ Patient CSS found<br>";
} else {
    echo "❌ Patient CSS not found<br>";
}

// Test JavaScript
echo "<h2>JavaScript Test</h2>";
if (file_exists('js/patient_dashboard.js')) {
    echo "✅ Patient JS found<br>";
} else {
    echo "❌ Patient JS not found<br>";
}

// Test sidebar
echo "<h2>Sidebar Test</h2>";
if (file_exists('includes/patient_sidebar.php')) {
    echo "✅ Patient sidebar found<br>";
} else {
    echo "❌ Patient sidebar not found<br>";
}

// Test database connection
echo "<h2>Database Test</h2>";
try {
    include("../config/db_connect.php");
    echo "✅ Database connection successful<br>";
    
    // Test if we can query users table
    $test_query = $conn->prepare("SELECT COUNT(*) as count FROM users");
    $test_query->execute();
    $result = $test_query->get_result();
    $count = $result->fetch_assoc()['count'];
    echo "✅ Users table accessible - {$count} users found<br>";
    
    // Test if we can query patients table
    $test_query = $conn->prepare("SELECT COUNT(*) as count FROM patients");
    $test_query->execute();
    $result = $test_query->get_result();
    $count = $result->fetch_assoc()['count'];
    echo "✅ Patients table accessible - {$count} patients found<br>";
    
    // Test if we can query appointments table
    $test_query = $conn->prepare("SELECT COUNT(*) as count FROM appointments");
    $test_query->execute();
    $result = $test_query->get_result();
    $count = $result->fetch_assoc()['count'];
    echo "✅ Appointments table accessible - {$count} appointments found<br>";
    
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
}

echo "<h2>Navigation Test</h2>";
echo "<p>Test the patient dashboard:</p>";
echo "<a href='patient_dashboard.php' style='background: #0ea5e9; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Patient Dashboard</a><br><br>";

echo "<p>Test patient record creation:</p>";
echo "<a href='create_patient_record.php' style='background: #10b981; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Create Patient Record</a><br><br>";

echo "<h2>Sidebar Links Test</h2>";
echo "<ul>";
echo "<li><a href='../appointments/appointments.php'>My Appointments</a></li>";
echo "<li><a href='../appointments/scheduling.php'>Book Appointment</a></li>";
echo "<li><a href='../support/my_tickets.php'>My Tickets</a></li>";
echo "</ul>";
?>
