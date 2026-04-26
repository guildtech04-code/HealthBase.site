<?php
// test_patient_sidebar.php - Test patient sidebar functionality
session_start();

// Simulate a logged-in user for testing
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'testpatient';
    $_SESSION['email'] = 'test@patient.com';
    $_SESSION['role'] = 'user';
}

echo "<h1>Patient Sidebar Test</h1>";

// Test sidebar files
echo "<h2>File Tests</h2>";
if (file_exists('includes/patient_sidebar.php')) {
    echo "✅ Patient sidebar file found<br>";
} else {
    echo "❌ Patient sidebar file not found<br>";
}

if (file_exists('css/patient_dashboard.css')) {
    echo "✅ Patient CSS file found<br>";
} else {
    echo "❌ Patient CSS file not found<br>";
}

if (file_exists('js/patient_dashboard.js')) {
    echo "✅ Patient JS file found<br>";
} else {
    echo "❌ Patient JS file not found<br>";
}

if (file_exists('patient_tickets.php')) {
    echo "✅ Patient tickets file found<br>";
} else {
    echo "❌ Patient tickets file not found<br>";
}

if (file_exists('patient_appointments.php')) {
    echo "✅ Patient appointments file found<br>";
} else {
    echo "❌ Patient appointments file not found<br>";
}

if (file_exists('../appointments/notification_helper.php')) {
    echo "✅ Notification helper file found<br>";
} else {
    echo "❌ Notification helper file not found<br>";
}

echo "<h2>Sidebar Test</h2>";
echo "<p>Test the sidebar with text labels:</p>";
echo "<a href='patient_dashboard.php' style='background: #0ea5e9; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Test Patient Dashboard</a><br><br>";

echo "<h2>Sidebar Features Test</h2>";
echo "<ul>";
echo "<li><strong>Text Labels:</strong> Should be visible when sidebar is expanded</li>";
echo "<li><strong>Pinning:</strong> Click the thumbtack icon to pin/unpin</li>";
echo "<li><strong>Collapsing:</strong> Click the hamburger menu to collapse/expand</li>";
echo "<li><strong>Hover:</strong> Hover over sidebar when not pinned to expand</li>";
echo "</ul>";

echo "<h2>Navigation Test</h2>";
echo "<ul>";
echo "<li><a href='patient_dashboard.php'>Dashboard</a></li>";
echo "<li><a href='patient_appointments.php'>My Appointments</a></li>";
echo "<li><a href='patient_tickets.php'>My Tickets</a></li>";
echo "<li><a href='../appointments/scheduling.php'>Book Appointment</a></li>";
echo "</ul>";
?>
