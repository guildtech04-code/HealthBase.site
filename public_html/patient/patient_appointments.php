<?php
// patient_appointments.php - Patient's own appointments view
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include("../config/db_connect.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch patient info
$query = $conn->prepare("SELECT username, email, role FROM users WHERE id = ?");
$query->bind_param("i", $user_id);
$query->execute();
$result = $query->get_result();
$user = $result->fetch_assoc();
$username = htmlspecialchars($user['username']);
$email = htmlspecialchars($user['email']);
$role = htmlspecialchars($user['role']);

// Pass user data to sidebar
$sidebar_user_data = [
    'username' => $username,
    'email' => $email,
    'role' => $role
];

// Get patient ID from patients table
$patient_query = $conn->prepare("SELECT id FROM patients WHERE user_id = ?");
$patient_query->bind_param("i", $user_id);
$patient_query->execute();
$patient_result = $patient_query->get_result();
$patient_data = $patient_result->fetch_assoc();
$patient_id = $patient_data ? $patient_data['id'] : null;

// Check if user has patient record
if (!$patient_id) {
    header("Location: create_patient_record.php");
    exit();
}

// Fetch patient's appointments
$appointments = $conn->prepare("
    SELECT a.id, a.appointment_date, a.status,
           CONCAT(u.first_name, ' ', u.last_name) AS doctor_name, u.specialization
    FROM appointments a
    JOIN users u ON a.doctor_id = u.id
    WHERE a.patient_id=? 
    ORDER BY a.appointment_date DESC
");
$appointments->bind_param("i", $patient_id);
$appointments->execute();
$appointments_result = $appointments->get_result();

// Count appointments by status
$status_counts = $conn->prepare("
    SELECT status, COUNT(*) as count 
    FROM appointments 
    WHERE patient_id=?
    GROUP BY status
");
$status_counts->bind_param("i", $patient_id);
$status_counts->execute();
$status_result = $status_counts->get_result();

$appointment_stats = [
    'confirmed' => 0,
    'pending' => 0,
    'completed' => 0,
    'declined' => 0
];

while ($row = $status_result->fetch_assoc()) {
    $status = strtolower($row['status']);
    if (isset($appointment_stats[$status])) {
        $appointment_stats[$status] = $row['count'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Appointments - HealthBase</title>
    <link rel="icon" type="image/x-icon" href="/assets/icons/favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="css/patient_dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="patient-dashboard-page">
    <?php include 'includes/patient_sidebar.php'; ?>
    
    <div class="patient-main-content">
        <!-- Header -->
        <header class="patient-header">
            <div class="patient-header-left">
                <h1 class="patient-welcome">My Appointments</h1>
                <p class="patient-subtitle">View and manage your medical appointments</p>
            </div>
            <div class="patient-header-right">
                <a href="/appointments/scheduling.php" class="btn-primary">
                    <i class="fas fa-calendar-plus"></i>
                    Book New Appointment
                </a>
            </div>
        </header>

        <!-- Dashboard Content -->
        <div class="patient-dashboard-content">
            <!-- Stats Cards -->
            <div class="patient-stats-grid">
                <div class="patient-stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $appointment_stats['confirmed']; ?></h3>
                        <p>Confirmed</p>
                    </div>
                </div>
                
                <div class="patient-stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $appointment_stats['pending']; ?></h3>
                        <p>Pending</p>
                    </div>
                </div>
                
                <div class="patient-stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $appointment_stats['completed']; ?></h3>
                        <p>Completed</p>
                    </div>
                </div>
                
                <div class="patient-stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $appointment_stats['declined']; ?></h3>
                        <p>Declined</p>
                    </div>
                </div>
            </div>

            <!-- Appointments List -->
            <div class="patient-card appointments-list-card">
                <div class="patient-card-header">
                    <h3 class="patient-card-title">
                        <i class="fas fa-calendar-alt"></i>
                        All Appointments
                    </h3>
                </div>
                <div class="appointments-list">
                    <?php if ($appointments_result->num_rows > 0): ?>
                        <?php while ($row = $appointments_result->fetch_assoc()) { ?>
                            <div class="patient-appointment-item">
                                <div class="patient-appointment-info">
                                    <h4><?php echo htmlspecialchars($row['doctor_name']); ?></h4>
                                    <p><?php echo htmlspecialchars($row['specialization']); ?></p>
                                    <p><?php echo date("d M Y h:i A", strtotime($row['appointment_date'])); ?></p>
                                </div>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <span class="patient-status-badge patient-status-<?php echo strtolower($row['status']); ?>">
                                        <?php echo htmlspecialchars(ucfirst($row['status'])); ?>
                                    </span>
                                    <?php if (in_array(strtolower($row['status']), ['pending', 'confirmed'])): ?>
                                        <div style="display: flex; gap: 8px;">
                                            <a href="reschedule_appointment.php?id=<?php echo $row['id']; ?>" 
                                               style="padding: 8px 16px; background: #3b82f6; color: white; border-radius: 6px; text-decoration: none; font-size: 13px;">
                                                <i class="fas fa-calendar-edit"></i> Reschedule
                                            </a>
                                            <a href="cancel_appointment.php?id=<?php echo $row['id']; ?>" 
                                               onclick="return confirm('Are you sure you want to cancel this appointment?');"
                                               style="padding: 8px 16px; background: #ef4444; color: white; border-radius: 6px; text-decoration: none; font-size: 13px;">
                                                <i class="fas fa-times"></i> Cancel
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php } ?>
                    <?php else: ?>
                        <div class="patient-appointment-item">
                            <div class="patient-appointment-info">
                                <h4>No appointments found</h4>
                                <p>You haven't booked any appointments yet</p>
                            </div>
                            <a href="/appointments/scheduling.php" class="btn-primary">Book Appointment</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="js/patient_sidebar.js"></script>
</body>
</html>
