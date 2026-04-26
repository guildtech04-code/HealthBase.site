<?php
// test_sidebar_debug.php - Debug sidebar functionality
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
    <title>Sidebar Debug - HealthBase</title>
    <link rel="icon" type="image/x-icon" href="/assets/icons/favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="css/patient_dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* Force sidebar to be visible and expanded */
        .patient-sidebar {
            width: 280px !important;
            background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%) !important;
        }
        .patient-sidebar .nav-text {
            color: white !important;
            font-weight: bold !important;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.8) !important;
            display: block !important;
        }
        .patient-sidebar .brand-text {
            color: white !important;
            font-weight: bold !important;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.8) !important;
            display: block !important;
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
                <h1 class="patient-welcome">Sidebar Debug Test</h1>
                <p class="patient-subtitle">Testing sidebar text visibility and pinning</p>
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
                    <h4>Instructions:</h4>
                    <ol>
                        <li><strong>Check Text Visibility:</strong> Look at the sidebar - you should see white text labels next to icons</li>
                        <li><strong>Test Pinning:</strong> Click the thumbtack icon (📌) in the sidebar to pin/unpin</li>
                        <li><strong>Test Collapse:</strong> Click the hamburger menu (☰) to collapse/expand</li>
                        <li><strong>Check Console:</strong> Open browser console (F12) to see debug messages</li>
                    </ol>
                    
                    <h4>Expected Results:</h4>
                    <ul>
                        <li>Sidebar should start expanded with text visible</li>
                        <li>Text should be white with shadow for visibility</li>
                        <li>Pinning should work (thumbtack icon)</li>
                        <li>Collapsing should work (hamburger menu)</li>
                        <li>Console should show debug messages</li>
                    </ul>
                    
                    <h4>Debug Actions:</h4>
                    <button onclick="clearLocalStorage()" style="background: #dc2626; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin: 5px;">
                        Clear LocalStorage
                    </button>
                    <button onclick="forceExpandSidebar()" style="background: #10b981; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin: 5px;">
                        Force Expand Sidebar
                    </button>
                    <button onclick="forceCollapseSidebar()" style="background: #f59e0b; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin: 5px;">
                        Force Collapse Sidebar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="js/patient_dashboard.js"></script>
    <script>
        function clearLocalStorage() {
            localStorage.removeItem('patientSidebarPinned');
            localStorage.removeItem('patientSidebarCollapsed');
            console.log('LocalStorage cleared');
            location.reload();
        }
        
        function forceExpandSidebar() {
            const sidebar = document.getElementById('patientSidebar');
            sidebar.classList.remove('collapsed');
            sidebar.classList.add('pinned');
            console.log('Sidebar forced to expand');
        }
        
        function forceCollapseSidebar() {
            const sidebar = document.getElementById('patientSidebar');
            sidebar.classList.add('collapsed');
            sidebar.classList.remove('pinned');
            console.log('Sidebar forced to collapse');
        }
        
        // Additional debugging
        window.addEventListener('load', function() {
            console.log('Page loaded');
            const sidebar = document.getElementById('patientSidebar');
            console.log('Sidebar classes:', sidebar.className);
            console.log('Sidebar width:', sidebar.offsetWidth);
        });
    </script>
</body>
</html>
