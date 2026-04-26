<?php
session_start();
require_once '../config/db_connect.php';

// Check if user is logged in and has doctor role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header('Location: ../auth/login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Dashboard - SMART Scheduling</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="css/doctor_smart.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="doctor-smart-container">
        <!-- SMART Scheduling Header -->
        <div class="smart-header">
            <div class="header-content">
                <h1><i class="fas fa-brain"></i> SMART Scheduling Integration</h1>
                <div class="smart-status">
                    <span class="status-indicator active"></span>
                    <span>AI Assistant Active</span>
                </div>
            </div>
        </div>

        <!-- Notifications Panel -->
        <div class="notifications-panel">
            <div class="panel-header">
                <h2><i class="fas fa-bell"></i> Smart Notifications</h2>
                <div class="notification-controls">
                    <button class="btn btn-sm btn-primary" id="refresh-notifications">
                        <i class="fas fa-sync"></i> Refresh
                    </button>
                    <button class="btn btn-sm btn-secondary" id="mark-all-read">
                        <i class="fas fa-check-double"></i> Mark All Read
                    </button>
                </div>
            </div>
            
            <div class="notifications-container" id="notifications-container">
                <!-- Notifications will be loaded here -->
            </div>
        </div>

        <!-- Upcoming Appointments with Smart Features -->
        <div class="appointments-panel">
            <div class="panel-header">
                <h2><i class="fas fa-calendar-alt"></i> Today's Smart Schedule</h2>
                <div class="smart-features">
                    <span class="feature-badge">
                        <i class="fas fa-robot"></i> AI Optimized
                    </span>
                    <span class="feature-badge">
                        <i class="fas fa-heartbeat"></i> Health Risk Analyzed
                    </span>
                </div>
            </div>
            
            <div class="appointments-container" id="appointments-container">
                <!-- Appointments will be loaded here -->
            </div>
        </div>

        <!-- Smart Analytics -->
        <div class="analytics-panel">
            <div class="panel-header">
                <h2><i class="fas fa-chart-line"></i> Smart Analytics</h2>
            </div>
            
            <div class="analytics-grid">
                <div class="analytics-card">
                    <div class="card-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="card-content">
                        <h3>Avg. Waiting Time</h3>
                        <span class="card-number" id="avg-waiting">0 min</span>
                    </div>
                </div>
                
                <div class="analytics-card">
                    <div class="card-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="card-content">
                        <h3>Urgent Cases</h3>
                        <span class="card-number" id="urgent-count">0</span>
                    </div>
                </div>
                
                <div class="analytics-card">
                    <div class="card-icon">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    <div class="card-content">
                        <h3>Efficiency Score</h3>
                        <span class="card-number" id="efficiency-score">0%</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Smart Actions -->
        <div class="smart-actions">
            <div class="panel-header">
                <h2><i class="fas fa-magic"></i> Smart Actions</h2>
            </div>
            
            <div class="actions-grid">
                <button class="action-btn" id="optimize-today">
                    <i class="fas fa-magic"></i>
                    <span>Optimize Today's Schedule</span>
                </button>
                
                <button class="action-btn" id="send-reminders">
                    <i class="fas fa-bell"></i>
                    <span>Send Patient Reminders</span>
                </button>
                
                <button class="action-btn" id="analyze-risks">
                    <i class="fas fa-heartbeat"></i>
                    <span>Analyze Health Risks</span>
                </button>
                
                <button class="action-btn" id="generate-report">
                    <i class="fas fa-file-alt"></i>
                    <span>Generate Smart Report</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Notification Modal -->
    <div id="notification-modal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Notification Details</h2>
            <div id="notification-details"></div>
        </div>
    </div>

    <script src="js/doctor_smart.js"></script>
</body>
</html>
