<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

$user_role = $_SESSION['role'] ?? '';
if (!in_array($user_role, ['assistant', 'admin'])) {
    header('Location: ../dashboard/healthbase_dashboard.php');
    exit();
}

$user_query = $conn->prepare("SELECT username, email, role FROM users WHERE id = ?");
$user_query->bind_param("i", $_SESSION['user_id']);
$user_query->execute();
$user_result = $user_query->get_result()->fetch_assoc();

$sidebar_user_data = [
    'username' => htmlspecialchars($user_result['username']),
    'email' => htmlspecialchars($user_result['email']),
    'role' => htmlspecialchars($user_result['role'])
];

// Get audit logs (appointments history as example)
$audit_logs = $conn->query("
    SELECT a.id, a.appointment_date, a.status,
           CONCAT(p.first_name, ' ', p.last_name) as patient_name,
           CONCAT(u.first_name, ' ', u.last_name) as doctor_name
    FROM appointments a
    LEFT JOIN patients p ON a.patient_id = p.id
    LEFT JOIN users u ON a.doctor_id = u.id
    ORDER BY a.appointment_date DESC
    LIMIT 50
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Logs - HealthBase</title>
    <link rel="icon" type="image/x-icon" href="/assets/icons/favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/assistant.css">
</head>
<body class="assistant-dashboard-page">
<?php include 'includes/assistant_sidebar.php'; ?>

<div class="assistant-main-content">
    <header class="assistant-header">
        <div class="assistant-header-left">
            <h1 class="assistant-welcome">Audit Logs</h1>
            <p class="assistant-subtitle">Track and monitor all system activities</p>
        </div>
        <div class="assistant-header-right">
            <div class="current-time" style="display: flex; align-items: center; gap: 8px; padding: 8px 16px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
                <i class="fas fa-clock" style="color: #3b82f6;"></i>
                <span id="currentDateTime" style="color: #1e293b; font-weight: 600; font-size: 14px;"></span>
            </div>
        </div>
    </header>

    <div class="assistant-dashboard-content">
        <div class="content-card">
            <h3><i class="fas fa-clipboard-list"></i> Activity Logs</h3>
            <div style="overflow-x: auto; max-height: 600px;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 2px solid #e2e8f0;">
                            <th style="padding: 12px; text-align: left; font-weight: 600; color: #64748b; font-size: 13px;">Date</th>
                            <th style="padding: 12px; text-align: left; font-weight: 600; color: #64748b; font-size: 13px;">Action</th>
                            <th style="padding: 12px; text-align: left; font-weight: 600; color: #64748b; font-size: 13px;">User</th>
                            <th style="padding: 12px; text-align: left; font-weight: 600; color: #64748b; font-size: 13px;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($log = $audit_logs->fetch_assoc()): ?>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 15px; color: #334155; font-size: 14px;">
                                <?php echo date('M d, Y H:i', strtotime($log['appointment_date'])); ?>
                            </td>
                            <td style="padding: 15px; color: #334155; font-size: 14px;">
                                Appointment created for <?php echo htmlspecialchars($log['patient_name']); ?>
                            </td>
                            <td style="padding: 15px; color: #334155; font-size: 14px;">
                                <?php echo htmlspecialchars($log['doctor_name']); ?>
                            </td>
                            <td style="padding: 15px;">
                                <span style="padding: 5px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; background: #dbeafe; color: #1e40af;">
                                    <?php echo ucfirst($log['status']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
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

updateDateTime();
setInterval(updateDateTime, 1000);
</script>

</body>
</html>
