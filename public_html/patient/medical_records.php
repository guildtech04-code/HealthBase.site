<?php
// medical_records.php - Patient's Medical Records and Consultation History
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

// Get patient ID
$patient_query = $conn->prepare("SELECT id FROM patients WHERE user_id = ?");
$patient_query->bind_param("i", $user_id);
$patient_query->execute();
$patient_result = $patient_query->get_result();
$patient_data = $patient_result->fetch_assoc();
$patient_id = $patient_data ? $patient_data['id'] : null;

if (!$patient_id) {
    header("Location: create_patient_record.php");
    exit();
}

// Pagination: 5 records per page
$mr_per_page = 5;
$mr_page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($mr_page < 1) {
    $mr_page = 1;
}

$count_stmt = $conn->prepare("
    SELECT COUNT(*)
    FROM consultations c
    WHERE c.patient_id = ?
      AND NOT (
          DATE(c.visit_date) = '2026-04-22'
          AND HOUR(c.visit_date) = 10
          AND MINUTE(c.visit_date) = 0
          AND TRIM(COALESCE(c.chief_complaint, '')) = 'Sakit sa tyan'
          AND TRIM(COALESCE(c.diagnosis, '')) = 'Ulcer'
          AND COALESCE(c.treatment_plan, '') LIKE '%eatwell baby%'
      )
");
$count_stmt->bind_param('i', $patient_id);
$count_stmt->execute();
$count_row = $count_stmt->get_result()->fetch_row();
$total_consultation_records = $count_row ? (int) $count_row[0] : 0;

$mr_total_pages = $total_consultation_records > 0 ? (int) ceil($total_consultation_records / $mr_per_page) : 1;
if ($mr_page > $mr_total_pages) {
    $mr_page = $mr_total_pages;
}
$mr_offset = ($mr_page - 1) * $mr_per_page;

$showing_from = $total_consultation_records > 0 ? $mr_offset + 1 : 0;
$showing_to = min($mr_offset + $mr_per_page, $total_consultation_records);

$mr_prev = max(1, $mr_page - 1);
$mr_next = min($mr_total_pages, $mr_page + 1);

// Fetch consultation records with doctor info (paged)
// Include follow-up appointment information if exists
$consultations = $conn->prepare("
    SELECT c.id, c.visit_date, c.chief_complaint, c.consultation_notes, 
           c.diagnosis, c.treatment_plan, c.follow_up_date,
           CONCAT(u.first_name, ' ', u.last_name) AS doctor_name, u.specialization,
           a.id as appointment_id,
           a_followup.id as followup_appointment_id,
           a_followup.appointment_date as followup_appointment_date,
           a_followup.status as followup_appointment_status
    FROM consultations c
    JOIN appointments a ON c.appointment_id = a.id
    JOIN users u ON c.doctor_id = u.id
    LEFT JOIN appointments a_followup ON (
        a_followup.patient_id = c.patient_id 
        AND a_followup.doctor_id = c.doctor_id 
        AND DATE(a_followup.appointment_date) = DATE(c.follow_up_date)
        AND a_followup.status NOT IN ('Cancelled', 'declined')
    )
    WHERE c.patient_id = ?
      AND NOT (
          DATE(c.visit_date) = '2026-04-22'
          AND HOUR(c.visit_date) = 10
          AND MINUTE(c.visit_date) = 0
          AND TRIM(COALESCE(c.chief_complaint, '')) = 'Sakit sa tyan'
          AND TRIM(COALESCE(c.diagnosis, '')) = 'Ulcer'
          AND COALESCE(c.treatment_plan, '') LIKE '%eatwell baby%'
      )
    ORDER BY c.visit_date DESC
    LIMIT ? OFFSET ?
");
$consultations->bind_param('iii', $patient_id, $mr_per_page, $mr_offset);
$consultations->execute();
$consultations_result = $consultations->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Medical Records - HealthBase</title>
    <link rel="icon" type="image/x-icon" href="/assets/icons/favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="css/patient_dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* Medical records — structured HealthBase layout */
        .mr-intro {
            background: linear-gradient(135deg, #f0f9ff 0%, #ffffff 45%, #f8fafc 100%);
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 22px 24px 20px;
            margin-bottom: 24px;
            box-shadow: 0 4px 18px rgba(15, 23, 42, 0.06);
        }
        .mr-intro-top {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
        }
        .mr-intro h1.patient-welcome {
            margin-bottom: 6px;
        }
        .mr-stat-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }
        .mr-stat-pill {
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
        .mr-stat-pill i { color: #0ea5e9; }
        .mr-records-list {
            display: flex;
            flex-direction: column;
            gap: 28px;
            position: relative;
            padding-left: 0;
        }
        .mr-visit {
            background: #fff;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 16px rgba(15, 23, 42, 0.06);
            overflow: hidden;
            transition: box-shadow 0.2s ease, border-color 0.2s ease;
        }
        .mr-visit:hover {
            box-shadow: 0 8px 28px rgba(15, 23, 42, 0.1);
            border-color: #cbd5e1;
        }
        .mr-visit__head {
            display: flex;
            flex-wrap: wrap;
            align-items: stretch;
            justify-content: space-between;
            gap: 16px;
            padding: 18px 22px;
            background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
            color: #f8fafc;
        }
        .mr-visit__dateblock {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .mr-visit__calendar {
            width: 52px;
            height: 56px;
            border-radius: 12px;
            background: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.2);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .mr-visit__calendar-mo {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #94a3b8;
        }
        .mr-visit__calendar-day {
            font-size: 20px;
            font-weight: 800;
            line-height: 1.1;
            color: #fff;
        }
        .mr-visit__titleline {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .mr-visit__eyebrow {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: #94a3b8;
        }
        .mr-visit__titleline strong {
            font-size: 1.05rem;
            font-weight: 600;
            color: #fff;
        }
        .mr-visit__meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: flex-start;
            justify-content: flex-end;
            max-width: 100%;
        }
        .mr-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.15);
            color: #e2e8f0;
        }
        .mr-chip i { color: #38bdf8; font-size: 11px; }
        .mr-visit__body {
            padding: 22px 22px 24px;
        }
        .mr-sections {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }
        @media (max-width: 900px) {
            .mr-sections { grid-template-columns: 1fr; }
        }
        .mr-block {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 16px 18px;
            min-height: 0;
        }
        .mr-block--wide {
            grid-column: 1 / -1;
        }
        .mr-block__label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #0ea5e9;
            margin-bottom: 10px;
        }
        .mr-block__label i { font-size: 13px; opacity: 0.9; }
        .mr-block__text {
            margin: 0;
            font-size: 14px;
            line-height: 1.65;
            color: #334155;
            white-space: pre-wrap;
        }
        .mr-rx-block {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }
        .mr-rx-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 14px;
        }
        .mr-rx-title i { color: #3b82f6; }
        .mr-rx-grid {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .mr-rx-card {
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 14px 16px;
        }
        .mr-rx-name {
            font-weight: 700;
            font-size: 15px;
            color: #0f172a;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px dashed #e2e8f0;
        }
        .mr-rx-details {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 10px 16px;
            font-size: 13px;
            color: #64748b;
        }
        .mr-rx-details span {
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }
        .mr-rx-details i {
            color: #3b82f6;
            margin-top: 2px;
            flex-shrink: 0;
        }
        .mr-rx-instructions {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #f1f5f9;
            font-size: 13px;
            color: #475569;
        }
        .mr-rx-instructions strong { color: #334155; }
        .mr-follow {
            margin-top: 18px;
            padding: 16px 18px;
            border-radius: 12px;
            background: linear-gradient(135deg, #eff6ff 0%, #e0f2fe 100%);
            border: 1px solid #bae6fd;
            display: flex;
            gap: 14px;
            align-items: flex-start;
        }
        .mr-follow > i {
            color: #0284c7;
            font-size: 22px;
            margin-top: 2px;
        }
        .mr-follow__body { flex: 1; min-width: 0; }
        .mr-follow__title {
            font-weight: 700;
            color: #0c4a6e;
            margin-bottom: 6px;
            font-size: 14px;
        }
        .mr-follow__when {
            font-size: 15px;
            color: #0f172a;
            font-weight: 600;
            margin-bottom: 10px;
        }
        .mr-follow-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 10px;
            margin-top: 8px;
        }
        .mr-status-badge {
            padding: 5px 12px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .mr-status-badge--ok { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        .mr-status-badge--bad { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .mr-status-badge--wait { background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }
        .mr-status-badge--muted { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }
        .mr-btn-appt {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 8px;
            background: #2563eb;
            color: #fff !important;
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            transition: background 0.15s;
        }
        .mr-btn-appt:hover { background: #1d4ed8; }
        .mr-follow-pending {
            margin-top: 8px;
            padding: 10px 12px;
            border-radius: 8px;
            background: rgba(251, 191, 36, 0.12);
            border: 1px solid rgba(251, 191, 36, 0.35);
            font-size: 12px;
            color: #92400e;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .mr-empty {
            text-align: center;
            padding: 56px 28px;
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            border-radius: 16px;
            border: 2px dashed #cbd5e1;
            max-width: 520px;
            margin: 0 auto;
        }
        .mr-empty i {
            font-size: 56px;
            color: #cbd5e1;
            margin-bottom: 16px;
        }
        .mr-empty h3 { color: #475569; margin-bottom: 8px; font-size: 1.25rem; }
        .mr-empty p { color: #94a3b8; font-size: 14px; line-height: 1.55; margin-bottom: 20px; }
        .mr-empty a {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            border-radius: 10px;
            background: linear-gradient(135deg, #0ea5e9, #2563eb);
            color: #fff !important;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
        }
        .mr-empty a:hover { filter: brightness(1.05); }
        .mr-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #2563eb;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        .mr-back:hover { color: #1d4ed8; text-decoration: underline; }
        .mr-pagination {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            margin-top: 28px;
            padding: 16px 18px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
        }
        .mr-pagination--top {
            margin-top: 0;
            margin-bottom: 20px;
        }
        .mr-pagination__info {
            font-size: 14px;
            color: #475569;
        }
        .mr-pagination__info strong { color: #0f172a; font-weight: 600; }
        .mr-pagination__nav {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
        }
        .mr-page-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #334155 !important;
            transition: background 0.15s, border-color 0.15s;
        }
        .mr-page-btn:hover:not(.mr-page-btn--disabled) {
            background: #eff6ff;
            border-color: #93c5fd;
            color: #1d4ed8 !important;
        }
        .mr-page-btn--disabled {
            opacity: 0.45;
            pointer-events: none;
            cursor: not-allowed;
        }
        .mr-page-current {
            font-size: 13px;
            font-weight: 700;
            color: #0f172a;
            padding: 0 6px;
        }
        @media (max-width: 768px) {
            .mr-visit__head { flex-direction: column; }
            .mr-visit__meta { justify-content: flex-start; }
            .mr-pagination { flex-direction: column; align-items: stretch; text-align: center; }
            .mr-pagination__nav { justify-content: center; }
        }
    </style>
</head>
<body class="patient-dashboard-page">
    <?php include 'includes/patient_sidebar.php'; ?>

    <div class="patient-main-content">
        <header class="patient-header">
            <div class="patient-header-left">
                <a href="patient_dashboard.php" class="mr-back"><i class="fas fa-arrow-left"></i> Back to dashboard</a>
                <h1 class="patient-welcome"><i class="fas fa-file-medical"></i> My medical records</h1>
                <p class="patient-subtitle">Consultation history, diagnoses, prescriptions, and follow-ups in one place.</p>
            </div>
            <div class="patient-header-right">
                <div class="patient-date-info">
                    <i class="fas fa-calendar-day"></i>
                    <span><?php echo date("l, F j, Y"); ?></span>
                </div>
            </div>
        </header>

        <div class="patient-dashboard-content">
            <div class="mr-intro">
                <div class="mr-intro-top">
                    <div>
                        <p class="patient-subtitle" style="margin:0 0 8px 0;">HealthBase · Your care timeline</p>
                        <div class="mr-stat-row">
                            <span class="mr-stat-pill"><i class="fas fa-notes-medical"></i> <?php echo (int) $total_consultation_records; ?> visit<?php echo $total_consultation_records === 1 ? '' : 's'; ?> on file</span>
                        </div>
                    </div>
                </div>
            </div>

        <div class="mr-records-list">
            <?php if ($total_consultation_records > 0): ?>
                <nav class="mr-pagination mr-pagination--top" aria-label="Medical records pagination (top)">
                    <div class="mr-pagination__info">
                        Showing <strong><?php echo (int) $showing_from; ?>–<?php echo (int) $showing_to; ?></strong>
                        of <strong><?php echo (int) $total_consultation_records; ?></strong> visit<?php echo $total_consultation_records === 1 ? '' : 's'; ?>
                    </div>
                    <div class="mr-pagination__nav">
                        <?php if ($mr_page <= 1): ?>
                            <span class="mr-page-btn mr-page-btn--disabled"><i class="fas fa-chevron-left"></i> Previous</span>
                        <?php else: ?>
                            <a class="mr-page-btn" href="medical_records.php?page=<?php echo (int) $mr_prev; ?>"><i class="fas fa-chevron-left"></i> Previous</a>
                        <?php endif; ?>
                        <span class="mr-page-current">Page <?php echo (int) $mr_page; ?> of <?php echo (int) $mr_total_pages; ?></span>
                        <?php if ($mr_page >= $mr_total_pages): ?>
                            <span class="mr-page-btn mr-page-btn--disabled">Next <i class="fas fa-chevron-right"></i></span>
                        <?php else: ?>
                            <a class="mr-page-btn" href="medical_records.php?page=<?php echo (int) $mr_next; ?>">Next <i class="fas fa-chevron-right"></i></a>
                        <?php endif; ?>
                    </div>
                </nav>
                <?php
                $visit_index = $mr_offset;
                while ($consultation = $consultations_result->fetch_assoc()):
                    $visit_index++;
                    $vd_ts = strtotime($consultation['visit_date']);
                    $cal_mo = $vd_ts ? date('M', $vd_ts) : '';
                    $cal_day = $vd_ts ? date('j', $vd_ts) : '—';
                    $prescriptions_query = $conn->prepare('
                        SELECT medication_name, dosage, frequency, duration, instructions, quantity
                        FROM prescriptions
                        WHERE consultation_id = ?
                        ORDER BY id ASC
                    ');
                    $prescriptions_query->bind_param('i', $consultation['id']);
                    $prescriptions_query->execute();
                    $prescriptions_result = $prescriptions_query->get_result();
                ?>
                    <article class="mr-visit" aria-labelledby="mr-visit-title-<?php echo (int) $consultation['id']; ?>">
                        <div class="mr-visit__head">
                            <div class="mr-visit__dateblock">
                                <div class="mr-visit__calendar" aria-hidden="true">
                                    <span class="mr-visit__calendar-mo"><?= htmlspecialchars($cal_mo) ?></span>
                                    <span class="mr-visit__calendar-day"><?= htmlspecialchars((string) $cal_day) ?></span>
                                </div>
                                <div class="mr-visit__titleline">
                                    <span class="mr-visit__eyebrow">Visit #<?= (int) $visit_index ?></span>
                                    <strong id="mr-visit-title-<?= (int) $consultation['id'] ?>"><?= $vd_ts ? date('l, F j, Y · g:i A', $vd_ts) : '—' ?></strong>
                                </div>
                            </div>
                            <div class="mr-visit__meta">
                                <span class="mr-chip"><i class="fas fa-user-md"></i> Dr. <?= htmlspecialchars($consultation['doctor_name']) ?></span>
                                <span class="mr-chip"><i class="fas fa-stethoscope"></i> <?= htmlspecialchars($consultation['specialization']) ?></span>
                            </div>
                        </div>

                        <div class="mr-visit__body">
                        <div class="mr-sections">
                            <?php if (!empty($consultation['chief_complaint'])): ?>
                            <div class="mr-block">
                                <div class="mr-block__label"><i class="fas fa-user-injured"></i> Chief complaint</div>
                                <p class="mr-block__text"><?= nl2br(htmlspecialchars($consultation['chief_complaint'])) ?></p>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($consultation['consultation_notes'])): ?>
                            <div class="mr-block">
                                <div class="mr-block__label"><i class="fas fa-clipboard-list"></i> Clinical observations</div>
                                <p class="mr-block__text"><?= nl2br(htmlspecialchars($consultation['consultation_notes'])) ?></p>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($consultation['diagnosis'])): ?>
                            <div class="mr-block">
                                <div class="mr-block__label"><i class="fas fa-diagnoses"></i> Diagnosis</div>
                                <p class="mr-block__text"><?= nl2br(htmlspecialchars($consultation['diagnosis'])) ?></p>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($consultation['treatment_plan'])): ?>
                            <div class="mr-block mr-block--wide">
                                <div class="mr-block__label"><i class="fas fa-heartbeat"></i> Treatment plan</div>
                                <p class="mr-block__text"><?= nl2br(htmlspecialchars($consultation['treatment_plan'])) ?></p>
                            </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($prescriptions_result->num_rows > 0): ?>
                        <div class="mr-rx-block">
                            <div class="mr-rx-title"><i class="fas fa-pills"></i> Prescriptions</div>
                            <div class="mr-rx-grid">
                            <?php while ($presc = $prescriptions_result->fetch_assoc()): ?>
                                <div class="mr-rx-card">
                                    <div class="mr-rx-name"><?= htmlspecialchars($presc['medication_name']) ?></div>
                                    <div class="mr-rx-details">
                                        <?php if (!empty($presc['dosage'])): ?>
                                        <span><i class="fas fa-weight"></i> <?= htmlspecialchars($presc['dosage']) ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($presc['frequency'])): ?>
                                        <span><i class="fas fa-clock"></i> <?= htmlspecialchars($presc['frequency']) ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($presc['duration'])): ?>
                                        <span><i class="fas fa-calendar"></i> <?= htmlspecialchars($presc['duration']) ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($presc['quantity'])): ?>
                                        <span><i class="fas fa-capsules"></i> <?= htmlspecialchars($presc['quantity']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($presc['instructions'])): ?>
                                    <div class="mr-rx-instructions"><strong>Instructions</strong> · <?= nl2br(htmlspecialchars($presc['instructions'])) ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endwhile; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($consultation['follow_up_date'])):
                            $followup_appointment_id = $consultation['followup_appointment_id'] ?? null;
                            $followup_appointment_status = $consultation['followup_appointment_status'] ?? null;
                            $followup_appointment_date = $consultation['followup_appointment_date'] ?? null;
                            $has_appointment = !empty($followup_appointment_id);
                            $follow_up_date_formatted = date('F d, Y', strtotime($consultation['follow_up_date']));
                            $follow_up_date_formatted_with_time = $followup_appointment_date ? date('F d, Y h:i A', strtotime($followup_appointment_date)) : $follow_up_date_formatted;
                            $fu_st = $followup_appointment_status;
                            $badge_class = 'mr-status-badge--muted';
                            if ($fu_st === 'Cleared' || $fu_st === 'Completed' || strtolower((string) $fu_st) === 'completed') {
                                $badge_class = 'mr-status-badge--ok';
                            } elseif ($fu_st === 'Cancelled' || strtolower((string) $fu_st) === 'declined') {
                                $badge_class = 'mr-status-badge--bad';
                            } elseif ($fu_st === 'Pending' || $fu_st === 'Confirmed' || strtolower((string) $fu_st) === 'pending' || strtolower((string) $fu_st) === 'confirmed') {
                                $badge_class = 'mr-status-badge--wait';
                            }
                        ?>
                        <div class="mr-follow">
                            <i class="fas fa-calendar-check" aria-hidden="true"></i>
                            <div class="mr-follow__body">
                                <div class="mr-follow__title">Follow-up</div>
                                <div class="mr-follow__when"><?= htmlspecialchars($follow_up_date_formatted_with_time) ?></div>
                                <?php if ($has_appointment): ?>
                                    <div class="mr-follow-actions">
                                        <span class="mr-status-badge <?= $badge_class ?>"><i class="fas fa-circle" style="font-size:6px;"></i> <?= htmlspecialchars((string) $followup_appointment_status) ?></span>
                                        <a class="mr-btn-appt" href="patient_appointments.php"><i class="fas fa-external-link-alt"></i> View in My appointments</a>
                                    </div>
                                <?php else: ?>
                                    <div class="mr-follow-pending"><i class="fas fa-info-circle"></i> Follow-up appointment pending confirmation</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        </div>
                    </article>
                <?php endwhile; ?>
                <nav class="mr-pagination" aria-label="Medical records pagination">
                    <div class="mr-pagination__info">
                        Showing <strong><?php echo (int) $showing_from; ?>–<?php echo (int) $showing_to; ?></strong>
                        of <strong><?php echo (int) $total_consultation_records; ?></strong> visit<?php echo $total_consultation_records === 1 ? '' : 's'; ?>
                    </div>
                    <div class="mr-pagination__nav">
                        <?php if ($mr_page <= 1): ?>
                            <span class="mr-page-btn mr-page-btn--disabled"><i class="fas fa-chevron-left"></i> Previous</span>
                        <?php else: ?>
                            <a class="mr-page-btn" href="medical_records.php?page=<?php echo (int) $mr_prev; ?>"><i class="fas fa-chevron-left"></i> Previous</a>
                        <?php endif; ?>
                        <span class="mr-page-current">Page <?php echo (int) $mr_page; ?> of <?php echo (int) $mr_total_pages; ?></span>
                        <?php if ($mr_page >= $mr_total_pages): ?>
                            <span class="mr-page-btn mr-page-btn--disabled">Next <i class="fas fa-chevron-right"></i></span>
                        <?php else: ?>
                            <a class="mr-page-btn" href="medical_records.php?page=<?php echo (int) $mr_next; ?>">Next <i class="fas fa-chevron-right"></i></a>
                        <?php endif; ?>
                    </div>
                </nav>
            <?php else: ?>
                <div class="mr-empty">
                    <i class="fas fa-file-medical-alt" aria-hidden="true"></i>
                    <h3>No visits on file yet</h3>
                    <p>After your doctor completes a consultation, your record will show up here with diagnoses, plans, and prescriptions.</p>
                    <a href="/appointments/scheduling.php"><i class="fas fa-calendar-plus"></i> Book an appointment</a>
                </div>
            <?php endif; ?>
        </div>
        </div>
    </div>
</body>
</html>
