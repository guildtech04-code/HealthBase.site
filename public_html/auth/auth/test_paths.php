<?php
// Test file to verify all paths are working correctly after module reorganization
echo "<h1>HealthBase Module Path Test</h1>";

// Test database connection
echo "<h2>Database Connection Test</h2>";
try {
    require 'config/db_connect.php';
    echo "✅ Database connection successful<br>";
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "<br>";
}

// Test CSS files
echo "<h2>CSS Files Test</h2>";
if (file_exists('css/style.css')) {
    echo "✅ style.css found<br>";
} else {
    echo "❌ style.css not found<br>";
}

if (file_exists('css/dashboard.css')) {
    echo "✅ dashboard.css found<br>";
} else {
    echo "❌ dashboard.css not found<br>";
}

// Test JavaScript files
echo "<h2>JavaScript Files Test</h2>";
if (file_exists('js/script.js')) {
    echo "✅ script.js found<br>";
} else {
    echo "❌ script.js not found<br>";
}

// Test asset files
echo "<h2>Asset Files Test</h2>";
if (file_exists('assets/images/Logo.png')) {
    echo "✅ Logo.png found<br>";
} else {
    echo "❌ Logo.png not found<br>";
}

if (file_exists('assets/images/LoginPic.png')) {
    echo "✅ LoginPic.png found<br>";
} else {
    echo "❌ LoginPic.png not found<br>";
}

if (file_exists('assets/fonts/Belleza-Regular.ttf')) {
    echo "✅ Belleza-Regular.ttf found<br>";
} else {
    echo "❌ Belleza-Regular.ttf not found<br>";
}

// Test includes
echo "<h2>Include Files Test</h2>";
if (file_exists('includes/sidebar.php')) {
    echo "✅ sidebar.php found<br>";
} else {
    echo "❌ sidebar.php not found<br>";
}

// Test module files
echo "<h2>Module Files Test</h2>";

// Auth module
if (file_exists('auth/login.php')) {
    echo "✅ auth/login.php found<br>";
} else {
    echo "❌ auth/login.php not found<br>";
}

if (file_exists('auth/register.php')) {
    echo "✅ auth/register.php found<br>";
} else {
    echo "❌ auth/register.php not found<br>";
}

// Dashboard module
if (file_exists('dashboard/healthbase_dashboard.php')) {
    echo "✅ dashboard/healthbase_dashboard.php found<br>";
} else {
    echo "❌ dashboard/healthbase_dashboard.php not found<br>";
}

if (file_exists('dashboard/doctor_dashboard.php')) {
    echo "✅ dashboard/doctor_dashboard.php found<br>";
} else {
    echo "❌ dashboard/doctor_dashboard.php not found<br>";
}

// Appointments module
if (file_exists('appointments/appointments.php')) {
    echo "✅ appointments/appointments.php found<br>";
} else {
    echo "❌ appointments/appointments.php not found<br>";
}

if (file_exists('appointments/scheduling.php')) {
    echo "✅ appointments/scheduling.php found<br>";
} else {
    echo "❌ appointments/scheduling.php not found<br>";
}

// Support module
if (file_exists('support/support.php')) {
    echo "✅ support/support.php found<br>";
} else {
    echo "❌ support/support.php not found<br>";
}

if (file_exists('support/new_ticket.php')) {
    echo "✅ support/new_ticket.php found<br>";
} else {
    echo "❌ support/new_ticket.php not found<br>";
}

// Admin module
if (file_exists('admin/manage_users.php')) {
    echo "✅ admin/manage_users.php found<br>";
} else {
    echo "❌ admin/manage_users.php not found<br>";
}

// Notifications module
if (file_exists('notifications/fetch_notifications.php')) {
    echo "✅ notifications/fetch_notifications.php found<br>";
} else {
    echo "❌ notifications/fetch_notifications.php not found<br>";
}

// Test documentation
echo "<h2>Documentation Test</h2>";
if (file_exists('docs/Guildtech-Final-Manuscript (1).pdf')) {
    echo "✅ Proposal PDF found<br>";
} else {
    echo "❌ Proposal PDF not found<br>";
}

echo "<h2>Test Complete</h2>";
echo "<p>All module paths have been tested. Check the results above.</p>";
echo "<p><strong>New Module Structure:</strong></p>";
echo "<ul>";
echo "<li><strong>auth/</strong> - Authentication module (login, register, password reset, etc.)</li>";
echo "<li><strong>dashboard/</strong> - Dashboard module (main dashboard, doctor dashboard)</li>";
echo "<li><strong>appointments/</strong> - Appointments module (scheduling, appointments management)</li>";
echo "<li><strong>support/</strong> - Support module (tickets, contact support)</li>";
echo "<li><strong>admin/</strong> - Admin module (user management, bulk actions)</li>";
echo "<li><strong>notifications/</strong> - Notifications module (notification management)</li>";
echo "</ul>";
?>
