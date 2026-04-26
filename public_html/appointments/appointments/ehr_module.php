<?php
// ehr_module.php - EHR queue (doctors: own appointments; assistants/admins: system-wide — same UI as doctors)
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/auth_guard.php';
ensure_role(['doctor', 'assistant', 'admin']);
include("../config/db_connect.php");
require_once __DIR__ . '/../includes/security.php';

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? '';
$is_staff_ehr = in_array($role, ['assistant', 'admin'], true);

// Get user info for sidebar
if ($role === 'doctor') {
    $query = $conn->prepare("SELECT username, email, specialization FROM users WHERE id = ?");
} else {
    $query = $conn->prepare("SELECT username, email, role FROM users WHERE id = ?");
}
$query->bind_param("i", $user_id);
$query->execute();
$user_result = $query->get_result();
$user = $user_result->fetch_assoc();

$sidebar_user_data = [
    'username' => htmlspecialchars($user['username']),
    'email' => htmlspecialchars($user['email']),
    'role' => htmlspecialchars($role),
];
if ($role === 'doctor') {
    $sidebar_user_data['specialization'] = htmlspecialchars($user['specialization'] ?? 'General');
}

$ehr_queue_sql_select = "
    SELECT a.id, a.appointment_date, a.status, a.patient_id,
           p.id AS patient_pk,
           CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
           p.age, p.gender, p.health_concern,
           CASE 
               WHEN a.status = 'Pending' THEN 'warning'
               WHEN a.status = 'Confirmed' THEN 'success'
               WHEN a.status = 'Completed' THEN 'info'
               ELSE 'secondary'
           END as status_class,
           (SELECT COUNT(*) FROM consultations WHERE appointment_id = a.id) as has_consultation
";

// One row per patient: pick the latest eligible appointment (MAX id among queue-eligible rows)
$ehr_eligible_doctor = "
    a2.doctor_id = ?
    AND a2.status IN ('Pending', 'Confirmed', 'Completed')
    AND (a2.status != 'Completed' OR (SELECT COUNT(*) FROM consultations WHERE appointment_id = a2.id) = 0)
";
$ehr_eligible_staff = "
    a2.status IN ('Pending', 'Confirmed', 'Completed')
    AND (a2.status != 'Completed' OR (SELECT COUNT(*) FROM consultations WHERE appointment_id = a2.id) = 0)
";

$ehr_queue_sql_from_where_doctor = "
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    INNER JOIN (
        SELECT a2.patient_id, MAX(a2.id) AS id
        FROM appointments a2
        WHERE {$ehr_eligible_doctor}
        GROUP BY a2.patient_id
    ) ehr_one ON ehr_one.id = a.id
    ORDER BY a.appointment_date DESC
    LIMIT 50
";

$ehr_queue_sql_from_where_staff = "
    , CONCAT(du.first_name, ' ', du.last_name) AS doctor_name
    , du.id AS doctor_id
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN users du ON a.doctor_id = du.id
    INNER JOIN (
        SELECT a2.patient_id, MAX(a2.id) AS id
        FROM appointments a2
        WHERE {$ehr_eligible_staff}
        GROUP BY a2.patient_id
    ) ehr_one ON ehr_one.id = a.id
    ORDER BY a.appointment_date DESC
    LIMIT 50
";

if ($is_staff_ehr) {
    $pending_ehr = $conn->prepare($ehr_queue_sql_select . $ehr_queue_sql_from_where_staff);
    $pending_ehr->execute();
} else {
    $pending_ehr = $conn->prepare($ehr_queue_sql_select . $ehr_queue_sql_from_where_doctor);
    $pending_ehr->bind_param("i", $user_id);
    $pending_ehr->execute();
}
$pending_ehr_result = $pending_ehr->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>EHR Records - HealthBase</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <?php if ($is_staff_ehr): ?>
    <link rel="stylesheet" href="../assistant_view/css/assistant.css">
    <?php endif; ?>
    <link rel="stylesheet" href="../css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* Full width of the main column — avoid max-width + auto margins that center content away from the sidebar */
        .ehr-module-page .ehr-container {
            width: 100%;
            max-width: 100%;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body.ehr-module-page.dashboard-page .main-content {
            padding-left: 20px;
            padding-right: 20px;
            padding-top: 20px;
        }
        body.ehr-module-page.assistant-dashboard-page .assistant-dashboard-content {
            padding-left: 22px;
            padding-right: 22px;
        }
        
        .page-header {
            margin-bottom: 30px;
        }
        
        .page-header h1 {
            color: #1e293b;
            font-size: 28px;
            margin-bottom: 10px;
            font-weight: 700;
        }
        
        .page-header p {
            color: #64748b;
            font-size: 15px;
        }
        
        .ehr-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .ehr-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }
        
        .ehr-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.12);
        }
        
        .ehr-card-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .patient-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .patient-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
            font-weight: 700;
        }
        
        .patient-details h3 {
            margin: 0;
            color: #1e293b;
            font-size: 16px;
            font-weight: 600;
        }
        
        .patient-details p {
            margin: 5px 0 0 0;
            color: #64748b;
            font-size: 13px;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-warning {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-success {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-info {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .appointment-details {
            margin: 15px 0;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 14px;
        }
        
        .detail-label {
            color: #64748b;
            font-weight: 500;
        }
        
        .detail-value {
            color: #1e293b;
            font-weight: 600;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
        }
        
        .btn-primary {
            flex: 1;
            padding: 10px 16px;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }
        
        .btn-secondary {
            padding: 10px 16px;
            background: #f8fafc;
            color: #3b82f6;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .btn-secondary:hover {
            background: #f1f5f9;
            border-color: #3b82f6;
        }
        
        .empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        }
        
        .empty-state i {
            font-size: 64px;
            color: #cbd5e1;
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            color: #64748b;
            margin-bottom: 10px;
        }
        
        .empty-state p {
            color: #94a3b8;
        }

        /* Consultation form modal (doctors) */
        .ehr-consult-modal {
            position: fixed;
            inset: 0;
            z-index: 10060;
            display: block;
        }
        .ehr-consult-modal.is-hidden {
            display: none !important;
        }
        .ehr-consult-modal-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, 0.55);
        }
        .ehr-consult-modal-dialog {
            position: relative;
            margin: 2vh auto;
            max-width: min(1100px, 96vw);
            height: min(96vh, 900px);
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
        }
        .ehr-consult-modal-header {
            flex: 0 0 auto;
            padding: 12px 16px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #f8fafc;
        }
        .ehr-consult-modal-header h2 {
            margin: 0;
            font-size: 1.05rem;
            font-weight: 700;
            color: #0f172a;
        }
        .ehr-consult-modal-close {
            background: transparent;
            border: none;
            font-size: 1.5rem;
            line-height: 1;
            cursor: pointer;
            color: #64748b;
            padding: 4px 8px;
            border-radius: 6px;
        }
        .ehr-consult-modal-close:hover {
            background: #e2e8f0;
            color: #0f172a;
        }
        .ehr-consult-modal-body {
            flex: 1;
            min-height: 0;
            background: #f1f5f9;
        }
        #ehrConsultationIframe {
            width: 100%;
            height: 100%;
            min-height: 320px;
            border: 0;
            display: block;
            background: #fff;
        }
        .ehr-flash-success {
            background: #d1fae5;
            border-left: 4px solid #10b981;
            padding: 14px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            color: #065f46;
            font-weight: 500;
        }
    </style>
</head>
<body class="<?php echo $is_staff_ehr ? 'assistant-dashboard-page' : 'dashboard-page'; ?> ehr-module-page">
    <?php if ($is_staff_ehr): ?>
    <?php include __DIR__ . '/../assistant_view/includes/assistant_sidebar.php'; ?>
    <div class="assistant-main-content">
        <header class="assistant-header">
            <div class="assistant-header-left">
                <h1 class="assistant-welcome"><i class="fas fa-stethoscope"></i> Electronic Health Records (EHR)</h1>
                <p class="assistant-subtitle">Same appointment queue as doctors — system-wide pending visits &amp; documentation</p>
            </div>
            <div class="assistant-header-right">
                <div class="current-time" style="display: flex; align-items: center; gap: 8px; padding: 8px 16px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
                    <i class="fas fa-calendar-day" style="color: #3b82f6;"></i>
                    <span style="color: #1e293b; font-weight: 600; font-size: 14px;"><?php echo date('l, F j, Y'); ?></span>
                </div>
            </div>
        </header>
        <div class="assistant-dashboard-content">
    <?php else: ?>
    <?php include '../includes/doctor_sidebar.php'; ?>
    <div class="main-content">
    <?php endif; ?>

    <div class="ehr-container">
        <?php if (!$is_staff_ehr): ?>
            <div class="page-header">
                <h1><i class="fas fa-stethoscope"></i> Electronic Health Records (EHR)</h1>
                <p>Record diagnoses, prescriptions, and consultation notes for patient visits</p>
            </div>
            <?php if (isset($_GET['success']) && $_GET['success'] === 'consultation_recorded'): ?>
            <div class="ehr-flash-success">
                <i class="fas fa-check-circle"></i> Consultation recorded successfully.
            </div>
            <?php endif; ?>
        <?php endif; ?>

            <!-- Searchable EHR Records Dropdown -->
            <div style="background: white; padding: 20px; border-radius: 12px; margin-bottom: 30px; box-shadow: 0 2px 12px rgba(0,0,0,0.08);">
                <label style="display: block; margin-bottom: 10px; font-weight: 600; color: #1e293b;">
                    <i class="fas fa-search"></i> Quick Search EHR Records
                </label>
                <div style="position: relative;">
                    <input type="text" id="ehrSearchInput" placeholder="<?php echo $is_staff_ehr ? 'Search by patient, doctor, date, or health concern...' : 'Search by patient name, appointment date, or health concern...'; ?>"
                           style="width: 100%; padding: 12px 40px 12px 15px; border: 1.5px solid #e2e8f0; border-radius: 8px; font-size: 14px;">
                    <i class="fas fa-search" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); color: #64748b;"></i>
                </div>
                <div id="ehrSearchResults" style="margin-top: 10px; max-height: 300px; overflow-y: auto; display: none; border: 1px solid #e2e8f0; border-radius: 8px; background: white;">
                    <!-- Search results will appear here -->
                </div>
            </div>
            
            <?php if ($pending_ehr_result->num_rows > 0): ?>
                <div class="ehr-grid">
                    <?php while ($appt = $pending_ehr_result->fetch_assoc()): 
                        $initials = strtoupper(substr($appt['patient_name'], 0, 1));
                        if (($pos = strpos($appt['patient_name'], ' ')) !== false) {
                            $initials .= strtoupper(substr($appt['patient_name'], $pos + 1, 1));
                        }
                        $has_consultation = $appt['has_consultation'] > 0;
                    ?>
                        <div class="ehr-card" data-appointment-id="<?= (int) $appt['id'] ?>">
                            <div class="ehr-card-header">
                                <div class="patient-info">
                                    <div class="patient-avatar"><?= htmlspecialchars($initials) ?></div>
                                    <div class="patient-details">
                                        <h3>
                                            <?= htmlspecialchars($appt['patient_name']) ?>
                                            <span class="ehr-pk" title="Patient primary key (patients.id)">P#<?= (int) ($appt['patient_pk'] ?? $appt['patient_id']) ?></span>
                                        </h3>
                                        <p><?= htmlspecialchars($appt['age']) ?> years, <?= htmlspecialchars($appt['gender']) ?></p>
                                    </div>
                                </div>
                                <span class="status-badge status-<?= $appt['status_class'] ?>">
                                    <?= htmlspecialchars($appt['status']) ?>
                                </span>
                            </div>
                            
                            <div class="appointment-details">
                                <div class="detail-row">
                                    <span class="detail-label"><i class="fas fa-hashtag"></i> Appointment ID:</span>
                                    <span class="detail-value"><span class="ehr-pk" title="Appointment primary key (appointments.id)">A#<?= (int) $appt['id'] ?></span></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label"><i class="fas fa-calendar"></i> Date & Time:</span>
                                    <span class="detail-value"><?= date('M d, Y h:i A', strtotime($appt['appointment_date'])) ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label"><i class="fas fa-heartbeat"></i> Health Concern:</span>
                                    <span class="detail-value"><?= htmlspecialchars($appt['health_concern']) ?></span>
                                </div>
                                <?php if ($is_staff_ehr && !empty($appt['doctor_name'])): ?>
                                <div class="detail-row">
                                    <span class="detail-label"><i class="fas fa-user-md"></i> Doctor:</span>
                                    <span class="detail-value" style="display: flex; align-items: center; flex-wrap: wrap; gap: 8px; justify-content: flex-end;">
                                        <?= htmlspecialchars($appt['doctor_name']) ?>
                                        <?php if (isset($appt['doctor_id'])): ?>
                                        <span class="ehr-pk" title="Doctor user primary key (users.id)">D#<?= (int) $appt['doctor_id'] ?></span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <?php endif; ?>
                                <?php if ($has_consultation): ?>
                                <div class="detail-row">
                                    <span class="detail-label"><i class="fas fa-check-circle"></i> Status:</span>
                                    <span class="detail-value" style="color: #10b981;">Consultation Recorded</span>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="action-buttons">
                                <?php if ($is_staff_ehr): ?>
                                    <button type="button" class="btn-primary js-open-patient-history" data-patient-id="<?= (int) $appt['patient_id'] ?>">
                                        <i class="fas fa-history"></i> View Full History
                                    </button>
                                <?php else: ?>
                                    <?php if (!$has_consultation): ?>
                                    <button type="button" class="btn-primary js-open-consultation-modal" data-appointment-id="<?= (int) $appt['id'] ?>">
                                        <i class="fas fa-stethoscope"></i> Record Consultation
                                    </button>
                                    <?php else: ?>
                                    <button type="button" class="btn-secondary js-open-consultation-modal" data-appointment-id="<?= (int) $appt['id'] ?>">
                                        <i class="fas fa-edit"></i> View/Edit Record
                                    </button>
                                    <?php endif; ?>
                                    <button type="button" class="btn-secondary js-open-patient-history" data-patient-id="<?= (int) $appt['patient_id'] ?>">
                                        <i class="fas fa-history"></i> View History
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-clipboard-check"></i>
                    <h3>All Consultations Recorded</h3>
                    <p>All appointments have been processed. Check back later for new appointments.</p>
                </div>
            <?php endif; ?>
    </div>

    <?php if ($is_staff_ehr): ?>
        </div>
    </div>
    <?php else: ?>
    </div>
    <?php endif; ?>

<?php if (!$is_staff_ehr): ?>
<div id="ehrConsultationModal" class="ehr-consult-modal is-hidden" role="dialog" aria-modal="true" aria-labelledby="ehrConsultationModalTitle" aria-hidden="true">
    <div class="ehr-consult-modal-backdrop" id="ehrConsultationModalBackdrop" tabindex="-1"></div>
    <div class="ehr-consult-modal-dialog">
        <div class="ehr-consult-modal-header">
            <h2 id="ehrConsultationModalTitle">Consultation</h2>
            <button type="button" class="ehr-consult-modal-close" id="ehrConsultationModalClose" aria-label="Close">&times;</button>
        </div>
        <div class="ehr-consult-modal-body">
            <iframe id="ehrConsultationIframe" title="Consultation form" src="about:blank"></iframe>
        </div>
    </div>
</div>
<?php endif; ?>

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
.ehr-card .action-buttons button.btn-primary,
.ehr-card .action-buttons button.btn-secondary {
    text-decoration: none;
    font: inherit;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
</style>
    <script>window.HB_PATIENT_HISTORY_API = 'patient_history_data.php';</script>
    <script src="patient_history_modal.js?v=<?php echo urlencode((string) (@filemtime(__DIR__ . '/patient_history_modal.js') ?: time())); ?>"></script>
    <script>
        const isStaffEhr = <?= $is_staff_ehr ? 'true' : 'false' ?>;
        <?php if (!$is_staff_ehr): ?>
        (function () {
            const modal = document.getElementById('ehrConsultationModal');
            const iframe = document.getElementById('ehrConsultationIframe');
            const closeBtn = document.getElementById('ehrConsultationModalClose');
            const backdrop = document.getElementById('ehrConsultationModalBackdrop');
            function openConsultationModal(appointmentId) {
                if (!modal || !iframe) return;
                iframe.src = 'consultation_form.php?appointment_id=' + encodeURIComponent(appointmentId) + '&modal=1';
                modal.classList.remove('is-hidden');
                modal.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
            }
            function closeConsultationModal() {
                if (!modal || !iframe) return;
                modal.classList.add('is-hidden');
                modal.setAttribute('aria-hidden', 'true');
                iframe.src = 'about:blank';
                document.body.style.overflow = '';
            }
            window.HB_openConsultationModal = openConsultationModal;
            document.querySelectorAll('.js-open-consultation-modal').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const id = this.getAttribute('data-appointment-id');
                    if (id) openConsultationModal(id);
                });
            });
            if (closeBtn) closeBtn.addEventListener('click', closeConsultationModal);
            if (backdrop) backdrop.addEventListener('click', closeConsultationModal);
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && modal && !modal.classList.contains('is-hidden')) {
                    closeConsultationModal();
                }
            });
            window.addEventListener('message', function (ev) {
                if (ev.origin !== window.location.origin) return;
                if (!ev.data || typeof ev.data.type !== 'string') return;
                if (ev.data.type === 'hb-ehr-consultation-saved') {
                    closeConsultationModal();
                    window.location.href = 'ehr_module.php?success=consultation_recorded';
                    return;
                }
                if (ev.data.type === 'hb-ehr-consultation-error') {
                    closeConsultationModal();
                    const code = ev.data.code || '';
                    const msgs = {
                        appointment_not_found: 'Appointment was not found.',
                        consultation_exists: 'A consultation already exists for this visit.',
                        invalid_appointment_status: 'Consultation cannot be recorded for this appointment status.',
                        appointment_cancelled: 'This appointment was cancelled or declined.'
                    };
                    alert(msgs[code] || 'Could not complete this action.');
                    window.location.reload();
                }
            });
        })();
        <?php endif; ?>
        // Searchable EHR Records functionality
        const ehrSearchInput = document.getElementById('ehrSearchInput');
        const ehrSearchResults = document.getElementById('ehrSearchResults');
        const ehrCards = document.querySelectorAll('.ehr-card');
        
        // Store all appointment data for search
        const appointmentData = [];
        <?php 
        $pending_ehr_result->data_seek(0);
        while ($appt = $pending_ehr_result->fetch_assoc()): 
        ?>
        appointmentData.push({
            id: <?= (int) $appt['id'] ?>,
            patientPk: <?= (int) ($appt['patient_pk'] ?? $appt['patient_id']) ?>,
            patientName: '<?= addslashes($appt['patient_name']) ?>',
            appointmentDate: '<?= date('M d, Y h:i A', strtotime($appt['appointment_date'])) ?>',
            healthConcern: '<?= addslashes($appt['health_concern']) ?>',
            age: <?= (int) $appt['age'] ?>,
            gender: '<?= addslashes($appt['gender']) ?>',
            status: '<?= addslashes($appt['status']) ?>',
            patientId: <?= (int) $appt['patient_id'] ?>,
            hasConsultation: <?= (int) $appt['has_consultation'] ?>,
            doctorName: '<?= addslashes($appt['doctor_name'] ?? '') ?>',
            doctorId: <?= isset($appt['doctor_id']) ? (int) $appt['doctor_id'] : 'null' ?>
        });
        <?php endwhile; ?>
        
        function performSearch(query) {
            const raw = (query || '').trim();
            const numericOnly = /^\d+$/.test(raw);
            if (!raw || (raw.length < 2 && !numericOnly)) {
                ehrSearchResults.style.display = 'none';
                ehrCards.forEach(card => card.style.display = 'block');
                return;
            }
            
            const searchTerm = raw.toLowerCase();
            const idToken = raw.replace(/^#|^(p|a|d)#/i, '').trim();
            const matches = appointmentData.filter(appt => {
                let ok = appt.patientName.toLowerCase().includes(searchTerm) ||
                    appt.healthConcern.toLowerCase().includes(searchTerm) ||
                    appt.appointmentDate.toLowerCase().includes(searchTerm) ||
                    appt.status.toLowerCase().includes(searchTerm) ||
                    ('p#' + appt.patientPk).toLowerCase().includes(searchTerm) ||
                    ('a#' + appt.id).toLowerCase().includes(searchTerm);
                if (isStaffEhr && appt.doctorName) {
                    ok = ok || appt.doctorName.toLowerCase().includes(searchTerm);
                }
                if (isStaffEhr && appt.doctorId != null) {
                    ok = ok || ('d#' + appt.doctorId).toLowerCase().includes(searchTerm);
                }
                if (/^\d+$/.test(idToken)) {
                    ok = ok || String(appt.id) === idToken || String(appt.patientPk) === idToken ||
                        (appt.doctorId != null && String(appt.doctorId) === idToken);
                }
                return ok;
            });
            
            // Hide/show cards based on search
            ehrCards.forEach((card) => {
                const apptId = parseInt(card.getAttribute('data-appointment-id') || '0', 10);
                const isMatch = matches.some(m => m.id === apptId);
                card.style.display = isMatch ? 'block' : 'none';
            });
            
            // Show search results dropdown
            if (matches.length > 0) {
                ehrSearchResults.innerHTML = matches.map(appt => {
                    const href = 'consultation_form.php?appointment_id=' + appt.id;
                    const idLine = 'P#' + appt.patientPk + ' · A#' + appt.id +
                        (isStaffEhr && appt.doctorId != null ? ' · D#' + appt.doctorId : '');
                    const sub = (isStaffEhr && appt.doctorName
                        ? appt.appointmentDate + ' • ' + appt.doctorName
                        : appt.appointmentDate + ' • ' + appt.healthConcern) + ' · ' + idLine;
                    const clickGo = isStaffEhr
                        ? "event.preventDefault(); if (window.HB_openPatientHistory) window.HB_openPatientHistory(" + appt.patientId + "); document.getElementById('ehrSearchResults').style.display='none';"
                        : "event.preventDefault(); if (window.HB_openConsultationModal) { window.HB_openConsultationModal(" + appt.id + "); document.getElementById('ehrSearchResults').style.display='none'; } else { window.location.href='" + href + "'; }";
                    return `
                    <div style="padding: 12px; border-bottom: 1px solid #e2e8f0; cursor: pointer; transition: background 0.2s;"
                         onmouseover="this.style.background='#f8fafc'" 
                         onmouseout="this.style.background='white'"
                         onclick="${clickGo}">
                        <div style="font-weight: 600; color: #1e293b; margin-bottom: 4px;">${appt.patientName}</div>
                        <div style="font-size: 12px; color: #64748b;">${sub}</div>
                    </div>`;
                }).join('');
                ehrSearchResults.style.display = 'block';
            } else {
                ehrSearchResults.innerHTML = '<div style="padding: 20px; text-align: center; color: #64748b;">No matching records found</div>';
                ehrSearchResults.style.display = 'block';
            }
        }
        
        ehrSearchInput.addEventListener('input', function() {
            performSearch(this.value);
        });
        
        // Close search results when clicking outside
        document.addEventListener('click', function(e) {
            if (!ehrSearchInput.contains(e.target) && !ehrSearchResults.contains(e.target)) {
                ehrSearchResults.style.display = 'none';
            }
        });
    </script>
</body>
</html>

