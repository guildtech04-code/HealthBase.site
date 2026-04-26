<?php
session_start();
require_once '../config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

// Get user role
$user_role = $_SESSION['role'] ?? '';

// Only allow assistant and admin access
if (!in_array($user_role, ['assistant', 'admin'])) {
    header('Location: ../dashboard/healthbase_dashboard.php');
    exit();
}

// Get user info for sidebar
$user_query = $conn->prepare("SELECT username, email, role FROM users WHERE id = ?");
$user_query->bind_param("i", $_SESSION['user_id']);
$user_query->execute();
$user_result = $user_query->get_result()->fetch_assoc();

$sidebar_user_data = [
    'username' => htmlspecialchars($user_result['username']),
    'email' => htmlspecialchars($user_result['email']),
    'role' => htmlspecialchars($user_result['role'])
];

// Get statistics
$total_users = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
$total_doctors = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='doctor'")->fetch_assoc()['count'];
$total_patients = $conn->query("SELECT COUNT(*) as count FROM patients")->fetch_assoc()['count'];
$total_appointments = $conn->query("SELECT COUNT(*) as count FROM appointments")->fetch_assoc()['count'];
$pending_appointments = $conn->query("SELECT COUNT(*) as count FROM appointments WHERE status='Pending'")->fetch_assoc()['count'];
$confirmed_appointments = $conn->query("SELECT COUNT(*) as count FROM appointments WHERE status='Confirmed'")->fetch_assoc()['count'];
$completed_appointments = $conn->query("SELECT COUNT(*) as count FROM appointments WHERE status='Completed'")->fetch_assoc()['count'];

// Appointments per category across all doctors (last 30 days)
$category_labels = ['Derma', 'Orthopedic', 'Gastro'];
$category_counts = [0, 0, 0];
$category_query = $conn->query("
    SELECT
        CASE
            WHEN UPPER(COALESCE(p.health_concern, '')) LIKE '%DERMA%' THEN 'Derma'
            WHEN UPPER(COALESCE(p.health_concern, '')) LIKE '%ORTHO%' THEN 'Orthopedic'
            WHEN UPPER(COALESCE(p.health_concern, '')) LIKE '%GASTRO%' THEN 'Gastro'
            ELSE 'Other'
        END AS category_bucket,
        COUNT(*) AS total
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    WHERE DATE(a.appointment_date) BETWEEN DATE_SUB(CURDATE(), INTERVAL 29 DAY) AND CURDATE()
      AND LOWER(a.status) NOT IN ('declined', 'cancelled', 'canceled')
    GROUP BY category_bucket
");
if ($category_query) {
    while ($r = $category_query->fetch_assoc()) {
        $bucket = (string) ($r['category_bucket'] ?? 'Other');
        $count = (int) ($r['total'] ?? 0);
        if ($bucket === 'Derma') $category_counts[0] = $count;
        elseif ($bucket === 'Orthopedic') $category_counts[1] = $count;
        elseif ($bucket === 'Gastro') $category_counts[2] = $count;
        // Ignore "Other" bucket per dashboard requirement.
    }
}
$category_range_label = date('M j, Y', strtotime('-29 days')) . ' - ' . date('M j, Y');

// Get recent appointments
$recent_appointments = $conn->query("
    SELECT a.id, a.appointment_date, a.status,
           CONCAT(p.first_name, ' ', p.last_name) as patient_name,
           CONCAT(u.first_name, ' ', u.last_name) as doctor_name
    FROM appointments a
    LEFT JOIN patients p ON a.patient_id = p.id
    LEFT JOIN users u ON a.doctor_id = u.id
    ORDER BY a.appointment_date DESC
    LIMIT 10
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assistant Dashboard - HealthBase</title>
    <link rel="icon" type="image/x-icon" href="/assets/icons/favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/assistant.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="assistant-dashboard-page">
<?php include 'includes/assistant_sidebar.php'; ?>

<div class="assistant-main-content">
    <!-- Header -->
    <header class="assistant-header">
        <div class="assistant-header-left">
            <h1 class="assistant-welcome">Assistant Dashboard</h1>
            <p class="assistant-subtitle">Manage users, monitor activities, and oversee system operations</p>
        </div>
        <div class="assistant-header-right">
            <div class="current-time" style="display: flex; align-items: center; gap: 8px; padding: 8px 16px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
                <i class="fas fa-clock" style="color: #3b82f6;"></i>
                <span id="currentDateTime" style="color: #1e293b; font-weight: 600; font-size: 14px;"></span>
            </div>
        </div>
    </header>

    <div class="assistant-dashboard-content">
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon users">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3>Total Users</h3>
                    <div class="stat-value"><?php echo $total_users; ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon doctors">
                    <i class="fas fa-user-md"></i>
                </div>
                <div class="stat-content">
                    <h3>Doctors</h3>
                    <div class="stat-value"><?php echo $total_doctors; ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon patients">
                    <i class="fas fa-user-injured"></i>
                </div>
                <div class="stat-content">
                    <h3>Patients</h3>
                    <div class="stat-value"><?php echo $total_patients; ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon appointments">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-content">
                    <h3>Appointments</h3>
                    <div class="stat-value"><?php echo $total_appointments; ?></div>
                </div>
            </div>
        </div>

        <!-- Appointment Status -->
        <div class="content-card">
            <h3><i class="fas fa-chart-pie"></i> Appointment Status</h3>
            <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 0;">
                <div style="text-align: center; padding: 20px;">
                    <div style="font-size: 36px; font-weight: 700; color: #f59e0b;"><?php echo $pending_appointments; ?></div>
                    <div style="color: #64748b; font-weight: 500;">Pending</div>
                </div>
                <div style="text-align: center; padding: 20px;">
                    <div style="font-size: 36px; font-weight: 700; color: #10b981;"><?php echo $confirmed_appointments; ?></div>
                    <div style="color: #64748b; font-weight: 500;">Confirmed</div>
                </div>
                <div style="text-align: center; padding: 20px;">
                    <div style="font-size: 36px; font-weight: 700; color: #3b82f6;"><?php echo $completed_appointments; ?></div>
                    <div style="color: #64748b; font-weight: 500;">Completed</div>
                </div>
            </div>
        </div>

        <div class="content-card">
            <h3><i class="fas fa-layer-group"></i> Appointments per Category (All Doctors)</h3>
            <p style="margin: -4px 0 12px 0; color:#64748b; font-size:12px;">
                Date covered: <?php echo htmlspecialchars($category_range_label); ?>
            </p>
            <div style="height: 300px;">
                <canvas id="assistantCategoryChart"></canvas>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <!-- Recent Appointments -->
            <div class="content-card">
                <h3><i class="fas fa-clock"></i> Recent Appointments</h3>
                <div style="max-height: 400px; overflow-y: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="border-bottom: 2px solid #e2e8f0;">
                                <th style="padding: 12px; text-align: left; font-weight: 600; color: #64748b; font-size: 13px;">Date</th>
                                <th style="padding: 12px; text-align: left; font-weight: 600; color: #64748b; font-size: 13px;">Patient</th>
                                <th style="padding: 12px; text-align: left; font-weight: 600; color: #64748b; font-size: 13px;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($appointment = $recent_appointments->fetch_assoc()): ?>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 15px; color: #334155; font-size: 14px;">
                                    <?php echo date('M d, Y H:i', strtotime($appointment['appointment_date'])); ?>
                                </td>
                                <td style="padding: 15px; color: #334155; font-size: 14px;">
                                    <?php echo htmlspecialchars($appointment['patient_name']); ?>
                                </td>
                                <td style="padding: 15px;">
                                    <span style="padding: 5px 12px; border-radius: 6px; font-size: 12px; font-weight: 600;
                                        <?php 
                                        $status = strtolower($appointment['status']);
                                        if ($status == 'pending') echo 'background: #fef3c7; color: #92400e;';
                                        elseif ($status == 'confirmed') echo 'background: #d1fae5; color: #065f46;';
                                        elseif ($status == 'completed') echo 'background: #dbeafe; color: #1e40af;';
                                        else echo 'background: #fee2e2; color: #991b1b;';
                                        ?>">
                                        <?php echo $appointment['status']; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="content-card">
                <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                <div style="display: flex; flex-direction: column; gap: 15px;">
                    <a href="user_management.php" style="padding: 15px; background: #eff6ff; border-radius: 8px; text-decoration: none; color: #1e40af; font-weight: 600; transition: all 0.3s;">
                        <i class="fas fa-users-cog" style="margin-right: 10px;"></i> Manage Users
                    </a>
                    <a href="audit_logs.php" style="padding: 15px; background: #f0fdf4; border-radius: 8px; text-decoration: none; color: #065f46; font-weight: 600; transition: all 0.3s;">
                        <i class="fas fa-clipboard-list" style="margin-right: 10px;"></i> View Audit Logs
                    </a>
                    <a href="appointments_management.php" style="padding: 15px; background: #fef3c7; border-radius: 8px; text-decoration: none; color: #92400e; font-weight: 600; transition: all 0.3s;">
                        <i class="fas fa-calendar-check" style="margin-right: 10px;"></i> Manage Appointments
                    </a>
                    <a href="system_settings.php" style="padding: 15px; background: #f5f3ff; border-radius: 8px; text-decoration: none; color: #6b21a8; font-weight: 600; transition: all 0.3s;">
                        <i class="fas fa-cog" style="margin-right: 10px;"></i> System Settings
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Update date and time every second
function updateDateTime() {
    const now = new Date();
    const options = { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric',
        hour: '2-digit', 
        minute: '2-digit',
        second: '2-digit',
        hour12: true 
    };
    const datetimeElement = document.getElementById('currentDateTime');
    if (datetimeElement) {
        datetimeElement.textContent = now.toLocaleString('en-US', options);
    }
}

// Update immediately and then every second
updateDateTime();
setInterval(updateDateTime, 1000);

// Category chart
(function initAssistantCategoryChart() {
    const ctx = document.getElementById('assistantCategoryChart');
    if (!ctx || typeof Chart === 'undefined') return;
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($category_labels, JSON_UNESCAPED_UNICODE); ?>,
            datasets: [{
                label: 'Appointments',
                data: <?php echo json_encode($category_counts, JSON_UNESCAPED_UNICODE); ?>,
                backgroundColor: [
                    'rgba(16, 185, 129, 0.9)',
                    'rgba(59, 130, 246, 0.9)',
                    'rgba(245, 158, 11, 0.9)'
                ],
                borderColor: [
                    'rgba(16, 185, 129, 1)',
                    'rgba(59, 130, 246, 1)',
                    'rgba(245, 158, 11, 1)'
                ],
                borderWidth: 2,
                borderRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 } },
                x: { grid: { display: false } }
            }
        }
    });
})();
</script>

</body>
</html>
