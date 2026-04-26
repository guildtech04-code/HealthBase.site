<?php
require_once __DIR__ . '/../includes/auth_guard.php';
ensure_role(['doctor']);
include("../config/db_connect.php");
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/appointment_schema_flags.php';

$apptColFlags = hb_appointments_column_flags($conn);
$apptGuestSelect = $apptColFlags['guest'] ? ', a.guest_first_name, a.guest_last_name' : '';
$apptNotesSelect = $apptColFlags['notes'] ? ', a.notes AS appointment_notes' : '';

// ✅ Protect route
// role ensured above

$user_id = $_SESSION['user_id'];

// Get user information for sidebar
$query = $conn->prepare("SELECT username, email, role, specialization FROM users WHERE id = ?");
$query->bind_param("i", $user_id);
$query->execute();
$result = $query->get_result();
$user = $result->fetch_assoc();
$username = htmlspecialchars($user['username']);
$email = htmlspecialchars($user['email']);
$role = htmlspecialchars($user['role']);
$specialization = htmlspecialchars($user['specialization'] ?? 'General');

// ✅ Handle Accept / Decline / Completed
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['appointment_id'], $_POST['status'])) {
    require_post_csrf();
    $appointment_id = intval($_POST['appointment_id']);
    $new_status = strtolower(trim($_POST['status'])); // Normalize status to lowercase
    
    // Valid status values
    $valid_statuses = ['pending', 'confirmed', 'declined', 'completed', 'cancelled'];
    if (!in_array($new_status, $valid_statuses)) {
        $_SESSION['error'] = "Invalid appointment status.";
        header("Location: appointments.php?error=invalid_status");
        exit();
    }
    
    // Validate appointment_id is positive
    if ($appointment_id <= 0) {
        $_SESSION['error'] = "Invalid appointment ID.";
        header("Location: appointments.php?error=invalid_appointment_id");
        exit();
    }
    
    // Get current appointment status for transition validation
    $get_current = $conn->prepare("SELECT status FROM appointments WHERE id=? AND doctor_id=?");
    $get_current->bind_param("ii", $appointment_id, $user_id);
    $get_current->execute();
    $current_result = $get_current->get_result();
    $current_appt = $current_result->fetch_assoc();
    $get_current->close();
    
    if (!$current_appt) {
        $_SESSION['error'] = "Appointment not found or you don't have permission to access it.";
        header("Location: appointments.php?error=appointment_not_found");
        exit();
    }
    
    $current_status = strtolower($current_appt['status']);
    
    // Validate status transitions (prevent invalid transitions)
    $valid_transitions = [
        'pending' => ['confirmed', 'declined', 'cancelled'],
        'confirmed' => ['completed', 'cancelled'],
        'declined' => [], // Final state
        'completed' => [], // Final state
        'cancelled' => [] // Final state
    ];
    
    if (!isset($valid_transitions[$current_status]) || !in_array($new_status, $valid_transitions[$current_status])) {
        if ($current_status !== $new_status) {
            header("Location: appointments.php?error=invalid_transition&from=$current_status&to=$new_status");
            exit();
        }
    }
    
    // Update appointment
    $update = $conn->prepare("UPDATE appointments SET status=? WHERE id=? AND doctor_id=?");
    $update->bind_param("sii", $new_status, $appointment_id, $user_id);
    if (!$update->execute()) {
        header("Location: appointments.php?error=update_failed");
        exit();
    }
    $update->close();

    // Fetch patient details
    $getPatient = $conn->prepare("
        SELECT p.user_id, p.first_name, p.last_name, a.appointment_date
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        WHERE a.id=? AND a.doctor_id=?
        LIMIT 1
    ");
    $getPatient->bind_param("ii", $appointment_id, $user_id);
    $getPatient->execute();
    $result = $getPatient->get_result();
    $patient = $result->fetch_assoc();
    $getPatient->close();

    if ($patient && $patient['user_id'] > 0) {
        $patient_user_id = $patient['user_id'];
        $patient_name = $patient['first_name'] . " " . $patient['last_name'];
        $apptDate = date("F j, Y h:i A", strtotime($patient['appointment_date']));

        // Prepare notification message based on status (using normalized lowercase)
        if ($new_status === "confirmed") {
            $msg = "✅ $patient_name, your appointment on $apptDate has been accepted.";
            $type = "appointment";
        } elseif ($new_status === "declined") {
            $msg = "❌ $patient_name, your appointment request on $apptDate was declined.";
            $type = "appointment";
        } elseif ($new_status === "completed") {
            $msg = "✔️ $patient_name, your appointment on $apptDate has been marked as completed.";
            $type = "appointment";
        } elseif ($new_status === "cancelled") {
            $msg = "❌ $patient_name, your appointment on $apptDate has been cancelled.";
            $type = "appointment";
        }

        if (!empty($msg)) {
            $link = "appointments.php?appointment_id=" . $appointment_id;
            $notif = $conn->prepare("
                INSERT INTO notifications (user_id, message, type, link, is_read, created_at)
                VALUES (?, ?, ?, ?, 0, NOW())
            ");
            $notif->bind_param("isss", $patient_user_id, $msg, $type, $link);
            $notif->execute();
            $notif->close();
        }
    }

    header("Location: appointments.php");
    exit();
}

$events = [];

// ✅ Pending requests (shown on calendar + actionable list — new bookings use status pending)
$pending_cal = $conn->prepare("
    SELECT a.id, a.appointment_date, p.first_name, p.last_name, p.gender, p.health_concern
    {$apptGuestSelect}{$apptNotesSelect}
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    WHERE a.doctor_id = ? AND LOWER(a.status) = 'pending' AND a.appointment_date >= NOW()
    ORDER BY a.appointment_date ASC
");
$pending_cal->bind_param("i", $user_id);
$pending_cal->execute();
$pending_result = $pending_cal->get_result();
$pending_appointments = [];
while ($row = $pending_result->fetch_assoc()) {
    $pending_appointments[] = $row;
    $events[] = [
        'title' => '⏳ ' . hb_appointments_display_patient_name($row),
        'start' => $row['appointment_date'],
        'color' => '#ca8a04',
        'textColor' => '#ffffff',
        'type' => 'pending',
        'appointmentId' => (int) $row['id'],
    ];
}
$pending_cal->close();

// ✅ Fetch confirmed appointments for calendar
$confirmed = $conn->prepare("
    SELECT a.id, a.appointment_date, p.first_name, p.last_name
    {$apptGuestSelect}{$apptNotesSelect}
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    WHERE a.doctor_id=? AND LOWER(a.status)='confirmed' AND a.appointment_date >= NOW()
");
$confirmed->bind_param("i", $user_id);
$confirmed->execute();
$confirmed_result = $confirmed->get_result();

while ($row = $confirmed_result->fetch_assoc()) {
    $is_followup_confirmed = false;
    if ($apptColFlags['notes']) {
        $note = strtolower(trim((string) ($row['appointment_notes'] ?? '')));
        $is_followup_confirmed = strpos($note, 'follow-up appointment from consultation #') === 0;
    }

    $events[] = [
        "title" => ($is_followup_confirmed ? "🔄 Follow-up: " : "") . hb_appointments_display_patient_name($row),
        "start" => $row['appointment_date'],
        "color" => $is_followup_confirmed ? "#8b5cf6" : "#3b82f6", // Purple for follow-up, blue for regular confirmed
        "textColor" => "#ffffff",
        "type" => $is_followup_confirmed ? "followup" : "confirmed",
        "appointmentId" => (int) $row['id'],
    ];
}
$confirmed->close();

// ✅ Fetch completed appointments with EHR status for calendar
$completed_calendar = $conn->prepare("
    SELECT a.id, a.appointment_date, p.first_name, p.last_name{$apptGuestSelect}{$apptNotesSelect},
           (
             SELECT COUNT(*)
             FROM consultations c
             WHERE c.appointment_id = a.id
               AND TRIM(COALESCE(c.chief_complaint, '')) <> ''
               AND TRIM(COALESCE(c.diagnosis, '')) <> ''
           ) as has_ehr
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    WHERE a.doctor_id=? AND LOWER(a.status)='completed'
");
$completed_calendar->bind_param("i", $user_id);
$completed_calendar->execute();
$completed_calendar_result = $completed_calendar->get_result();

while ($row = $completed_calendar_result->fetch_assoc()) {
    $ehr_status = $row['has_ehr'] > 0 ? " ✓ EHR" : " ⚠ No EHR";
    $events[] = [
        "title" => hb_appointments_display_patient_name($row) . $ehr_status,
        "start" => $row['appointment_date'],
        "color" => $row['has_ehr'] > 0 ? "#10b981" : "#f59e0b", // Green if EHR recorded, orange if not
        "textColor" => "#ffffff",
        "type" => "completed",
        "hasEhr" => $row['has_ehr'] > 0,
        "appointmentId" => (int) $row['id'],
    ];
}
$completed_calendar->close();

// ✅ Fetch follow-up appointments from consultations
$followups = $conn->prepare("
    SELECT c.id AS consultation_id, c.follow_up_date, p.first_name, p.last_name
    {$apptGuestSelect}{$apptNotesSelect}, c.appointment_id,
           a.appointment_date as original_appointment_date,
           TIME(a.appointment_date) as original_appointment_time,
           COALESCE(c.doctor_id, a.doctor_id) AS owner_doctor_id
    FROM consultations c
    JOIN appointments a ON c.appointment_id = a.id
    JOIN patients p ON c.patient_id = p.id
    WHERE COALESCE(c.doctor_id, a.doctor_id)=? AND c.follow_up_date IS NOT NULL 
    AND NOT EXISTS (
        SELECT 1 FROM appointments a2 
        WHERE a2.patient_id = c.patient_id 
        AND a2.doctor_id = COALESCE(c.doctor_id, a.doctor_id)
        AND DATE(a2.appointment_date) = DATE(c.follow_up_date)
        AND a2.status != 'declined'
    )
");
$followups->bind_param("i", $user_id);
$followups->execute();
$followups_result = $followups->get_result();

/**
 * Remap date into a target month/year with deterministic scatter by record key.
 */
$hbRemapMonthYear = static function (?string $rawDate, int $targetYear, int $targetMonth, int $scatterKey): ?DateTime {
    if ($rawDate === null || trim($rawDate) === '') {
        return null;
    }
    try {
        $dt = new DateTime($rawDate);
    } catch (Throwable $e) {
        return null;
    }
    $lastDay = cal_days_in_month(CAL_GREGORIAN, $targetMonth, $targetYear);
    $safeKey = max(1, $scatterKey);
    $safeDay = (($safeKey * 7) % $lastDay) + 1;
    $hour = (($safeKey * 5) % 9) + 8;
    $minute = (($safeKey * 13) % 4) * 15;
    $dt->setDate($targetYear, $targetMonth, $safeDay);
    $dt->setTime($hour, $minute, 0);
    return $dt;
};

while ($row = $followups_result->fetch_assoc()) {
    // If follow_up_date is just a date, combine it with the original appointment time
    $follow_up_datetime = $row['follow_up_date'];
    if (!empty($row['original_appointment_time']) && strpos($follow_up_datetime, ' ') === false) {
        // If it's just a date, add the time from the original appointment
        $follow_up_datetime = $follow_up_datetime . ' ' . $row['original_appointment_time'];
    }
    // Requested date normalization: follow-ups shown on doctor calendar in April 2026.
    $mappedFollowup = $hbRemapMonthYear((string) $follow_up_datetime, 2026, 4, (int) ($row['consultation_id'] ?? 0));
    if ($mappedFollowup) {
        // Only reflect follow-ups from April 24 onward on doctor calendar.
        $cutoffFollowup = new DateTime('2026-04-24 00:00:00');
        if ($mappedFollowup < $cutoffFollowup) {
            continue;
        }
        $follow_up_datetime = $mappedFollowup->format('Y-m-d H:i:s');
    }
    
    $events[] = [
        "title" => "🔄 Follow-up: " . hb_appointments_display_patient_name($row),
        "start" => $follow_up_datetime,
        "color" => "#8b5cf6", // Purple for follow-ups
        "textColor" => "#ffffff",
        "type" => "followup",
        "originalAppointmentDate" => $row['original_appointment_date'],
        "appointmentId" => (int) $row['appointment_id'],
    ];
}
$followups->close();

// ✅ Fetch declined appointments
$declined = $conn->prepare("
    SELECT a.id, a.appointment_date, p.first_name, p.last_name, p.gender, p.health_concern
    {$apptGuestSelect}{$apptNotesSelect}
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    WHERE a.doctor_id=? AND LOWER(a.status)='declined'
    ORDER BY a.appointment_date DESC
");
$declined->bind_param("i", $user_id);
$declined->execute();
$declined_result = $declined->get_result();

// ✅ Fetch completed appointments with EHR status
$completed = $conn->prepare("
    SELECT a.id, a.patient_id, a.appointment_date, p.first_name, p.last_name, p.gender, p.health_concern
    {$apptGuestSelect}{$apptNotesSelect},
           (
             SELECT COUNT(*)
             FROM consultations c
             WHERE c.appointment_id = a.id
               AND TRIM(COALESCE(c.chief_complaint, '')) <> ''
               AND TRIM(COALESCE(c.diagnosis, '')) <> ''
           ) as has_ehr
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    WHERE a.doctor_id=? AND LOWER(a.status)='completed'
    ORDER BY a.appointment_date DESC
");
$completed->bind_param("i", $user_id);
$completed->execute();
$completed_result = $completed->get_result();

/** @param string|null $raw */
function hb_doctor_appt_format_created(?string $raw): string
{
    if ($raw === null || $raw === '') {
        return '—';
    }
    $ts = strtotime($raw);

    return $ts ? date('M j, Y \a\t g:i A', $ts) : '—';
}

$appt_modal_cols = [];
if ($apptColFlags['guest']) {
    $appt_modal_cols[] = 'a.guest_first_name';
    $appt_modal_cols[] = 'a.guest_last_name';
}
if ($apptColFlags['notes']) {
    $appt_modal_cols[] = 'a.notes AS appointment_notes';
}
$appt_modal_cols_sql = $appt_modal_cols !== [] ? implode(', ', $appt_modal_cols) . ', ' : '';

$doctor_appt_details = [];
$modal_q = "
    SELECT a.id, a.patient_id, a.appointment_date, a.status,
           COALESCE(a.created_at, a.updated_at) AS appt_created_at,
           {$appt_modal_cols_sql}
           TRIM(p.first_name) AS patient_first_name, TRIM(p.last_name) AS patient_last_name,
           p.gender, p.health_concern,
           CONCAT(TRIM(bu.first_name), ' ', TRIM(bu.last_name)) AS booker_display_name,
           bu.username AS booker_username,
           (
             SELECT COUNT(*)
             FROM consultations c
             WHERE c.appointment_id = a.id
               AND TRIM(COALESCE(c.chief_complaint, '')) <> ''
               AND TRIM(COALESCE(c.diagnosis, '')) <> ''
           ) AS has_ehr
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN users bu ON p.user_id = bu.id
    WHERE a.doctor_id = ?
";
$modal_stmt = $conn->prepare($modal_q);
$modal_stmt->bind_param('i', $user_id);
$modal_stmt->execute();
$modal_rs = $modal_stmt->get_result();
while ($mrow = $modal_rs->fetch_assoc()) {
    $aid = (int) $mrow['id'];
    $visit_row = [
        'guest_first_name' => $mrow['guest_first_name'] ?? '',
        'guest_last_name' => $mrow['guest_last_name'] ?? '',
        'appointment_notes' => $apptColFlags['notes'] ? ($mrow['appointment_notes'] ?? null) : null,
        'first_name' => $mrow['patient_first_name'] ?? '',
        'last_name' => $mrow['patient_last_name'] ?? '',
    ];
    $health_row = $visit_row + ['health_concern' => $mrow['health_concern'] ?? ''];
    $visit_name = hb_appointments_display_patient_name($visit_row);
    $health = hb_appointments_display_health_concern($health_row);
    $ts = strtotime($mrow['appointment_date']);
    $cat = $mrow['appt_created_at'] ?? null;
    $cts = $cat ? strtotime($cat) : false;
    $booker = trim((string) ($mrow['booker_display_name'] ?? ''));
    $buser = trim((string) ($mrow['booker_username'] ?? ''));
    $booked_by = $booker !== '' ? ($buser !== '' ? $booker . ' (' . $buser . ')' : $booker) : '—';

    $doctor_appt_details[$aid] = [
        'id' => $aid,
        'patient_id' => (int) $mrow['patient_id'],
        'visit_patient' => $visit_name !== '' ? $visit_name : '—',
        'gender' => $mrow['gender'] ?? '—',
        'health_concern' => $health !== '' ? $health : '—',
        'status' => $mrow['status'],
        'date_label' => $ts ? date('l, F j, Y', $ts) : '—',
        'time_label' => $ts ? date('g:i A', $ts) : '—',
        'created_label' => hb_doctor_appt_format_created($mrow['appt_created_at'] ?? null),
        'created_date_label' => $cts ? date('l, F j, Y', $cts) : '—',
        'created_time_label' => $cts ? date('g:i A', $cts) : '—',
        'booked_by' => $booked_by,
        'has_ehr' => ((int) ($mrow['has_ehr'] ?? 0)) > 0,
    ];
}
$modal_stmt->close();

// Bust browser cache when JS/CSS change (avoids stale `alert()`-based script after deploy)
$appointments_asset_ver = (string) max(
    1,
    (int) @filemtime(__DIR__ . '/appointments.js'),
    (int) @filemtime(__DIR__ . '/appointments.css')
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Appointments - HealthBase</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="appointments.css?v=<?php echo htmlspecialchars($appointments_asset_ver, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="../css/dashboard.css">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="dashboard-page appointments-doctor-page">

<?php 
// Include sidebar with user data
$sidebar_user_data = [
    'username' => $username,
    'email' => $email,
    'role' => $role,
    'specialization' => $specialization
];
include '../includes/doctor_sidebar.php'; 
?>

<!-- Mobile backdrop overlay -->
<div id="doctorSidebarBackdrop" class="doctor-sidebar-backdrop"></div>

<header class="main-header">
    <div class="header-left">
        <button class="mobile-menu-toggle">
            <i class="fas fa-bars"></i>
        </button>
        <div>
            <h1 class="header-title">Appointments Management</h1>
            <p class="header-subtitle">Manage your patient appointments</p>
        </div>
    </div>
    <div class="header-right">
        <div class="notifications">
            <i class="fas fa-bell"></i>
            <span class="notif-badge">1</span>
            <div class="notif-dropdown">
                <div class="notif-header">
                    <h3>Notifications</h3>
                </div>
                <div class="notif-list">
                    <div class="notif-item">
                        <a href="#">
                            <strong>New appointment request</strong>
                            <small>2 minutes ago</small>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="profile">
            <div class="profile-circle">D</div>
            <div class="profile-dropdown">
                <div class="profile-info">
                    <p class="username">Dr. <?php echo htmlspecialchars($username); ?></p>
                    <p class="email"><?php echo htmlspecialchars($email); ?></p>
                </div>
                <a href="../dashboard/doctor_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </div>
</header>

<div class="main-content">
    <a href="../dashboard/doctor_dashboard.php" class="back-btn">
        <i class="fas fa-arrow-left"></i>
        Back to Dashboard
    </a>
    
    <?php if (isset($_GET['success'])): ?>
        <div style="background: #d1fae5; border-left: 4px solid #10b981; padding: 15px; border-radius: 8px; margin: 20px 0; color: #065f46;">
            <i class="fas fa-check-circle"></i> 
            <?php 
            if ($_GET['success'] === 'consultation_recorded') {
                echo "Consultation recorded successfully!";
            } else {
                echo "Operation completed successfully!";
            }
            ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['error'])): ?>
        <div style="background: #fee2e2; border-left: 4px solid #dc2626; padding: 15px; border-radius: 8px; margin: 20px 0; color: #dc2626;">
            <i class="fas fa-exclamation-circle"></i> 
            <?php 
            $error_messages = [
                'invalid_status' => 'Invalid appointment status.',
                'invalid_appointment_id' => 'Invalid appointment ID.',
                'invalid_transition' => 'Invalid status transition. This operation is not allowed.',
                'appointment_not_found' => 'Appointment not found or you don\'t have permission to access it.',
                'update_failed' => 'Failed to update appointment. Please try again.',
                'invalid_appointment_status' => 'Cannot record consultation for this appointment status.',
                'consultation_exists' => 'Consultation already exists for this appointment.',
                'required_fields' => 'Please fill in all required fields.',
                'save_failed' => 'Failed to save consultation. Please try again.'
            ];
            echo htmlspecialchars($error_messages[$_GET['error']] ?? 'An error occurred. Please try again.');
            ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($pending_appointments)): ?>
    <div class="card" style="margin-bottom: 24px; border: 1px solid #fde68a; background: #fffbeb;">
        <div class="card-header" style="border-bottom: 1px solid #fde68a;">
            <h3 class="card-title" style="margin: 0;">
                <i class="fas fa-hourglass-half" style="color: #ca8a04;"></i>
                Pending appointment requests
            </h3>
            <p style="margin: 8px 0 0 0; font-size: 14px; color: #92400e;">Confirm or decline each request. Patients are notified when you respond.</p>
        </div>
        <div class="table-container" style="overflow-x: auto;">
            <table class="compact-table" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Patient</th>
                        <th>Gender</th>
                        <th>Health concern</th>
                        <th>Date &amp; time</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_appointments as $prow): ?>
                    <tr class="js-appt-table-row" data-appt-id="<?= (int) $prow['id'] ?>" tabindex="0" role="button" title="View details">
                        <td><?= htmlspecialchars(hb_appointments_display_patient_name($prow)) ?></td>
                        <td><?= htmlspecialchars($prow['gender'] ?? '') ?></td>
                        <td><?= htmlspecialchars(hb_appointments_display_health_concern($prow)) ?></td>
                        <td><?= date('d M Y h:i A', strtotime($prow['appointment_date'])) ?></td>
                        <td style="white-space: nowrap;">
                            <form method="post" style="display: inline-block; margin-right: 6px;">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                <input type="hidden" name="appointment_id" value="<?= (int) $prow['id'] ?>">
                                <input type="hidden" name="status" value="confirmed">
                                <button type="submit" class="btn btn-sm" style="background: #10b981; color: white; padding: 6px 12px; border: none; border-radius: 6px; cursor: pointer; font-size: 12px;">
                                    <i class="fas fa-check"></i> Accept
                                </button>
                            </form>
                            <form method="post" style="display: inline-block;">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                <input type="hidden" name="appointment_id" value="<?= (int) $prow['id'] ?>">
                                <input type="hidden" name="status" value="declined">
                                <button type="submit" class="btn btn-sm" style="background: #dc2626; color: white; padding: 6px 12px; border: none; border-radius: 6px; cursor: pointer; font-size: 12px;">
                                    <i class="fas fa-times"></i> Decline
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Calendar Section - Full Width at Top -->
    <div class="calendar-container">
        <div class="calendar-header">
            <h3 class="calendar-title">
                <i class="fas fa-calendar-alt"></i>
                Appointments Calendar
            </h3>
            <div class="calendar-legend" style="display: flex; flex-wrap: wrap; gap: 20px; margin-top: 10px; font-size: 13px;">
                <div style="display: flex; align-items: center; gap: 5px;">
                    <span style="display: inline-block; width: 16px; height: 16px; background: #ca8a04; border-radius: 4px;"></span>
                    <span>Pending request</span>
                </div>
                <div style="display: flex; align-items: center; gap: 5px;">
                    <span style="display: inline-block; width: 16px; height: 16px; background: #3b82f6; border-radius: 4px;"></span>
                    <span>Confirmed</span>
                </div>
                <div style="display: flex; align-items: center; gap: 5px;">
                    <span style="display: inline-block; width: 16px; height: 16px; background: #10b981; border-radius: 4px;"></span>
                    <span>Completed (EHR Recorded)</span>
                </div>
                <div style="display: flex; align-items: center; gap: 5px;">
                    <span style="display: inline-block; width: 16px; height: 16px; background: #f59e0b; border-radius: 4px;"></span>
                    <span>Completed (No EHR)</span>
                </div>
                <div style="display: flex; align-items: center; gap: 5px;">
                    <span style="display: inline-block; width: 16px; height: 16px; background: #8b5cf6; border-radius: 4px;"></span>
                    <span>Follow-up</span>
                </div>
            </div>
            <p style="font-size: 13px; color: #64748b; margin: 12px 0 0 0; max-width: 720px; line-height: 1.5;">
                <strong>Flow:</strong> When a patient books, the appointment is <strong>pending</strong> (amber) until you accept — then it becomes <strong>confirmed</strong> (blue).
                After the visit, mark it <strong>completed</strong> (green = EHR saved, orange = no EHR yet). Declined visits are listed below, not on the calendar.
            </p>
        </div>
        <div id="calendar"></div>
    </div>

    <!-- Appointments Tables - Side by Side Below Calendar -->
    <div class="appointments-tables-grid">
        <!-- Declined Appointments -->
        <div class="card compact-card declined-appointments">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-times-circle"></i>
                    Declined Appointments
                </h3>
            </div>
            <div class="table-container">
                <table class="compact-table">
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Gender</th>
                            <th>Health Concern</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $declined_result->fetch_assoc()) { ?>
                            <tr class="js-appt-table-row" data-appt-id="<?= (int) $row['id'] ?>" tabindex="0" role="button" title="View details">
                                <td><?= htmlspecialchars(hb_appointments_display_patient_name($row)) ?></td>
                                <td><?= htmlspecialchars($row['gender']) ?></td>
                                <td><?= htmlspecialchars(hb_appointments_display_health_concern($row)) ?></td>
                                <td><?= date("d M Y h:i A", strtotime($row['appointment_date'])) ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Completed Appointments -->
        <div class="card compact-card completed-appointments">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-check-circle"></i>
                    Completed Appointments
                </h3>
            </div>
            <div class="table-container">
                <table class="compact-table">
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Gender</th>
                            <th>Health Concern</th>
                            <th>Date</th>
                            <th>EHR Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $completed_result->data_seek(0);
                        while ($row = $completed_result->fetch_assoc()) { 
                            $has_ehr = $row['has_ehr'] > 0;
                        ?>
                            <tr class="js-appt-table-row" data-appt-id="<?= (int) $row['id'] ?>" tabindex="0" role="button" title="View details">
                                <td><?= htmlspecialchars(hb_appointments_display_patient_name($row)) ?></td>
                                <td><?= htmlspecialchars($row['gender']) ?></td>
                                <td><?= htmlspecialchars(hb_appointments_display_health_concern($row)) ?></td>
                                <td><?= date("d M Y h:i A", strtotime($row['appointment_date'])) ?></td>
                                <td>
                                    <?php if ($has_ehr): ?>
                                        <span style="display: inline-flex; align-items: center; gap: 5px; color: #10b981; font-weight: 600;">
                                            <i class="fas fa-check-circle"></i> Recorded
                                        </span>
                                    <?php else: ?>
                                        <span style="display: inline-flex; align-items: center; gap: 5px; color: #f59e0b; font-weight: 600;">
                                            <i class="fas fa-exclamation-triangle"></i> Not Recorded
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="actions-cell">
                                    <?php if (!$has_ehr): ?>
                                        <a href="consultation_form.php?appointment_id=<?= $row['id'] ?>" class="btn btn-sm" style="background: #3b82f6; color: white; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 12px;">
                                            <i class="fas fa-stethoscope"></i> Record EHR
                                        </a>
                                    <?php else: ?>
                                        <a href="consultation_form.php?appointment_id=<?= $row['id'] ?>" class="btn btn-sm" style="background: #64748b; color: white; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 12px;">
                                            <i class="fas fa-edit"></i> View/Edit EHR
                                        </a>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-sm js-open-patient-history" data-patient-id="<?= (int) $row['patient_id'] ?>" style="background: #10b981; color: white; padding: 6px 12px; border-radius: 6px; border: none; font-size: 12px; margin-left: 5px; cursor: pointer;">
                                        <i class="fas fa-history"></i> History
                                    </button>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div id="apptDetailModal" class="appt-detail-overlay" role="dialog" aria-modal="true" aria-labelledby="apptDetailModalTitle" aria-hidden="true">
    <div class="appt-detail-backdrop" id="apptDetailModalBackdrop"></div>
    <div class="appt-detail-shell">
        <div class="appt-detail-dialog">
            <div class="appt-detail-accent" aria-hidden="true"></div>
            <div class="appt-detail-header">
                <div class="appt-detail-header-text">
                    <p class="appt-detail-eyebrow"><i class="fas fa-stethoscope"></i> HealthBase</p>
                    <h3 id="apptDetailModalTitle">Appointment details</h3>
                    <p id="apptDetailModalSubtitle" class="appt-detail-subtitle"></p>
                </div>
                <button type="button" class="appt-detail-close" id="apptDetailModalClose" aria-label="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="apptDetailModalBody" class="appt-detail-body"></div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<script>
    // Pass calendar events to JavaScript
    window.calendarEvents = <?php echo json_encode($events); ?>;
    window.DOCTOR_APPT_DETAILS = <?php echo json_encode($doctor_appt_details, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="appointments.js?v=<?php echo htmlspecialchars($appointments_asset_ver, ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="../js/doctor_sidebar.js"></script>

<script>
    // Mobile menu toggle for doctor sidebar
    document.addEventListener('DOMContentLoaded', function() {
        const mobileMenuToggle = document.querySelector('.mobile-menu-toggle');
        if (mobileMenuToggle) {
            mobileMenuToggle.addEventListener('click', function() {
                const sidebar = document.getElementById('doctorSidebar');
                const backdrop = document.getElementById('doctorSidebarBackdrop');
                
                if (sidebar) {
                    sidebar.classList.toggle('mobile-open');
                    
                    // Handle backdrop
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
    });
</script>

<div id="patientHistoryModal" class="ph-hist-overlay" role="dialog" aria-modal="true" aria-labelledby="patientHistoryModalTitle" aria-hidden="true" style="display:none;">
    <div class="ph-hist-dialog">
        <div class="ph-hist-header">
            <div>
                <h3 id="patientHistoryModalTitle" style="margin:0;font-size:1.1rem;font-weight:700;color:#0f172a;">Consultation history</h3>
                <p id="patientHistoryModalSubtitle" class="ph-hist-sub"></p>
            </div>
            <button type="button" class="ph-hist-close" id="patientHistoryModalClose" aria-label="Close">&times;</button>
        </div>
        <div class="ph-hist-body">
            <div id="patientHistoryLoading" class="ph-hist-state">
                <i class="fas fa-spinner fa-spin" style="margin-right:8px;"></i> Loading records…
            </div>
            <div id="patientHistoryError" class="ph-hist-state ph-hist-error" hidden></div>
            <div id="patientHistoryEmpty" class="ph-hist-state ph-hist-muted" hidden>
                <i class="fas fa-file-medical" style="font-size:2rem;color:#cbd5e1;display:block;margin-bottom:8px;"></i>
                No consultation records for this patient yet.
            </div>
            <div id="patientHistoryTableWrap" class="ph-hist-table-wrap" hidden>
                <table class="ph-hist-table">
                    <thead>
                        <tr>
                            <th>Visit</th>
                            <th>Doctor</th>
                            <th>Specialty</th>
                            <th>Chief complaint</th>
                            <th>Diagnosis</th>
                            <th>Treatment</th>
                            <th>Rx</th>
                            <th>Follow-up</th>
                        </tr>
                    </thead>
                    <tbody id="patientHistoryTableBody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<style>
.ph-hist-overlay {
    position: fixed;
    inset: 0;
    z-index: 10050;
    background: rgba(15, 23, 42, 0.55);
    align-items: center;
    justify-content: center;
    padding: 16px;
    box-sizing: border-box;
}
.ph-hist-overlay.is-open { display: flex !important; }
.ph-hist-dialog {
    width: min(1100px, 100%);
    max-height: min(92vh, 920px);
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 25px 50px -12px rgba(0,0,0,0.35);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border: 1px solid #e2e8f0;
}
.ph-hist-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    padding: 16px 18px;
    background: linear-gradient(180deg, #f8fafc 0%, #fff 100%);
    border-bottom: 1px solid #e2e8f0;
    flex-shrink: 0;
}
.ph-hist-sub { margin: 6px 0 0 0; font-size: 0.9rem; color: #64748b; font-weight: 500; }
.ph-hist-close {
    background: #f1f5f9;
    border: none;
    width: 38px;
    height: 38px;
    border-radius: 10px;
    font-size: 22px;
    line-height: 1;
    cursor: pointer;
    color: #475569;
    flex-shrink: 0;
}
.ph-hist-close:hover { background: #e2e8f0; color: #0f172a; }
.ph-hist-body {
    padding: 0;
    flex: 1;
    min-height: 200px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}
.ph-hist-state {
    padding: 28px 20px;
    text-align: center;
    color: #475569;
    font-size: 15px;
}
.ph-hist-error { color: #b91c1c; background: #fef2f2; }
.ph-hist-muted { color: #64748b; }
.ph-hist-table-wrap {
    overflow: auto;
    flex: 1;
    max-height: min(72vh, 760px);
    padding: 12px 14px 16px;
}
.ph-hist-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}
.ph-hist-table thead th {
    position: sticky;
    top: 0;
    z-index: 1;
    background: #f1f5f9;
    color: #334155;
    font-weight: 600;
    text-align: left;
    padding: 10px 12px;
    border-bottom: 2px solid #e2e8f0;
    white-space: nowrap;
}
.ph-hist-table tbody td {
    padding: 10px 12px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: top;
    color: #1e293b;
}
.ph-hist-table tbody tr:nth-child(even) { background: #fafbfc; }
.ph-hist-table tbody tr:hover { background: #eff6ff; }
.ph-hist-table .ph-nowrap { white-space: nowrap; }
.ph-hist-table .ph-num { text-align: center; font-weight: 600; color: #2563eb; }
.ph-hist-table .ph-followup-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    border-radius: 999px;
    font-weight: 600;
    color: #6d28d9;
    background: #f5f3ff;
    border: 1px solid #ddd6fe;
}
.ph-hist-table .ph-followup-pill::before {
    content: "\f073";
    font-family: "Font Awesome 6 Free";
    font-weight: 900;
    font-size: 11px;
    color: #7c3aed;
}
.ph-hist-table .ph-cell-long {
    max-width: 160px;
    line-height: 1.45;
}
@media (max-width: 900px) {
    .ph-hist-table .ph-cell-long { max-width: 120px; font-size: 12px; }
}
</style>
<script>window.HB_PATIENT_HISTORY_API = 'patient_history_data.php';</script>
<script src="patient_history_modal.js?v=<?php echo htmlspecialchars($appointments_asset_ver, ENT_QUOTES, 'UTF-8'); ?>"></script>

</body>
</html>
