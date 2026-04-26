<?php
// debug_sidebar.php - Debug sidebar text visibility
session_start();

// Simulate a logged-in user for testing
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'testpatient';
    $_SESSION['email'] = 'test@patient.com';
    $_SESSION['role'] = 'user';
}

$sidebar_user_data = [
    'username' => 'testpatient',
    'email' => 'test@patient.com',
    'role' => 'user'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Debug Sidebar - HealthBase</title>
    <link rel="icon" type="image/x-icon" href="/assets/icons/favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="css/patient_dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* Debug styles to make text more visible */
        .patient-sidebar {
            background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%) !important;
        }
        .patient-sidebar .nav-text {
            color: white !important;
            font-weight: bold !important;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.8) !important;
        }
        .patient-sidebar .brand-text {
            color: white !important;
            font-weight: bold !important;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.8) !important;
        }
        .patient-sidebar .user-name {
            color: white !important;
            font-weight: bold !important;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.8) !important;
        }
        .patient-sidebar .user-role {
            color: rgba(255, 255, 255, 0.9) !important;
            font-weight: bold !important;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.8) !important;
        }
    </style>
</head>
<body class="patient-dashboard-page">
    <?php include 'includes/patient_sidebar.php'; ?>
    
    <div class="patient-main-content">
        <header class="patient-header">
            <div class="patient-header-left">
                <h1 class="patient-welcome">Debug Sidebar</h1>
                <p class="patient-subtitle">Testing sidebar text visibility</p>
            </div>
        </header>

        <div class="patient-dashboard-content">
            <div class="patient-card">
                <div class="patient-card-header">
                    <h3 class="patient-card-title">
                        <i class="fas fa-bug"></i>
                        Sidebar Debug Information
                    </h3>
                </div>
                
                <div style="padding: 25px;">
                    <h4>Sidebar Text Visibility Test</h4>
                    <p>Check if you can see the following text in the sidebar:</p>
                    <ul>
                        <li><strong>Brand Text:</strong> "HealthBase" should be visible at the top</li>
                        <li><strong>Navigation Text:</strong> "Dashboard", "My Appointments", "Book Appointment", "My Tickets"</li>
                        <li><strong>User Info:</strong> "testpatient" and "Patient" should be visible at the bottom</li>
                        <li><strong>Logout Text:</strong> "Logout" should be visible</li>
                    </ul>
                    
                    <h4>Sidebar Controls</h4>
                    <ul>
                        <li><strong>Hamburger Menu:</strong> Click to collapse/expand sidebar</li>
                        <li><strong>Thumbtack Icon:</strong> Click to pin/unpin sidebar</li>
                        <li><strong>Hover:</strong> Hover over sidebar when not pinned to expand</li>
                    </ul>
                    
                    <h4>Expected Behavior</h4>
                    <ul>
                        <li>Sidebar should start expanded (not collapsed)</li>
                        <li>All text should be white and visible</li>
                        <li>Text should have shadow for better visibility</li>
                        <li>Sidebar should be blue gradient background</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script src="js/patient_dashboard.js"></script>
</body>
</html>
