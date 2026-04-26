<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include("../config/db_connect.php");

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user info (authoritative role from DB — must match patient_history_data.php)
$user_query = $conn->prepare("SELECT username, email, role, specialization FROM users WHERE id = ?");
$user_query->bind_param("i", $user_id);
$user_query->execute();
$user_result = $user_query->get_result()->fetch_assoc();

if (!$user_result) {
    session_destroy();
    header("Location: ../auth/login.php");
    exit();
}

$username = $user_result['username'] ?? '';
$email = $user_result['email'] ?? '';
$role = $user_result['role'] ?? '';
$user_specialization = trim((string) ($user_result['specialization'] ?? ''));

$role_lower = strtolower(trim((string) $role));
if (!in_array($role_lower, ['assistant', 'admin', 'doctor'], true)) {
    header("Location: ../dashboard/healthbase_dashboard.php");
    exit();
}

$_SESSION['role'] = $role;
$is_doctor_user = ($role_lower === 'doctor');

$sidebar_user_data = [
    'username' => htmlspecialchars($username),
    'email' => htmlspecialchars($email),
    'role' => htmlspecialchars($role),
    'specialization' => $user_specialization !== '' ? $user_specialization : 'General',
];

// Fetch patients: doctors only if there is consultation history with them OR a non–cancelled/declined appointment
$appt_not_void = "(a.status IS NULL OR a.status NOT IN ('Declined','Cancelled','declined','cancelled','Canceled','canceled'))";
if ($is_doctor_user) {
    $patients_query = $conn->prepare("
        SELECT 
            p.id,
            p.first_name,
            p.last_name,
            u.email,
            p.gender,
            p.age,
            p.health_concern,
            (SELECT COUNT(*) FROM appointments a1 WHERE a1.patient_id = p.id AND a1.doctor_id = ?) as total_appointments,
            (SELECT MAX(appointment_date) FROM appointments a2 WHERE a2.patient_id = p.id AND a2.doctor_id = ?) as last_appointment,
            (SELECT COUNT(*) FROM appointments a3 WHERE a3.patient_id = p.id AND a3.status = 'Pending' AND a3.doctor_id = ?) as pending_appointments
        FROM patients p
        INNER JOIN users u ON p.user_id = u.id
        WHERE p.id IN (
            SELECT patient_id FROM consultations WHERE doctor_id = ?
            UNION
            SELECT patient_id FROM appointments a WHERE doctor_id = ? AND $appt_not_void
        )
        ORDER BY p.last_name, p.first_name
    ");
    $d1 = (int) $user_id;
    $d2 = (int) $user_id;
    $d3 = (int) $user_id;
    $d4 = (int) $user_id;
    $d5 = (int) $user_id;
    $patients_query->bind_param("iiiii", $d1, $d2, $d3, $d4, $d5);
} else {
    $patients_query = $conn->prepare("
        SELECT 
            p.id,
            p.first_name,
            p.last_name,
            u.email,
            p.gender,
            p.age,
            p.health_concern,
            (SELECT COUNT(*) FROM appointments WHERE patient_id = p.id) as total_appointments,
            (SELECT MAX(appointment_date) FROM appointments WHERE patient_id = p.id) as last_appointment,
            (SELECT COUNT(*) FROM appointments WHERE patient_id = p.id AND status = 'Pending') as pending_appointments
        FROM patients p
        INNER JOIN users u ON p.user_id = u.id
        ORDER BY p.last_name, p.first_name
    ");
}

if (!$patients_query->execute()) {
    die("Execute failed: " . $patients_query->error);
}

$patients_result = $patients_query->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Management - HealthBase</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <?php if ($is_doctor_user): ?>
    <link rel="stylesheet" href="../css/dashboard.css">
    <?php else: ?>
    <link rel="stylesheet" href="css/assistant.css">
    <?php endif; ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
        }
        .search-section {
            background: white;
            padding: 12px 14px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 14px;
            border: 1px solid #e2e8f0;
        }
        
        .search-section h3 {
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
            margin: 0 0 10px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .assistant-header {
            margin-bottom: 0;
        }

        /* Main column width is handled globally in assistant.css (calc with sidebar margin). */
        body.patient-management-page .assistant-main-content {
            min-width: 0;
        }

        body.patient-management-page.dashboard-page .main-content {
            width: auto;
            max-width: none;
            margin-right: 0;
            box-sizing: border-box;
            /* Pull content toward the sidebar (dashboard.css uses 30px left) */
            padding-left: 8px !important;
            padding-top: 12px;
            padding-right: 24px;
            padding-bottom: 24px;
        }

        body.patient-management-page .assistant-header {
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            /* assistant-main-content has no outer padding — align title with sidebar edge */
            padding: 8px 16px 10px 8px !important;
        }

        body.patient-management-page .assistant-header-left {
            flex: 1 1 auto;
            min-width: 0;
            display: flex;
            flex-direction: row;
            align-items: center;
            flex-wrap: wrap;
            gap: 6px 12px;
            text-align: left;
        }

        body.patient-management-page .assistant-header-right {
            flex-shrink: 0;
        }

        body.patient-management-page .assistant-welcome,
        body.patient-management-page .header-title {
            text-align: left;
            width: auto;
            max-width: 100%;
            margin: 0;
            font-size: 1.125rem;
            line-height: 1.3;
            font-weight: 700;
        }

        body.patient-management-page .assistant-subtitle,
        body.patient-management-page .header-subtitle {
            text-align: left;
            margin: 0 !important;
            font-size: 12px;
            font-weight: 500;
            color: #64748b;
            padding-left: 12px;
            border-left: 1px solid #e2e8f0;
            line-height: 1.35;
        }

        body.patient-management-page .pm-header-date {
            padding: 5px 10px;
            font-size: 12px;
            font-weight: 600;
        }

        body.patient-management-page .pm-header-date i {
            font-size: 12px;
        }

        body.patient-management-page .main-header {
            justify-content: space-between;
            height: auto;
            min-height: 0;
            padding-top: 8px;
            padding-bottom: 10px;
            /* Sits inside .main-content; keep inner padding minimal so title stays left */
            padding-left: 0 !important;
            padding-right: 12px !important;
            align-items: center;
            margin-left: 0;
            width: 100% !important;
        }

        body.patient-management-page .header-left {
            flex-direction: row;
            align-items: center;
            flex-wrap: wrap;
            gap: 6px 12px;
            text-align: left;
            flex: 1 1 auto;
            min-width: 0;
        }

        body.patient-management-page .header-right {
            flex-shrink: 0;
        }

        body.patient-management-page .assistant-dashboard-content {
            padding: 14px 16px 24px 8px;
        }

        body.patient-management-page.dashboard-page .dashboard-content {
            padding: 14px 0 24px 0;
        }

        @media (max-width: 640px) {
            body.patient-management-page .assistant-subtitle,
            body.patient-management-page .header-subtitle {
                border-left: none;
                padding-left: 0;
                width: 100%;
            }
        }

        .pm-search-toolbar {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr)) auto;
            gap: 8px 12px;
            align-items: end;
        }

        .pm-search-actions {
            display: flex;
            flex-direction: row;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
            justify-content: flex-end;
            padding-bottom: 1px;
        }

        .pm-search-actions .btn,
        .pm-search-actions a.btn {
            padding: 7px 14px;
            font-size: 13px;
            border-radius: 6px;
            margin: 0;
        }

        @media (max-width: 1100px) {
            .pm-search-toolbar {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
            .pm-search-actions {
                grid-column: 1 / -1;
                justify-content: flex-start;
            }
        }

        @media (max-width: 520px) {
            .pm-search-toolbar {
                grid-template-columns: 1fr;
            }
        }
        
        .search-group {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }
        
        .search-group label {
            font-weight: 600;
            color: #334155;
            margin-bottom: 4px;
            font-size: 12px;
        }
        
        .search-group input,
        .search-group select {
            padding: 7px 10px;
            border: 1.5px solid #e2e8f0;
            border-radius: 6px;
            font-size: 13px;
            font-family: 'Inter', sans-serif;
        }
        
        .search-group input:focus,
        .search-group select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .patient-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(100%, 280px), 1fr));
            gap: 20px;
        }
        
        .patient-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid #f1f5f9;
            transition: all 0.3s ease;
            min-width: 0;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-sizing: border-box;
        }
        
        .patient-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
            border-color: #3b82f6;
        }
        
        .patient-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
            padding-bottom: 16px;
            border-bottom: 2px solid #f1f5f9;
            min-width: 0;
        }
        
        .patient-avatar {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: 700;
        }
        
        .patient-info {
            min-width: 0;
            flex: 1 1 auto;
        }

        .patient-info h3 {
            margin: 0 0 5px 0;
            font-size: 17px;
            color: #1e293b;
            font-weight: 700;
            word-break: break-word;
            overflow-wrap: anywhere;
        }
        
        .patient-info p {
            margin: 0;
            color: #64748b;
            font-size: 12px;
            word-break: break-all;
            overflow-wrap: anywhere;
        }
        
        .patient-details {
            margin-top: 15px;
        }
        
        .patient-details-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
            padding: 8px 0;
            border-bottom: 1px solid #f1f5f9;
            min-width: 0;
        }
        
        .patient-details-row:last-child {
            border-bottom: none;
        }
        
        .patient-details-row span:first-child {
            color: #64748b;
            font-size: 14px;
        }
        
        .patient-details-row span:last-child {
            color: #1e293b;
            font-weight: 600;
            font-size: 14px;
            text-align: right;
            min-width: 0;
            flex-shrink: 1;
        }
        
        .patient-actions {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-top: auto;
            padding-top: 16px;
            border-top: 2px solid #f1f5f9;
            width: 100%;
            min-width: 0;
            box-sizing: border-box;
        }
        
        .btn {
            padding: 10px 12px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 13px;
            text-align: center;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            box-sizing: border-box;
            line-height: 1.25;
        }

        .patient-actions .btn,
        .patient-actions a.btn {
            width: 100%;
            max-width: 100%;
            flex: none;
            min-width: 0;
            white-space: normal;
            word-break: break-word;
        }
        
        .btn-history {
            background: #3b82f6;
            color: white;
        }
        
        .btn-history:hover {
            background: #2563eb;
            transform: translateY(-2px);
        }
        
        .btn-appointments {
            background: #10b981;
            color: white;
        }
        
        .btn-appointments:hover {
            background: #059669;
            transform: translateY(-2px);
        }
        
        .stats-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .stats-badge.primary {
            background: #eff6ff;
            color: #1e40af;
        }
        
        .stats-badge.success {
            background: #d1fae5;
            color: #065f46;
        }
        
        .stats-badge.warning {
            background: #fef3c7;
            color: #92400e;
        }
    </style>
</head>
<body class="<?php echo $is_doctor_user ? 'dashboard-page' : 'assistant-dashboard-page'; ?> patient-management-page">
    <?php
    if ($is_doctor_user) {
        include '../includes/doctor_sidebar.php'; 
    } else {
        include 'includes/assistant_sidebar.php';
    }
    ?>

    <div class="<?php echo $is_doctor_user ? 'main-content' : 'assistant-main-content'; ?>">
        <header class="<?php echo $is_doctor_user ? 'main-header' : 'assistant-header'; ?>">
            <div class="<?php echo $is_doctor_user ? 'header-left' : 'assistant-header-left'; ?>">
                <h1 class="<?php echo $is_doctor_user ? 'header-title' : 'assistant-welcome'; ?>">Patient Management</h1>
                <p class="<?php echo $is_doctor_user ? 'header-subtitle' : 'assistant-subtitle'; ?>">View and manage all patients in the system</p>
            </div>
            <?php if ($is_doctor_user): ?>
            <div class="header-right">
                <div class="current-time pm-header-date" style="display: flex; align-items: center; gap: 6px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
                    <i class="fas fa-calendar-day" style="color: #3b82f6;"></i>
                    <span style="color: #334155;"><?php echo date('D, M j, Y'); ?></span>
                </div>
            </div>
            <?php else: ?>
            <div class="assistant-header-right">
                <div class="current-time pm-header-date" style="display: flex; align-items: center; gap: 6px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
                    <i class="fas fa-calendar-day" style="color: #3b82f6;"></i>
                    <span style="color: #334155;"><?php echo date('D, M j, Y'); ?></span>
                </div>
            </div>
            <?php endif; ?>
        </header>

        <div class="<?php echo $is_doctor_user ? 'dashboard-content' : 'assistant-dashboard-content'; ?>">
            <!-- Search Section -->
            <div class="search-section">
                <h3>
                    <i class="fas fa-search"></i> Search Patients
                </h3>
                <form method="GET" id="searchForm">
                    <div class="pm-search-toolbar">
                        <div class="search-group">
                            <label>Patient Name</label>
                            <input type="text" name="search" placeholder="Search by name..." 
                                   value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                        </div>
                        <div class="search-group">
                            <label>Category / Department</label>
                            <select name="department">
                                <option value="">All Departments</option>
                                <option value="derma" <?= ($_GET['department'] ?? '') === 'derma' ? 'selected' : '' ?>>Derma</option>
                                <option value="ortho" <?= ($_GET['department'] ?? '') === 'ortho' ? 'selected' : '' ?>>Orthopedic</option>
                                <option value="gastro" <?= ($_GET['department'] ?? '') === 'gastro' ? 'selected' : '' ?>>Gastro</option>
                            </select>
                        </div>
                        <div class="search-group">
                            <label>Health Concern</label>
                            <input type="text" name="health_concern" placeholder="Filter by concern..." 
                                   value="<?= htmlspecialchars($_GET['health_concern'] ?? '') ?>">
                        </div>
                        <div class="search-group">
                            <label>Appointments</label>
                            <select name="appointment_filter">
                                <option value="">All Patients</option>
                                <option value="has_appointments" <?= ($_GET['appointment_filter'] ?? '') === 'has_appointments' ? 'selected' : '' ?>>With Appointments</option>
                                <option value="no_appointments" <?= ($_GET['appointment_filter'] ?? '') === 'no_appointments' ? 'selected' : '' ?>>No Appointments</option>
                            </select>
                        </div>
                        <div class="pm-search-actions">
                            <button type="submit" class="btn" style="background: #3b82f6; color: white; font-weight: 600; border: none;">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                            <a href="patient_management.php" class="btn" style="background: #64748b; color: white; font-weight: 600; text-decoration: none;">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Patient Grid -->
            <div class="patient-grid">
                <?php
                $search = $_GET['search'] ?? '';
                $department = $_GET['department'] ?? '';
                $health_concern = $_GET['health_concern'] ?? '';
                $appointment_filter = $_GET['appointment_filter'] ?? '';
                
                $filtered_count = 0;
                while ($patient = $patients_result->fetch_assoc()):
                    // Apply filters
                    $show = true;
                    
                    if ($search && (stripos($patient['first_name'] . ' ' . $patient['last_name'], $search) === false)) {
                        $show = false;
                    }
                    
                    if ($department) {
                        $concernText = strtoupper((string) ($patient['health_concern'] ?? ''));
                        $matchesDepartment = false;
                        if ($department === 'derma') {
                            $matchesDepartment = strpos($concernText, 'DERMA') !== false;
                        } elseif ($department === 'ortho') {
                            $matchesDepartment = strpos($concernText, 'ORTHO') !== false;
                        } elseif ($department === 'gastro') {
                            $matchesDepartment = strpos($concernText, 'GASTRO') !== false;
                        }
                        if (!$matchesDepartment) {
                            $show = false;
                        }
                    }
                    
                    if ($health_concern && stripos($patient['health_concern'], $health_concern) === false) {
                        $show = false;
                    }
                    
                    if ($appointment_filter === 'has_appointments' && $patient['total_appointments'] == 0) {
                        $show = false;
                    }
                    
                    if ($appointment_filter === 'no_appointments' && $patient['total_appointments'] > 0) {
                        $show = false;
                    }
                    
                    if (!$show) continue;
                    $filtered_count++;
                    
                    $initials = strtoupper(substr($patient['first_name'], 0, 1) . substr($patient['last_name'], 0, 1));
                ?>
                    <div class="patient-card">
                        <div class="patient-header">
                            <div class="patient-avatar"><?= $initials ?></div>
                            <div class="patient-info">
                                <h3><?= htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) ?></h3>
                                <p><?= htmlspecialchars($patient['email']) ?></p>
                            </div>
                        </div>
                        
                        <div class="patient-details">
                            <div class="patient-details-row">
                                <span><i class="fas fa-venus-mars"></i> Gender:</span>
                                <span><?= htmlspecialchars($patient['gender']) ?></span>
                            </div>
                            <div class="patient-details-row">
                                <span><i class="fas fa-birthday-cake"></i> Age:</span>
                                <span><?= htmlspecialchars($patient['age']) ?></span>
                            </div>
                            <div class="patient-details-row">
                                <span><i class="fas fa-clipboard-medical"></i> Health Concern:</span>
                                <span style="font-size: 12px; max-width: 60%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    <?= htmlspecialchars($patient['health_concern']) ?>
                                </span>
                            </div>
                            <div class="patient-details-row">
                                <span><i class="fas fa-calendar-check"></i> Total Appointments:</span>
                                <span class="stats-badge primary"><?= htmlspecialchars($patient['total_appointments']) ?></span>
                            </div>
                            <?php if ($patient['pending_appointments'] > 0): ?>
                            <div class="patient-details-row">
                                <span><i class="fas fa-clock"></i> Pending:</span>
                                <span class="stats-badge warning"><?= htmlspecialchars($patient['pending_appointments']) ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($patient['last_appointment']): ?>
                            <div class="patient-details-row">
                                <span><i class="fas fa-calendar"></i> Last Appointment:</span>
                                <span style="font-size: 12px;">
                                    <?= date('M d, Y', strtotime($patient['last_appointment'])) ?>
                                </span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="patient-actions">
                            <button type="button"
                               class="btn btn-history js-open-patient-history"
                               data-patient-id="<?= (int) $patient['id'] ?>">
                                <i class="fas fa-history"></i> View History
                            </button>
                            <a href="patient_demographics.php?patient_id=<?= (int) $patient['id'] ?>"
                               class="btn" style="background:#6366f1;color:white;">
                                <i class="fas fa-id-card"></i> Demographics
                            </a>
                            <a href="../assistant_view/assistant_appointments.php?patient_id=<?= $patient['id'] ?>" 
                               class="btn btn-appointments">
                                <i class="fas fa-calendar"></i> Appointments
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
                
                <?php if ($filtered_count === 0): ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: 60px 20px; background: white; border-radius: 12px; box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);">
                        <i class="fas fa-user-slash" style="font-size: 64px; color: #cbd5e1; margin-bottom: 20px;"></i>
                        <h3 style="color: #64748b; margin-bottom: 10px;">No Patients Found</h3>
                        <p style="color: #94a3b8;">Try adjusting your search filters</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

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
.ph-hist-table .ph-cell-long {
    max-width: 160px;
    line-height: 1.45;
}
@media (max-width: 900px) {
    .ph-hist-table .ph-cell-long { max-width: 120px; font-size: 12px; }
}
</style>
<script>window.HB_PATIENT_HISTORY_API = '../appointments/patient_history_data.php';</script>
<script src="../appointments/patient_history_modal.js"></script>

</body>
</html>

