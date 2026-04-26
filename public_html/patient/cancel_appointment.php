<?php
require_once __DIR__ . '/../includes/auth_guard.php';
ensure_role(['user']);
require '../config/db_connect.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../appointments/notification_helper.php';

$user_id = $_SESSION['user_id'];
$appointment_id = (int)($_GET['id'] ?? 0);

if ($appointment_id <= 0) {
    header('Location: patient_appointments.php');
    exit;
}

// Get patient ID
$patient_query = $conn->prepare("SELECT id FROM patients WHERE user_id = ?");
$patient_query->bind_param("i", $user_id);
$patient_query->execute();
$patient_result = $patient_query->get_result();
$patient_data = $patient_result->fetch_assoc();

if (!$patient_data) {
    header('Location: create_patient_record.php');
    exit;
}

$patient_id = $patient_data['id'];

// Verify appointment belongs to this patient
$appt_query = $conn->prepare("
    SELECT a.id, a.appointment_date, a.status, a.doctor_id,
           CONCAT(u.first_name, ' ', u.last_name) AS doctor_name, 
           u.specialization 
    FROM appointments a
    JOIN users u ON a.doctor_id = u.id
    WHERE a.id = ? AND a.patient_id = ?
");
$appt_query->bind_param("ii", $appointment_id, $patient_id);
$appt_query->execute();
$appt_result = $appt_query->get_result();
$appointment = $appt_result->fetch_assoc();

if (!$appointment) {
    header('Location: patient_appointments.php?error=notfound');
    exit;
}

$doctor_id = (int)$appointment['doctor_id'];

// Handle cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    require_post_csrf();
    
    $conn->begin_transaction();
    try {
        $old_datetime = $appointment['appointment_date'];
        
        $update_stmt = $conn->prepare("UPDATE appointments SET status = 'Cancelled' WHERE id = ? AND patient_id = ?");
        $update_stmt->bind_param("ii", $appointment_id, $patient_id);
        if (!$update_stmt->execute()) {
            throw new Exception('Failed to cancel appointment.');
        }
        
        // Log change
        $chg_stmt = $conn->prepare("
            INSERT INTO appointment_changes (appointment_id, changed_by_user_id, change_type, old_datetime, new_datetime, reason)
            VALUES (?, ?, 'cancel', ?, NULL, NULL)
        ");
        $chg_stmt->bind_param("iis", $appointment_id, $user_id, $old_datetime);
        if (!$chg_stmt->execute()) {
            throw new Exception('Failed to log appointment change.');
        }
        
        // Notify doctor
        $fmt = date("M d, Y h:i A", strtotime($old_datetime));
        addNotification($conn, $doctor_id, 'appointment_cancelled', $appointment_id, $fmt);
        
        $conn->commit();
        header('Location: patient_appointments.php?success=cancelled');
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Failed to cancel appointment.";
    }
}

include 'includes/patient_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Cancel Appointment - HealthBase</title>
    <link rel="icon" type="image/x-icon" href="../assets/icons/favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="css/patient_dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .cancel-container {
            max-width: 600px;
            margin: 30px auto;
            padding: 30px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
        }
        .appointment-info {
            background: #f8fafc;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            border-left: 4px solid #ef4444;
        }
        .appointment-info h3 {
            margin: 0 0 10px 0;
            color: #1e293b;
        }
        .appointment-info p {
            margin: 5px 0;
            color: #64748b;
        }
        .btn-group {
            display: flex;
            gap: 15px;
            margin-top: 25px;
        }
        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-family: 'Inter', sans-serif;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            flex: 1;
        }
        .btn-secondary {
            background: #6b7280;
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-family: 'Inter', sans-serif;
            font-size: 16px;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            flex: 1;
        }
        .alert-error {
            background: #fee2e2;
            color: #dc2626;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #fecaca;
        }
    </style>
</head>
<body class="patient-dashboard-page">
    <div class="patient-main-content">
        <header class="patient-header">
            <div class="patient-header-left">
                <h1 class="patient-welcome">Cancel Appointment</h1>
                <p class="patient-subtitle">Confirm appointment cancellation</p>
            </div>
        </header>

        <div class="cancel-container">
            <?php if (isset($error)): ?>
                <div class="alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="appointment-info">
                <h3>Appointment Details</h3>
                <p><strong>Doctor:</strong> <?php echo htmlspecialchars($appointment['doctor_name']); ?></p>
                <p><strong>Specialization:</strong> <?php echo htmlspecialchars($appointment['specialization'] ?? 'General'); ?></p>
                <p><strong>Date & Time:</strong> <?php echo date("F j, Y h:i A", strtotime($appointment['appointment_date'])); ?></p>
                <p><strong>Status:</strong> <?php echo htmlspecialchars(ucfirst($appointment['status'])); ?></p>
            </div>

            <div style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <i class="fas fa-exclamation-triangle"></i> Are you sure you want to cancel this appointment? This action cannot be undone.
            </div>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                <input type="hidden" name="confirm" value="1">
                
                <div class="btn-group">
                    <button type="submit" class="btn-danger">
                        <i class="fas fa-times"></i> Cancel Appointment
                    </button>
                    <a href="patient_appointments.php" class="btn-secondary">
                        <i class="fas fa-arrow-left"></i> Go Back
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script src="js/patient_sidebar.js"></script>
</body>
</html>

