<?php
session_start();
require_once '../config/db_connect.php';
require_once __DIR__ . '/../appointments/appointment_patient_overlap.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

$user_role = $_SESSION['role'] ?? '';
if (!in_array($user_role, ['assistant', 'admin', 'doctor'])) {
    header('Location: ../dashboard/healthbase_dashboard.php');
    exit();
}

$user_query = $conn->prepare("SELECT username, email, role, specialization FROM users WHERE id = ?");
$user_query->bind_param("i", $_SESSION['user_id']);
$user_query->execute();
$user_result = $user_query->get_result()->fetch_assoc();

if (!$user_result || empty($user_result)) {
    session_destroy();
    header('Location: ../auth/login.php');
    exit();
}

$username = $user_result['username'] ?? '';
$email = $user_result['email'] ?? '';
$role = $user_result['role'] ?? '';
$user_specialization = trim((string) ($user_result['specialization'] ?? ''));
$session_uid = (int) $_SESSION['user_id'];

$sidebar_user_data = [
    'username' => htmlspecialchars($username),
    'email' => htmlspecialchars($email),
    'role' => htmlspecialchars($role),
    'specialization' => $user_specialization !== '' ? $user_specialization : 'General',
];

$message = '';
$messageType = '';

function aaCanonicalAppointmentStatus($raw, $fallback = 'Pending') {
    $key = strtolower(trim((string) $raw));
    $map = [
        'pending' => 'Pending',
        'in progress' => 'Pending',
        'ongoing' => 'Pending',
        'confirmed' => 'Confirmed',
        'completed' => 'Completed',
        'cleared' => 'Completed',
        'declined' => 'Cancelled',
        'canceled' => 'Cancelled',
        'cancelled' => 'Cancelled',
    ];
    if (isset($map[$key])) {
        return $map[$key];
    }
    $fb = strtolower(trim((string) $fallback));
    return $map[$fb] ?? 'Pending';
}

/**
 * Resolve canonical status to a DB-safe value based on appointments.status column support.
 * Prevents enum mismatch (e.g., Cancelled vs Canceled) from being stored as blank.
 */
function aaDbStatusValue(mysqli $conn, $canonicalStatus) {
    static $allowed = null;
    if ($allowed === null) {
        $allowed = [];
        $col = $conn->query("SHOW COLUMNS FROM appointments LIKE 'status'");
        $row = $col ? $col->fetch_assoc() : null;
        $type = strtolower((string) ($row['Type'] ?? ''));
        if (preg_match("/^enum\((.*)\)$/", $type, $m)) {
            $raw = str_getcsv($m[1], ',', "'");
            foreach ($raw as $v) {
                $val = trim((string) $v);
                if ($val !== '') {
                    $allowed[strtolower($val)] = $val;
                }
            }
        }
    }

    $canon = aaCanonicalAppointmentStatus($canonicalStatus, 'Pending');
    $key = strtolower($canon);

    $candidates = [
        'pending' => ['Pending', 'pending'],
        'confirmed' => ['Confirmed', 'confirmed'],
        'completed' => ['Completed', 'completed', 'Cleared', 'cleared'],
        'cancelled' => ['Cancelled', 'cancelled', 'Canceled', 'canceled', 'Declined', 'declined'],
    ];

    if (!empty($allowed) && isset($candidates[$key])) {
        foreach ($candidates[$key] as $cand) {
            $lk = strtolower($cand);
            if (isset($allowed[$lk])) {
                return $allowed[$lk];
            }
        }
        // If enum exists but no candidate matched, keep first enum value to avoid empty inserts.
        $first = reset($allowed);
        return $first !== false ? $first : $canon;
    }

    return $canon;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'create') {
        $patient_id = intval($_POST['patient_id']);
        $doctor_id = intval($_POST['doctor_id']);
        if ($role === 'doctor') {
            $doctor_id = $session_uid;
        }
        $date = $_POST['date'];
        $time = $_POST['time'];
        $status = aaDbStatusValue($conn, $_POST['status'] ?? 'Pending');
        
        $appointment_date = $date . ' ' . $time . ':00';

        $overlap_result = hb_resolve_patient_appointment_overlap($conn, $patient_id, $appointment_date, 30, false);
        if (!$overlap_result['ok']) {
            $message = $overlap_result['error'] ?? 'Scheduling conflict for this patient.';
            $messageType = 'error';
        } else {

        $insert = $conn->prepare("INSERT INTO appointments (patient_id, doctor_id, appointment_date, status) VALUES (?, ?, ?, ?)");
        $insert->bind_param("iiss", $patient_id, $doctor_id, $appointment_date, $status);
        
        if ($insert->execute()) {
            $appointment_id = $insert->insert_id;
            
            // Automatically create consultation record for this appointment
            // Check if consultation_status column exists
            $check_cols = $conn->query("
                SELECT COLUMN_NAME 
                FROM information_schema.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'consultations' 
                AND COLUMN_NAME = 'consultation_status'
            ");
            $has_consultation_status = $check_cols->num_rows > 0;
            
            // Check if consultation already exists (prevent duplicates)
            $check_consultation = $conn->prepare("SELECT id FROM consultations WHERE appointment_id = ? LIMIT 1");
            $check_consultation->bind_param("i", $appointment_id);
            $check_consultation->execute();
            $existing_consultation = $check_consultation->get_result()->fetch_assoc();
            $check_consultation->close();
            
            if (!$existing_consultation) {
                // Get appointment date for consultation record
                $appointment_date = $date . ' ' . $time . ':00';
                
                // Create consultation record with minimal data (to be filled later by doctor)
                if ($has_consultation_status) {
                    $create_consultation = $conn->prepare("
                        INSERT INTO consultations (appointment_id, patient_id, doctor_id, visit_date, consultation_status)
                        VALUES (?, ?, ?, ?, 'Pending')
                    ");
                    $create_consultation->bind_param("iiis", $appointment_id, $patient_id, $doctor_id, $appointment_date);
                } else {
                    $create_consultation = $conn->prepare("
                        INSERT INTO consultations (appointment_id, patient_id, doctor_id, visit_date)
                        VALUES (?, ?, ?, ?)
                    ");
                    $create_consultation->bind_param("iiis", $appointment_id, $patient_id, $doctor_id, $appointment_date);
                }
                
                if (!$create_consultation->execute()) {
                    // Log error but don't fail the appointment creation
                    error_log("Failed to auto-create consultation record for appointment #$appointment_id: " . $create_consultation->error);
                }
                $create_consultation->close();
            }
            
            // Get appointment details for notification
            $appt_query = $conn->prepare("
                SELECT CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                       CONCAT(u.first_name, ' ', u.last_name) as doctor_name,
                       u.id as doctor_user_id
                FROM appointments a
                JOIN patients p ON a.patient_id = p.id
                JOIN users u ON a.doctor_id = u.id
                WHERE a.id = ?
            ");
            $appt_query->bind_param("i", $appointment_id);
            $appt_query->execute();
            $appt_result = $appt_query->get_result()->fetch_assoc();
            
            // Notify doctor
            if ($appt_result) {
                $notification_message = "New appointment created: " . $appt_result['patient_name'] . " on " . date('M d, Y h:i A', strtotime($appointment_date));
                $notification_link = "../appointments/appointments.php";
                $notif_query = $conn->prepare("INSERT INTO notifications (user_id, message, type, link, is_read, created_at) VALUES (?, ?, 'appointment', ?, 0, NOW())");
                $notif_query->bind_param("iss", $appt_result['doctor_user_id'], $notification_message, $notification_link);
                $notif_query->execute();
            }
            
            $message = 'Appointment created successfully and doctor has been notified';
            $messageType = 'success';
        } else {
            $message = 'Error creating appointment';
            $messageType = 'error';
        }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'update') {
        $appointment_id = intval($_POST['appointment_id']);
        $patient_id = intval($_POST['patient_id']);
        $doctor_id = intval($_POST['doctor_id']);
        if ($role === 'doctor') {
            $doctor_id = $session_uid;
            $own_chk = $conn->prepare("SELECT id FROM appointments WHERE id = ? AND doctor_id = ? LIMIT 1");
            $own_chk->bind_param("ii", $appointment_id, $session_uid);
            $own_chk->execute();
            if ($own_chk->get_result()->num_rows === 0) {
                $message = 'You can only update appointments assigned to you.';
                $messageType = 'error';
                $own_chk->close();
            } else {
                $own_chk->close();
            }
        }

        $skip_update = ($messageType === 'error');

        // Get existing values so status-only edits still work when date/time are unchanged.
        $existing_stmt = $conn->prepare("SELECT appointment_date, status FROM appointments WHERE id = ? LIMIT 1");
        $existing_stmt->bind_param("i", $appointment_id);
        $existing_stmt->execute();
        $existing_appt = $existing_stmt->get_result()->fetch_assoc();
        $existing_stmt->close();
        if (!$existing_appt) {
            $message = 'Appointment not found.';
            $messageType = 'error';
            $skip_update = true;
        }

        $date = trim((string) ($_POST['date'] ?? ''));
        $time = trim((string) ($_POST['time'] ?? ''));
        if ($date === '' && !empty($existing_appt['appointment_date'])) {
            $date = date('Y-m-d', strtotime((string) $existing_appt['appointment_date']));
        }
        if ($time === '' && !empty($existing_appt['appointment_date'])) {
            $time = date('H:i', strtotime((string) $existing_appt['appointment_date']));
        }

        // Keep previous DB status if incoming status is empty/invalid.
        $status = aaDbStatusValue($conn, aaCanonicalAppointmentStatus($_POST['status'] ?? '', (string) ($existing_appt['status'] ?? 'Pending')));

        $appointment_date = '';
        if ($date !== '' && $time !== '') {
            $appointment_date = $date . ' ' . $time . ':00';
        } else {
            $message = 'Invalid date/time for appointment update.';
            $messageType = 'error';
            $skip_update = true;
        }
        
        $update = $conn->prepare("UPDATE appointments SET doctor_id=?, patient_id=?, appointment_date=?, status=? WHERE id=?");
        $update->bind_param("iissi", $doctor_id, $patient_id, $appointment_date, $status, $appointment_id);
        
        if ($skip_update) {
            // Access denied message already set
        } elseif ($update->execute()) {
            // Notify involved users
            $appt_query = $conn->prepare("
                SELECT CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                       CONCAT(u.first_name, ' ', u.last_name) as doctor_name,
                       u.id as doctor_user_id, p.user_id as patient_user_id
                FROM appointments a
                JOIN patients p ON a.patient_id = p.id
                JOIN users u ON a.doctor_id = u.id
                WHERE a.id = ?
            ");
            $appt_query->bind_param("i", $appointment_id);
            $appt_query->execute();
            $appt_result = $appt_query->get_result()->fetch_assoc();
            
            if ($appt_result) {
                $notification_message = "Appointment updated: " . $appt_result['patient_name'] . " on " . date('M d, Y h:i A', strtotime($appointment_date));
                $notification_link = "../appointments/appointments.php";
                
                // Notify doctor
                $notif_query = $conn->prepare("INSERT INTO notifications (user_id, message, type, link, is_read, created_at) VALUES (?, ?, 'appointment', ?, 0, NOW())");
                $notif_query->bind_param("iss", $appt_result['doctor_user_id'], $notification_message, $notification_link);
                $notif_query->execute();
                
                // Notify patient
                if ($appt_result['patient_user_id']) {
                    $notif_query2 = $conn->prepare("INSERT INTO notifications (user_id, message, type, link, is_read, created_at) VALUES (?, ?, 'appointment', ?, 0, NOW())");
                    $notif_query2->bind_param("iss", $appt_result['patient_user_id'], $notification_message, $notification_link);
                    $notif_query2->execute();
                }
            }
            
            $message = 'Appointment updated successfully';
            $messageType = 'success';
        } else {
            $message = 'Error updating appointment';
            $messageType = 'error';
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $appointment_id = intval($_POST['appointment_id']);

        $appt_query = $conn->prepare("
            SELECT CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                   CONCAT(u.first_name, ' ', u.last_name) as doctor_name,
                   u.id as doctor_user_id, p.user_id as patient_user_id
            FROM appointments a
            JOIN patients p ON a.patient_id = p.id
            JOIN users u ON a.doctor_id = u.id
            WHERE a.id = ?
        ");
        $appt_query->bind_param("i", $appointment_id);
        $appt_query->execute();
        $appt_result = $appt_query->get_result()->fetch_assoc();

        $block_delete = false;
        if ($role === 'doctor') {
            if (!$appt_result) {
                $message = 'Appointment not found.';
                $messageType = 'error';
                $block_delete = true;
            } elseif ((int) $appt_result['doctor_user_id'] !== $session_uid) {
                $message = 'You can only delete appointments assigned to you.';
                $messageType = 'error';
                $block_delete = true;
            }
        }

        if (!$block_delete) {
            if ($role === 'doctor') {
                $delete = $conn->prepare("DELETE FROM appointments WHERE id = ? AND doctor_id = ?");
                $delete->bind_param("ii", $appointment_id, $session_uid);
            } else {
                $delete = $conn->prepare("DELETE FROM appointments WHERE id=?");
                $delete->bind_param("i", $appointment_id);
            }

            if ($delete->execute() && $delete->affected_rows > 0) {
                if ($appt_result) {
                    $notification_message = "Appointment cancelled: " . $appt_result['patient_name'];
                    $notification_link = "../appointments/appointments.php";

                    if ($appt_result['doctor_user_id']) {
                        $notif_query = $conn->prepare("INSERT INTO notifications (user_id, message, type, link, is_read, created_at) VALUES (?, ?, 'appointment', ?, 0, NOW())");
                        $notif_query->bind_param("iss", $appt_result['doctor_user_id'], $notification_message, $notification_link);
                        $notif_query->execute();
                    }

                    if ($appt_result['patient_user_id']) {
                        $notif_query2 = $conn->prepare("INSERT INTO notifications (user_id, message, type, link, is_read, created_at) VALUES (?, ?, 'appointment', ?, 0, NOW())");
                        $notif_query2->bind_param("iss", $appt_result['patient_user_id'], $notification_message, $notification_link);
                        $notif_query2->execute();
                    }
                }

                $message = 'Appointment deleted successfully';
                $messageType = 'success';
            } else {
                $message = 'Error deleting appointment.';
                $messageType = 'error';
            }
        }
    }
}

// Get filter parameters
$patient_name_filter = $_GET['patient_name'] ?? '';
$doctor_name_filter = $_GET['doctor_name'] ?? '';
$date_from_filter = $_GET['date_from'] ?? '';
$date_to_filter = $_GET['date_to'] ?? '';
$status_filter = $_GET['status'] ?? '';
$doctor_id_filter = isset($_GET['doctor_id']) ? intval($_GET['doctor_id']) : 0;
$auto_create = isset($_GET['create']) && $_GET['create'] === 'true';

// Pagination parameters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$records_per_page = 15;
$offset = ($page - 1) * $records_per_page;

// Build WHERE clause
$where_conditions = [];
$params = [];
$param_types = '';

if (!empty($patient_name_filter)) {
    $where_conditions[] = "CONCAT(p.first_name, ' ', p.last_name) LIKE ?";
    $params[] = '%' . $patient_name_filter . '%';
    $param_types .= 's';
}

if (!empty($doctor_name_filter)) {
    $where_conditions[] = "CONCAT(du.first_name, ' ', du.last_name) LIKE ?";
    $params[] = '%' . $doctor_name_filter . '%';
    $param_types .= 's';
}

if (!empty($date_from_filter)) {
    $where_conditions[] = "DATE(a.appointment_date) >= ?";
    $params[] = $date_from_filter;
    $param_types .= 's';
}

if (!empty($date_to_filter)) {
    $where_conditions[] = "DATE(a.appointment_date) <= ?";
    $params[] = $date_to_filter;
    $param_types .= 's';
}

if (!empty($status_filter)) {
    $where_conditions[] = "a.status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
}

// Doctors only see appointments where they are the provider (ignore ?doctor_id= URL for assistants' filter)
if ($role === 'doctor') {
    $where_conditions[] = 'a.doctor_id = ?';
    $params[] = $session_uid;
    $param_types .= 'i';
}

if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
} else {
    $where_clause = '';
}

// Add doctor_id filter if specified (assistants/admins only)
if ($doctor_id_filter > 0 && $role !== 'doctor') {
    $where_clause .= ($where_clause ? ' AND ' : 'WHERE ') . 'a.doctor_id = ?';
    $params[] = $doctor_id_filter;
    $param_types .= 'i';
}

// Get total count of appointments
$count_query = "
    SELECT COUNT(*) as total
    FROM appointments a
    LEFT JOIN patients p ON a.patient_id = p.id
    LEFT JOIN users du ON a.doctor_id = du.id
    $where_clause
";

if (!empty($params)) {
    $count_stmt = $conn->prepare($count_query);
    $count_stmt->bind_param($param_types, ...$params);
    $count_stmt->execute();
    $total_records = $count_stmt->get_result()->fetch_assoc()['total'];
} else {
    $total_records = $conn->query($count_query)->fetch_assoc()['total'];
}

// Calculate total pages based on patients (not individual appointments)
// We'll calculate this after grouping

// Get all appointments (without pagination limit for grouping)
$appointments_query = "
    SELECT a.id, a.appointment_date, a.status,
           COALESCE(p.first_name, '') as patient_first_name,
           COALESCE(p.last_name, '') as patient_last_name,
           COALESCE(du.first_name, '') as doctor_first_name,
           COALESCE(du.last_name, '') as doctor_last_name,
           COALESCE(du.specialization, 'Unknown') as doctor_specialization,
           p.id as patient_id, du.id as doctor_id,
           pu.email as patient_email, du.email as doctor_email
    FROM appointments a
    LEFT JOIN patients p ON a.patient_id = p.id
    LEFT JOIN users du ON a.doctor_id = du.id
    LEFT JOIN users pu ON p.user_id = pu.id
    $where_clause
    ORDER BY p.first_name, p.last_name, a.appointment_date DESC
";

if (!empty($params)) {
    $appointments_stmt = $conn->prepare($appointments_query);
    $appointments_stmt->bind_param($param_types, ...$params);
    $appointments_stmt->execute();
    $appointments_result = $appointments_stmt->get_result();
} else {
    $appointments_result = $conn->query($appointments_query);
}

// Group appointments by patient name
$grouped_appointments = [];
while ($appointment = $appointments_result->fetch_assoc()) {
    $patient_name = trim($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']);
    if (empty($patient_name)) {
        $patient_name = 'Unknown Patient';
    }
    
    if (!isset($grouped_appointments[$patient_name])) {
        $grouped_appointments[$patient_name] = [
            'patient_id' => $appointment['patient_id'],
            'patient_email' => $appointment['patient_email'] ?? 'N/A',
            'appointments' => []
        ];
    }
    
    $grouped_appointments[$patient_name]['appointments'][] = $appointment;
}

// Get all appointments first to count unique patients
$all_appointments_query = "
    SELECT DISTINCT p.id as patient_id,
           COALESCE(p.first_name, '') as patient_first_name,
           COALESCE(p.last_name, '') as patient_last_name
    FROM appointments a
    LEFT JOIN patients p ON a.patient_id = p.id
    LEFT JOIN users du ON a.doctor_id = du.id
";
if ($where_clause !== '') {
    $all_appointments_query .= $where_clause . ' AND (p.first_name IS NOT NULL OR p.last_name IS NOT NULL)';
} else {
    $all_appointments_query .= ' WHERE (p.first_name IS NOT NULL OR p.last_name IS NOT NULL)';
}

if (!empty($params)) {
    $all_patients_stmt = $conn->prepare($all_appointments_query);
    $all_patients_stmt->bind_param($param_types, ...$params);
    $all_patients_stmt->execute();
    $all_patients_result = $all_patients_stmt->get_result();
} else {
    $all_patients_result = $conn->query($all_appointments_query);
}

$total_patients = $all_patients_result->num_rows;
$total_pages = ceil($total_patients / $records_per_page);

// Apply pagination to grouped patients (not individual appointments)
$grouped_appointments = array_slice($grouped_appointments, $offset, $records_per_page, true);

$aa_appt_on_page = 0;
foreach ($grouped_appointments as $_aa_row) {
    $aa_appt_on_page += count($_aa_row['appointments']);
}
$aa_patients_on_page = count($grouped_appointments);

$aa_has_filters = $patient_name_filter !== '' || $doctor_name_filter !== '' || $date_from_filter !== '' || $date_to_filter !== '' || $status_filter !== '';

// Get all patients and doctors for dropdowns
$patients = $conn->query("
    SELECT id, CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) as name, user_id
    FROM patients
    ORDER BY first_name
");

if ($role === 'doctor') {
    $doc_stmt = $conn->prepare("
        SELECT id, CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) as name,
               COALESCE(specialization, 'Unknown') as specialization
        FROM users
        WHERE id = ? AND role = 'doctor' AND status = 'active'
        LIMIT 1
    ");
    $doc_stmt->bind_param('i', $session_uid);
    $doc_stmt->execute();
    $doctors = $doc_stmt->get_result();
} else {
    $doctors = $conn->query("
        SELECT id, CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) as name, 
               COALESCE(specialization, 'Unknown') as specialization
        FROM users
        WHERE role = 'doctor' AND status = 'active'
        ORDER BY first_name
    ");
}

// Function to normalize status values (map old statuses to new ones; case-insensitive for DB variants)
function normalizeStatus($status) {
    $key = strtolower(trim((string) $status));
    $statusMap = [
        'completed' => 'Completed',
        'confirmed' => 'Confirmed',
        'cleared' => 'Completed',
        'ongoing' => 'In Progress',
        'declined' => 'Cancelled',
        'cancelled' => 'Cancelled',
        'canceled' => 'Cancelled',
        'pending' => 'Pending',
    ];
    if (isset($statusMap[$key])) {
        return $statusMap[$key];
    }
    $canonical = ['pending' => 'Pending', 'in progress' => 'In Progress', 'confirmed' => 'Confirmed', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];
    return $canonical[$key] ?? $status;
}

function aa_patient_initials($name) {
    $name = trim((string) $name);
    if ($name === '') {
        return '?';
    }
    $parts = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY);
    $sub = function ($s) {
        if (function_exists('mb_substr')) {
            return mb_substr($s, 0, 1, 'UTF-8');
        }
        return substr($s, 0, 1);
    };
    $a = strtoupper($sub($parts[0]));
    if (count($parts) > 1) {
        $a .= strtoupper($sub(end($parts)));
    }
    return $a;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments - HealthBase</title>
    <link rel="icon" type="image/x-icon" href="/assets/icons/favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <?php if ($role === 'doctor'): ?>
    <link rel="stylesheet" href="../css/dashboard.css">
    <?php else: ?>
    <link rel="stylesheet" href="css/assistant.css">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        .message-alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .message-alert.success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #10b981;
        }

        .message-alert.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #ef4444;
        }

        .add-appointment-btn {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            margin-bottom: 20px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .add-appointment-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.4);
        }

        .filter-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            padding: 20px;
            margin-bottom: 20px;
        }

        .filter-form {
            width: 100%;
        }

        .filter-row {
            display: grid;
            grid-template-columns: 1.5fr 1.5fr 1fr 1fr 1fr auto;
            gap: 15px;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-weight: 600;
            color: #334155;
            font-size: 13px;
            margin-bottom: 5px;
        }

        .filter-group input,
        .filter-group select {
            padding: 10px 12px;
            border: 1.5px solid #e2e8f0;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s;
            font-family: 'Inter', sans-serif;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .filter-actions {
            display: flex;
            gap: 10px;
        }

        .btn-filter {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            border: none;
            padding: 10px 16px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
        }

        .btn-filter:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        }

        .btn-clear {
            background: white;
            color: #64748b;
            border: 1.5px solid #e2e8f0;
            padding: 10px 16px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            text-decoration: none;
        }

        .btn-clear:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
            text-decoration: none;
            color: #64748b;
        }

        .results-info {
            margin-bottom: 15px;
            padding: 10px 0;
        }

        .results-count {
            font-size: 14px;
            color: #64748b;
            font-weight: 500;
        }

        .table-wrapper {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: #f8fafc;
        }

        th {
            padding: 15px 20px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            color: #64748b;
            border-bottom: 1px solid #e2e8f0;
        }

        td {
            padding: 15px 20px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            font-size: 14px;
        }

        tr:last-child td {
            border-bottom: none;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            text-transform: capitalize;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-completed {
            background: #d1fae5;
            color: #065f46;
        }

        .status-cancelled {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-in-progress {
            background: #dbeafe;
            color: #1e40af;
        }

        /* Legacy status support - map old statuses to new styling */
        .status-cleared {
            background: #d1fae5;
            color: #065f46;
        }

        .status-confirmed {
            background: #d1fae5;
            color: #065f46;
        }

        .status-declined {
            background: #fee2e2;
            color: #991b1b;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-icon {
            padding: 8px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-icon.view {
            background: #8b5cf6;
            color: white;
        }

        .btn-icon.view:hover {
            background: #7c3aed;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.4);
        }

        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            padding: 30px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 20px;
            font-weight: 600;
            color: #1e293b;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #64748b;
            transition: color 0.3s;
        }

        .close-modal:hover {
            color: #1e293b;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #334155;
            font-size: 14px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 1.5px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
            font-family: 'Inter', sans-serif;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 25px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            flex: 1;
            transition: all 0.3s;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(59, 130, 246, 0.4);
        }

        .btn-cancel {
            background: white;
            color: #64748b;
            border: 1.5px solid #e2e8f0;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-cancel:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }

        .appointment-details {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            padding: 25px;
        }

        .detail-section {
            margin-bottom: 25px;
        }

        .detail-section h4 {
            font-size: 16px;
            font-weight: 600;
            color: #334155;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .detail-row {
            display: flex;
            padding: 12px 15px;
            background: #f8fafc;
            border-radius: 8px;
            margin-bottom: 8px;
        }

        .detail-label {
            font-weight: 600;
            color: #64748b;
            width: 120px;
            flex-shrink: 0;
        }

        .detail-value {
            color: #334155;
            flex: 1;
        }

        .pagination-wrapper {
            display: flex;
            justify-content: center;
            margin-top: 30px;
        }

        .pagination {
            display: flex;
            align-items: center;
            gap: 8px;
            background: white;
            padding: 10px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .page-link {
            padding: 8px 12px;
            border-radius: 6px;
            text-decoration: none;
            color: #64748b;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 4px;
            min-width: 40px;
            justify-content: center;
        }

        .page-link:hover {
            background: #f1f5f9;
            color: #3b82f6;
            text-decoration: none;
        }

        .page-link.active {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
        }

        .page-link.prev,
        .page-link.next {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }

        .page-link.prev:hover,
        .page-link.next:hover {
            background: #e2e8f0;
            border-color: #cbd5e1;
        }

        .page-ellipsis {
            padding: 8px 4px;
            color: #94a3b8;
            font-weight: 500;
        }

        .patient-row {
            border-bottom: 2px solid #e2e8f0;
        }

        .patient-row:hover {
            background: #f8fafc;
        }

        .patient-toggle-btn {
            background: none;
            border: none;
            cursor: pointer;
            color: #2563eb;
            font-size: 14px;
            padding: 6px;
            border-radius: 8px;
            transition: transform 0.3s ease, background 0.15s ease;
        }
        .patient-toggle-btn:hover {
            background: rgba(37, 99, 235, 0.1);
        }

        .patient-toggle-btn i {
            transition: transform 0.3s ease;
        }

        .patient-toggle-btn.expanded i {
            transform: rotate(90deg);
        }

        .patient-appointments {
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                max-height: 0;
            }
            to {
                opacity: 1;
                max-height: 1000px;
            }
        }

        .patient-appointments table {
            margin: 0;
        }

        .patient-appointments tbody tr:hover {
            background: #f1f5f9;
        }

        /* —— Structured workspace (assistant appointments) —— */
        .aa-intro {
            background: linear-gradient(135deg, #f0f9ff 0%, #ffffff 50%, #f8fafc 100%);
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 20px 22px 18px;
            margin-bottom: 22px;
            box-shadow: 0 4px 18px rgba(15, 23, 42, 0.06);
        }
        .aa-intro__eyebrow {
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #0369a1;
            margin: 0 0 10px 0;
        }
        .aa-intro__stats {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }
        .aa-stat-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 600;
            background: #fff;
            border: 1px solid #bae6fd;
            color: #0369a1;
        }
        .aa-stat-pill i { color: #0ea5e9; }
        .aa-stat-pill--muted {
            border-color: #e2e8f0;
            color: #475569;
        }
        .aa-stat-pill--muted i { color: #64748b; }
        .aa-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 20px;
        }
        .aa-toolbar__hint {
            font-size: 14px;
            color: #64748b;
            max-width: 420px;
            line-height: 1.5;
            margin: 0;
        }
        .aa-toolbar .add-appointment-btn { margin-bottom: 0; }
        .aa-filters {
            padding: 0;
            overflow: hidden;
        }
        .aa-filters__head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 16px 20px;
            background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
            border-bottom: 1px solid #e2e8f0;
        }
        .aa-filters__head h2 {
            margin: 0;
            font-size: 1rem;
            font-weight: 700;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .aa-filters__head h2 i { color: #2563eb; }
        .aa-badge-filter {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 4px 10px;
            border-radius: 999px;
            background: #dbeafe;
            color: #1d4ed8;
        }
        .aa-badge-filter--off {
            background: #f1f5f9;
            color: #64748b;
        }
        .aa-filters .filter-form { padding: 20px; }
        /* Filter: grouped clusters (less scattered) */
        .aa-filter-layout {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .aa-filter-clusters {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 14px;
            align-items: stretch;
        }
        .aa-filter-cluster {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 14px 16px 16px;
        }
        .aa-filter-cluster__title {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.07em;
            text-transform: uppercase;
            color: #475569;
            margin: 0 0 12px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .aa-filter-cluster__title i {
            color: #2563eb;
            font-size: 12px;
        }
        .aa-filter-cluster__fields {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
        }
        .aa-filter-cluster--people .aa-filter-cluster__fields {
            grid-template-columns: 1fr 1fr;
        }
        .aa-filter-cluster--people.aa-filter-cluster--single .aa-filter-cluster__fields {
            grid-template-columns: 1fr;
        }
        @media (max-width: 900px) {
            .aa-filter-cluster--people .aa-filter-cluster__fields {
                grid-template-columns: 1fr;
            }
        }
        .aa-filter-cluster--dates .aa-filter-cluster__fields {
            grid-template-columns: 1fr 1fr;
        }
        @media (max-width: 600px) {
            .aa-filter-cluster--dates .aa-filter-cluster__fields {
                grid-template-columns: 1fr;
            }
        }
        .aa-filter-footer {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            padding-top: 4px;
            border-top: 1px solid #f1f5f9;
            margin-top: 4px;
        }
        .aa-filter-footer .filter-actions {
            margin: 0;
        }
        /* Modal forms: sectioned layout */
        .aa-modal-section {
            margin-bottom: 22px;
            padding-bottom: 20px;
            border-bottom: 1px solid #f1f5f9;
        }
        .aa-modal-section:last-of-type {
            border-bottom: none;
            margin-bottom: 8px;
            padding-bottom: 0;
        }
        .aa-modal-section__head {
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #64748b;
            margin: 0 0 14px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .aa-modal-section__head i { color: #2563eb; font-size: 13px; }
        .aa-form-grid {
            display: grid;
            gap: 16px;
        }
        .aa-form-grid--2 {
            grid-template-columns: 1fr 1fr;
        }
        @media (max-width: 540px) {
            .aa-form-grid--2 {
                grid-template-columns: 1fr;
            }
        }
        .aa-modal-section .form-group {
            margin-bottom: 0;
        }
        .modal-content .form-group select,
        .modal-content .form-group input[type="date"] {
            min-height: 44px;
            box-sizing: border-box;
        }
        .aa-results-bar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 14px 18px;
            margin-bottom: 14px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
        }
        .aa-results-bar__main {
            font-size: 14px;
            color: #475569;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .aa-results-bar__main i { color: #0ea5e9; }
        .aa-results-bar__main strong { color: #0f172a; font-weight: 600; }
        .aa-table-shell {
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 16px rgba(15, 23, 42, 0.06);
            overflow: hidden;
            background: #fff;
        }
        .aa-table-shell .table-wrapper {
            box-shadow: none;
            border-radius: 0;
        }
        .aa-table-shell table thead {
            background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
        }
        .aa-table-shell table thead th {
            color: #e2e8f0;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            border-bottom: none;
            padding: 14px 20px;
        }
        .aa-patient-cell-inner {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .aa-avatar {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: linear-gradient(135deg, #0ea5e9, #2563eb);
            color: #fff;
            font-weight: 800;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            letter-spacing: 0.02em;
        }
        .aa-patient-text { min-width: 0; }
        .aa-patient-name {
            font-weight: 700;
            color: #0f172a;
            font-size: 15px;
            cursor: pointer;
            line-height: 1.3;
        }
        .aa-patient-name:hover { color: #2563eb; }
        .aa-patient-email {
            font-size: 12px;
            color: #64748b;
            margin-top: 2px;
            word-break: break-word;
        }
        .aa-appt-count {
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            padding: 8px 16px 4px 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .aa-appt-count i { color: #94a3b8; }
        .patient-appointments {
            border-top: 1px solid #e2e8f0;
        }
        .aa-nested-table {
            width: 100%;
            background: #f8fafc;
            border-collapse: collapse;
        }
        .aa-nested-table thead {
            background: #e2e8f0 !important;
        }
        .aa-nested-table thead th {
            color: #475569 !important;
            font-size: 11px !important;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 10px 14px !important;
        }
        .aa-nested-table tbody td {
            padding: 12px 14px;
            font-size: 13px;
            vertical-align: middle;
        }
        .aa-nested-table tbody tr { border-bottom: 1px solid #e2e8f0; }
        .aa-nested-table tbody tr:last-child { border-bottom: none; }
        .aa-doc-name { font-weight: 600; color: #0f172a; font-size: 13px; }
        .aa-doc-spec { font-size: 11px; color: #64748b; margin-top: 2px; }
        .aa-action-row { display: flex; gap: 6px; flex-wrap: wrap; }
        .aa-empty {
            text-align: center;
            padding: 48px 28px;
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 16px;
            border: 2px dashed #cbd5e1;
            max-width: 520px;
            margin: 24px auto;
        }
        .aa-empty i {
            font-size: 52px;
            color: #cbd5e1;
            margin-bottom: 14px;
        }
        .aa-empty h3 { color: #475569; margin: 0 0 8px; font-size: 1.2rem; }
        .aa-empty p { color: #94a3b8; font-size: 14px; line-height: 1.55; margin: 0 0 18px; }
        .aa-header-clock {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: #f8fafc;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
        }
        .aa-header-clock i { color: #2563eb; }
        .aa-header-clock span { color: #0f172a; font-weight: 600; font-size: 14px; }
        .pagination-wrapper.aa-pagination-wrap { margin-top: 28px; }
        .pagination.aa-pagination {
            padding: 14px 18px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(15, 23, 42, 0.06);
        }

        @media (max-width: 1400px) {
            .filter-row {
                grid-template-columns: 1fr 1fr;
                gap: 15px;
            }
            
            .filter-actions {
                grid-column: 1 / -1;
                justify-content: center;
            }
        }

        @media (max-width: 768px) {
            .filter-row {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .pagination {
                flex-wrap: wrap;
                gap: 4px;
            }
        }

        /*
         * Manage Appointments: .main-header is nested inside .main-content. Global dashboard.css
         * applies doctor-sidebar margin/width to .main-header for layouts where the header is beside
         * the sidebar — that stacks incorrectly here and misaligns the title row.
         */
        body.page-assistant-appointments.dashboard-page .main-content > .main-header {
            margin-left: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
        }
        body.page-assistant-appointments.dashboard-page .main-content {
            padding: 22px 32px 36px 32px;
            box-sizing: border-box;
        }
        body.page-assistant-appointments.assistant-dashboard-page .assistant-header {
            padding-left: 32px;
            padding-right: 32px;
            box-sizing: border-box;
        }
        body.page-assistant-appointments.assistant-dashboard-page .assistant-dashboard-content {
            padding: 28px 32px 36px 32px;
            box-sizing: border-box;
        }
    </style>
</head>
<body class="<?php echo $role === 'doctor' ? 'dashboard-page' : 'assistant-dashboard-page'; ?> page-assistant-appointments">
<?php 
if ($role === 'doctor') {
    include '../includes/doctor_sidebar.php'; 
} else {
    include 'includes/assistant_sidebar.php';
}
?>

<div class="<?php echo $role === 'doctor' ? 'main-content' : 'assistant-main-content'; ?>">
    <header class="<?php echo $role === 'doctor' ? 'main-header' : 'assistant-header'; ?>">
        <div class="<?php echo $role === 'doctor' ? 'header-left' : 'assistant-header-left'; ?>">
            <h1 class="<?php echo $role === 'doctor' ? 'header-title' : 'assistant-welcome'; ?>">Appointments</h1>
            <p class="<?php echo $role === 'doctor' ? 'header-subtitle' : 'assistant-subtitle'; ?>"><?= $role === 'doctor' ? 'View and manage appointments assigned to you' : 'View and manage all appointments' ?></p>
        </div>
        <?php if ($role !== 'doctor'): ?>
        <div class="assistant-header-right">
            <div class="current-time aa-header-clock">
                <i class="fas fa-clock"></i>
                <span id="currentDateTime"></span>
            </div>
        </div>
        <?php endif; ?>
    </header>

    <div class="<?php echo $role === 'doctor' ? 'dashboard-content' : 'assistant-dashboard-content'; ?>">
        <?php if ($message): ?>
            <div class="message-alert <?= $messageType ?>">
                <i class="fas <?= $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
                <?= $message ?>
            </div>
        <?php endif; ?>

        <div class="aa-intro">
            <p class="aa-intro__eyebrow">HealthBase · Scheduling workspace</p>
            <div class="aa-intro__stats">
                <span class="aa-stat-pill"><i class="fas fa-calendar-check"></i> <?= (int) $total_records ?> appointment<?= (int) $total_records === 1 ? '' : 's' ?> total</span>
                <span class="aa-stat-pill aa-stat-pill--muted"><i class="fas fa-users"></i> <?= (int) $total_patients ?> patient<?= (int) $total_patients === 1 ? '' : 's' ?> in list</span>
                <span class="aa-stat-pill aa-stat-pill--muted"><i class="fas fa-layer-group"></i> This page: <?= (int) $aa_patients_on_page ?> patient<?= (int) $aa_patients_on_page === 1 ? '' : 's' ?>, <?= (int) $aa_appt_on_page ?> visit<?= (int) $aa_appt_on_page === 1 ? '' : 's' ?></span>
                <span class="aa-stat-pill aa-stat-pill--muted"><i class="fas fa-file-alt"></i> Page <?= (int) $page ?> of <?= (int) max(1, $total_pages) ?></span>
            </div>
        </div>

        <div class="aa-toolbar">
            <p class="aa-toolbar__hint">Expand a patient to see every visit, provider, and status. Use filters to narrow by name, date, or status.</p>
        </div>
        <p class="aa-crosslink" style="font-size:13px;color:#64748b;margin:-8px 0 20px 0;line-height:1.5;">
            <i class="fas fa-id-card" style="color:#6366f1;" aria-hidden="true"></i>
            Emergency contacts, referring physicians, employment, and HMO are stored in
            <a href="patient_management.php" style="color:#2563eb;font-weight:600;">Patient Management</a>
            → <strong>Demographics</strong> (per patient).
        </p>


        <!-- Filter Section -->
        <div class="filter-section aa-filters">
            <div class="aa-filters__head">
                <h2><i class="fas fa-sliders-h"></i> Search &amp; filters</h2>
                <span class="aa-badge-filter<?= $aa_has_filters ? '' : ' aa-badge-filter--off' ?>"><?= $aa_has_filters ? 'Filters on' : 'Showing all' ?></span>
            </div>
            <form method="GET" class="filter-form aa-filter-layout">
                <div class="aa-filter-clusters">
                    <div class="aa-filter-cluster aa-filter-cluster--people<?= $role === 'doctor' ? ' aa-filter-cluster--single' : '' ?>">
                        <div class="aa-filter-cluster__title"><i class="fas fa-user-friends" aria-hidden="true"></i> People</div>
                        <div class="aa-filter-cluster__fields">
                            <div class="filter-group">
                                <label for="patient_name">Patient</label>
                                <input type="text" id="patient_name" name="patient_name" value="<?= htmlspecialchars($patient_name_filter) ?>" placeholder="Search by patient name…" autocomplete="off">
                            </div>
                            <?php if ($role !== 'doctor'): ?>
                            <div class="filter-group">
                                <label for="doctor_name">Doctor</label>
                                <input type="text" id="doctor_name" name="doctor_name" value="<?= htmlspecialchars($doctor_name_filter) ?>" placeholder="Search by doctor name…" autocomplete="off">
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="aa-filter-cluster aa-filter-cluster--dates">
                        <div class="aa-filter-cluster__title"><i class="fas fa-calendar-alt" aria-hidden="true"></i> Visit dates</div>
                        <div class="aa-filter-cluster__fields">
                            <div class="filter-group">
                                <label for="date_from">From</label>
                                <input type="date" id="date_from" name="date_from" value="<?= htmlspecialchars($date_from_filter) ?>">
                            </div>
                            <div class="filter-group">
                                <label for="date_to">To</label>
                                <input type="date" id="date_to" name="date_to" value="<?= htmlspecialchars($date_to_filter) ?>">
                            </div>
                        </div>
                    </div>
                    <div class="aa-filter-cluster">
                        <div class="aa-filter-cluster__title"><i class="fas fa-flag" aria-hidden="true"></i> Appointment status</div>
                        <div class="aa-filter-cluster__fields">
                            <div class="filter-group">
                                <label for="status">Status</label>
                                <select id="status" name="status">
                                    <option value="">All statuses</option>
                                    <option value="Pending" <?= $status_filter === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="Confirmed" <?= $status_filter === 'Confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                    <option value="Completed" <?= $status_filter === 'Completed' ? 'selected' : '' ?>>Completed</option>
                                    <option value="Cancelled" <?= $status_filter === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="aa-filter-footer">
                    <div class="filter-actions">
                        <button type="submit" class="btn-filter">
                            <i class="fas fa-search"></i> Apply filters
                        </button>
                        <a href="assistant_appointments.php" class="btn-clear">
                            <i class="fas fa-times"></i> Clear all
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Results summary (only when there is data) -->
        <?php if ($total_patients > 0): ?>
        <div class="aa-results-bar">
            <div class="aa-results-bar__main">
                <i class="fas fa-list-ul" aria-hidden="true"></i>
                <span>Showing <strong><?= (int) ($offset + 1) ?>–<?= (int) min($offset + $records_per_page, $total_patients) ?></strong> of <strong><?= (int) $total_patients ?></strong> patients · <strong><?= (int) $total_records ?></strong> appointment<?= (int) $total_records === 1 ? '' : 's' ?> matching filters</span>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($total_patients < 1): ?>
        <div class="aa-empty">
            <i class="fas fa-calendar-times" aria-hidden="true"></i>
            <h3>No appointments match</h3>
            <p>Try clearing filters or widening the date range.</p>
        </div>
        <?php else: ?>

        <div class="aa-table-shell">
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th style="width: 280px;">Patient</th>
                        <th colspan="2">Visits &amp; details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($grouped_appointments as $patient_name => $patient_data): 
                        $appointment_count = count($patient_data['appointments']);
                        $patient_key = 'patient_' . md5($patient_name);
                        $aa_init = aa_patient_initials($patient_name);
                    ?>
                    <tr class="patient-row" data-patient-key="<?= htmlspecialchars($patient_key) ?>">
                        <td>
                            <div class="aa-patient-cell-inner">
                                <button type="button" class="patient-toggle-btn" onclick="togglePatientAppointments('<?= htmlspecialchars($patient_key) ?>')" aria-expanded="false" aria-controls="appointments-<?= htmlspecialchars($patient_key) ?>">
                                    <i class="fas fa-chevron-right" id="icon-<?= htmlspecialchars($patient_key) ?>"></i>
                                </button>
                                <div class="aa-avatar" aria-hidden="true"><?= htmlspecialchars($aa_init) ?></div>
                                <div class="aa-patient-text">
                                    <div class="aa-patient-name" role="button" tabindex="0" onclick="togglePatientAppointments('<?= htmlspecialchars($patient_key) ?>')" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();togglePatientAppointments('<?= htmlspecialchars($patient_key) ?>');}">
                                        <?php echo htmlspecialchars($patient_name); ?>
                                    </div>
                                    <div class="aa-patient-email">
                                        <?php echo htmlspecialchars($patient_data['patient_email']); ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td colspan="2" style="padding: 0;">
                            <div class="aa-appt-count">
                                <i class="fas fa-calendar-check"></i> <?= (int) $appointment_count ?> appointment<?= $appointment_count !== 1 ? 's' : '' ?>
                            </div>
                            <div class="patient-appointments" id="appointments-<?= htmlspecialchars($patient_key) ?>" style="display: none;">
                                <table class="aa-nested-table">
                                    <thead>
                                        <tr>
                                            <th>Date &amp; time</th>
                                            <th>Doctor</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($patient_data['appointments'] as $appointment): ?>
                                        <tr>
                                            <td>
                                                <?php echo date('M d, Y · H:i', strtotime($appointment['appointment_date'])); ?>
                                            </td>
                                            <td>
                                                <div class="aa-doc-name">Dr. <?php echo htmlspecialchars($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']); ?></div>
                                                <div class="aa-doc-spec"><?php echo htmlspecialchars($appointment['doctor_specialization']); ?></div>
                                            </td>
                                            <td>
                                                <?php 
                                                $displayStatus = normalizeStatus($appointment['status']);
                                                $statusClass = strtolower(str_replace(' ', '-', $displayStatus));
                                                ?>
                                                <span class="status-badge status-<?php echo htmlspecialchars($statusClass); ?>">
                                                    <?php echo htmlspecialchars($displayStatus); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-buttons aa-action-row">
                                                    <button type="button" class="btn-icon view" onclick="viewDetails(<?= (int) $appointment['id'] ?>, '<?= htmlspecialchars($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($appointment['appointment_date'], ENT_QUOTES) ?>', '<?= htmlspecialchars($appointment['status'], ENT_QUOTES) ?>', '<?= htmlspecialchars($appointment['patient_email'] ?? '', ENT_QUOTES) ?>', '<?= htmlspecialchars($appointment['doctor_email'], ENT_QUOTES) ?>', '<?= htmlspecialchars($appointment['doctor_specialization'], ENT_QUOTES) ?>')" title="View details" style="padding: 6px 10px; font-size: 11px;">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button type="button" class="btn-icon edit" onclick="editAppointment(<?= (int) $appointment['id'] ?>, '<?= htmlspecialchars($appointment['appointment_date'], ENT_QUOTES) ?>', '<?= (int) $appointment['patient_id'] ?>', '<?= (int) $appointment['doctor_id'] ?>', '<?= htmlspecialchars($appointment['status'], ENT_QUOTES) ?>')" title="Edit appointment" style="background: #10b981; color: white; padding: 6px 10px; font-size: 11px;">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <?php
                                                    $normalizedStatus = normalizeStatus($appointment['status']);
                                                    $patient_pk = (int) $appointment['patient_id'];
                                                    $doctor_uid = (int) $appointment['doctor_id'];
                                                    if (strcasecmp($normalizedStatus, 'Cancelled') === 0):
                                                    ?>
                                                    <?php elseif (strcasecmp($normalizedStatus, 'Pending') === 0): ?>
                                                    <?php if ($role === 'doctor'): ?>
                                                    <a href="../appointments/consultation_form.php?appointment_id=<?= (int) $appointment['id'] ?>" class="btn-icon" title="Record consultation for this appointment" style="background: #8b5cf6; color: white; padding: 6px 10px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; font-size: 11px;">
                                                        <i class="fas fa-stethoscope"></i>
                                                    </a>
                                                    <?php endif; ?>
                                                    <?php endif; ?>
                                                    <button
                                                        type="button"
                                                        class="btn-icon js-open-patient-history"
                                                        data-patient-id="<?= (int) $appointment['patient_id'] ?>"
                                                        title="View full patient consultation history"
                                                        style="background: #0ea5e9; color: white; padding: 6px 10px; font-size: 11px;">
                                                        <i class="fas fa-history"></i>
                                                    </button>
                                                    <button type="button" class="btn-icon" onclick="deleteAppointment(<?= (int) $appointment['id'] ?>)" title="Delete appointment" style="background: #ef4444; color: white; padding: 6px 10px; font-size: 11px;">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination-wrapper aa-pagination-wrap">
            <div class="pagination aa-pagination">
                <?php
                $query_params = [];
                if (!empty($patient_name_filter)) $query_params['patient_name'] = $patient_name_filter;
                if (!empty($doctor_name_filter)) $query_params['doctor_name'] = $doctor_name_filter;
                if (!empty($date_from_filter)) $query_params['date_from'] = $date_from_filter;
                if (!empty($date_to_filter)) $query_params['date_to'] = $date_to_filter;
                if (!empty($status_filter)) $query_params['status'] = $status_filter;
                $query_string = !empty($query_params) ? '&' . http_build_query($query_params) : '';
                
                // Update total_pages to use total_patients
                $total_pages = ceil($total_patients / $records_per_page);
                ?>
                
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?><?= $query_string ?>" class="page-link prev">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                <?php endif; ?>
                
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                if ($start_page > 1): ?>
                    <a href="?page=1<?= $query_string ?>" class="page-link">1</a>
                    <?php if ($start_page > 2): ?>
                        <span class="page-ellipsis">...</span>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <a href="?page=<?= $i ?><?= $query_string ?>" class="page-link <?= $i == $page ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?>
                        <span class="page-ellipsis">...</span>
                    <?php endif; ?>
                    <a href="?page=<?= $total_pages ?><?= $query_string ?>" class="page-link"><?= $total_pages ?></a>
                <?php endif; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?><?= $query_string ?>" class="page-link next">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php endif; ?>

    </div>
</div>

<!-- Create Appointment Modal -->
<div class="modal-overlay" id="createModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-calendar-plus"></i> Create New Appointment</h3>
            <button class="close-modal" onclick="closeModal('createModal')">&times;</button>
        </div>
        <form method="POST" id="createForm">
            <input type="hidden" name="action" value="create">
            <div class="aa-modal-section">
                <div class="aa-modal-section__head"><i class="fas fa-user-md" aria-hidden="true"></i> Patient &amp; provider</div>
                <div class="aa-form-grid aa-form-grid--2">
                    <div class="form-group">
                        <label for="create_patient">Patient <span style="color: red;">*</span></label>
                        <select id="create_patient" name="patient_id" required>
                            <option value="">Choose a patient…</option>
                            <?php 
                            $patients->data_seek(0);
                            while ($patient = $patients->fetch_assoc()): ?>
                                <option value="<?= $patient['id'] ?>"><?= htmlspecialchars($patient['name']) ?> (user #<?= $patient['user_id'] ?>)</option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="create_doctor">Doctor <span style="color: red;">*</span></label>
                        <select id="create_doctor" name="doctor_id" required>
                            <?php if ($role !== 'doctor'): ?>
                            <option value="">Choose a doctor…</option>
                            <?php endif; ?>
                            <?php
                            $doctors->data_seek(0);
                            while ($doctor = $doctors->fetch_assoc()):
                            ?>
                                <option value="<?= (int) $doctor['id'] ?>"<?= $role === 'doctor' ? ' selected' : '' ?>><?= htmlspecialchars($doctor['name']) ?> — <?= htmlspecialchars($doctor['specialization']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="aa-modal-section">
                <div class="aa-modal-section__head"><i class="fas fa-clock" aria-hidden="true"></i> Date &amp; time</div>
                <div class="aa-form-grid aa-form-grid--2">
                    <div class="form-group">
                        <label for="create_date">Date <span style="color: red;">*</span></label>
                        <input type="date" id="create_date" name="date" required min="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label for="create_time">Time <span style="color: red;">*</span></label>
                        <select id="create_time" name="time" required>
                            <option value="">Choose doctor &amp; date first…</option>
                        </select>
                        <small id="create_time_hint" style="display: block; margin-top: 6px; color: #64748b;"></small>
                    </div>
                </div>
            </div>
            <div class="aa-modal-section">
                <div class="aa-modal-section__head"><i class="fas fa-info-circle" aria-hidden="true"></i> Booking status</div>
                <div class="form-group">
                    <label for="create_status">Status <span style="color: red;">*</span></label>
                    <select id="create_status" name="status" required>
                        <option value="Pending">Pending</option>
                        <option value="Confirmed">Confirmed</option>
                        <option value="Completed">Completed</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn-cancel" onclick="closeModal('createModal')">Cancel</button>
                <button type="submit" class="btn-primary">Create Appointment</button>
            </div>
        </form>
    </div>
</div>

<!-- View Details Modal -->
<div class="modal-overlay" id="detailsModal">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h3><i class="fas fa-info-circle"></i> Appointment Details</h3>
            <button class="close-modal" onclick="closeModal('detailsModal')">&times;</button>
        </div>
        <div class="appointment-details">
            <div class="detail-section">
                <h4><i class="fas fa-clock"></i> Appointment Information</h4>
                <div class="detail-row">
                    <div class="detail-label">Date & Time:</div>
                    <div class="detail-value" id="detail-datetime"></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Status:</div>
                    <div class="detail-value" id="detail-status"></div>
                </div>
            </div>
            <div class="detail-section">
                <h4><i class="fas fa-user-injured"></i> Patient Information</h4>
                <div class="detail-row">
                    <div class="detail-label">Name:</div>
                    <div class="detail-value" id="detail-patient-name"></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Email:</div>
                    <div class="detail-value" id="detail-patient-email"></div>
                </div>
            </div>
            <div class="detail-section">
                <h4><i class="fas fa-user-md"></i> Doctor Information</h4>
                <div class="detail-row">
                    <div class="detail-label">Name:</div>
                    <div class="detail-value" id="detail-doctor-name"></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Specialization:</div>
                    <div class="detail-value" id="detail-doctor-spec"></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Email:</div>
                    <div class="detail-value" id="detail-doctor-email"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Appointment Modal -->
<div class="modal-overlay" id="editModal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Edit Appointment</h3>
            <button class="close-modal" onclick="closeModal('editModal')">&times;</button>
        </div>
        <form method="POST" id="editForm">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="appointment_id" id="edit_appointment_id">
            <div class="aa-modal-section">
                <div class="aa-modal-section__head"><i class="fas fa-user-md" aria-hidden="true"></i> Patient &amp; provider</div>
                <div class="aa-form-grid aa-form-grid--2">
                    <div class="form-group">
                        <label for="edit_patient">Patient <span style="color: red;">*</span></label>
                        <select id="edit_patient" name="patient_id" required>
                            <option value="">Choose a patient…</option>
                            <?php 
                            $patients->data_seek(0);
                            while ($patient = $patients->fetch_assoc()): ?>
                                <option value="<?= $patient['id'] ?>"><?= htmlspecialchars($patient['name']) ?> (user #<?= $patient['user_id'] ?>)</option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_doctor">Doctor <span style="color: red;">*</span></label>
                        <?php if ($role === 'doctor'): ?>
                        <input type="hidden" name="doctor_id" value="<?= (int) $session_uid ?>">
                        <?php endif; ?>
                        <select id="edit_doctor" required <?php if ($role === 'doctor'): ?>disabled style="opacity:0.9"<?php else: ?>name="doctor_id"<?php endif; ?>>
                            <?php if ($role !== 'doctor'): ?>
                            <option value="">Choose a doctor…</option>
                            <?php endif; ?>
                            <?php
                            $doctors->data_seek(0);
                            while ($doctor = $doctors->fetch_assoc()):
                            ?>
                                <option value="<?= (int) $doctor['id'] ?>"><?= htmlspecialchars($doctor['name']) ?> — <?= htmlspecialchars($doctor['specialization']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="aa-modal-section">
                <div class="aa-modal-section__head"><i class="fas fa-clock" aria-hidden="true"></i> Date &amp; time</div>
                <div class="aa-form-grid aa-form-grid--2">
                    <div class="form-group">
                        <label for="edit_date">Date <span style="color: red;">*</span></label>
                        <input type="date" id="edit_date" name="date" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_time">Time <span style="color: red;">*</span></label>
                        <select id="edit_time" name="time" required>
                            <option value="">Choose doctor &amp; date first…</option>
                        </select>
                        <small id="edit_time_hint" style="display: block; margin-top: 6px; color: #64748b;"></small>
                    </div>
                </div>
            </div>
            <div class="aa-modal-section">
                <div class="aa-modal-section__head"><i class="fas fa-info-circle" aria-hidden="true"></i> Booking status</div>
                <div class="form-group">
                    <label for="edit_status">Status <span style="color: red;">*</span></label>
                    <select id="edit_status" name="status" required>
                        <option value="Pending">Pending</option>
                        <option value="Confirmed">Confirmed</option>
                        <option value="Completed">Completed</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn-cancel" onclick="closeModal('editModal')">Cancel</button>
                <button type="submit" class="btn-primary">Update Appointment</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
const APPT_SLOTS_API = 'api/appointment_available_times.php';

async function fetchAppointmentTimeSlots(doctorId, date, excludeAppointmentId) {
    if (!doctorId || !date) {
        return { times: [], message: '' };
    }
    let url = APPT_SLOTS_API + '?doctor_id=' + encodeURIComponent(doctorId) + '&date=' + encodeURIComponent(date);
    if (excludeAppointmentId) {
        url += '&exclude_appointment_id=' + encodeURIComponent(excludeAppointmentId);
    }
    try {
        const res = await fetch(url, { credentials: 'same-origin' });
        if (!res.ok) {
            return { times: [], message: 'Could not load availability.' };
        }
        return await res.json();
    } catch (e) {
        return { times: [], message: 'Network error loading times.' };
    }
}

function fillTimeSelect(selectEl, hintEl, times, message, preserveValue, emptyOptionLabel) {
    const prev = preserveValue || '';
    selectEl.innerHTML = '';
    const opt0 = document.createElement('option');
    opt0.value = '';
    if (times && times.length) {
        opt0.textContent = 'Select time';
    } else {
        opt0.textContent = emptyOptionLabel || 'No slots available';
    }
    selectEl.appendChild(opt0);
    (times || []).forEach(function (t) {
        const o = document.createElement('option');
        o.value = t.value;
        o.textContent = t.label;
        selectEl.appendChild(o);
    });
    if (prev && [...selectEl.options].some(function (o) { return o.value === prev; })) {
        selectEl.value = prev;
    } else if (prev) {
        const keep = document.createElement('option');
        keep.value = prev;
        keep.textContent = prev + ' (current)';
        selectEl.appendChild(keep);
        selectEl.value = prev;
    }
    if (hintEl) {
        hintEl.textContent = message || '';
    }
}

async function refreshCreateTimeSlots() {
    const doc = document.getElementById('create_doctor').value;
    const dt = document.getElementById('create_date').value;
    const sel = document.getElementById('create_time');
    const hint = document.getElementById('create_time_hint');
    if (!doc || !dt) {
        fillTimeSelect(sel, hint, [], 'Choose a doctor and date to see clinic and online consultation hours.', '', 'Choose doctor & date first…');
        return;
    }
    hint.textContent = 'Loading times…';
    const data = await fetchAppointmentTimeSlots(doc, dt, 0);
    fillTimeSelect(sel, hint, data.times || [], data.message || '');
}

async function refreshEditTimeSlots(preserveTime) {
    const doc = document.getElementById('edit_doctor').value;
    const dt = document.getElementById('edit_date').value;
    const apptId = document.getElementById('edit_appointment_id').value;
    const sel = document.getElementById('edit_time');
    const hint = document.getElementById('edit_time_hint');
    if (!doc || !dt) {
        fillTimeSelect(sel, hint, [], '', preserveTime, 'Choose doctor & date first…');
        return;
    }
    hint.textContent = 'Loading times…';
    const data = await fetchAppointmentTimeSlots(doc, dt, apptId || 0);
    fillTimeSelect(sel, hint, data.times || [], data.message || '', preserveTime);
}

function openCreateModal() {
    document.getElementById('createModal').style.display = 'flex';
    refreshCreateTimeSlots();
}

function viewDetails(id, patientName, doctorName, datetime, status, patientEmail, doctorEmail, doctorSpec) {
    document.getElementById('detail-datetime').textContent = new Date(datetime).toLocaleString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
    
    // Normalize status for display
    const raw = String(status || '').trim().toLowerCase();
    const statusMap = {
        'pending': 'Pending',
        'confirmed': 'Confirmed',
        'completed': 'Completed',
        'cleared': 'Completed',
        'ongoing': 'In Progress',
        'in progress': 'In Progress',
        'declined': 'Cancelled',
        'canceled': 'Cancelled',
        'cancelled': 'Cancelled'
    };
    const displayStatus = statusMap[raw] || status;
    const statusClass = displayStatus.toLowerCase().replace(/\s+/g, '-');
    
    document.getElementById('detail-status').innerHTML = '<span class="status-badge status-' + statusClass + '">' + displayStatus + '</span>';
    document.getElementById('detail-patient-name').textContent = patientName;
    document.getElementById('detail-patient-email').textContent = patientEmail || 'N/A';
    document.getElementById('detail-doctor-name').textContent = 'Dr. ' + doctorName;
    document.getElementById('detail-doctor-spec').textContent = doctorSpec;
    document.getElementById('detail-doctor-email').textContent = doctorEmail || 'N/A';
    
    document.getElementById('detailsModal').style.display = 'flex';
}

async function editAppointment(id, dateTime, patientId, doctorId, status) {
    document.getElementById('edit_appointment_id').value = id;
    document.getElementById('edit_patient').value = patientId;
    document.getElementById('edit_doctor').value = doctorId;
    
    // Parse MySQL DATETIME safely across browsers (avoid relying on Date parsing for "YYYY-MM-DD HH:MM:SS")
    let date = '';
    let time = '';
    if (typeof dateTime === 'string') {
        const m = dateTime.trim().match(/^(\d{4}-\d{2}-\d{2})\s+(\d{2}:\d{2})(?::\d{2})?$/);
        if (m) {
            date = m[1];
            time = m[2];
        }
    }
    if (!date || !time) {
        const datetime = new Date(dateTime);
        if (!Number.isNaN(datetime.getTime())) {
            date = datetime.toISOString().split('T')[0];
            time = datetime.toTimeString().split(' ')[0].substring(0, 5);
        }
    }
    
    document.getElementById('edit_date').value = date;
    
    // Normalize status for dropdown (case-insensitive, DB-safe).
    const rawStatus = String(status || '').trim().toLowerCase();
    const statusMap = {
        'in progress': 'Pending',
        'ongoing': 'Pending',
        'pending': 'Pending',
        'confirmed': 'Confirmed',
        'completed': 'Completed',
        'cleared': 'Completed',
        'cancelled': 'Cancelled',
        'canceled': 'Cancelled',
        'declined': 'Cancelled'
    };
    const normalizedStatus = statusMap[rawStatus] || 'Pending';
    document.getElementById('edit_status').value = normalizedStatus;
    
    document.getElementById('editModal').style.display = 'flex';
    await refreshEditTimeSlots(time);
}

function deleteAppointment(id) {
    if (confirm('Are you sure you want to delete this appointment? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="delete"><input type="hidden" name="appointment_id" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
    }
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
    if (modalId === 'createModal') {
        document.getElementById('createForm').reset();
        fillTimeSelect(
            document.getElementById('create_time'),
            document.getElementById('create_time_hint'),
            [],
            'Choose a doctor and date to see clinic and online consultation hours.',
            '',
            'Choose doctor & date first…'
        );
    } else if (modalId === 'editModal') {
        document.getElementById('editForm').reset();
        fillTimeSelect(document.getElementById('edit_time'), document.getElementById('edit_time_hint'), [], '', '', 'Choose doctor & date first…');
    }
}

document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.style.display = 'none';
    }
});

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

(function () {
    var cd = document.getElementById('create_doctor');
    var cdt = document.getElementById('create_date');
    var ed = document.getElementById('edit_doctor');
    var edt = document.getElementById('edit_date');
    if (cd) cd.addEventListener('change', refreshCreateTimeSlots);
    if (cdt) cdt.addEventListener('change', refreshCreateTimeSlots);
    if (ed) {
        ed.addEventListener('change', function () {
            refreshEditTimeSlots(document.getElementById('edit_time').value);
        });
    }
    if (edt) {
        edt.addEventListener('change', function () {
            refreshEditTimeSlots(document.getElementById('edit_time').value);
        });
    }
})();

// Toggle patient appointments
function togglePatientAppointments(patientKey) {
    const appointmentsDiv = document.getElementById('appointments-' + patientKey);
    const toggleBtn = document.querySelector(`[onclick="togglePatientAppointments('${patientKey}')"]`);
    const icon = document.getElementById('icon-' + patientKey);
    
    if (appointmentsDiv.style.display === 'none' || appointmentsDiv.style.display === '') {
        appointmentsDiv.style.display = 'block';
        toggleBtn.classList.add('expanded');
        if (icon) {
            icon.classList.remove('fa-chevron-right');
            icon.classList.add('fa-chevron-down');
        }
    } else {
        appointmentsDiv.style.display = 'none';
        toggleBtn.classList.remove('expanded');
        if (icon) {
            icon.classList.remove('fa-chevron-down');
            icon.classList.add('fa-chevron-right');
        }
    }
}
</script>

<!-- Consultation history (same modal pattern as EHR / appointments) -->
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
    background: rgba(15, 23, 42, 0.72);
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
    box-shadow: 0 25px 50px -12px rgba(0,0,0,0.45);
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
<?php
$_ph_js = dirname(__DIR__) . '/appointments/patient_history_modal.js';
$_ph_v = @filemtime($_ph_js) ?: time();
?>
<script>window.HB_PATIENT_HISTORY_API = '../appointments/patient_history_data.php';</script>
<script src="../appointments/patient_history_modal.js?v=<?php echo urlencode((string) $_ph_v); ?>"></script>

</body>
</html>

