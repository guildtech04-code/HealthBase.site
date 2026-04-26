<?php
// ehr_records.php - Assistant View of All EHR/Consultation Records
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/auth_guard.php';
ensure_role(['assistant', 'admin']);
include("../config/db_connect.php");
require_once __DIR__ . '/../includes/security.php';

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'assistant';

// Get filter parameters
$patient_name_filter = $_GET['patient_name'] ?? '';
$doctor_name_filter = $_GET['doctor_name'] ?? '';
$date_from_filter = $_GET['date_from'] ?? '';
$date_to_filter = $_GET['date_to'] ?? '';
$consultation_status_filter = $_GET['consultation_status'] ?? '';

// Pagination parameters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Build query with filters
$where_conditions = [];
$params = [];
$param_types = '';

if (!empty($patient_name_filter)) {
    $where_conditions[] = "(CONCAT(p.first_name, ' ', p.last_name) LIKE ?)";
    $params[] = '%' . $patient_name_filter . '%';
    $param_types .= 's';
}

if (!empty($doctor_name_filter)) {
    $where_conditions[] = "(CONCAT(u.first_name, ' ', u.last_name) LIKE ?)";
    $params[] = '%' . $doctor_name_filter . '%';
    $param_types .= 's';
}

if (!empty($date_from_filter)) {
    $where_conditions[] = "DATE(c.visit_date) >= ?";
    $params[] = $date_from_filter;
    $param_types .= 's';
}

if (!empty($date_to_filter)) {
    $where_conditions[] = "DATE(c.visit_date) <= ?";
    $params[] = $date_to_filter;
    $param_types .= 's';
}

// Check if consultation_status column exists before adding filter
$check_consultation_status = $conn->query("
    SELECT COUNT(*) as col_exists
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'consultations' 
    AND COLUMN_NAME = 'consultation_status'
");
$consultation_status_exists = $check_consultation_status->fetch_assoc()['col_exists'] > 0;

if (!empty($consultation_status_filter) && $consultation_status_exists) {
    $where_conditions[] = "c.consultation_status = ?";
    $params[] = $consultation_status_filter;
    $param_types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count for pagination
$count_query = "
    SELECT COUNT(*) as total
    FROM consultations c
    JOIN appointments a ON c.appointment_id = a.id
    JOIN patients p ON c.patient_id = p.id
    JOIN users u ON c.doctor_id = u.id
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

// Total pages will be calculated after grouping

// Check if new columns exist in the database
$check_columns = $conn->query("
    SELECT COLUMN_NAME 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'consultations' 
    AND COLUMN_NAME IN ('report_summary', 'consultation_status', 'systolic_bp', 'diastolic_bp', 'heart_rate', 'temperature_c', 'respiratory_rate', 'oxygen_saturation', 'weight_kg', 'height_cm', 'bmi')
");
$existing_columns = [];
while ($row = $check_columns->fetch_assoc()) {
    $existing_columns[] = $row['COLUMN_NAME'];
}

// Build query with only existing columns
$base_columns = "c.id, c.visit_date, c.chief_complaint, c.consultation_notes, 
           c.diagnosis, c.treatment_plan, c.follow_up_date";
$new_columns = "";
if (in_array('report_summary', $existing_columns)) {
    $new_columns .= ", c.report_summary";
}
if (in_array('consultation_status', $existing_columns)) {
    $new_columns .= ", c.consultation_status";
}
if (in_array('systolic_bp', $existing_columns)) {
    $new_columns .= ", c.systolic_bp, c.diastolic_bp, c.heart_rate";
}
if (in_array('temperature_c', $existing_columns)) {
    $new_columns .= ", c.temperature_c, c.respiratory_rate, c.oxygen_saturation";
}
if (in_array('weight_kg', $existing_columns)) {
    $new_columns .= ", c.weight_kg, c.height_cm, c.bmi";
}

// Get all consultations (without pagination limit for grouping)
// Include follow-up appointment information if exists
$consultations_query = "
    SELECT $base_columns $new_columns,
           CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
           p.age, p.gender, p.id as patient_id,
           CONCAT(u.first_name, ' ', u.last_name) AS doctor_name, u.specialization,
           a.id as appointment_id, a.status as appointment_status,
           a_followup.id as followup_appointment_id,
           a_followup.appointment_date as followup_appointment_date,
           a_followup.status as followup_appointment_status
    FROM consultations c
    JOIN appointments a ON c.appointment_id = a.id
    JOIN patients p ON c.patient_id = p.id
    JOIN users u ON c.doctor_id = u.id
    LEFT JOIN appointments a_followup ON (
        a_followup.patient_id = c.patient_id 
        AND a_followup.doctor_id = c.doctor_id 
        AND DATE(a_followup.appointment_date) = DATE(c.follow_up_date)
        AND a_followup.status NOT IN ('Cancelled', 'declined')
    )
    $where_clause
    ORDER BY p.first_name, p.last_name, c.visit_date DESC
";

if (!empty($params)) {
    $stmt = $conn->prepare($consultations_query);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $consultations_result = $stmt->get_result();
} else {
    $consultations_result = $conn->query($consultations_query);
}

// Group consultation records by patient so each patient appears once with expandable records
$grouped_consultations = [];
if ($consultations_result && $consultations_result->num_rows > 0) {
    while ($row = $consultations_result->fetch_assoc()) {
        $patient_name = trim($row['patient_name'] ?? '');
        if ($patient_name === '') {
            $patient_name = 'Unknown Patient';
        }

        if (!isset($grouped_consultations[$patient_name])) {
            $grouped_consultations[$patient_name] = [
                'patient_id'      => $row['patient_id'] ?? null,
                'age'             => $row['age'] ?? null,
                'gender'          => $row['gender'] ?? null,
                'consultations'   => []
            ];
        }

        $grouped_consultations[$patient_name]['consultations'][] = $row;
    }
}

// Get total count of unique patients for pagination
$total_patients = count($grouped_consultations);
$total_pages = ceil($total_patients / $records_per_page);

// Apply pagination to grouped patients
$grouped_consultations = array_slice($grouped_consultations, $offset, $records_per_page, true);

// Function to normalize appointment status values (map old statuses to new ones)
function normalizeAppointmentStatus($status) {
    $statusMap = [
        'Completed' => 'Cleared',
        'Confirmed' => 'Cleared',
        'Declined' => 'Cancelled'
    ];
    return $statusMap[$status] ?? $status;
}

// Get user info for sidebar
$query = $conn->prepare("SELECT username, email FROM users WHERE id = ?");
$query->bind_param("i", $user_id);
$query->execute();
$user_result = $query->get_result();
$user = $user_result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>EHR Records - HealthBase</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../assistant_view/css/assistant.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .ehr-container {
            max-width: 1400px;
            margin: 20px auto;
            padding: 0 20px;
        }
        
        .page-header {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        
        .page-header h1 {
            font-size: 28px;
            color: #1e293b;
            margin-bottom: 10px;
            font-weight: 700;
        }
        
        .page-header p {
            color: #64748b;
            font-size: 15px;
        }
        
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 5px;
            color: #475569;
            font-weight: 500;
            font-size: 13px;
        }
        
        .filter-group input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .filter-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-filter {
            padding: 8px 20px;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
        }
        
        .btn-clear {
            padding: 8px 20px;
            background: #e2e8f0;
            color: #475569;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
        }
        
        .records-grid {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .consultation-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        
        .consultation-card:hover {
            box-shadow: 0 4px 20px rgba(0,0,0,0.12);
        }
        
        .consultation-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .consultation-header-left h3 {
            font-size: 20px;
            color: #1e293b;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .consultation-meta {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            color: #64748b;
            font-size: 14px;
            margin-top: 10px;
        }
        
        .consultation-meta span {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .consultation-meta i {
            color: #3b82f6;
        }
        
        .consultation-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .content-section {
            background: #f8fafc;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #3b82f6;
        }
        
        .content-section h4 {
            font-size: 14px;
            color: #3b82f6;
            margin-bottom: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .content-section p {
            color: #1e293b;
            font-size: 14px;
            line-height: 1.6;
            white-space: pre-wrap;
        }
        
        .prescriptions-section {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }
        
        .prescription-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            margin-bottom: 12px;
        }
        
        .prescription-header {
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 10px;
        }
        
        .prescription-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            font-size: 13px;
            color: #64748b;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .empty-state i {
            font-size: 64px;
            color: #cbd5e1;
            margin-bottom: 20px;
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

        /* Patient grouping styles */
        .patient-group-card {
            background: white;
            border-radius: 12px;
            padding: 0;
            margin-bottom: 20px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }

        .patient-group-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 20px;
            cursor: pointer;
            background: #0f172a;
            border-bottom: 1px solid #1e293b;
        }

        .patient-header-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .patient-toggle-icon {
            width: 28px;
            height: 28px;
            border-radius: 999px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(148, 163, 184, 0.15);
            color: #e5e7eb;
            transition: background 0.2s ease, transform 0.2s ease;
        }

        .patient-toggle-icon i {
            transition: transform 0.2s ease;
        }

        .patient-group-header:hover .patient-toggle-icon {
            background: rgba(59, 130, 246, 0.3);
        }

        .patient-toggle-icon.expanded i {
            transform: rotate(90deg);
        }

        .patient-name {
            font-weight: 600;
            color: #e5e7eb;
            font-size: 15px;
        }

        .patient-email {
            font-size: 12px;
            color: #9ca3af;
        }

        .patient-meta {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .patient-count-pill {
            font-size: 11px;
            color: #e5e7eb;
            background: rgba(148, 163, 184, 0.25);
            padding: 6px 10px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .patient-count-pill i {
            color: #93c5fd;
        }

        .patient-consultations {
            padding: 18px 20px 8px 20px;
            background: #f8fafc;
        }

        .patient-toggle-btn {
            transition: transform 0.3s ease;
        }

        .patient-toggle-btn i {
            transition: transform 0.3s ease;
        }

        .patient-toggle-btn.expanded i {
            transform: rotate(90deg);
        }

        .patient-consultations {
            animation: slideDown 0.3s ease;
            margin-top: 15px;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                max-height: 0;
            }
            to {
                opacity: 1;
                max-height: 5000px;
            }
        }
    </style>
</head>
<body class="assistant-dashboard-page">
    <?php include 'includes/assistant_sidebar.php'; ?>
    
    <div class="assistant-main-content">
        <!-- Header -->
        <header class="assistant-header">
            <div class="assistant-header-left">
                <h1 class="assistant-welcome">EHR Records</h1>
                <p class="assistant-subtitle">View all consultation records, diagnoses, prescriptions, and treatment plans</p>
            </div>
            <div class="assistant-header-right">
                <div class="current-time" style="display: flex; align-items: center; gap: 8px; padding: 8px 16px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
                    <i class="fas fa-clock" style="color: #3b82f6;"></i>
                    <span id="currentDateTime" style="color: #1e293b; font-weight: 600; font-size: 14px;"></span>
                </div>
            </div>
        </header>

        <div class="assistant-dashboard-content">
            <div class="ehr-container">
            
            <!-- Searchable EHR Records Dropdown -->
            <div style="background: white; padding: 20px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 2px 12px rgba(0,0,0,0.08);">
                <label style="display: block; margin-bottom: 10px; font-weight: 600; color: #1e293b;">
                    <i class="fas fa-search"></i> Quick Search EHR Records
                </label>
                <div style="position: relative;">
                    <input type="text" id="ehrSearchInput" placeholder="Search by patient name, doctor name, diagnosis, or health concern..." 
                           style="width: 100%; padding: 12px 40px 12px 15px; border: 1.5px solid #e2e8f0; border-radius: 8px; font-size: 14px;">
                    <i class="fas fa-search" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); color: #64748b;"></i>
                </div>
                <div id="ehrSearchResults" style="margin-top: 10px; max-height: 300px; overflow-y: auto; display: none; border: 1px solid #e2e8f0; border-radius: 8px; background: white;">
                    <!-- Search results will appear here -->
                </div>
            </div>
            
            <div class="filter-section">
                <form method="GET" class="filter-form">
                    <div class="filter-grid">
                        <div class="filter-group">
                            <label>Patient Name</label>
                            <input type="text" name="patient_name" value="<?= htmlspecialchars($patient_name_filter) ?>" placeholder="Search by patient name">
                        </div>
                        <div class="filter-group">
                            <label>Doctor Name</label>
                            <input type="text" name="doctor_name" value="<?= htmlspecialchars($doctor_name_filter) ?>" placeholder="Search by doctor name">
                        </div>
                        <div class="filter-group">
                            <label>Date From</label>
                            <input type="date" name="date_from" value="<?= htmlspecialchars($date_from_filter) ?>">
                        </div>
                        <div class="filter-group">
                            <label>Date To</label>
                            <input type="date" name="date_to" value="<?= htmlspecialchars($date_to_filter) ?>">
                        </div>
                        <div class="filter-group">
                            <label>Consultation Status</label>
                            <select name="consultation_status" style="width: 100%; padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 14px;">
                                <option value="">All Statuses</option>
                                <option value="Pending" <?= $consultation_status_filter === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="Cleared" <?= $consultation_status_filter === 'Cleared' ? 'selected' : '' ?>>Cleared</option>
                                <option value="Cancelled" <?= $consultation_status_filter === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            </select>
                        </div>
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="btn-filter">
                            <i class="fas fa-search"></i> Filter
                        </button>
                        <a href="ehr_records.php" class="btn-clear">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    </div>
                </form>
            </div>

            <!-- Results Info -->
            <div class="results-info">
                <span class="results-count">Showing <?= $offset + 1 ?>-<?= min($offset + $records_per_page, $total_patients) ?> of <?= $total_patients ?> patients (<?= $total_records ?> total records)</span>
            </div>

            <div class="records-grid">
                <?php if (!empty($grouped_consultations)): ?>
                    <?php foreach ($grouped_consultations as $patient_name => $patient_data): 
                        $consultation_count = count($patient_data['consultations']);
                        $patient_key = 'patient_' . md5($patient_name);
                    ?>
                        <div class="consultation-card patient-group-card" data-patient-key="<?= $patient_key ?>">
                            <div class="consultation-header">
                                <div class="consultation-header-left">
                                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 15px;">
                                        <button class="patient-toggle-btn" onclick="togglePatientConsultations('<?= $patient_key ?>')" style="background: none; border: none; cursor: pointer; color: #3b82f6; font-size: 16px; padding: 4px;">
                                            <i class="fas fa-chevron-right" id="icon-<?= $patient_key ?>"></i>
                                        </button>
                                        <div style="flex: 1;">
                                            <h3 style="margin: 0; font-size: 20px; color: #1e293b; cursor: pointer;" onclick="togglePatientConsultations('<?= $patient_key ?>')">
                                                <i class="fas fa-user-injured"></i> <?php echo htmlspecialchars($patient_name); ?>
                                            </h3>
                                            <div style="display: flex; gap: 15px; margin-top: 8px; flex-wrap: wrap;">
                                                <?php if ($patient_data['age']): ?>
                                                <span style="font-size: 13px; color: #64748b;">
                                                    <i class="fas fa-birthday-cake"></i> Age: <?= $patient_data['age'] ?>
                                                </span>
                                                <?php endif; ?>
                                                <?php if ($patient_data['gender']): ?>
                                                <span style="font-size: 13px; color: #64748b;">
                                                    <i class="fas fa-<?= $patient_data['gender'] === 'Male' ? 'mars' : 'venus' ?>"></i> <?= htmlspecialchars($patient_data['gender']) ?>
                                                </span>
                                                <?php endif; ?>
                                                <span style="font-size: 13px; color: #64748b;">
                                                    <i class="fas fa-file-medical-alt"></i> <?= $consultation_count ?> consultation<?= $consultation_count != 1 ? 's' : '' ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <a href="../appointments/patient_history.php?patient_id=<?= $patient_data['patient_id'] ?>" class="btn-filter" style="text-decoration: none; display: inline-block;">
                                        <i class="fas fa-history"></i> View Full History
                                    </a>
                                </div>
                            </div>

                            <div class="patient-consultations" id="consultations-<?= $patient_key ?>" style="display: none;">
                                <?php foreach ($patient_data['consultations'] as $consultation): 
                        // Fetch prescriptions for this consultation
                        $prescriptions_query = $conn->prepare("
                            SELECT medication_name, dosage, frequency, duration, instructions, quantity
                            FROM prescriptions
                            WHERE consultation_id = ?
                            ORDER BY id ASC
                        ");
                        $prescriptions_query->bind_param("i", $consultation['id']);
                        $prescriptions_query->execute();
                        $prescriptions_result = $prescriptions_query->get_result();
                    ?>
                                    <div class="consultation-card" style="margin-top: 15px; background: #f8fafc; border: 1px solid #e2e8f0;" 
                                         data-consultation-id="<?= $consultation['id'] ?>" 
                                         data-patient-name="<?= htmlspecialchars(strtolower($consultation['patient_name'])) ?>"
                                         data-doctor-name="<?= htmlspecialchars(strtolower($consultation['doctor_name'])) ?>"
                                         data-diagnosis="<?= htmlspecialchars(strtolower($consultation['diagnosis'] ?? '')) ?>">
                                        <div class="consultation-header">
                                            <div class="consultation-header-left">
                                                <h3 style="font-size: 16px;"><i class="fas fa-stethoscope"></i> Consultation Record #<?= $consultation['id'] ?></h3>
                                                <div class="consultation-meta">
                                                    <span><i class="fas fa-calendar"></i> <?= date('F d, Y', strtotime($consultation['visit_date'])) ?></span>
                                                    <span><i class="fas fa-clock"></i> <?= date('h:i A', strtotime($consultation['visit_date'])) ?></span>
                                                    <span><i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($consultation['doctor_name']) ?></span>
                                                    <span><i class="fas fa-clipboard"></i> <?= htmlspecialchars($consultation['specialization']) ?></span>
                                                </div>
                                                <div style="display: flex; gap: 10px; margin-top: 12px; flex-wrap: wrap;">
                                                    <?php if (isset($consultation['appointment_status']) && !empty($consultation['appointment_status'])): 
                                                        $apptStatus = normalizeAppointmentStatus($consultation['appointment_status']);
                                                    ?>
                                                    <span style="padding: 6px 14px; border-radius: 12px; font-size: 12px; font-weight: 600; text-transform: uppercase; display: inline-flex; align-items: center; gap: 6px;
                                                        <?php 
                                                        if ($apptStatus === 'Cleared') echo 'background: #dbeafe; color: #1e40af; border: 1px solid #93c5fd;';
                                                        elseif ($apptStatus === 'Cancelled') echo 'background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5;';
                                                        else echo 'background: #fef3c7; color: #92400e; border: 1px solid #fcd34d;';
                                                        ?>">
                                                        <i class="fas fa-calendar-check"></i>
                                                        <span style="font-size: 10px; opacity: 0.8; margin-right: 4px;">APPT:</span>
                                                        <?= htmlspecialchars($apptStatus) ?>
                                                    </span>
                                                    <?php endif; ?>
                                                    <?php if (isset($consultation['consultation_status']) && !empty($consultation['consultation_status'])): 
                                                        $consultStatus = $consultation['consultation_status'];
                                                    ?>
                                                    <span style="padding: 6px 14px; border-radius: 12px; font-size: 12px; font-weight: 600; text-transform: uppercase; display: inline-flex; align-items: center; gap: 6px;
                                                        <?php 
                                                        if ($consultStatus === 'Cleared') echo 'background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7;';
                                                        elseif ($consultStatus === 'Cancelled') echo 'background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5;';
                                                        else echo 'background: #fef3c7; color: #92400e; border: 1px solid #fcd34d;';
                                                        ?>">
                                                        <i class="fas fa-<?= $consultStatus === 'Cleared' ? 'check-circle' : ($consultStatus === 'Cancelled' ? 'times-circle' : 'clock') ?>"></i>
                                                        <span style="font-size: 10px; opacity: 0.8; margin-right: 4px;">CONSULT:</span>
                                                        <?= htmlspecialchars($consultStatus) ?>
                                                    </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="consultation-content">
                                            <?php if (!empty($consultation['chief_complaint'])): ?>
                                            <div class="content-section">
                                                <h4><i class="fas fa-user-injured"></i> Chief Complaint</h4>
                                                <p><?= nl2br(htmlspecialchars($consultation['chief_complaint'])) ?></p>
                                            </div>
                                            <?php endif; ?>

                                            <?php if (!empty($consultation['consultation_notes'])): ?>
                                            <div class="content-section">
                                                <h4><i class="fas fa-clipboard-list"></i> Clinical Observations</h4>
                                                <p><?= nl2br(htmlspecialchars($consultation['consultation_notes'])) ?></p>
                                            </div>
                                            <?php endif; ?>

                                            <?php if (!empty($consultation['diagnosis'])): ?>
                                            <div class="content-section">
                                                <h4><i class="fas fa-diagnoses"></i> Diagnosis</h4>
                                                <p><?= nl2br(htmlspecialchars($consultation['diagnosis'])) ?></p>
                                            </div>
                                            <?php endif; ?>

                                            <?php if (!empty($consultation['treatment_plan'])): ?>
                                            <div class="content-section">
                                                <h4><i class="fas fa-heartbeat"></i> Treatment Plan</h4>
                                                <p><?= nl2br(htmlspecialchars($consultation['treatment_plan'])) ?></p>
                                            </div>
                                            <?php endif; ?>

                                            <?php if (isset($consultation['report_summary']) && !empty($consultation['report_summary'])): ?>
                                            <div class="content-section">
                                                <h4><i class="fas fa-file-medical-alt"></i> Report Summary</h4>
                                                <p><?= nl2br(htmlspecialchars($consultation['report_summary'])) ?></p>
                                            </div>
                                            <?php endif; ?>

                                            <?php if (isset($consultation['systolic_bp']) && ($consultation['systolic_bp'] || $consultation['diastolic_bp'] || $consultation['heart_rate'] || 
                                                      $consultation['temperature_c'] || $consultation['respiratory_rate'] || $consultation['oxygen_saturation'] ||
                                                      $consultation['weight_kg'] || $consultation['height_cm'] || $consultation['bmi'])): ?>
                                            <div class="content-section" style="grid-column: 1 / -1;">
                                                <h4><i class="fas fa-heartbeat"></i> Vital Signs</h4>
                                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-top: 10px;">
                                                    <?php if ($consultation['systolic_bp'] && $consultation['diastolic_bp']): ?>
                                                    <div>
                                                        <strong style="color: #64748b; font-size: 12px;">Blood Pressure</strong>
                                                        <div style="color: #1e293b; font-size: 16px; font-weight: 600;">
                                                            <?= $consultation['systolic_bp'] ?>/<?= $consultation['diastolic_bp'] ?> mmHg
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>
                                                    <?php if ($consultation['heart_rate']): ?>
                                                    <div>
                                                        <strong style="color: #64748b; font-size: 12px;">Heart Rate</strong>
                                                        <div style="color: #1e293b; font-size: 16px; font-weight: 600;">
                                                            <?= $consultation['heart_rate'] ?> bpm
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>
                                                    <?php if ($consultation['temperature_c']): ?>
                                                    <div>
                                                        <strong style="color: #64748b; font-size: 12px;">Temperature</strong>
                                                        <div style="color: #1e293b; font-size: 16px; font-weight: 600;">
                                                            <?= $consultation['temperature_c'] ?> °C
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>
                                                    <?php if ($consultation['respiratory_rate']): ?>
                                                    <div>
                                                        <strong style="color: #64748b; font-size: 12px;">Respiratory Rate</strong>
                                                        <div style="color: #1e293b; font-size: 16px; font-weight: 600;">
                                                            <?= $consultation['respiratory_rate'] ?> bpm
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>
                                                    <?php if ($consultation['oxygen_saturation']): ?>
                                                    <div>
                                                        <strong style="color: #64748b; font-size: 12px;">Oxygen Saturation</strong>
                                                        <div style="color: #1e293b; font-size: 16px; font-weight: 600;">
                                                            <?= $consultation['oxygen_saturation'] ?>%
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>
                                                    <?php if ($consultation['weight_kg']): ?>
                                                    <div>
                                                        <strong style="color: #64748b; font-size: 12px;">Weight</strong>
                                                        <div style="color: #1e293b; font-size: 16px; font-weight: 600;">
                                                            <?= $consultation['weight_kg'] ?> kg
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>
                                                    <?php if ($consultation['height_cm']): ?>
                                                    <div>
                                                        <strong style="color: #64748b; font-size: 12px;">Height</strong>
                                                        <div style="color: #1e293b; font-size: 16px; font-weight: 600;">
                                                            <?= $consultation['height_cm'] ?> cm
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>
                                                    <?php if ($consultation['bmi']): ?>
                                                    <div>
                                                        <strong style="color: #64748b; font-size: 12px;">BMI</strong>
                                                        <div style="color: #1e293b; font-size: 16px; font-weight: 600;">
                                                            <?= $consultation['bmi'] ?>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </div>

                                        <?php if ($prescriptions_result->num_rows > 0): ?>
                                        <div class="prescriptions-section">
                                            <h4 style="font-size: 16px; color: #1e293b; margin-bottom: 15px;"><i class="fas fa-pills"></i> Prescriptions</h4>
                                            <?php while ($presc = $prescriptions_result->fetch_assoc()): ?>
                                                <div class="prescription-item">
                                                    <div class="prescription-header"><?= htmlspecialchars($presc['medication_name']) ?></div>
                                                    <div class="prescription-details">
                                                        <?php if (!empty($presc['dosage'])): ?>
                                                        <span><i class="fas fa-weight"></i> Dosage: <?= htmlspecialchars($presc['dosage']) ?></span>
                                                        <?php endif; ?>
                                                        <?php if (!empty($presc['frequency'])): ?>
                                                        <span><i class="fas fa-clock"></i> Frequency: <?= htmlspecialchars($presc['frequency']) ?></span>
                                                        <?php endif; ?>
                                                        <?php if (!empty($presc['duration'])): ?>
                                                        <span><i class="fas fa-calendar"></i> Duration: <?= htmlspecialchars($presc['duration']) ?></span>
                                                        <?php endif; ?>
                                                        <?php if (!empty($presc['quantity'])): ?>
                                                        <span><i class="fas fa-capsules"></i> Quantity: <?= htmlspecialchars($presc['quantity']) ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php if (!empty($presc['instructions'])): ?>
                                                    <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #e2e8f0; font-size: 13px; color: #64748b;">
                                                        <strong>Instructions:</strong> <?= nl2br(htmlspecialchars($presc['instructions'])) ?>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endwhile; ?>
                                        </div>
                                        <?php endif; ?>

                                        <?php if (!empty($consultation['follow_up_date'])): 
                                            $followup_appointment_id = $consultation['followup_appointment_id'] ?? null;
                                            $followup_appointment_status = $consultation['followup_appointment_status'] ?? null;
                                            $followup_appointment_date = $consultation['followup_appointment_date'] ?? null;
                                            $has_appointment = !empty($followup_appointment_id);
                                            $follow_up_date_formatted = date('F d, Y', strtotime($consultation['follow_up_date']));
                                            $follow_up_date_formatted_with_time = $followup_appointment_date ? date('F d, Y h:i A', strtotime($followup_appointment_date)) : $follow_up_date_formatted;
                                        ?>
                                        <div style="background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); padding: 15px; border-radius: 8px; margin-top: 20px; border-left: 4px solid #3b82f6;">
                                            <div style="display: flex; align-items: start; gap: 12px; margin-bottom: 10px;">
                                                <i class="fas fa-calendar-check" style="color: #3b82f6; font-size: 20px; margin-top: 2px;"></i>
                                                <div style="flex: 1;">
                                                    <strong style="color: #1e293b; display: block; margin-bottom: 6px;">Follow-up Scheduled:</strong>
                                                    <div style="color: #1e293b; font-size: 15px; margin-bottom: 8px;">
                                                        <?= $follow_up_date_formatted_with_time ?>
                                                    </div>
                                                    <?php if ($has_appointment): ?>
                                                        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-top: 8px;">
                                                            <span style="padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 600; text-transform: uppercase; display: inline-flex; align-items: center; gap: 4px;
                                                                <?php 
                                                                $status = $followup_appointment_status;
                                                                if ($status === 'Cleared' || $status === 'Completed') echo 'background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7;';
                                                                elseif ($status === 'Cancelled' || strtolower($status) === 'declined') echo 'background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5;';
                                                                elseif ($status === 'Pending' || $status === 'Confirmed') echo 'background: #fef3c7; color: #92400e; border: 1px solid #fcd34d;';
                                                                else echo 'background: #e2e8f0; color: #475569; border: 1px solid #cbd5e1;';
                                                                ?>">
                                                                <i class="fas fa-circle" style="font-size: 6px;"></i>
                                                                Appointment: <?= htmlspecialchars($status) ?>
                                                            </span>
                                                            <a href="../appointments/appointments.php?appointment_id=<?= $followup_appointment_id ?>" 
                                                               style="padding: 4px 12px; background: #3b82f6; color: white; border-radius: 6px; text-decoration: none; font-size: 12px; font-weight: 500; display: inline-flex; align-items: center; gap: 4px; transition: background 0.2s;"
                                                               onmouseover="this.style.background='#2563eb'" 
                                                               onmouseout="this.style.background='#3b82f6'">
                                                                <i class="fas fa-external-link-alt"></i> View Appointment
                                                            </a>
                                                        </div>
                                                    <?php else: ?>
                                                        <div style="margin-top: 8px; padding: 8px 12px; background: rgba(251, 191, 36, 0.1); border-radius: 6px; border: 1px solid rgba(251, 191, 36, 0.3);">
                                                            <span style="font-size: 12px; color: #92400e; display: flex; align-items: center; gap: 6px;">
                                                                <i class="fas fa-exclamation-triangle"></i>
                                                                Follow-up date set, but no appointment created yet
                                                            </span>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-file-medical-alt"></i>
                        <h3>No Consultation Records Found</h3>
                        <p>No EHR records match your search criteria.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination-wrapper">
                <div class="pagination">
                    <?php
                    $query_params = [];
                    if (!empty($patient_name_filter)) $query_params['patient_name'] = $patient_name_filter;
                    if (!empty($doctor_name_filter)) $query_params['doctor_name'] = $doctor_name_filter;
                    if (!empty($date_from_filter)) $query_params['date_from'] = $date_from_filter;
                    if (!empty($date_to_filter)) $query_params['date_to'] = $date_to_filter;
                    if (!empty($consultation_status_filter)) $query_params['consultation_status'] = $consultation_status_filter;
                    $query_string = !empty($query_params) ? '&' . http_build_query($query_params) : '';
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
            </div>
        </div>
    </div>

    <script>
        // Current Date/Time for Assistant Header
        function updateDateTime() {
            const element = document.getElementById('currentDateTime');
            if (!element) return; // Element doesn't exist (not assistant view)
            
            const now = new Date();
            const options = { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit', 
                minute: '2-digit',
                second: '2-digit'
            };
            element.textContent = now.toLocaleDateString('en-US', options);
        }
        updateDateTime();
        setInterval(updateDateTime, 1000);

        // Searchable EHR Records functionality
        const ehrSearchInput = document.getElementById('ehrSearchInput');
        const ehrSearchResults = document.getElementById('ehrSearchResults');
        const consultationCards = document.querySelectorAll('.consultation-card');
        
        function performSearch(query) {
            if (!query || query.length < 2) {
                ehrSearchResults.style.display = 'none';
                // Show all cards
                consultationCards.forEach(card => card.style.display = 'block');
                return;
            }
            
            const searchTerm = query.toLowerCase();
            const matches = [];
            
            consultationCards.forEach(card => {
                const patientName = card.dataset.patientName || '';
                const doctorName = card.dataset.doctorName || '';
                const diagnosis = card.dataset.diagnosis || '';
                const consultationId = card.dataset.consultationId || '';
                
                const isMatch = 
                    patientName.includes(searchTerm) ||
                    doctorName.includes(searchTerm) ||
                    diagnosis.includes(searchTerm) ||
                    consultationId.includes(searchTerm);
                
                if (isMatch) {
                    matches.push({
                        id: consultationId,
                        patientName: card.querySelector('.consultation-meta span')?.textContent || '',
                        card: card
                    });
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
            
            // Show search results dropdown
            if (matches.length > 0) {
                ehrSearchResults.innerHTML = matches.map(match => {
                    const card = match.card;
                    const visitDate = card.querySelector('.consultation-meta span')?.textContent || '';
                    const doctorName = card.querySelectorAll('.consultation-meta span')[3]?.textContent || '';
                    return `
                        <div style="padding: 12px; border-bottom: 1px solid #e2e8f0; cursor: pointer; transition: background 0.2s;"
                             onmouseover="this.style.background='#f8fafc'" 
                             onmouseout="this.style.background='white'"
                             onclick="document.getElementById('ehrSearchResults').style.display='none'; document.getElementById('ehrSearchInput').value=''; document.querySelectorAll('.consultation-card').forEach(c => c.style.display = 'block'); window.scrollTo({top: ${card.offsetTop - 100}, behavior: 'smooth'});">
                            <div style="font-weight: 600; color: #1e293b; margin-bottom: 4px;">${match.patientName}</div>
                            <div style="font-size: 12px; color: #64748b;">
                                ${visitDate} • ${doctorName}
                            </div>
                        </div>
                    `;
                }).join('');
                ehrSearchResults.style.display = 'block';
            } else {
                ehrSearchResults.innerHTML = '<div style="padding: 20px; text-align: center; color: #64748b;">No matching records found</div>';
                ehrSearchResults.style.display = 'block';
            }
        }
        
        if (ehrSearchInput) {
            ehrSearchInput.addEventListener('input', function() {
                performSearch(this.value);
            });
        }
        
        // Toggle patient consultations
        function togglePatientConsultations(patientKey) {
            const consultationsDiv = document.getElementById('consultations-' + patientKey);
            const toggleBtn = document.querySelector(`[onclick="togglePatientConsultations('${patientKey}')"]`);
            const icon = document.getElementById('icon-' + patientKey);
            
            if (consultationsDiv) {
                if (consultationsDiv.style.display === 'none' || consultationsDiv.style.display === '') {
                    consultationsDiv.style.display = 'block';
                    if (icon) icon.style.transform = 'rotate(90deg)';
                    if (toggleBtn) toggleBtn.classList.add('expanded');
                } else {
                    consultationsDiv.style.display = 'none';
                    if (icon) icon.style.transform = 'rotate(0deg)';
                    if (toggleBtn) toggleBtn.classList.remove('expanded');
                }
            }
        }

        // Close search results when clicking outside
        document.addEventListener('click', function(e) {
            if (ehrSearchInput && ehrSearchResults && 
                !ehrSearchInput.contains(e.target) && !ehrSearchResults.contains(e.target)) {
                ehrSearchResults.style.display = 'none';
            }
        });
    </script>
</body>
</html>

