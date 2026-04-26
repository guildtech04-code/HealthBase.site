<?php
// doctor_availability.php - Doctor Availability View for Patients
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once '../config/db_connect.php';

/**
 * Aligns Dr. Edward Sarrosa (MMC profile) and clinic hours with Makati Medical Center:
 * https://www.makatimed.net.ph/doctors-v2/profile.php?id=1565
 * — Replaces legacy "Mark/Make Jabez Cruz" doctor row when present.
 * — Clinic: Wednesday AM 9:00–12:00, Saturday PM 1:00–4:00 (By Appointment). Teleconsultation: none.
 */
function ensure_edward_sarrosa_mmc_schedule(mysqli $conn): void {
    $legacy_email = 'vincemorales1007@gmail.com';
    $doctor_id = null;
    $from_jabez = false;

    // Match legacy Jabez/Cruz variants (including typo "Make"), then normalize to Edward Sarrosa.
    $jab = $conn->query("SELECT id FROM users WHERE role = 'doctor' AND (
        (LOWER(TRIM(first_name)) LIKE '%mark%' AND LOWER(TRIM(last_name)) LIKE '%jabez%')
        OR (LOWER(TRIM(first_name)) LIKE '%make%' AND LOWER(TRIM(last_name)) LIKE '%jabez%')
        OR (LOWER(TRIM(last_name)) LIKE '%mark%' AND LOWER(TRIM(first_name)) LIKE '%jabez%')
        OR (LOWER(TRIM(last_name)) LIKE '%make%' AND LOWER(TRIM(first_name)) LIKE '%jabez%')
        OR (LOWER(TRIM(last_name)) LIKE '%cruz%' AND LOWER(TRIM(first_name)) LIKE '%mark%' AND LOWER(TRIM(first_name)) LIKE '%jabez%')
        OR (LOWER(TRIM(last_name)) LIKE '%cruz%' AND LOWER(TRIM(first_name)) LIKE '%make%' AND LOWER(TRIM(first_name)) LIKE '%jabez%')
        OR (LOWER(username) LIKE '%jabez%' AND LOWER(username) LIKE '%mark%')
        OR (LOWER(username) LIKE '%jabez%' AND LOWER(username) LIKE '%make%')
    ) LIMIT 1");
    if ($jab && ($row = $jab->fetch_assoc())) {
        $doctor_id = (int) $row['id'];
        $from_jabez = true;
    }

    if (!$doctor_id) {
        $stmt = $conn->prepare('SELECT id FROM users WHERE role = ? AND email = ? LIMIT 1');
        if ($stmt) {
            $role = 'doctor';
            $stmt->bind_param('ss', $role, $legacy_email);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row) {
                $doctor_id = (int) $row['id'];
            }
        }
    }

    if (!$doctor_id) {
        $res = $conn->query("SELECT id FROM users WHERE role = 'doctor' AND (
            (UPPER(TRIM(last_name)) = 'SARROSA' AND UPPER(TRIM(first_name)) LIKE '%EDWARD%')
            OR (UPPER(TRIM(first_name)) LIKE '%SARROSA%' AND UPPER(TRIM(last_name)) LIKE '%EDWARD%')
        ) LIMIT 1");
        if ($res && ($r = $res->fetch_assoc())) {
            $doctor_id = (int) $r['id'];
        }
    }

    if (!$doctor_id) {
        return;
    }

    $fn = 'Edward';
    $ln = 'Sarrosa';
    $spec = 'Orthopaedic Surgery';

    $upd = $conn->prepare('UPDATE users SET first_name = ?, last_name = ?, specialization = ? WHERE id = ? AND role = ?');
    if ($upd) {
        $role_doc = 'doctor';
        $upd->bind_param('sssis', $fn, $ln, $spec, $doctor_id, $role_doc);
        $upd->execute();
        $upd->close();
    }

    $mmc = [
        ['clinic', 3, 'AM', '09:00:00', '12:00:00', 'By Appointment'],
        ['clinic', 6, 'PM', '13:00:00', '16:00:00', 'By Appointment'],
    ];

    if ($from_jabez) {
        $del = $conn->prepare('DELETE FROM doctor_schedules WHERE doctor_id = ?');
        if ($del) {
            $del->bind_param('i', $doctor_id);
            $del->execute();
            $del->close();
        }
    }

    $chk = $conn->prepare('SELECT COUNT(*) AS c FROM doctor_schedules WHERE doctor_id = ?');
    if (!$chk) {
        return;
    }
    $chk->bind_param('i', $doctor_id);
    $chk->execute();
    $cnt = (int) ($chk->get_result()->fetch_assoc()['c'] ?? 0);
    $chk->close();

    if ($cnt > 0) {
        return;
    }

    $ins = $conn->prepare('INSERT INTO doctor_schedules (doctor_id, schedule_type, day_of_week, time_period, start_time, end_time, appointment_type, is_available) VALUES (?, ?, ?, ?, ?, ?, ?, 1)');
    if (!$ins) {
        return;
    }
    foreach ($mmc as $r) {
        $stype = $r[0];
        $dow = (int) $r[1];
        $tper = $r[2];
        $st = $r[3];
        $en = $r[4];
        $atype = $r[5];
        $ins->bind_param('isissss', $doctor_id, $stype, $dow, $tper, $st, $en, $atype);
        $ins->execute();
    }
    $ins->close();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_role = $_SESSION['role'] ?? '';
if (!in_array($user_role, ['assistant', 'admin'])) {
    header('Location: ../dashboard/healthbase_dashboard.php');
    exit();
}

ensure_edward_sarrosa_mmc_schedule($conn);

$user_id = $_SESSION['user_id'];

// Handle management POST actions (assistant/admin only)
$flash_success = '';
$flash_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic CSRF-lite via referer (optional) and role guard is already above.
    $action = $_POST['action'] ?? '';

    if ($action === 'add_schedule') {
        $doctor_id = (int)($_POST['doctor_id'] ?? 0);
        $schedule_type = $_POST['schedule_type'] ?? 'clinic';
        $day_of_week = (int)($_POST['day_of_week'] ?? -1);
        $time_period = $_POST['time_period'] ?? 'Any';
        $start_time = $_POST['start_time'] ?? '';
        $end_time = $_POST['end_time'] ?? '';
        $appointment_type = $_POST['appointment_type'] ?? 'By Appointment';
        $slot_minutes = (int)($_POST['slot_minutes'] ?? 60);
        $effective_from = !empty($_POST['effective_from']) ? $_POST['effective_from'] : null;
        $effective_to = !empty($_POST['effective_to']) ? $_POST['effective_to'] : null;
        $is_available = 1;

        if ($doctor_id > 0 && $day_of_week >= 0 && $day_of_week <= 6 && preg_match('/^\d{2}:\d{2}$/', $start_time) && preg_match('/^\d{2}:\d{2}$/', $end_time)) {
            // Insert into new doctor_schedules table
            $stmt = $conn->prepare("INSERT INTO doctor_schedules (doctor_id, schedule_type, day_of_week, time_period, start_time, end_time, appointment_type, is_available, effective_from, effective_to) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isisssssss", $doctor_id, $schedule_type, $day_of_week, $time_period, $start_time, $end_time, $appointment_type, $is_available, $effective_from, $effective_to);
            if ($stmt && $stmt->execute()) {
                $flash_success = 'Schedule added successfully.';
            } else {
                $flash_error = 'Failed to add schedule: ' . ($stmt ? $conn->error : 'Database error');
            }
        } else {
            $flash_error = 'Invalid schedule inputs.';
        }
    } elseif ($action === 'add_override') {
        $doctor_id = (int)($_POST['doctor_id'] ?? 0);
        $date = $_POST['date'] ?? '';
        $is_available = isset($_POST['is_available']) ? 1 : 0;
        $start_time = !empty($_POST['start_time']) ? $_POST['start_time'] : null;
        $end_time = !empty($_POST['end_time']) ? $_POST['end_time'] : null;

        if ($doctor_id > 0 && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $stmt = $conn->prepare("INSERT INTO provider_overrides (doctor_id, date, is_available, start_time, end_time, reason) VALUES (?, ?, ?, ?, ?, ?)");
            $reason = $_POST['reason'] ?? null;
            $stmt->bind_param("isisss", $doctor_id, $date, $is_available, $start_time, $end_time, $reason);
            if ($stmt && $stmt->execute()) {
                $flash_success = 'Override saved successfully.';
            } else {
                $flash_error = 'Failed to save override.';
            }
        } else {
            $flash_error = 'Invalid override inputs.';
        }
    } elseif ($action === 'add_holiday') {
        $date = $_POST['date'] ?? '';
        $name = trim($_POST['name'] ?? 'Holiday');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && $name !== '') {
            $stmt = $conn->prepare("INSERT INTO clinic_holidays (date, name) VALUES (?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name)");
            $stmt->bind_param("ss", $date, $name);
            if ($stmt && $stmt->execute()) {
                $flash_success = 'Holiday saved successfully.';
            } else {
                $flash_error = 'Failed to save holiday.';
            }
        } else {
            $flash_error = 'Invalid holiday inputs.';
        }
    } elseif ($action === 'edit_schedule') {
        $schedule_id = (int)($_POST['schedule_id'] ?? 0);
        $schedule_type = $_POST['schedule_type'] ?? 'clinic';
        $day_of_week = (int)($_POST['day_of_week'] ?? -1);
        $time_period = $_POST['time_period'] ?? 'Any';
        $start_time = $_POST['start_time'] ?? '';
        $end_time = $_POST['end_time'] ?? '';
        $appointment_type = $_POST['appointment_type'] ?? 'By Appointment';
        
        if ($schedule_id > 0 && preg_match('/^\d{2}:\d{2}$/', $start_time) && preg_match('/^\d{2}:\d{2}$/', $end_time)) {
            $stmt = $conn->prepare("UPDATE doctor_schedules SET schedule_type = ?, day_of_week = ?, time_period = ?, start_time = ?, end_time = ?, appointment_type = ? WHERE id = ?");
            $stmt->bind_param("sississ", $schedule_type, $day_of_week, $time_period, $start_time, $end_time, $appointment_type, $schedule_id);
            if ($stmt->execute()) {
                $flash_success = 'Schedule updated successfully.';
            } else {
                $flash_error = 'Failed to update schedule: ' . $conn->error;
            }
        } else {
            $flash_error = 'Invalid schedule inputs.';
        }
    } elseif ($action === 'delete_schedule') {
        $schedule_id = (int)($_POST['schedule_id'] ?? 0);
        if ($schedule_id > 0) {
            $stmt = $conn->prepare("DELETE FROM doctor_schedules WHERE id = ?");
            $stmt->bind_param("i", $schedule_id);
            if ($stmt->execute()) {
                $flash_success = 'Schedule deleted successfully.';
            } else {
                $flash_error = 'Failed to delete schedule.';
            }
        }
    }
}

// Fetch patient info
$query = $conn->prepare("SELECT username, email, role FROM users WHERE id = ?");
$query->bind_param("i", $user_id);
$query->execute();
$result = $query->get_result();
$user = $result->fetch_assoc();
$username = htmlspecialchars($user['username'] ?? '');
$email = htmlspecialchars($user['email'] ?? '');
$role = htmlspecialchars($user['role'] ?? '');

// Pass user data to sidebar
$sidebar_user_data = [
    'username' => $username,
    'email' => $email,
    'role' => $role
];

// Get filter parameters
$specialization_filter = $_GET['specialization'] ?? '';
$date_filter = $_GET['date'] ?? date('Y-m-d');

// Get all doctors with their availability for the selected date
$doctors_query = "
    SELECT u.id, 
           COALESCE(u.first_name, '') as first_name, 
           COALESCE(u.last_name, '') as last_name, 
           COALESCE(u.specialization, 'Unknown') as specialization, 
           COALESCE(u.email, '') as email,
           COUNT(a.id) as total_appointments,
           COUNT(CASE WHEN DATE(a.appointment_date) = ? AND a.status IN ('Confirmed', 'Pending') THEN 1 END) as today_appointments
    FROM users u
    LEFT JOIN appointments a ON u.id = a.doctor_id
    WHERE u.role = 'doctor' AND u.status = 'active'
    GROUP BY u.id, u.first_name, u.last_name, u.specialization, u.email
    ORDER BY u.specialization, u.first_name
";

$doctors_stmt = $conn->prepare($doctors_query);
$doctors_stmt->bind_param("s", $date_filter);
$doctors_stmt->execute();
$doctors_result = $doctors_stmt->get_result();

// Get all doctors for availability overview
$all_doctors_query = "
    SELECT u.id, 
           COALESCE(u.first_name, '') as first_name, 
           COALESCE(u.last_name, '') as last_name, 
           COALESCE(u.specialization, 'Unknown') as specialization, 
           COALESCE(u.email, '') as email
    FROM users u
    WHERE u.role = 'doctor' AND u.status = 'active'
    ORDER BY u.specialization, u.first_name
";
$all_doctors_result = $conn->query($all_doctors_query);

// Get unique specializations for filter
$specializations_query = "SELECT DISTINCT specialization FROM users WHERE role = 'doctor' AND status = 'active' ORDER BY specialization";
$specializations_result = $conn->query($specializations_query);

// Get today's appointments for each doctor
function getDoctorAvailability($conn, $doctor_id, $date) {
    $availability_query = "
        SELECT 
            HOUR(appointment_date) as hour,
            COUNT(*) as booked_count,
            GROUP_CONCAT(CONCAT(HOUR(appointment_date), ':', MINUTE(appointment_date))) as booked_times
        FROM appointments 
        WHERE doctor_id = ? AND DATE(appointment_date) = ? AND status IN ('Confirmed', 'Pending')
        GROUP BY HOUR(appointment_date)
    ";
    
    $stmt = $conn->prepare($availability_query);
    $stmt->bind_param("is", $doctor_id, $date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $booked_hours = [];
    while ($row = $result->fetch_assoc()) {
        $booked_hours[] = $row['hour'];
    }
    
    return $booked_hours;
}

// Generate time slots (9 AM to 5 PM)
function generateTimeSlots() {
    $slots = [];
    for ($hour = 9; $hour <= 17; $hour++) {
        $display_hour = $hour > 12 ? $hour - 12 : $hour;
        $ampm = $hour >= 12 ? 'PM' : 'AM';
        $slots[] = [
            'hour' => $hour,
            'display' => $display_hour . ':00 ' . $ampm,
            'available' => true
        ];
    }
    return $slots;
}

// Get doctor schedules from new doctor_schedules table with IDs
function getDoctorSchedules($conn, $doctor_id) {
    $schedules_query = "
        SELECT 
            id,
            schedule_type,
            day_of_week,
            time_period,
            start_time,
            end_time,
            appointment_type
        FROM doctor_schedules
        WHERE doctor_id = ? AND is_available = 1
        ORDER BY schedule_type, day_of_week, time_period
    ";
    
    $stmt = $conn->prepare($schedules_query);
    $stmt->bind_param("i", $doctor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $schedules = [
        'clinic' => [],
        'teleconsultation' => []
    ];
    
    while ($row = $result->fetch_assoc()) {
        $day_names = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $day_name = $day_names[$row['day_of_week']];
        
        $time_str = date('g:i A', strtotime($row['start_time'])) . ' - ' . 
                   date('g:i A', strtotime($row['end_time']));
        
        $schedules[$row['schedule_type']][] = [
            'id' => $row['id'],
            'day' => $day_name,
            'day_of_week' => $row['day_of_week'],
            'time_period' => $row['time_period'],
            'time' => $time_str,
            'start_time' => $row['start_time'],
            'end_time' => $row['end_time'],
            'appointment_type' => $row['appointment_type'],
            'schedule_type' => $row['schedule_type']
        ];
    }
    
    return $schedules;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Doctor Availability - HealthBase</title>
    <link rel="icon" type="image/x-icon" href="/assets/icons/favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="css/assistant.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { 
            --hb-bg:#f5f8fb; 
            --hb-card:#ffffff; 
            --hb-border:#e6eef5; 
            --hb-text:#0f172a; 
            --hb-sub:#667085; 
            --hb-primary:#2563eb; 
            --hb-primary-2:#3b82f6; 
            --hb-success:#22c55e; 
            --hb-danger:#dc2626; 
            --hb-shadow:0 10px 24px rgba(2,6,23,.08);
            --hb-radius:14px; 
            --hb-gap:16px;
        }
        body.assistant-dashboard-page { background: var(--hb-bg); }
        .assistant-main-content { padding-right: 18px; }

        /* Header */
        .assistant-header { display:flex; align-items:center; justify-content:space-between; margin-bottom: var(--hb-gap); }
        .assistant-welcome { margin:0; font-size:20px; font-weight:700; color:var(--hb-text); }
        .assistant-subtitle { margin:4px 0 0; color:var(--hb-sub); font-size:13px; }
        .refresh-btn, .btn, .btn-filter { background: linear-gradient(135deg, var(--hb-primary-2), var(--hb-primary)); color:#fff; border:none; padding:10px 14px; border-radius:10px; font-weight:700; cursor:pointer; box-shadow:0 8px 20px rgba(37,99,235,.25); transition:transform .15s ease; }
        .refresh-btn:hover, .btn:hover, .btn-filter:hover { transform: translateY(-1px); }
        .btn.secondary { background:#64748b; box-shadow:none; }
        .btn.danger { background: var(--hb-danger); box-shadow:0 8px 20px rgba(220,38,38,.20); }

        /* Card/Tiles */
        .card, .filter-section, .manage-card, .overview-card, .list-card, .availability-container, .availability-overview { 
            background: var(--hb-card); border:1px solid var(--hb-border); border-radius: var(--hb-radius); padding: 14px; box-shadow: var(--hb-shadow); 
        }
        .tiles, .overview-grid, .manage-grid { display:grid; gap: var(--hb-gap); }
        .tiles { grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); }
        .overview-grid { grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); }
        .manage-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        @media (max-width: 1200px) { .manage-grid { grid-template-columns: 1fr; } }

        /* Forms */
        label { font-size:12px; color:#475569; font-weight:600; margin:0 0 6px 2px; display:block; }
        select, input[type="text"], input[type="date"], input[type="time"], input[type="number"] { width:100%; padding:10px 12px; border:1.5px solid var(--hb-border); border-radius:10px; background:#fff; font-size:13px; transition:.15s; }
        select:focus, input:focus { outline:none; border-color: var(--hb-primary); box-shadow: 0 0 0 3px rgba(37,99,235,.12); }
        .filter-row { display:grid; grid-template-columns: 1fr 1fr auto; gap:12px; align-items:end; }
        @media (max-width: 900px) { .filter-row { grid-template-columns: 1fr; } }
        .row { display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:12px; }
        @media (max-width: 600px) { .row { grid-template-columns: 1fr; } }

        /* Overview tiles */
        .overview-card { display:flex; flex-direction:column; min-height: 150px; }
        .overview-card:hover { transform: translateY(-2px); box-shadow:0 14px 32px rgba(2,6,23,.12); }
        .overview-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:6px; }
        .spec-badge { background: linear-gradient(135deg, var(--hb-primary-2), var(--hb-primary)); color:#fff; padding:4px 10px; border-radius:999px; font-size:11px; font-weight:700; }
        .availability-bar { width:100%; height:6px; background: var(--hb-border); border-radius:999px; overflow:hidden; margin:6px 0; }
        .availability-fill { height:100%; background: linear-gradient(90deg, var(--hb-success), #16a34a); }
        .stat-row { display:flex; justify-content:space-between; font-size:12px; margin:2px 0; }
        .stat-label { color: var(--hb-sub); }
        .stat-value { font-weight:700; }
        .stat-value.available { color: var(--hb-success); }
        .stat-value.percentage { color: var(--hb-primary); }
        .overview-actions { margin-top:auto; }

        /* Time slot pills */
        .time-slots-grid, .slots { display:grid; grid-template-columns: repeat(auto-fit, minmax(90px, 1fr)); gap:8px; margin-top:8px; }
        .time-slot, .slot { padding:8px 8px; text-align:center; border:1.5px solid var(--hb-border); border-radius:999px; font-size:12px; font-weight:700; background:#fff; line-height: 1; }
        .time-slot.available, .slot.on { background:#eff6ff; border-color: var(--hb-primary-2); color:#1e3a8a; }
        .time-slot.available:hover, .slot.on:hover { background: var(--hb-primary-2); color:#fff; }
        .time-slot.booked, .slot.off { background:#f1f5f9; color:#94a3b8; cursor:not-allowed; }

        /* Status chips */
        .availability-status { display:flex; align-items:center; gap:6px; font-weight:700; font-size:12px; padding:6px 10px; border-radius:999px; }
        .status-available { background:#d1fae5; color:#065f46; }
        .status-busy { background:#fee2e2; color:#991b1b; }
        .status-indicator { width:8px; height:8px; border-radius:50%; }
        .indicator-available { background:#10b981; }
        .indicator-busy { background:#ef4444; }

        /* Tables */
        .table-wrap { overflow-x:auto; border:1px solid var(--hb-border); border-radius:12px; }
        table { width:100%; border-collapse:collapse; font-size:13px; }
        th, td { padding:10px 12px; border-bottom:1px solid var(--hb-border); text-align:left; }
        th { background:#f8fafc; color:#334155; font-weight:700; }

        /* Tighten generic text spacing inside detail section */
        .availability-container p { margin: 4px 0; }
        .availability-container h3, .availability-container h4 { margin: 0 0 8px 0; color: var(--hb-text); }
        .availability-container .btn { padding: 8px 12px; border-radius: 8px; }

        /* Doctor schedule tiles */
        .doctors-grid { display:grid; gap: var(--hb-gap); grid-template-columns: repeat(auto-fit, minmax(520px, 1fr)); }
        .doctor-card { background: var(--hb-card); border:1px solid var(--hb-border); border-radius: var(--hb-radius); padding: 16px; box-shadow: var(--hb-shadow); }
        .doctor-card .doctor-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; }
        .doctor-card h4 { margin:0; font-size:16px; color: var(--hb-text); font-weight:700; }
        .doctor-card .doctor-info p { margin:2px 0 0; color: var(--hb-sub); font-size:12px; }
        .doctor-card .doctor-stats { display:grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap:10px; margin-top:10px; }
        .doctor-card .stat-item { text-align:center; background:#f8fafc; border:1px solid var(--hb-border); border-radius:10px; padding:8px; }
        .doctor-card .stat-number { font-weight:700; color: var(--hb-text); }
        .doctor-card .stat-label { color: var(--hb-sub); font-size:11px; margin-top:2px; }
        .doctor-card .doctor-actions { display:flex; gap:10px; margin-top:10px; }

        /* Section spacing */
        .assistant-dashboard-page .card + .card, .tiles + .tiles, .card + .tiles, .tiles + .card { margin-top: var(--hb-gap); }
    </style>
</head>
<body class="assistant-dashboard-page">
    <?php include 'includes/assistant_sidebar.php'; ?>
    
    <div class="assistant-main-content">
        <!-- Header -->
        <header class="assistant-header">
            <div class="assistant-header-left">
                <h1 class="assistant-welcome">Doctor Availability</h1>
                <p class="assistant-subtitle">View doctor schedules and availability</p>
            </div>
            <div class="assistant-header-right">
                <button class="refresh-btn" onclick="location.reload()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
        </header>

        <div class="assistant-dashboard-content">
            <?php if ($flash_success): ?>
                <div class="flash flash-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($flash_success) ?></div>
            <?php endif; ?>
            <?php if ($flash_error): ?>
                <div class="flash flash-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($flash_error) ?></div>
            <?php endif; ?>

            <!-- Filter Section -->
            <div class="filter-section">
                <form method="GET" class="filter-form">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="specialization">Specialization:</label>
                            <select id="specialization" name="specialization">
                                <option value="">All Specializations</option>
                                <?php while ($spec = $specializations_result->fetch_assoc()): ?>
                                    <option value="<?= htmlspecialchars($spec['specialization'] ?? '') ?>" 
                                            <?= $specialization_filter === ($spec['specialization'] ?? '') ? 'selected' : '' ?>
                                    >
                                        <?= htmlspecialchars($spec['specialization'] ?? 'Unknown') ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="date">Date:</label>
                            <input type="date" id="date" name="date" value="<?= htmlspecialchars($date_filter) ?>" min="<?= date('Y-m-d') ?>">
                        </div>
                        <button type="submit" class="btn-filter">
                            <i class="fas fa-search"></i> Filter
                        </button>
                    </div>
                </form>
            </div>

            <!-- Doctor Schedules Display -->
            <div class="availability-container">
                <h3 style="margin-bottom: 20px; color: #1e293b; font-size: 20px; font-weight: 600;">
                    <i class="fas fa-calendar-alt" style="margin-right: 8px; color: #3b82f6;"></i>
                    Doctor Schedules
                </h3>

                            <?php
                // Get all active doctors and apply filter
                $all_doctors_result->data_seek(0);
                $doctors_array = [];
                while ($doctor = $all_doctors_result->fetch_assoc()) {
                    // Apply specialization filter if set
                    if (empty($specialization_filter) || ($doctor['specialization'] ?? '') === $specialization_filter) {
                        $doctors_array[] = $doctor;
                    }
                }
                
                // Only show content if there are doctors to display
                if (count($doctors_array) > 0):
                    foreach ($doctors_array as $doctor):
                    $is_mmc_edward_sarrosa = strcasecmp(trim($doctor['first_name'] ?? ''), 'Edward') === 0
                        && strcasecmp(trim($doctor['last_name'] ?? ''), 'Sarrosa') === 0;
                    $schedules = getDoctorSchedules($conn, $doctor['id']);
                    $clinic_schedules = $schedules['clinic'];
                    $tele_schedules = $schedules['teleconsultation'];
                    
                    // Organize schedules by day
                    $clinic_by_day = [
                        'Sunday' => ['AM' => [], 'PM' => []],
                        'Monday' => ['AM' => [], 'PM' => []],
                        'Tuesday' => ['AM' => [], 'PM' => []],
                        'Wednesday' => ['AM' => [], 'PM' => []],
                        'Thursday' => ['AM' => [], 'PM' => []],
                        'Friday' => ['AM' => [], 'PM' => []],
                        'Saturday' => ['AM' => [], 'PM' => []]
                    ];
                    
                    foreach ($clinic_schedules as $schedule) {
                        $day = $schedule['day'];
                        $period = $schedule['time_period'];
                        if ($period === 'Any') {
                            $clinic_by_day[$day]['AM'][] = $schedule;
                            $clinic_by_day[$day]['PM'][] = $schedule;
                                } else {
                            $clinic_by_day[$day][$period][] = $schedule;
                        }
                    }
                    
                    $tele_by_day = [
                        'Sunday' => ['AM' => [], 'PM' => []],
                        'Monday' => ['AM' => [], 'PM' => []],
                        'Tuesday' => ['AM' => [], 'PM' => []],
                        'Wednesday' => ['AM' => [], 'PM' => []],
                        'Thursday' => ['AM' => [], 'PM' => []],
                        'Friday' => ['AM' => [], 'PM' => []],
                        'Saturday' => ['AM' => [], 'PM' => []]
                    ];
                    
                    foreach ($tele_schedules as $schedule) {
                        $day = $schedule['day'];
                        $period = $schedule['time_period'];
                        if ($period === 'Any') {
                            $tele_by_day[$day]['AM'][] = $schedule;
                            $tele_by_day[$day]['PM'][] = $schedule;
                        } else {
                            $tele_by_day[$day][$period][] = $schedule;
                        }
                    }
                ?>
                    <div class="doctor-schedule-section" style="margin-bottom: 40px; background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                        <div class="doctor-profile" style="display: flex; align-items: center; margin-bottom: 25px; padding-bottom: 20px; border-bottom: 2px solid #e2e8f0;">
                            <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #3b82f6, #2563eb); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 20px; color: white; font-weight: bold; font-size: 24px;">
                                <?= strtoupper(substr($doctor['first_name'], 0, 1) . substr($doctor['last_name'], 0, 1)) ?>
                                    </div>
                            <div>
                                <h3 style="margin: 0; font-size: 20px; color: #1e293b; font-weight: 700;">
                                    <?= strtoupper(htmlspecialchars($doctor['last_name'] . ', ' . $doctor['first_name'])) ?>
                                </h3>
                                <p style="margin: 5px 0; color: #64748b; font-size: 14px;"><?= htmlspecialchars($doctor['specialization'] ?? 'General Medicine') ?></p>
                                <?php if ($is_mmc_edward_sarrosa): ?>
                                <p style="margin: 0; color: #94a3b8; font-size: 12px;">Local Number: 2319 | Room: Hall B 319</p>
                                <?php endif; ?>
                                    </div>
                                </div>

                        <!-- Clinic Schedule -->
                        <div class="schedule-table-section" style="margin-bottom: 30px;">
                            <h4 style="margin-bottom: 15px; color: #1e293b; font-size: 16px; font-weight: 600;">
                                <i class="fas fa-hospital" style="margin-right: 8px; color: #3b82f6;"></i>
                                Clinic Schedule
                            </h4>
                            <table style="width: 100%; border-collapse: collapse; background: white;">
                                <thead>
                                    <tr style="background: #f8fafc;">
                                        <th style="padding: 12px; text-align: left; border: 1px solid #e2e8f0; font-weight: 600; color: #334155;">DAY</th>
                                        <th style="padding: 12px; text-align: left; border: 1px solid #e2e8f0; font-weight: 600; color: #334155;">AM</th>
                                        <th style="padding: 12px; text-align: left; border: 1px solid #e2e8f0; font-weight: 600; color: #334155;">PM</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $day): ?>
                                        <tr>
                                            <td style="padding: 10px; border: 1px solid #e2e8f0; font-weight: 500; color: #334155;"><?= $day ?></td>
                                            <td style="padding: 10px; border: 1px solid #e2e8f0; color: #64748b;">
                                                <?php if (!empty($clinic_by_day[$day]['AM'])): ?>
                                                    <?php foreach ($clinic_by_day[$day]['AM'] as $slot): ?>
                                                        <div style="display: flex; align-items: center; gap: 5px; margin-bottom: 8px; flex-wrap: wrap;">
                                                            <span><?= htmlspecialchars($slot['time']) ?></span>
                                                            <?php if ($slot['appointment_type'] === 'By Appointment'): ?>
                                                                <i class="fas fa-info-circle" style="color: #dc2626;"></i>
                                                            <?php else: ?>
                                                                <i class="fas fa-info-circle" style="color: #2563eb;"></i>
                                                            <?php endif; ?>
                                                            <button onclick="editSchedule(<?= $slot['id'] ?>, '<?= $slot['schedule_type'] ?>', <?= $slot['day_of_week'] ?>, '<?= $slot['time_period'] ?>', '<?= $slot['start_time'] ?>', '<?= $slot['end_time'] ?>', '<?= htmlspecialchars($slot['appointment_type'], ENT_QUOTES) ?>')" style="padding: 4px 8px; background: #3b82f6; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 11px;"><i class="fas fa-edit"></i></button>
                                                            <button onclick="deleteSchedule(<?= $slot['id'] ?>)" style="padding: 4px 8px; background: #dc2626; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 11px;"><i class="fas fa-trash"></i></button>
                                        </div>
                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td style="padding: 10px; border: 1px solid #e2e8f0; color: #64748b;">
                                                <?php if (!empty($clinic_by_day[$day]['PM'])): ?>
                                                    <?php foreach ($clinic_by_day[$day]['PM'] as $slot): ?>
                                                        <div style="display: flex; align-items: center; gap: 5px; margin-bottom: 8px; flex-wrap: wrap;">
                                                            <span><?= htmlspecialchars($slot['time']) ?></span>
                                                            <?php if ($slot['appointment_type'] === 'By Appointment'): ?>
                                                                <i class="fas fa-info-circle" style="color: #dc2626;"></i>
                                                            <?php else: ?>
                                                                <i class="fas fa-info-circle" style="color: #2563eb;"></i>
                                                            <?php endif; ?>
                                                            <button onclick="editSchedule(<?= $slot['id'] ?>, '<?= $slot['schedule_type'] ?>', <?= $slot['day_of_week'] ?>, '<?= $slot['time_period'] ?>', '<?= $slot['start_time'] ?>', '<?= $slot['end_time'] ?>', '<?= htmlspecialchars($slot['appointment_type'], ENT_QUOTES) ?>')" style="padding: 4px 8px; background: #3b82f6; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 11px;"><i class="fas fa-edit"></i></button>
                                                            <button onclick="deleteSchedule(<?= $slot['id'] ?>)" style="padding: 4px 8px; background: #dc2626; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 11px;"><i class="fas fa-trash"></i></button>
                                </div>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <div style="margin-top: 15px; font-size: 12px; color: #64748b;">
                                <p style="margin: 5px 0;"><i class="fas fa-info-circle" style="color: #dc2626;"></i> By Appointment</p>
                                <p style="margin: 5px 0;"><i class="fas fa-info-circle" style="color: #2563eb;"></i> First Come, First Served</p>
                                <p style="margin-top: 10px; font-size: 11px; color: #94a3b8;">* The schedule is subject to change. Please call the clinic or MakatiMed On-Call at +632 8888 8999 for more information.</p>
                                    </div>
                                </div>

                        <!-- Teleconsultation Schedule -->
                        <div class="schedule-table-section">
                            <h4 style="margin-bottom: 15px; color: #1e293b; font-size: 16px; font-weight: 600;">
                                <i class="fas fa-headset" style="margin-right: 8px; color: #22c55e;"></i>
                                Teleconsultation Schedule
                            </h4>
                            <table style="width: 100%; border-collapse: collapse; background: white;">
                                <thead>
                                    <tr style="background: #f8fafc;">
                                        <th style="padding: 12px; text-align: left; border: 1px solid #e2e8f0; font-weight: 600; color: #334155;">DAY</th>
                                        <th style="padding: 12px; text-align: left; border: 1px solid #e2e8f0; font-weight: 600; color: #334155;">AM</th>
                                        <th style="padding: 12px; text-align: left; border: 1px solid #e2e8f0; font-weight: 600; color: #334155;">PM</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $has_tele_schedule = false;
                                    foreach (['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $day): 
                                        if (!empty($tele_by_day[$day]['AM']) || !empty($tele_by_day[$day]['PM'])) {
                                            $has_tele_schedule = true;
                                        }
                                    endforeach;
                                    ?>
                                    
                                    <?php if (!$has_tele_schedule): ?>
                                        <tr>
                                            <td colspan="3" style="padding: 30px; text-align: center; color: #94a3b8;">
                                                <i class="fas fa-calendar-times" style="font-size: 24px; margin-bottom: 10px; display: block;"></i>
                                                No Schedule
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach (['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $day): ?>
                                            <tr>
                                                <td style="padding: 10px; border: 1px solid #e2e8f0; font-weight: 500; color: #334155;"><?= $day ?></td>
                                                <td style="padding: 10px; border: 1px solid #e2e8f0; color: #64748b;">
                                                    <?php if (!empty($tele_by_day[$day]['AM'])): ?>
                                                        <?php foreach ($tele_by_day[$day]['AM'] as $slot): ?>
                                                            <div style="display: flex; align-items: center; gap: 5px; margin-bottom: 8px; flex-wrap: wrap;">
                                                                <span><?= htmlspecialchars($slot['time']) ?></span>
                                                                <i class="fas fa-headset" style="color: #22c55e;"></i>
                                                                <button onclick="editSchedule(<?= $slot['id'] ?>, '<?= $slot['schedule_type'] ?>', <?= $slot['day_of_week'] ?>, '<?= $slot['time_period'] ?>', '<?= $slot['start_time'] ?>', '<?= $slot['end_time'] ?>', '<?= htmlspecialchars($slot['appointment_type'], ENT_QUOTES) ?>')" style="padding: 4px 8px; background: #3b82f6; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 11px;"><i class="fas fa-edit"></i></button>
                                                                <button onclick="deleteSchedule(<?= $slot['id'] ?>)" style="padding: 4px 8px; background: #dc2626; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 11px;"><i class="fas fa-trash"></i></button>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="padding: 10px; border: 1px solid #e2e8f0; color: #64748b;">
                                                    <?php if (!empty($tele_by_day[$day]['PM'])): ?>
                                                        <?php foreach ($tele_by_day[$day]['PM'] as $slot): ?>
                                                            <div style="display: flex; align-items: center; gap: 5px; margin-bottom: 8px; flex-wrap: wrap;">
                                                                <span><?= htmlspecialchars($slot['time']) ?></span>
                                                                <i class="fas fa-headset" style="color: #22c55e;"></i>
                                                                <button onclick="editSchedule(<?= $slot['id'] ?>, '<?= $slot['schedule_type'] ?>', <?= $slot['day_of_week'] ?>, '<?= $slot['time_period'] ?>', '<?= $slot['start_time'] ?>', '<?= $slot['end_time'] ?>', '<?= htmlspecialchars($slot['appointment_type'], ENT_QUOTES) ?>')" style="padding: 4px 8px; background: #3b82f6; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 11px;"><i class="fas fa-edit"></i></button>
                                                                <button onclick="deleteSchedule(<?= $slot['id'] ?>)" style="padding: 4px 8px; background: #dc2626; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 11px;"><i class="fas fa-trash"></i></button>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                                </div>
                            </div>
                <?php 
                    endforeach; // end foreach doctors
                else: // No doctors match the filter
                ?>
                    <div style="padding: 60px 20px; text-align: center; background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                        <i class="fas fa-user-md" style="font-size: 48px; color: #94a3b8; margin-bottom: 20px;"></i>
                        <h3 style="margin: 0 0 10px 0; color: #1e293b; font-size: 20px; font-weight: 600;">
                            No Doctors Found
                        </h3>
                        <p style="margin: 0; color: #64748b; font-size: 14px;">
                            <?php if (!empty($specialization_filter)): ?>
                                No doctors found with the specialization "<?= htmlspecialchars($specialization_filter) ?>".
                                Please try a different filter.
                <?php else: ?>
                                No active doctors found in the system.
                            <?php endif; ?>
                        </p>
                        <button onclick="location.href='?specialization=&date=<?= date('Y-m-d') ?>'" style="margin-top: 20px; padding: 10px 20px; background: #3b82f6; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
                            <i class="fas fa-redo"></i> Clear Filter
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="js/assistant_sidebar.js"></script>
    
    <!-- Edit Schedule Modal -->
    <div id="editModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
        <div style="background: white; padding: 30px; border-radius: 12px; max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto;">
            <h3 style="margin: 0 0 20px 0; color: #1e293b; font-size: 20px; font-weight: 600;">
                <i class="fas fa-edit"></i> Edit Schedule
            </h3>
            <form method="POST" id="editScheduleForm">
                <input type="hidden" name="action" value="edit_schedule">
                <input type="hidden" name="schedule_id" id="edit_schedule_id">
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Schedule Type</label>
                    <select name="schedule_type" id="edit_schedule_type" style="width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 8px;">
                        <option value="clinic">Clinic Schedule</option>
                        <option value="teleconsultation">Teleconsultation Schedule</option>
                    </select>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Day</label>
                    <select name="day_of_week" id="edit_day_of_week" style="width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 8px;">
                        <option value="0">Sunday</option>
                        <option value="1">Monday</option>
                        <option value="2">Tuesday</option>
                        <option value="3">Wednesday</option>
                        <option value="4">Thursday</option>
                        <option value="5">Friday</option>
                        <option value="6">Saturday</option>
                    </select>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Time Period</label>
                    <select name="time_period" id="edit_time_period" style="width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 8px;">
                        <option value="AM">AM (Morning)</option>
                        <option value="PM">PM (Afternoon)</option>
                        <option value="Any">Any (All Day)</option>
                    </select>
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Appointment Type</label>
                    <select name="appointment_type" id="edit_appointment_type" style="width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 8px;">
                        <option value="By Appointment">By Appointment (Red Icon)</option>
                        <option value="Walk-in">Walk-in (Blue Icon)</option>
                        <option value="First Come First Served">First Come First Served</option>
                    </select>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">Start Time</label>
                        <input type="time" name="start_time" id="edit_start_time" style="width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 8px;" required>
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 5px; font-weight: 600;">End Time</label>
                        <input type="time" name="end_time" id="edit_end_time" style="width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 8px;" required>
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <button type="submit" style="flex: 1; padding: 12px; background: #3b82f6; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                    <button type="button" onclick="closeEditModal()" style="flex: 1; padding: 12px; background: #64748b; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function editSchedule(scheduleId, scheduleType, dayOfWeek, timePeriod, startTime, endTime, appointmentType) {
            document.getElementById('edit_schedule_id').value = scheduleId;
            document.getElementById('edit_schedule_type').value = scheduleType;
            document.getElementById('edit_day_of_week').value = dayOfWeek;
            document.getElementById('edit_time_period').value = timePeriod;
            document.getElementById('edit_start_time').value = startTime.substring(0, 5);
            document.getElementById('edit_end_time').value = endTime.substring(0, 5);
            document.getElementById('edit_appointment_type').value = appointmentType;
            document.getElementById('editModal').style.display = 'flex';
        }
        
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        function deleteSchedule(scheduleId) {
            if (confirm('Are you sure you want to delete this schedule?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="delete_schedule">' +
                                '<input type="hidden" name="schedule_id" value="' + scheduleId + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Close modal when clicking outside
        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });
    </script>
</body>
</html>
