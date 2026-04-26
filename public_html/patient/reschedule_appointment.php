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

// Verify appointment belongs to this patient and get details
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
$error = '';
$success = '';

// Handle reschedule
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    
    $new_date = $_POST['date'] ?? '';
    $hour = (int)($_POST['hour'] ?? 0);
    $ampm = $_POST['ampm'] ?? 'AM';
    
    // Convert to 24-hour format
    if ($ampm === "PM" && $hour != 12) { $hour += 12; }
    elseif ($ampm === "AM" && $hour == 12) { $hour = 0; }
    $timeFormatted = sprintf("%02d:00:00", $hour);
    
    $new_appointment_date = $new_date . " " . $timeFormatted;
    
    // Check for conflicts
    $conflict_check = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM appointments 
        WHERE doctor_id = ? 
        AND appointment_date = ? 
        AND LOWER(status) IN ('pending', 'confirmed')
        AND id != ?
    ");
    $conflict_check->bind_param("isi", $doctor_id, $new_appointment_date, $appointment_id);
    $conflict_check->execute();
    $conflict_result = $conflict_check->get_result();
    $conflict = $conflict_result->fetch_assoc();
    
    if ($conflict['count'] > 0) {
        $error = "This time slot is already booked. Please choose another time.";
    } elseif (strtotime($new_appointment_date) < time()) {
        $error = "Cannot reschedule to a past date/time.";
    } else {
        // Begin transaction
        $conn->begin_transaction();
        try {
            $old_datetime = $appointment['appointment_date'];
            
            // Get current status to preserve it if it was confirmed
            $current_status = $appointment['status'];
            $new_status = (strtolower($current_status) === 'confirmed') ? 'confirmed' : 'pending';
            
            $update_stmt = $conn->prepare("
                UPDATE appointments 
                SET appointment_date = ?, status = ?
                WHERE id = ? AND patient_id = ?
            ");
            $update_stmt->bind_param("ssii", $new_appointment_date, $new_status, $appointment_id, $patient_id);
            if (!$update_stmt->execute()) {
                throw new Exception('Failed to update appointment.');
            }
            
            // Log change
            $chg_stmt = $conn->prepare("
                INSERT INTO appointment_changes (appointment_id, changed_by_user_id, change_type, old_datetime, new_datetime, reason)
                VALUES (?, ?, 'reschedule', ?, ?, NULL)
            ");
            $chg_stmt->bind_param("iiss", $appointment_id, $user_id, $old_datetime, $new_appointment_date);
            if (!$chg_stmt->execute()) {
                throw new Exception('Failed to log appointment change.');
            }
            
            // Notify doctor with new date
            $fmt = date("M d, Y h:i A", strtotime($new_appointment_date));
            addNotification($conn, $doctor_id, 'appointment_rescheduled', $appointment_id, $fmt);
            
            $conn->commit();
            header('Location: patient_appointments.php?success=rescheduled');
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Failed to reschedule appointment.";
        }
    }
}

// Get doctor's existing appointments for calendar
$existing_appt_query = $conn->prepare("
    SELECT appointment_date 
    FROM appointments 
    WHERE doctor_id = ? 
    AND LOWER(status) IN ('pending', 'confirmed')
    AND id != ?
");
$existing_appt_query->bind_param("ii", $doctor_id, $appointment_id);
$existing_appt_query->execute();
$existing_result = $existing_appt_query->get_result();

$booked_dates = [];
while ($row = $existing_result->fetch_assoc()) {
    $booked_dates[] = date("Y-m-d", strtotime($row['appointment_date']));
}

include 'includes/patient_sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reschedule Appointment - HealthBase</title>
    <link rel="icon" type="image/x-icon" href="../assets/icons/favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="css/patient_dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .reschedule-container {
            max-width: 700px;
            margin: 30px auto;
            padding: 30px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
        }
        .current-appt {
            background: #eff6ff;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            border-left: 4px solid #3b82f6;
        }
        .current-appt h3 { margin: 0 0 10px 0; color: #1e293b; }
        .current-appt p { margin: 5px 0; color: #64748b; }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #334155;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 12px;
            border: 1.5px solid #e2e8f0;
            border-radius: 8px;
            font-family: 'Inter', sans-serif;
            font-size: 15px;
        }
        .time-slots {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        .time-slot {
            padding: 12px;
            text-align: center;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        .time-slot:hover { border-color: #3b82f6; background: #eff6ff; }
        .time-slot.selected { background: #3b82f6; border-color: #3b82f6; color: white; }
        .time-slot.unavailable { background: #f1f5f9; opacity: 0.6; cursor: not-allowed; }
        .btn-primary {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-family: 'Inter', sans-serif;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            margin-top: 20px;
        }
        .alert { padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .alert-error { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
    </style>
</head>
<body class="patient-dashboard-page">
    <div class="patient-main-content">
        <header class="patient-header">
            <div class="patient-header-left">
                <h1 class="patient-welcome">Reschedule Appointment</h1>
                <p class="patient-subtitle">Choose a new date and time</p>
            </div>
        </header>

        <div class="reschedule-container">
            <div class="current-appt">
                <h3>Current Appointment</h3>
                <p><strong>Doctor:</strong> <?php echo htmlspecialchars($appointment['doctor_name']); ?></p>
                <p><strong>Specialization:</strong> <?php echo htmlspecialchars($appointment['specialization'] ?? 'General'); ?></p>
                <p><strong>Current Date & Time:</strong> <?php echo date("F j, Y h:i A", strtotime($appointment['appointment_date'])); ?></p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="rescheduleForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                
                <div class="form-group">
                    <label><i class="fas fa-calendar-alt"></i> Select New Date</label>
                    <input type="text" id="calendar" name="date" placeholder="Select Date" required>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-clock"></i> Select New Time</label>
                    <div class="time-slots" id="timeSlots">
                        <!-- Generated by JS -->
                    </div>
                    <input type="hidden" name="hour" id="selectedHour" required>
                    <input type="hidden" name="ampm" id="selectedAmpm" required>
                </div>

                <button type="submit" class="btn-primary" id="submitBtn" disabled>
                    <i class="fas fa-calendar-check"></i> Confirm Reschedule
                </button>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
    // Calendar
    const calendar = flatpickr("#calendar", {
        altInput: true,
        altFormat: "F j, Y",
        dateFormat: "Y-m-d",
        minDate: "today",
        inline: true,
        onChange: function(selectedDates) {
            checkAvailability();
        }
    });

    function hour24To12Parts(hour24) {
        const ampm = hour24 >= 12 ? "PM" : "AM";
        let h = hour24 % 12;
        if (h === 0) h = 12;
        return { label: h + ":00 " + ampm, clockHour: h, ampm: ampm };
    }

    // Generate time slots
    function generateTimeSlots() {
        const container = document.getElementById("timeSlots");
        container.innerHTML = "";
        
        for (let i = 9; i <= 17; i++) {
            const slot = document.createElement("div");
            slot.className = "time-slot";
            const parts = hour24To12Parts(i);
            slot.textContent = parts.label;
            slot.dataset.hour = String(parts.clockHour);
            slot.dataset.ampm = parts.ampm;
            slot.dataset.fullHour = i;
            
            slot.addEventListener("click", function() {
                if (!this.classList.contains("unavailable")) {
                    document.querySelectorAll(".time-slot.selected").forEach(s => s.classList.remove("selected"));
                    this.classList.add("selected");
                    document.getElementById("selectedHour").value = this.dataset.hour;
                    document.getElementById("selectedAmpm").value = this.dataset.ampm;
                    document.getElementById("submitBtn").disabled = false;
                }
            });
            
            container.appendChild(slot);
        }
    }

    // Check availability
    function checkAvailability() {
        const selectedDate = document.getElementById("calendar").value;
        if (!selectedDate) return;
        
        fetch(`../appointments/check_availability.php?doctor_id=<?php echo $doctor_id; ?>&date=${selectedDate}`)
            .then(response => response.json())
            .then(data => {
                document.querySelectorAll(".time-slot").forEach(slot => {
                    const fullHour = parseInt(slot.dataset.fullHour);
                    slot.classList.remove("unavailable");
                    
                    if (data.booked_times && data.booked_times.includes(fullHour)) {
                        slot.classList.add("unavailable");
                    }
                });
            })
            .catch(err => console.error("Error:", err));
    }

    generateTimeSlots();
    checkAvailability();
    </script>
    <script src="js/patient_sidebar.js"></script>
</body>
</html>

