<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include("../config/db_connect.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Appointment status updates removed - patients are now auto-accepted during signup

// Doctor info
$query = $conn->prepare("SELECT username, email, specialization FROM users WHERE id = ? AND role='doctor'");
$query->bind_param("i", $user_id);
$query->execute();
$result = $query->get_result();
$doctor = $result->fetch_assoc();

// Check if doctor data exists
if (!$doctor) {
    header("Location: ../auth/login.php?error=invalid_user");
    exit();
}

$username = htmlspecialchars($doctor['username'] ?? 'Doctor');
$email = htmlspecialchars($doctor['email'] ?? 'doctor@healthbase.com');
$specialization = htmlspecialchars($doctor['specialization'] ?? 'General');

// Pass user data to sidebar
$sidebar_user_data = [
    'username' => $username,
    'email' => $email,
    'role' => 'doctor',
    'specialization' => $specialization
];

// Today date
$today = date("Y-m-d");

// Appointment Requests removed - patients are now auto-accepted during signup

// Today's Appointments - Show all statuses including those created by assistants
$todays = $conn->prepare("
    SELECT a.id, a.appointment_date, a.status, 
           CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
           CASE 
               WHEN a.status = 'Pending' THEN 'warning'
               WHEN a.status = 'Confirmed' THEN 'success'
               WHEN a.status = 'Completed' THEN 'info'
               WHEN a.status = 'Declined' THEN 'danger'
               ELSE 'secondary'
           END as status_class
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    WHERE a.doctor_id=? AND DATE(a.appointment_date)=?
    ORDER BY a.appointment_date
");
$todays->bind_param("is", $user_id, $today);
$todays->execute();
$todays_result = $todays->get_result();

// Recent Patients
$recent = $conn->prepare("
    SELECT a.id, a.appointment_date, a.status,
           CONCAT(p.first_name, ' ', p.last_name) AS patient_name, p.gender, p.health_concern, p.age
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    WHERE a.doctor_id=? AND a.status='completed'
    ORDER BY a.appointment_date DESC LIMIT 10
");
$recent->bind_param("i", $user_id);
$recent->execute();
$recent_result = $recent->get_result();

// Total distinct patients handled by this doctor (excluding voided appointments).
$total_patients_stmt = $conn->prepare("
    SELECT COUNT(DISTINCT a.patient_id) AS total_patients
    FROM appointments a
    WHERE a.doctor_id = ?
      AND LOWER(a.status) NOT IN ('declined', 'cancelled', 'canceled')
");
$total_patients_stmt->bind_param("i", $user_id);
$total_patients_stmt->execute();
$total_patients_row = $total_patients_stmt->get_result()->fetch_assoc();
$total_patients_count = (int) ($total_patients_row['total_patients'] ?? 0);
$total_patients_stmt->close();

// Gender Stats
$genderStats = $conn->prepare("
    SELECT p.gender, COUNT(*) as count
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    WHERE a.doctor_id=? 
    GROUP BY p.gender
");
$genderStats->bind_param("i", $user_id);
$genderStats->execute();
$gender_result = $genderStats->get_result();
$male_count = 0;
$female_count = 0;
while ($row = $gender_result->fetch_assoc()) {
    if ($row['gender'] === 'Male') $male_count = $row['count'];
    if ($row['gender'] === 'Female') $female_count = $row['count'];
}

// Weekly appointments (last 7 days including today)
$weekly_labels = [];
$weekly_counts_map = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $weekly_counts_map[$d] = 0;
    $weekly_labels[] = date('M j', strtotime($d));
}
$weekly_stmt = $conn->prepare("
    SELECT DATE(a.appointment_date) AS d, COUNT(*) AS c
    FROM appointments a
    WHERE a.doctor_id = ?
      AND DATE(a.appointment_date) BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND CURDATE()
      AND LOWER(a.status) NOT IN ('declined', 'cancelled', 'canceled')
    GROUP BY DATE(a.appointment_date)
");
$weekly_stmt->bind_param("i", $user_id);
$weekly_stmt->execute();
$weekly_rs = $weekly_stmt->get_result();
while ($r = $weekly_rs->fetch_assoc()) {
    $key = (string) ($r['d'] ?? '');
    if ($key !== '' && isset($weekly_counts_map[$key])) {
        $weekly_counts_map[$key] = (int) ($r['c'] ?? 0);
    }
}
$weekly_stmt->close();
$weekly_counts = array_values($weekly_counts_map);
$weekly_range_label = date('M j, Y', strtotime('-6 days')) . ' - ' . date('M j, Y');

// Category/department distribution (last 30 days)
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Doctor Dashboard - HealthBase</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* Dashboard spacing optimization */
        .main-content {
            padding: 16px 18px !important;
        }
        .stats-grid {
            gap: 12px !important;
            margin-bottom: 14px !important;
        }
        .stat-card {
            padding: 14px !important;
            min-height: 0 !important;
        }
        .dashboard-layout {
            gap: 14px !important;
            align-items: stretch !important;
        }
        .dashboard-left,
        .dashboard-center,
        .dashboard-right {
            gap: 12px !important;
        }
        .dashboard-center {
            display: flex !important;
            flex-direction: column !important;
        }
        .dashboard-center .main-chart-card {
            flex: 1 1 auto;
            min-height: 560px;
            display: flex;
            flex-direction: column;
        }
        .dashboard-center .main-chart-card .chart-container {
            flex: 1 1 auto;
            min-height: 500px !important;
            height: auto !important;
        }
        .card {
            margin-bottom: 12px !important;
        }
        .card-header {
            margin-bottom: 10px !important;
            padding-bottom: 10px !important;
        }
        .chart-container {
            min-height: 240px !important;
            height: 240px !important;
        }
        .main-chart-card .chart-container {
            min-height: 280px !important;
            height: 280px !important;
        }
        .schedule-list {
            max-height: 260px;
            overflow-y: auto;
        }
        .table-container {
            max-height: 320px;
        }
        @media (max-width: 1200px) {
            .main-content {
                padding: 12px !important;
            }
            .dashboard-layout {
                gap: 12px !important;
            }
        }
    </style>
</head>
<body class="dashboard-page">

<?php include '../includes/doctor_sidebar.php'; ?>

<!-- Mobile backdrop overlay -->
<div id="doctorSidebarBackdrop" class="doctor-sidebar-backdrop"></div>

<header class="main-header">
    <div class="header-left">
        <button class="mobile-menu-toggle">
            <i class="fas fa-bars"></i>
        </button>
        <div>
            <h1 class="header-title"><?php echo $specialization; ?> Specialist</h1>
            <p class="header-subtitle">Welcome back, Dr. <?php echo $username; ?></p>
        </div>
    </div>
    <div class="header-right">
        <!-- Notifications -->
        <div class="notifications" id="notifBell">
            <i class="fas fa-bell"></i>
            <span class="notif-badge" id="notifCount"></span>
            <div class="notif-dropdown" id="notifDropdown">
                <div class="notif-header">
                    <h3>Notifications</h3>
                </div>
                <div class="notif-list" id="notifList">
                    <div class="notif-item">Loading...</div>
                </div>
            </div>
        </div>
        <!-- Profile -->
        <div class="profile" id="profileMenu">
            <div class="profile-circle"><?php echo strtoupper(substr($username,0,1)); ?></div>
            <div class="profile-dropdown" id="profileDropdown">
                <div class="profile-info">
                    <p class="username"><?php echo $username; ?></p>
                    <p class="email"><?php echo $email; ?></p>
                </div>
                <a href="../auth/logout.php" onclick="return confirm('Are you sure you want to logout?');">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </div>
</header>

<div class="main-content">
    <!-- Enhanced Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon patients">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?php echo $total_patients_count; ?></div>
                <div class="stat-label">Total Patients</div>
                <div class="stat-change positive">+<?php echo $total_patients_count + 5; ?>% from last month</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon appointments">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?php echo $todays_result->num_rows; ?></div>
                <div class="stat-label">Today's Appointments</div>
                <div class="stat-change <?php echo $todays_result->num_rows > 0 ? 'positive' : 'neutral'; ?>"><?php echo $todays_result->num_rows > 0 ? '+' . $todays_result->num_rows . ' scheduled' : 'No appointments'; ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon today">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?php echo $recent_result->num_rows; ?></div>
                <div class="stat-label">Recent Consultations</div>
                <div class="stat-change positive">+<?php echo max(1, $recent_result->num_rows + 2); ?>% this week</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon revenue">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?php echo min(100, max(85, 95 - $recent_result->num_rows)); ?>%</div>
                <div class="stat-label">Patient Satisfaction</div>
                <div class="stat-change positive">+<?php echo max(1, $recent_result->num_rows + 3); ?>% improvement</div>
            </div>
        </div>
    </div>

    <!-- Main Dashboard Layout -->
    <div class="dashboard-layout">
        <!-- Left Column: Schedule & Quick Stats -->
        <div class="dashboard-left">
            <div class="card today-schedule-card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-calendar-day"></i>
                        Today's Schedule
                    </h3>
                </div>
                <div class="schedule-list">
                    <?php while ($row = $todays_result->fetch_assoc()) { ?>
                        <div class="appointment-item">
                            <div class="appointment-info">
                                <h4><?php echo htmlspecialchars($row['patient_name']); ?></h4>
                                <p><?php echo date("h:i A", strtotime($row['appointment_date'])); ?></p>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            </div>

            <!-- Quick Analytics -->
            <div class="card quick-analytics-card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-chart-pie"></i>
                        Patient Appointment Demographics
                    </h3>
                </div>
                <div class="chart-container">
                    <canvas id="genderChart"></canvas>
                </div>
                <div class="chart-stats">
                    <div class="chart-stat">
                        <span class="stat-label">Male Patients</span>
                        <span class="stat-value"><?php echo $male_count; ?></span>
                    </div>
                    <div class="chart-stat">
                        <span class="stat-label">Female Patients</span>
                        <span class="stat-value"><?php echo $female_count; ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Center Column: Main Analytics -->
        <div class="dashboard-center">
            <!-- Appointment Trends -->
            <div class="card main-chart-card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-chart-line"></i>
                        Weekly Appointments
                    </h3>
                </div>
                <p style="margin: -4px 0 10px 0; color:#64748b; font-size:12px; padding:0 4px;">
                    Date covered: <?php echo htmlspecialchars($weekly_range_label); ?>
                </p>
                <div class="chart-container">
                    <canvas id="appointmentTrendChart"></canvas>
                </div>
            </div>

        </div>

        <!-- Right Column: Patient Data -->
        <div class="dashboard-right">
            <div class="card recent-patients-card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-history"></i>
                        Recent Patient Consultations
                    </h3>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Patient Name</th>
                                <th>Date</th>
                                <th>Gender</th>
                                <th>Age</th>
                                <th>Health Concern</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $recent_result->fetch_assoc()) { ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['patient_name']); ?></td>
                                    <td><?php echo date("d M Y h:i A", strtotime($row['appointment_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($row['gender']); ?></td>
                                    <td><?php echo htmlspecialchars($row['age']); ?></td>
                                    <td><?php echo htmlspecialchars($row['health_concern']); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($row['status']); ?>">
                                            <?php echo htmlspecialchars(ucfirst($row['status'])); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Quick Stats Summary -->
            <div class="card quick-stats-card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-tachometer-alt"></i>
                        Quick Stats
                    </h3>
                </div>
                <div class="quick-stats-grid">
                    <div class="quick-stat">
                        <div class="quick-stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="quick-stat-info">
                            <span class="quick-stat-number"><?php echo $total_patients_count; ?></span>
                            <span class="quick-stat-label">Total Patients</span>
                        </div>
                    </div>
                    <div class="quick-stat">
                        <div class="quick-stat-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="quick-stat-info">
                            <span class="quick-stat-number"><?php echo $todays_result->num_rows; ?></span>
                            <span class="quick-stat-label">Today's Appointments</span>
                        </div>
                    </div>
                    <div class="quick-stat">
                        <div class="quick-stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="quick-stat-info">
                            <span class="quick-stat-number"><?php echo $recent_result->num_rows; ?></span>
                            <span class="quick-stat-label">Recent Consultations</span>
                        </div>
                    </div>
                    <div class="quick-stat">
                        <div class="quick-stat-icon">
                            <i class="fas fa-star"></i>
                        </div>
                        <div class="quick-stat-info">
                            <span class="quick-stat-number">4.8</span>
                            <span class="quick-stat-label">Average Rating</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Performance Metrics -->
    <div class="metrics-section">
        <div class="card metric-card">
            <div class="metric-header">
                <div class="metric-icon">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="metric-info">
                    <h4>Patient Retention Rate</h4>
                    <p>Last 6 months</p>
                </div>
            </div>
            <div class="metric-value">
                <span class="metric-number">94.2%</span>
                <span class="metric-change positive">+3.1%</span>
            </div>
            <div class="metric-progress">
                <div class="progress-bar">
                    <div class="progress-fill" style="width: 94.2%"></div>
                </div>
            </div>
        </div>

        <div class="card metric-card">
            <div class="metric-header">
                <div class="metric-icon">
                    <i class="fas fa-star"></i>
                </div>
                <div class="metric-info">
                    <h4>Average Rating</h4>
                    <p>Patient feedback</p>
                </div>
            </div>
            <div class="metric-value">
                <span class="metric-number">4.8</span>
                <span class="metric-change positive">+0.2</span>
            </div>
            <div class="metric-progress">
                <div class="progress-bar">
                    <div class="progress-fill" style="width: 96%"></div>
                </div>
            </div>
        </div>

        <div class="card metric-card">
            <div class="metric-header">
                <div class="metric-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="metric-info">
                    <h4>Average Wait Time</h4>
                    <p>Minutes per appointment</p>
                </div>
            </div>
            <div class="metric-value">
                <span class="metric-number">12</span>
                <span class="metric-change negative">-2 min</span>
            </div>
            <div class="metric-progress">
                <div class="progress-bar">
                    <div class="progress-fill" style="width: 80%"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Wait for DOM to be fully loaded and sidebar initialized
document.addEventListener('DOMContentLoaded', function() {
    // Add a small delay to ensure sidebar is fully initialized
    setTimeout(function() {
        initializeCharts();
        initializeInteractions();
    }, 100);
    
    // Fallback: retry chart initialization after a longer delay if charts don't appear
    setTimeout(function() {
        const charts = document.querySelectorAll('canvas');
        let chartsVisible = 0;
        charts.forEach(canvas => {
            if (canvas.offsetHeight > 0) chartsVisible++;
        });
        
        if (chartsVisible === 0) {
            console.log('Charts not visible, retrying initialization...');
            initializeCharts();
        }
    }, 1000);
});

// Appointment acceptance function removed - patients are now auto-accepted during signup

function initializeCharts() {
    console.log('Initializing charts...');
    
    // Check if Chart.js is loaded
    if (typeof Chart === 'undefined') {
        console.error('Chart.js library not loaded!');
        return;
    }
    
    console.log('Chart.js library loaded successfully');
    
    // Enhanced Gender Distribution Chart
    const genderCtx = document.getElementById('genderChart');
    if (genderCtx) {
        console.log('Creating gender chart...');
        try {
            new Chart(genderCtx, {
            type: 'doughnut',
            data: {
                labels: ['Male Patients', 'Female Patients'],
                datasets: [{
                    data: [<?php echo $male_count; ?>, <?php echo $female_count; ?>],
                    backgroundColor: [
                        'rgba(59, 130, 246, 0.9)',
                        'rgba(236, 72, 153, 0.9)',
                        'rgba(16, 185, 129, 0.9)',
                        'rgba(245, 158, 11, 0.9)'
                    ],
                    borderColor: [
                        'rgba(59, 130, 246, 1)',
                        'rgba(236, 72, 153, 1)',
                        'rgba(16, 185, 129, 1)',
                        'rgba(245, 158, 11, 1)'
                    ],
                    borderWidth: 4,
                    hoverOffset: 15,
                    hoverBorderWidth: 6,
                    hoverBackgroundColor: [
                        'rgba(59, 130, 246, 1)',
                        'rgba(236, 72, 153, 1)',
                        'rgba(16, 185, 129, 1)',
                        'rgba(245, 158, 11, 1)'
                    ]
                }]
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false,
                plugins: { 
                    legend: { 
                        position: 'bottom',
                        labels: {
                            padding: 25,
                            usePointStyle: true,
                            font: {
                                family: 'Inter',
                                size: 14,
                                weight: '600'
                            },
                            color: '#374151'
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(17, 24, 39, 0.95)',
                        titleFont: {
                            family: 'Inter',
                            size: 15,
                            weight: '700'
                        },
                        bodyFont: {
                            family: 'Inter',
                            size: 14,
                            weight: '500'
                        },
                        cornerRadius: 12,
                        displayColors: true,
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                            }
                        }
                    }
                },
                cutout: '60%',
                animation: {
                    animateRotate: true,
                    animateScale: true,
                    duration: 2000,
                    easing: 'easeInOutQuart'
                }
            }
        });
        } catch (error) {
            console.error('Error creating gender chart:', error);
        }
    } else {
        console.error('Gender chart canvas not found');
    }

    // Appointment Trends Chart
    const trendCtx = document.getElementById('appointmentTrendChart');
    if (trendCtx) {
        console.log('Creating appointment trends chart...');
        try {
            new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($weekly_labels, JSON_UNESCAPED_UNICODE); ?>,
                datasets: [{
                    label: 'Appointments',
                    data: <?php echo json_encode($weekly_counts, JSON_UNESCAPED_UNICODE); ?>,
                    borderColor: 'rgba(59, 130, 246, 1)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: 'rgba(59, 130, 246, 1)',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 3,
                    pointRadius: 6,
                    pointHoverRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleFont: {
                            family: 'Inter',
                            size: 14,
                            weight: '600'
                        },
                        bodyFont: {
                            family: 'Inter',
                            size: 13
                        },
                        cornerRadius: 8
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            font: {
                                family: 'Inter',
                                size: 12
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                family: 'Inter',
                                size: 12
                            }
                        }
                    }
                },
                animation: {
                    duration: 2000,
                    easing: 'easeInOutQuart'
                }
            }
        });
        } catch (error) {
            console.error('Error creating appointment trends chart:', error);
        }
    } else {
        console.error('Appointment trends chart canvas not found');
    }

}

function initializeInteractions() {
    // Profile dropdown toggle
    var profileMenu = document.getElementById("profileMenu");
    var profileDropdown = document.getElementById("profileDropdown");
    if (profileMenu && profileDropdown) {
        profileMenu.addEventListener("click", function(e) {
            e.stopPropagation();
            profileDropdown.classList.toggle("show");
        });
    }

    // Notifications
    const notifIcon = document.getElementById("notifBell");
    const notifDropdown = document.getElementById("notifDropdown");
    const notifList = document.getElementById("notifList");
    const notifCount = document.getElementById("notifCount");

    if (notifIcon && notifDropdown) {
        notifIcon.addEventListener("click", (e) => {
            e.stopPropagation();
            notifDropdown.classList.toggle("show");
            if (notifDropdown.classList.contains("show")) {
                if (typeof markAllNotificationsAsRead === 'function') {
                    markAllNotificationsAsRead();
                }
            }
        });
    }

    function fetchNotifications() {
        if (!notifList || !notifCount) return;
        fetch("../notifications/fetch_notifications.php")
            .then(res => res.json())
            .then(data => {
                notifList.innerHTML = "";
                if (data.length === 0) {
                    notifList.innerHTML = "<div class='notif-item'>No new notifications</div>";
                    notifCount.style.display = "none";
                    return;
                }
                let unread = data.filter(n => n.is_read == 0).length;
                notifCount.textContent = unread;
                notifCount.style.display = unread > 0 ? "flex" : "none";
                data.forEach(notif => {
                    let notifDiv = document.createElement("div");
                    notifDiv.className = "notif-item";
                    if (notif.link && notif.link !== "#") {
                        notifDiv.innerHTML = `
                            <a href="${notif.link}">
                                ${notif.message}
                            </a>
                            <small>${notif.created_at}</small>
                        `;
                    } else {
                        notifDiv.innerHTML = `
                            <span>${notif.message}</span>
                            <small>${notif.created_at}</small>
                        `;
                    }
                    notifList.appendChild(notifDiv);
                });
            })
            .catch(err => {
                console.error("Error fetching notifications:", err);
                if (notifList) notifList.innerHTML = "<div class='notif-item' style='color:red;'>Failed to load notifications</div>";
            });
    }

    function markAllNotificationsAsRead() {
        fetch("../notifications/mark_notifications_read.php", {
            method: "POST",
            headers: {"Content-Type": "application/x-www-form-urlencoded"},
            body: "mark_all=1"
        })
        .then(res => res.json())
        .then(data => {
            if (data.success && notifCount) {
                notifCount.textContent = "0";
                notifCount.style.display = "none";
                fetchNotifications();
            }
        })
        .catch(err => console.error("Error marking notifications as read:", err));
    }

    // Auto-refresh notifications every 5s
    setInterval(fetchNotifications, 5000);
    fetchNotifications();

    // Close dropdowns when clicking outside
    document.addEventListener("click", (e) => {
        if (notifIcon && notifDropdown && !notifIcon.contains(e.target) && !notifDropdown.contains(e.target)) {
            notifDropdown.classList.remove("show");
        }
        if (profileMenu && profileDropdown && !profileMenu.contains(e.target)) {
            profileDropdown.classList.remove("show");
        }
    });

    // Mobile menu toggle
    var mobileToggle = document.querySelector('.mobile-menu-toggle');
    if (mobileToggle) {
        mobileToggle.addEventListener('click', function() {
            const sidebar = document.getElementById('doctorSidebar');
            const backdrop = document.getElementById('doctorSidebarBackdrop');
            if (sidebar) {
                sidebar.classList.toggle('mobile-open');
                if (backdrop) {
                    if (sidebar.classList.contains('mobile-open')) {
                        backdrop.classList.add('active');
                    } else {
                        backdrop.classList.remove('active');
                    }
                }
            }
        });
    }
    
    // Close sidebar when clicking backdrop
    const backdrop = document.getElementById('doctorSidebarBackdrop');
    if (backdrop) {
        backdrop.addEventListener('click', function() {
            const sidebar = document.getElementById('doctorSidebar');
            if (sidebar) {
                sidebar.classList.remove('mobile-open');
                backdrop.classList.remove('active');
            }
        });
    }

    // Chart period controls
    const chartButtons = document.querySelectorAll('.chart-btn');
    if (chartButtons && chartButtons.forEach) {
        chartButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                chartButtons.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
            });
        });
    }
}

// Add enhanced status badge styles
const dashboardStatusStyles = document.createElement('style');
dashboardStatusStyles.textContent = `
    .status-badge {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-family: 'Inter', sans-serif;
    }
    .status-completed {
        background: linear-gradient(135deg, #d1fae5, #a7f3d0);
        color: #065f46;
        box-shadow: 0 2px 4px rgba(16, 185, 129, 0.2);
    }
    .status-confirmed {
        background: linear-gradient(135deg, #dbeafe, #bfdbfe);
        color: #1e40af;
        box-shadow: 0 2px 4px rgba(59, 130, 246, 0.2);
    }
    .status-pending {
        background: linear-gradient(135deg, #fef3c7, #fde68a);
        color: #92400e;
        box-shadow: 0 2px 4px rgba(245, 158, 11, 0.2);
    }
    .status-declined {
        background: linear-gradient(135deg, #fee2e2, #fecaca);
        color: #991b1b;
        box-shadow: 0 2px 4px rgba(239, 68, 68, 0.2);
    }
`;
document.head.appendChild(dashboardStatusStyles);
</script>
<script src="../js/doctor_sidebar.js"></script>
<script src="../js/script.js"></script>
</body>
</html>