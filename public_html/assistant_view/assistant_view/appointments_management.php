<?php
session_start();
require_once '../config/db_connect.php';
require_once __DIR__ . '/../appointments/appointment_patient_overlap.php';

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

// Check if user data was found
if (!$user_result || empty($user_result)) {
    // User not found, redirect to login
    session_destroy();
    header('Location: ../auth/login.php');
    exit();
}

// Ensure all required fields exist
$username = isset($user_result['username']) ? $user_result['username'] : '';
$email = isset($user_result['email']) ? $user_result['email'] : '';
$role = isset($user_result['role']) ? $user_result['role'] : '';

$sidebar_user_data = [
    'username' => htmlspecialchars($username),
    'email' => htmlspecialchars($email),
    'role' => htmlspecialchars($role)
];

$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'create') {
            $patient_id = intval($_POST['patient_id']);
            $doctor_id = intval($_POST['doctor_id']);
            $date = $_POST['date'];
            $time = $_POST['time'];
            $status = $_POST['status'];
            
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
                
                $message = 'Appointment created successfully';
                $messageType = 'success';
            } else {
                $message = 'Error creating appointment';
                $messageType = 'error';
            }
            }
        } elseif ($_POST['action'] === 'update_status') {
            $appointment_id = intval($_POST['appointment_id']);
            $status = $_POST['status'];
            
            $update = $conn->prepare("UPDATE appointments SET status = ? WHERE id = ?");
            $update->bind_param("si", $status, $appointment_id);
            
            if ($update->execute()) {
                // Get appointment details for notification
                $appointment_query = $conn->prepare("
                    SELECT a.doctor_id, a.appointment_date, 
                           CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                           CONCAT(u.first_name, ' ', u.last_name) as doctor_name
                    FROM appointments a
                    LEFT JOIN patients p ON a.patient_id = p.id
                    LEFT JOIN users u ON a.doctor_id = u.id
                    WHERE a.id = ?
                ");
                $appointment_query->bind_param("i", $appointment_id);
                $appointment_query->execute();
                $appointment_details = $appointment_query->get_result()->fetch_assoc();
                
                // Create notification for the doctor about status change
                if ($appointment_details) {
                    $appointment_datetime = date('M d, Y h:i A', strtotime($appointment_details['appointment_date']));
                    $notification_message = "Appointment status updated by assistant: {$appointment_details['patient_name']} on {$appointment_datetime} is now {$status}";
                    
                    $notification_query = $conn->prepare("INSERT INTO notifications (user_id, message, type, link, is_read, created_at) VALUES (?, ?, 'appointment', ?, 0, NOW())");
                    $notification_link = "../appointments/appointments.php?appointment_id=" . $appointment_id;
                    $notification_query->bind_param("iss", $appointment_details['doctor_id'], $notification_message, $notification_link);
                    $notification_query->execute();
                }
                
                $message = 'Appointment status updated and doctor has been notified';
                $messageType = 'success';
            } else {
                $message = 'Error updating appointment';
                $messageType = 'error';
            }
        }
    }
}

// Get filter parameters
$patient_name_filter = $_GET['patient_name'] ?? '';
$date_from_filter = $_GET['date_from'] ?? '';
$date_to_filter = $_GET['date_to'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Pagination parameters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$records_per_page = 15;
$offset = ($page - 1) * $records_per_page;

// Build the WHERE clause for filtering
$where_conditions = [];
$params = [];
$param_types = '';

if (!empty($patient_name_filter)) {
    $where_conditions[] = "CONCAT(p.first_name, ' ', p.last_name) LIKE ?";
    $params[] = '%' . $patient_name_filter . '%';
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

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count for pagination
$count_query = "
    SELECT COUNT(*) as total
    FROM appointments a
    LEFT JOIN patients p ON a.patient_id = p.id
    LEFT JOIN users u ON a.doctor_id = u.id
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

$total_pages = ceil($total_records / $records_per_page);

// Get appointments with pagination and filtering
$appointments_query = "
    SELECT a.id, a.appointment_date, a.status,
           CONCAT(p.first_name, ' ', p.last_name) as patient_name,
           CONCAT(u.first_name, ' ', u.last_name) as doctor_name,
           u.id as doctor_id, p.id as patient_id
    FROM appointments a
    LEFT JOIN patients p ON a.patient_id = p.id
    LEFT JOIN users u ON a.doctor_id = u.id
    $where_clause
    ORDER BY a.appointment_date DESC
    LIMIT $records_per_page OFFSET $offset
";

if (!empty($params)) {
    $appointments_stmt = $conn->prepare($appointments_query);
    $appointments_stmt->bind_param($param_types, ...$params);
    $appointments_stmt->execute();
    $appointments = $appointments_stmt->get_result();
} else {
    $appointments = $conn->query($appointments_query);
}

// Get all patients for dropdown
$patients = $conn->query("
    SELECT id, CONCAT(first_name, ' ', last_name) as name, user_id
    FROM patients
    ORDER BY first_name
");

// Get all doctors for dropdown
$doctors = $conn->query("
    SELECT id, CONCAT(first_name, ' ', last_name) as name, specialization
    FROM users
    WHERE role = 'doctor' AND status = 'active'
    ORDER BY first_name
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments Management - HealthBase</title>
    <link rel="icon" type="image/x-icon" href="/assets/icons/favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/assistant.css">
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

        .status-confirmed {
            background: #d1fae5;
            color: #065f46;
        }

        .status-completed {
            background: #dbeafe;
            color: #1e40af;
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

        .btn-icon.edit {
            background: #3b82f6;
            color: white;
        }

        .btn-icon.edit:hover {
            background: #2563eb;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
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

        /* Filter Section Styles */
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
            grid-template-columns: 2fr 1fr 1fr 1fr auto;
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

        /* Results Info */
        .results-info {
            margin-bottom: 15px;
            padding: 10px 0;
        }

        .results-count {
            font-size: 14px;
            color: #64748b;
            font-weight: 500;
        }

        /* Pagination Styles */
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

        /* Responsive Design */
        @media (max-width: 1200px) {
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
            
            .page-link {
                min-width: 35px;
                padding: 6px 8px;
                font-size: 13px;
            }
        }
    </style>
</head>
<body class="assistant-dashboard-page">
<?php include 'includes/assistant_sidebar.php'; ?>

<div class="assistant-main-content">
    <header class="assistant-header">
        <div class="assistant-header-left">
            <h1 class="assistant-welcome">Appointments Management</h1>
            <p class="assistant-subtitle">Manage and monitor all appointments</p>
        </div>
        <div class="assistant-header-right">
            <div class="current-time" style="display: flex; align-items: center; gap: 8px; padding: 8px 16px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
                <i class="fas fa-clock" style="color: #3b82f6;"></i>
                <span id="currentDateTime" style="color: #1e293b; font-weight: 600; font-size: 14px;"></span>
            </div>
        </div>
    </header>

    <div class="assistant-dashboard-content">
        <?php if ($message): ?>
            <div class="message-alert <?= $messageType ?>">
                <i class="fas <?= $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
                <?= $message ?>
            </div>
        <?php endif; ?>

        <button class="add-appointment-btn" onclick="openCreateModal()">
            <i class="fas fa-calendar-plus"></i> Create New Appointment
        </button>

        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" class="filter-form">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="patient_name">Patient Name:</label>
                        <input type="text" id="patient_name" name="patient_name" value="<?= htmlspecialchars($patient_name_filter) ?>" placeholder="Search by patient name...">
                    </div>
                    <div class="filter-group">
                        <label for="date_from">Date From:</label>
                        <input type="date" id="date_from" name="date_from" value="<?= htmlspecialchars($date_from_filter) ?>">
                    </div>
                    <div class="filter-group">
                        <label for="date_to">Date To:</label>
                        <input type="date" id="date_to" name="date_to" value="<?= htmlspecialchars($date_to_filter) ?>">
                    </div>
                    <div class="filter-group">
                        <label for="status">Status:</label>
                        <select id="status" name="status">
                            <option value="">All Statuses</option>
                            <option value="Pending" <?= $status_filter === 'Pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="Confirmed" <?= $status_filter === 'Confirmed' ? 'selected' : '' ?>>Confirmed</option>
                            <option value="Completed" <?= $status_filter === 'Completed' ? 'selected' : '' ?>>Completed</option>
                            <option value="Declined" <?= $status_filter === 'Declined' ? 'selected' : '' ?>>Declined</option>
                        </select>
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="btn-filter">
                            <i class="fas fa-search"></i> Filter
                        </button>
                        <a href="appointments_management.php" class="btn-clear">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Results Info -->
        <div class="results-info">
            <span class="results-count">Showing <?= $offset + 1 ?>-<?= min($offset + $records_per_page, $total_records) ?> of <?= $total_records ?> appointments</span>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Patient</th>
                        <th>Doctor</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($appointment = $appointments->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo date('M d, Y H:i', strtotime($appointment['appointment_date'])); ?></td>
                        <td><?php echo htmlspecialchars($appointment['patient_name']); ?></td>
                        <td><?php echo htmlspecialchars($appointment['doctor_name']); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo strtolower($appointment['status']); ?>">
                                <?php echo $appointment['status']; ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn-icon edit" onclick="openEditModal(<?= $appointment['id'] ?>, '<?= htmlspecialchars($appointment['status']) ?>')">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination-wrapper">
            <div class="pagination">
                <?php
                // Build query string for pagination links
                $query_params = [];
                if (!empty($patient_name_filter)) $query_params['patient_name'] = $patient_name_filter;
                if (!empty($date_from_filter)) $query_params['date_from'] = $date_from_filter;
                if (!empty($date_to_filter)) $query_params['date_to'] = $date_to_filter;
                if (!empty($status_filter)) $query_params['status'] = $status_filter;
                $query_string = !empty($query_params) ? '&' . http_build_query($query_params) : '';
                ?>
                
                <!-- Previous Page -->
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?><?= $query_string ?>" class="page-link prev">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                <?php endif; ?>
                
                <!-- Page Numbers -->
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
                
                <!-- Next Page -->
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?><?= $query_string ?>" class="page-link next">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
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
            <div class="form-group">
                <label for="create_patient">Patient <span style="color: red;">*</span></label>
                <select id="create_patient" name="patient_id" required>
                    <option value="">Select Patient</option>
                    <?php while ($patient = $patients->fetch_assoc()): ?>
                        <option value="<?= $patient['id'] ?>"><?= htmlspecialchars($patient['name']) ?> (ID: <?= $patient['user_id'] ?>)</option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="create_doctor">Doctor <span style="color: red;">*</span></label>
                <select id="create_doctor" name="doctor_id" required>
                    <option value="">Select Doctor</option>
                    <?php while ($doctor = $doctors->fetch_assoc()): ?>
                        <option value="<?= $doctor['id'] ?>"><?= htmlspecialchars($doctor['name']) ?> — <?= htmlspecialchars($doctor['specialization']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="create_date">Date <span style="color: red;">*</span></label>
                <input type="date" id="create_date" name="date" required min="<?= date('Y-m-d') ?>">
            </div>
            <div class="form-group">
                <label for="create_time">Time <span style="color: red;">*</span></label>
                <input type="time" id="create_time" name="time" required>
            </div>
            <div class="form-group">
                <label for="create_status">Status <span style="color: red;">*</span></label>
                <select id="create_status" name="status" required>
                    <option value="Pending">Pending</option>
                    <option value="Confirmed">Confirmed</option>
                    <option value="Completed">Completed</option>
                    <option value="Declined">Declined</option>
                </select>
            </div>
            <div class="form-actions">
                <button type="button" class="btn-cancel" onclick="closeModal('createModal')">Cancel</button>
                <button type="submit" class="btn-primary">Create Appointment</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Status Modal -->
<div class="modal-overlay" id="editModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Update Appointment Status</h3>
            <button class="close-modal" onclick="closeModal('editModal')">&times;</button>
        </div>
        <form method="POST" id="editForm">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" id="edit_appointment_id" name="appointment_id">
            <div class="form-group">
                <label for="edit_status">Status <span style="color: red;">*</span></label>
                <select id="edit_status" name="status" required>
                    <option value="Pending">Pending</option>
                    <option value="Confirmed">Confirmed</option>
                    <option value="Completed">Completed</option>
                    <option value="Declined">Declined</option>
                </select>
            </div>
            <div class="form-actions">
                <button type="button" class="btn-cancel" onclick="closeModal('editModal')">Cancel</button>
                <button type="submit" class="btn-primary">Update Status</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
function openCreateModal() {
    document.getElementById('createModal').style.display = 'flex';
}

function openEditModal(appointmentId, currentStatus) {
    document.getElementById('edit_appointment_id').value = appointmentId;
    document.getElementById('edit_status').value = currentStatus;
    document.getElementById('editModal').style.display = 'flex';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
    if (modalId === 'createModal') {
        document.getElementById('createForm').reset();
    } else {
        document.getElementById('editForm').reset();
    }
}

// Close modal when clicking outside
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.style.display = 'none';
    }
});
</script>

</body>
</html>
