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

    $query = $conn->prepare("SELECT username, email, role FROM users WHERE id = ?");
    $query->bind_param("i", $user_id);
    $query->execute();
    $result = $query->get_result();
    $user = $result->fetch_assoc();
    $raw_role = $user['role'] ?? '';
    $username = htmlspecialchars($user['username']);
    $email = htmlspecialchars($user['email']);
    $role = htmlspecialchars($raw_role);

    $sidebar_user_data = [
        'username' => $username,
        'email' => $email,
        'role' => $role
    ];

    if ($raw_role === 'doctor') {
        $spec_q = $conn->prepare("SELECT specialization FROM users WHERE id = ?");
        $spec_q->bind_param("i", $user_id);
        $spec_q->execute();
        $spec_row = $spec_q->get_result()->fetch_assoc();
        $sidebar_user_data['specialization'] = htmlspecialchars($spec_row['specialization'] ?? 'General');
    }

    $staff_roles = ['assistant', 'admin', 'doctor'];
    $is_staff = in_array($raw_role, $staff_roles, true);
    $requested_patient_id = isset($_GET['patient_id']) ? (int) $_GET['patient_id'] : 0;
    $is_modal_embed = isset($_GET['modal']) && (string) $_GET['modal'] === '1';
    $embed_load_error = null;

    /** Staff opened /appointments/patient_history.php without ?patient_id= — show picker instead of redirecting away */
    $staff_select_patient = $is_staff && $requested_patient_id <= 0;

    if ($is_staff && !$staff_select_patient) {
        $pv = $conn->prepare("SELECT id, first_name, last_name FROM patients WHERE id = ?");
        $pv->bind_param("i", $requested_patient_id);
        $pv->execute();
        $prow = $pv->get_result()->fetch_assoc();
        if (!$prow) {
            if ($is_modal_embed) {
                $embed_load_error = 'Patient not found.';
                $patient_id = 0;
                $view_patient_name = '';
            } else {
                header('Location: /assistant_view/patient_management.php');
                exit();
            }
        } else {
            $patient_id = (int) $prow['id'];
            $view_patient_name = htmlspecialchars(trim($prow['first_name'] . ' ' . $prow['last_name']));
        }
    } elseif ($staff_select_patient) {
        $patient_id = 0;
        $view_patient_name = '';
    } else {
        $patient_query = $conn->prepare("SELECT id FROM patients WHERE user_id = ?");
        $patient_query->bind_param("i", $user_id);
        $patient_query->execute();
        $patient_result = $patient_query->get_result();
        $patient_data = $patient_result->fetch_assoc();
        $patient_id = $patient_data ? (int) $patient_data['id'] : null;
        $view_patient_name = '';

        if (!$patient_id) {
            header("Location: ../patient/create_patient_record.php");
            exit();
        }
    }

    if ($is_staff && !$staff_select_patient && $patient_id > 0 && $raw_role === 'doctor') {
        $acc = $conn->prepare('SELECT (
            EXISTS(SELECT 1 FROM consultations c WHERE c.patient_id = ? AND c.doctor_id = ?)
            OR EXISTS(
                SELECT 1 FROM appointments a
                WHERE a.patient_id = ? AND a.doctor_id = ?
                AND (a.status IS NULL OR a.status NOT IN (\'Declined\',\'Cancelled\',\'declined\',\'cancelled\',\'Canceled\',\'canceled\'))
            )
        ) AS ok');
        $acc->bind_param('iiii', $patient_id, $user_id, $patient_id, $user_id);
        $acc->execute();
        $okRow = $acc->get_result()->fetch_assoc();
        if (!$okRow || empty($okRow['ok'])) {
            if ($is_modal_embed) {
                $embed_load_error = 'You do not have access to this patient.';
                $patient_id = 0;
                $view_patient_name = '';
            } else {
                header('Location: /assistant_view/patient_management.php');
                exit();
            }
        }
    }

    if ($raw_role === 'doctor') {
        $layout = 'doctor';
    } elseif (in_array($raw_role, ['assistant', 'admin'], true)) {
        $layout = 'assistant';
    } else {
        $layout = 'patient';
    }

    if ($staff_select_patient) {
        $appointments_page_href = '/assistant_view/patient_management.php';
    } elseif ($is_staff) {
        $appointments_page_href = '/assistant_view/assistant_appointments.php?patient_id=' . $patient_id;
    } else {
        $appointments_page_href = '/patient/patient_appointments.php';
    }

    // Fetch consultation records with doctor info (paginated: 4 per page)
    // Include follow-up appointment information if exists
    $consultations_result = null;
    $consultation_total_count = 0;
    $history_per_page = 4;
    $history_page = 1;
    $history_total_pages = 0;

    if ($staff_select_patient || $embed_load_error) {
        // no query until a patient is chosen (or embed error)
    } else {
        if ($raw_role === 'doctor') {
            $count_stmt = $conn->prepare("SELECT COUNT(*) FROM consultations c WHERE c.patient_id = ? AND c.doctor_id = ?");
            $count_stmt->bind_param("ii", $patient_id, $user_id);
        } else {
            $count_stmt = $conn->prepare("SELECT COUNT(*) FROM consultations c WHERE c.patient_id = ?");
            $count_stmt->bind_param("i", $patient_id);
        }
        $count_stmt->execute();
        $consultation_total_count = (int) $count_stmt->get_result()->fetch_row()[0];

        if ($consultation_total_count > 0) {
            $history_total_pages = (int) ceil($consultation_total_count / $history_per_page);
            $history_page = max(1, (int) ($_GET['page'] ?? 1));
            if ($history_page > $history_total_pages) {
                $history_page = $history_total_pages;
            }
            $history_offset = ($history_page - 1) * $history_per_page;

            if ($raw_role === 'doctor') {
                $consultations = $conn->prepare("
                    SELECT c.id, c.visit_date, c.chief_complaint, c.consultation_notes, 
                        c.diagnosis, c.treatment_plan, c.follow_up_date,
                        CONCAT(u.first_name, ' ', u.last_name) AS doctor_name, u.specialization,
                        a.id as appointment_id,
                        a.appointment_date AS source_appointment_date,
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
                    WHERE c.patient_id = ? AND c.doctor_id = ?
                    ORDER BY c.visit_date DESC
                    LIMIT ? OFFSET ?
                ");
                $consultations->bind_param("iiii", $patient_id, $user_id, $history_per_page, $history_offset);
            } else {
                $consultations = $conn->prepare("
                    SELECT c.id, c.visit_date, c.chief_complaint, c.consultation_notes, 
                        c.diagnosis, c.treatment_plan, c.follow_up_date,
                        CONCAT(u.first_name, ' ', u.last_name) AS doctor_name, u.specialization,
                        a.id as appointment_id,
                        a.appointment_date AS source_appointment_date,
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
                    ORDER BY c.visit_date DESC
                    LIMIT ? OFFSET ?
                ");
                $consultations->bind_param("iii", $patient_id, $history_per_page, $history_offset);
            }
            $consultations->execute();
            $consultations_result = $consultations->get_result();
        }
    }

    $history_page_url = function ($pageNum) use ($is_staff, $patient_id, $is_modal_embed) {
        $q = ['page' => (int) $pageNum];
        if ($is_staff && $patient_id > 0) {
            $q['patient_id'] = $patient_id;
        }
        if ($is_modal_embed) {
            $q['modal'] = '1';
        }
        return '/appointments/patient_history.php?' . http_build_query($q);
    };

    /**
     * Trim and normalize line breaks for history display (avoids huge gaps from pre-wrap + blank lines).
     */
    function patient_history_format_note(?string $s): string
    {
        $s = trim(preg_replace('/\R/u', "\n", (string) $s));
        $s = preg_replace("/\n{3,}/", "\n\n", $s);

        return nl2br(htmlspecialchars($s, ENT_QUOTES, 'UTF-8'));
    }

    /**
     * Resolve display timestamp for a visit; falls back to linked appointment when visit_date is invalid.
     *
     * @return array{0:bool,1:string,2:string} [ok, date line, time line or empty]
     */
    function patient_history_visit_datetime(array $row): array
    {
        $try = static function ($raw) {
            if ($raw === null || $raw === '') {
                return false;
            }
            $ts = strtotime((string) $raw);
            if ($ts === false) {
                return false;
            }
            $y = (int) date('Y', $ts);
            if ($y < 1970 || $y > 2100) {
                return false;
            }

            return $ts;
        };

        $ts = $try($row['visit_date'] ?? null);
        if ($ts === false) {
            $ts = $try($row['source_appointment_date'] ?? null);
        }
        if ($ts === false) {
            return [false, 'Date not recorded', ''];
        }

        return [true, date('l, F j, Y', $ts), date('g:i A', $ts)];
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php
            if ($staff_select_patient) {
                echo 'Patient History';
            } elseif ($is_staff && !empty($view_patient_name)) {
                echo 'Medical Records — ' . $view_patient_name;
            } else {
                echo 'My Medical Records';
            }
        ?> - HealthBase</title>
        <link rel="icon" type="image/x-icon" href="/assets/icons/favicon.ico">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
        <?php if ($layout === 'assistant'): ?>
        <link rel="stylesheet" href="/assistant_view/css/assistant.css">
        <?php elseif ($layout === 'doctor'): ?>
        <link rel="stylesheet" href="/css/dashboard.css">
        <?php else: ?>
        <link rel="stylesheet" href="/patient/css/patient_dashboard.css">
        <?php endif; ?>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
        <style>
            .patient-main-content,
            .assistant-main-content,
            .main-content {
                padding: 20px;
            }

            /* Use full main area width; trim double padding from layout + this page */
            body.patient-history-page .patient-main-content,
            body.patient-history-page .assistant-main-content,
            body.patient-history-page .main-content {
                padding: 0;
            }

            body.patient-history-page .patient-dashboard-content,
            body.patient-history-page .assistant-dashboard-content,
            body.patient-history-page .dashboard-content {
                padding: 12px 12px 20px;
                width: 100%;
                max-width: none;
                box-sizing: border-box;
            }

            /* Assistant column width uses assistant.css calc(100% - sidebar). Patient layout unchanged. */
            body.patient-history-page .assistant-main-content {
                min-width: 0;
            }

            body.patient-history-page .patient-main-content {
                width: auto;
                max-width: none;
                min-width: 0;
                box-sizing: border-box;
            }

            body.patient-history-page.dashboard-page .main-content {
                width: auto;
                max-width: none;
                margin-right: 0;
                box-sizing: border-box;
            }

            /* Full-width top bar; title + meta on one row */
            body.patient-history-page .records-page-header.assistant-header {
                display: block;
                width: 100%;
                max-width: none;
                margin: 0;
                padding: 16px 20px;
                box-sizing: border-box;
                border-radius: 0;
            }

            body.patient-history-page .records-page-header.patient-header {
                display: block;
                width: 100%;
                max-width: none;
                padding: 16px 20px;
                box-sizing: border-box;
            }

            body.patient-history-page .records-header-inner {
                width: 100%;
                max-width: none;
            }

            body.patient-history-page .records-header-title-row {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                justify-content: space-between;
                gap: 12px 24px;
                width: 100%;
            }

            body.patient-history-page .records-header-title-row .assistant-welcome,
            body.patient-history-page .records-header-title-row .patient-welcome,
            body.patient-history-page .records-header-title-row .header-title {
                flex: 1 1 220px;
                margin: 0;
                min-width: 0;
            }

            body.patient-history-page .records-header-meta {
                flex-shrink: 0;
            }

            body.patient-history-page .records-page-header .assistant-subtitle,
            body.patient-history-page .records-page-header .patient-subtitle {
                margin-top: 10px;
                margin-bottom: 0;
            }

            body.patient-history-page .records-page-header .records-back-link {
                margin-bottom: 8px;
            }

            body.patient-history-page .main-header.records-page-header {
                height: auto;
                min-height: 70px;
                width: 100%;
                max-width: none;
                padding: 12px 16px;
                align-items: flex-start;
                flex-wrap: wrap;
                box-sizing: border-box;
            }

            body.patient-history-page .main-header.records-page-header .header-left {
                flex: 1 1 100%;
                width: 100%;
                max-width: 100%;
                min-width: 0;
                display: flex;
                flex-direction: column;
                align-items: flex-start;
                gap: 6px;
            }

            body.patient-history-page .main-header.records-page-header .header-title {
                font-size: 22px;
            }

            body.patient-history-page .main-header.records-page-header .records-header-title-row {
                width: 100%;
            }

            .records-container {
                display: flex;
                flex-direction: column;
                gap: 0;
                width: 100%;
                max-width: none;
                margin: 0;
            }

            .history-intro {
                font-size: 13px;
                color: #64748b;
                margin-bottom: 20px;
                padding: 12px 16px;
                background: #f8fafc;
                border-radius: 10px;
                border: 1px solid #e2e8f0;
            }

            .history-tiles {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(min(100%, 260px), 1fr));
                gap: 14px;
                align-items: start;
            }

            .visit-card {
                display: flex;
                flex-direction: column;
                min-width: 0;
                width: 100%;
                background: #fff;
                border-radius: 14px;
                border: 1px solid #e2e8f0;
                box-shadow: 0 2px 8px rgba(15, 23, 42, 0.06);
                overflow: hidden;
                transition: box-shadow 0.2s ease, border-color 0.2s ease, transform 0.2s ease;
            }

            .visit-card:hover {
                border-color: #cbd5e1;
                box-shadow: 0 8px 24px rgba(15, 23, 42, 0.1);
                transform: translateY(-2px);
            }

            .visit-header {
                padding: 18px 20px;
                background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
                border-bottom: 1px solid #e2e8f0;
            }

            .visit-header-top {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                gap: 10px 14px;
                margin-bottom: 10px;
            }

            .visit-index {
                font-size: 11px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.06em;
                color: #3b82f6;
                background: #eff6ff;
                padding: 4px 10px;
                border-radius: 6px;
                border: 1px solid #bfdbfe;
            }

            .visit-datetime {
                font-size: 17px;
                font-weight: 700;
                color: #0f172a;
            }

            .visit-time {
                font-size: 14px;
                font-weight: 500;
                color: #64748b;
            }

            .visit-provider {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                gap: 8px;
                font-size: 14px;
                color: #475569;
            }

            .visit-provider strong {
                color: #1e293b;
                font-weight: 600;
            }

            .visit-spec-chip {
                font-size: 12px;
                padding: 3px 10px;
                background: #fff;
                border: 1px solid #e2e8f0;
                border-radius: 999px;
                color: #64748b;
            }

            .visit-body {
                padding: 16px;
                display: flex;
                flex-direction: column;
                gap: 0;
            }

            .visit-section {
                margin-bottom: 14px;
            }

            .visit-section:last-child {
                margin-bottom: 0;
            }

            .visit-section-assessment .visit-assessment-panel {
                background: #fafbfc;
                border: 1px solid #e8ecf1;
                border-radius: 10px;
                padding: 10px 12px;
            }

            .visit-assessment-part + .visit-assessment-part {
                margin-top: 10px;
                padding-top: 10px;
                border-top: 1px solid #e8ecf1;
            }

            .visit-assessment-label {
                display: block;
                font-size: 10px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                color: #64748b;
                margin-bottom: 4px;
            }

            .visit-assessment-text {
                font-size: 14px;
                line-height: 1.55;
                color: #334155;
                white-space: normal;
                word-break: break-word;
            }

            .visit-section-title {
                font-size: 12px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                color: #64748b;
                margin: 0 0 8px 0;
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .visit-section-title i {
                color: #3b82f6;
                font-size: 13px;
            }

            .visit-section-box {
                background: #fafbfc;
                border: 1px solid #e8ecf1;
                border-radius: 10px;
                padding: 12px 14px;
                font-size: 14px;
                line-height: 1.65;
                color: #334155;
                white-space: pre-wrap;
            }

            .visit-section-box.visit-assessment-text {
                white-space: normal;
                word-break: break-word;
            }

            .visit-section-box.empty-skip {
                display: none;
            }

            .rx-block {
                margin-top: 4px;
            }

            .rx-table-wrap {
                overflow-x: auto;
                border: 1px solid #e2e8f0;
                border-radius: 10px;
                background: #fff;
            }

            .rx-table {
                width: 100%;
                border-collapse: collapse;
                font-size: 13px;
            }

            .rx-table th {
                text-align: left;
                padding: 10px 12px;
                background: #f8fafc;
                color: #475569;
                font-weight: 600;
                border-bottom: 1px solid #e2e8f0;
                white-space: nowrap;
            }

            .rx-table td {
                padding: 10px 12px;
                border-bottom: 1px solid #f1f5f9;
                color: #334155;
                vertical-align: top;
            }

            .rx-table tr:last-child td {
                border-bottom: none;
            }

            .rx-med {
                font-weight: 600;
                color: #0f172a;
            }

            .rx-instructions {
                font-size: 12px;
                color: #64748b;
                margin-top: 6px;
                line-height: 1.5;
            }

            .follow-up-card {
                margin-top: 0;
                padding: 14px;
                background: linear-gradient(135deg, #eff6ff 0%, #f0f9ff 100%);
                border: 1px solid #bfdbfe;
                border-radius: 10px;
                display: flex;
                gap: 14px;
                align-items: flex-start;
            }

            .follow-up-card > i {
                color: #2563eb;
                font-size: 22px;
                margin-top: 2px;
            }

            .follow-up-title {
                font-size: 12px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.04em;
                color: #1e40af;
                margin-bottom: 6px;
            }

            .follow-up-date-text {
                font-size: 15px;
                font-weight: 600;
                color: #0f172a;
                margin-bottom: 10px;
            }

            .status-pill {
                padding: 4px 10px;
                border-radius: 6px;
                font-size: 11px;
                font-weight: 700;
                text-transform: uppercase;
                display: inline-flex;
                align-items: center;
                gap: 4px;
            }

            .btn-appt-link {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 8px 14px;
                background: #2563eb;
                color: #fff !important;
                border-radius: 8px;
                text-decoration: none;
                font-size: 12px;
                font-weight: 600;
                margin-top: 8px;
                transition: background 0.15s;
            }

            .btn-appt-link:hover {
                background: #1d4ed8;
            }

            .follow-up-pending {
                margin-top: 8px;
                padding: 10px 12px;
                background: rgba(251, 191, 36, 0.12);
                border-radius: 8px;
                border: 1px solid rgba(251, 191, 36, 0.35);
                font-size: 12px;
                color: #92400e;
            }

            .status-pill.ok { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
            .status-pill.bad { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
            .status-pill.pending { background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }
            .status-pill.neutral { background: #e2e8f0; color: #475569; border: 1px solid #cbd5e1; }

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

            .empty-state h3 {
                color: #64748b;
                margin-bottom: 10px;
            }

            .empty-state p {
                color: #94a3b8;
            }

            .records-back-link {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                color: #3b82f6;
                text-decoration: none;
                font-size: 14px;
                font-weight: 600;
                margin-bottom: 12px;
            }

            .records-back-link:hover {
                color: #2563eb;
                text-decoration: underline;
            }

            .history-pagination {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                justify-content: center;
                gap: 8px;
                margin-top: 24px;
                padding: 14px 16px;
                background: #f8fafc;
                border: 1px solid #e2e8f0;
                border-radius: 10px;
            }

            .history-page-num {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 38px;
                height: 38px;
                padding: 0 12px;
                border-radius: 8px;
                text-decoration: none;
                font-size: 14px;
                font-weight: 600;
                color: #334155;
                background: #fff;
                border: 1px solid #e2e8f0;
                transition: background 0.15s, border-color 0.15s, color 0.15s;
                box-sizing: border-box;
            }

            a.history-page-num:hover {
                background: #eff6ff;
                border-color: #93c5fd;
                color: #1d4ed8;
            }

            .history-page-num.is-active {
                background: #3b82f6;
                color: #fff !important;
                border-color: #3b82f6;
                cursor: default;
            }

            body.patient-history-modal-embed {
                margin: 0;
                padding: 0;
                overflow-x: hidden;
            }
            body.patient-history-modal-embed .assistant-main-content,
            body.patient-history-modal-embed .main-content,
            body.patient-history-modal-embed .patient-main-content {
                margin-left: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
                min-height: auto;
            }
            body.patient-history-modal-embed .patient-dashboard-content,
            body.patient-history-modal-embed .assistant-dashboard-content,
            body.patient-history-modal-embed .dashboard-content {
                padding: 8px 10px 16px !important;
            }

            @media (max-width: 768px) {
            .patient-main-content,
            .assistant-main-content,
            .main-content {
                margin-left: 0;
            }

            body.patient-history-page .patient-dashboard-content,
            body.patient-history-page .assistant-dashboard-content,
            body.patient-history-page .dashboard-content {
                padding: 10px 12px 16px;
            }

            .history-tiles {
                gap: 12px;
            }

            .visit-card:hover {
                transform: none;
            }

            .rx-table th,
            .rx-table td {
                padding: 8px 10px;
                font-size: 12px;
            }
        }
        </style>
    </head>
    <body class="<?php echo $layout === 'assistant' ? 'assistant-dashboard-page' : ($layout === 'doctor' ? 'dashboard-page' : 'patient-dashboard-page'); ?> patient-history-page<?php echo $is_modal_embed ? ' patient-history-modal-embed' : ''; ?>">
        <?php if (!$is_modal_embed): ?>
        <?php
        if ($layout === 'assistant') {
            include __DIR__ . '/../assistant_view/includes/assistant_sidebar.php';
        } elseif ($layout === 'doctor') {
            include __DIR__ . '/../includes/doctor_sidebar.php';
        } else {
            include __DIR__ . '/../patient/includes/patient_sidebar.php';
        }
        ?>
        <?php endif; ?>

        <div class="<?php echo $layout === 'assistant' ? 'assistant-main-content' : ($layout === 'doctor' ? 'main-content' : 'patient-main-content'); ?>">
            <?php if ($layout === 'assistant'): ?>
            <header class="assistant-header records-page-header">
                <div class="records-header-inner">
                    <?php if ($is_modal_embed): ?>
                    <p class="records-back-link" style="margin:0 0 8px 0;cursor:default;opacity:0.85;font-size:13px;">
                        <i class="fas fa-window-restore"></i> Pop-up view — close from the page that opened this window
                    </p>
                    <?php else: ?>
                    <a href="/assistant_view/patient_management.php" class="records-back-link">
                        <i class="fas fa-arrow-left"></i> Back to Patient Management
                    </a>
                    <?php endif; ?>
                    <div class="records-header-title-row">
                        <h1 class="assistant-welcome"><i class="fas fa-file-medical"></i> <?php echo $staff_select_patient ? 'Patient History' : ('Medical Records' . ($view_patient_name ? ' — ' . $view_patient_name : '')); ?></h1>
                        <div class="records-header-meta current-time" style="display: flex; align-items: center; gap: 8px; padding: 8px 16px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
                            <i class="fas fa-calendar-day" style="color: #3b82f6;"></i>
                            <span style="color: #1e293b; font-weight: 600; font-size: 14px;"><?php echo date('l, F j, Y'); ?></span>
                        </div>
                    </div>
                    <p class="assistant-subtitle"><?php echo $staff_select_patient ? 'Open a patient below or from Patient Management to view their records.' : 'Consultation history, diagnoses, prescriptions, and treatment plans'; ?></p>
                </div>
            </header>
            <?php elseif ($layout === 'doctor'): ?>
            <header class="main-header records-page-header">
                <div class="header-left">
                    <?php if ($is_modal_embed): ?>
                    <p class="records-back-link" style="margin:0 0 8px 0;cursor:default;opacity:0.85;font-size:13px;">
                        <i class="fas fa-window-restore"></i> Pop-up view — use <strong>Close</strong> on Appointments to return
                    </p>
                    <?php else: ?>
                    <a href="/assistant_view/patient_management.php" class="records-back-link">
                        <i class="fas fa-arrow-left"></i> Back to Patient Management
                    </a>
                    <?php endif; ?>
                    <div class="records-header-title-row">
                        <h1 class="header-title"><i class="fas fa-file-medical"></i> <?php echo $staff_select_patient ? 'Patient History' : ('Medical Records' . (!empty($view_patient_name) ? ' — ' . $view_patient_name : '')); ?></h1>
                        <div class="records-header-meta" style="display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-calendar-day" style="color: #3b82f6;"></i>
                            <span style="font-weight: 600;"><?php echo date('l, F j, Y'); ?></span>
                        </div>
                    </div>
                    <p class="header-subtitle"><?php echo $staff_select_patient ? 'Choose a patient to view consultation history, or open Patient Management and use View History.' : 'Consultation history, diagnoses, prescriptions, and treatment plans'; ?></p>
                </div>
            </header>
            <?php else: ?>
            <header class="patient-header records-page-header">
                <div class="records-header-inner">
                    <div class="records-header-title-row">
                        <h1 class="patient-welcome"><i class="fas fa-file-medical"></i> My Medical Records</h1>
                        <div class="records-header-meta patient-date-info">
                            <i class="fas fa-calendar-day"></i>
                            <span><?php echo date('l, F j, Y'); ?></span>
                        </div>
                    </div>
                    <p class="patient-subtitle">View your consultation history, diagnoses, prescriptions, and treatment plans</p>
                </div>
            </header>
            <?php endif; ?>

            <div class="<?php echo $layout === 'assistant' ? 'assistant-dashboard-content' : ($layout === 'doctor' ? 'dashboard-content' : 'patient-dashboard-content'); ?>">

            <div class="records-container">
                <?php if ($embed_load_error): ?>
                    <div class="empty-state" style="max-width: 520px; margin: 0 auto;">
                        <i class="fas fa-exclamation-circle" style="color:#dc2626;"></i>
                        <h3>Unable to load</h3>
                        <p><?= htmlspecialchars($embed_load_error) ?></p>
                    </div>
                <?php elseif ($staff_select_patient): ?>
                    <div class="empty-state" style="max-width: 520px; margin: 0 auto;">
                        <i class="fas fa-folder-open"></i>
                        <h3>Select a patient</h3>
                        <p style="max-width: 420px; margin-left: auto; margin-right: auto;">You are on <strong>Patient History</strong>. Add <code style="background:#f1f5f9;padding:2px 6px;border-radius:4px;">?patient_id=</code> in the URL, or go to Patient Management and click <strong>View History</strong> on a patient.</p>
                        <p style="margin-top: 16px;">
                            <a href="/assistant_view/patient_management.php" style="display: inline-flex; align-items: center; gap: 8px; padding: 12px 20px; background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; border-radius: 10px; text-decoration: none; font-weight: 600; font-size: 14px;">
                                <i class="fas fa-users"></i> Open Patient Management
                            </a>
                        </p>
                    </div>
                <?php elseif (!$staff_select_patient && $consultation_total_count > 0): ?>
                    <?php
                    $total_visits = $consultation_total_count;
                    $visit_num = ($history_page - 1) * $history_per_page;
                    ?>
                    <p class="history-intro"><i class="fas fa-th-large" style="color:#3b82f6;"></i> <strong><?= (int) $total_visits ?></strong> consultation<?= $total_visits === 1 ? '' : 's' ?> — shown as tiles, newest first. Page <strong><?= (int) $history_page ?></strong> of <strong><?= (int) $history_total_pages ?></strong> (<?= (int) $history_per_page ?> per page).</p>
                    <div class="history-tiles" role="list">
                    <?php while ($consultation = $consultations_result->fetch_assoc()):
                        $visit_num++;
                        $prescriptions_query = $conn->prepare("
                            SELECT medication_name, dosage, frequency, duration, instructions, quantity
                            FROM prescriptions
                            WHERE consultation_id = ?
                            ORDER BY id ASC
                        ");
                        $prescriptions_query->bind_param("i", $consultation['id']);
                        $prescriptions_query->execute();
                        $prescriptions_result = $prescriptions_query->get_result();
                        [$visit_dt_ok, $visit_date_line, $visit_time_line] = patient_history_visit_datetime($consultation);
                    ?>
                        <article class="visit-card" role="listitem">
                            <header class="visit-header">
                                <div class="visit-header-top">
                                    <span class="visit-index">Visit <?= (int) $visit_num ?> of <?= (int) $total_visits ?></span>
                                    <span class="visit-datetime"><?= htmlspecialchars($visit_date_line) ?></span>
                                    <?php if ($visit_dt_ok && $visit_time_line !== ''): ?>
                                    <span class="visit-time"><i class="fas fa-clock" style="opacity:0.7;"></i> <?= htmlspecialchars($visit_time_line) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="visit-provider">
                                    <strong>Dr. <?= htmlspecialchars($consultation['doctor_name']) ?></strong>
                                    <span class="visit-spec-chip"><?= htmlspecialchars($consultation['specialization']) ?></span>
                                </div>
                            </header>
                            <div class="visit-body">
                                <?php if (!empty($consultation['chief_complaint'])): ?>
                                <section class="visit-section">
                                    <h3 class="visit-section-title"><i class="fas fa-comment-medical"></i> Reason for visit</h3>
                                    <div class="visit-section-box visit-assessment-text"><?= patient_history_format_note($consultation['chief_complaint']) ?></div>
                                </section>
                                <?php endif; ?>

                                <?php if (!empty($consultation['consultation_notes']) || !empty($consultation['diagnosis'])): ?>
                                <section class="visit-section visit-section-assessment">
                                    <h3 class="visit-section-title"><i class="fas fa-notes-medical"></i> Assessment</h3>
                                    <div class="visit-assessment-panel">
                                        <?php if (!empty($consultation['consultation_notes'])): ?>
                                        <div class="visit-assessment-part">
                                            <span class="visit-assessment-label">Clinical notes</span>
                                            <div class="visit-assessment-text"><?= patient_history_format_note($consultation['consultation_notes']) ?></div>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (!empty($consultation['diagnosis'])): ?>
                                        <div class="visit-assessment-part">
                                            <span class="visit-assessment-label">Diagnosis</span>
                                            <div class="visit-assessment-text"><?= patient_history_format_note($consultation['diagnosis']) ?></div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </section>
                                <?php endif; ?>

                                <?php if (!empty($consultation['treatment_plan'])): ?>
                                <section class="visit-section">
                                    <h3 class="visit-section-title"><i class="fas fa-clipboard-check"></i> Treatment plan</h3>
                                    <div class="visit-section-box visit-assessment-text"><?= patient_history_format_note($consultation['treatment_plan']) ?></div>
                                </section>
                                <?php endif; ?>

                                <?php if ($prescriptions_result->num_rows > 0): ?>
                                <section class="visit-section rx-block">
                                    <h3 class="visit-section-title"><i class="fas fa-prescription-bottle-medical"></i> Prescriptions (<?= (int) $prescriptions_result->num_rows ?>)</h3>
                                    <div class="rx-table-wrap">
                                        <table class="rx-table">
                                            <thead>
                                                <tr>
                                                    <th>Medication</th>
                                                    <th>Dosage</th>
                                                    <th>Frequency</th>
                                                    <th>Duration</th>
                                                    <th>Qty</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php while ($presc = $prescriptions_result->fetch_assoc()): ?>
                                                <tr>
                                                    <td class="rx-med"><?= htmlspecialchars($presc['medication_name']) ?>
                                                        <?php if (!empty($presc['instructions'])): ?>
                                                        <div class="rx-instructions"><strong>Instructions:</strong> <?= patient_history_format_note($presc['instructions']) ?></div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?= !empty($presc['dosage']) ? htmlspecialchars($presc['dosage']) : '—' ?></td>
                                                    <td><?= !empty($presc['frequency']) ? htmlspecialchars($presc['frequency']) : '—' ?></td>
                                                    <td><?= !empty($presc['duration']) ? htmlspecialchars($presc['duration']) : '—' ?></td>
                                                    <td><?= !empty($presc['quantity']) ? htmlspecialchars($presc['quantity']) : '—' ?></td>
                                                </tr>
                                                <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </section>
                                <?php endif; ?>

                                <?php if (!empty($consultation['follow_up_date'])):
                                    $followup_appointment_id = $consultation['followup_appointment_id'] ?? null;
                                    $followup_appointment_status = $consultation['followup_appointment_status'] ?? null;
                                    $followup_appointment_date = $consultation['followup_appointment_date'] ?? null;
                                    $has_appointment = !empty($followup_appointment_id);
                                    $follow_up_date_formatted_with_time = $followup_appointment_date
                                        ? date('F j, Y \a\t g:i A', strtotime($followup_appointment_date))
                                        : date('F j, Y', strtotime($consultation['follow_up_date']));
                                    $status = $followup_appointment_status;
                                    $pill = 'neutral';
                                    if ($status === 'Cleared' || $status === 'Completed') {
                                        $pill = 'ok';
                                    } elseif ($status === 'Cancelled' || strtolower((string) $status) === 'declined') {
                                        $pill = 'bad';
                                    } elseif ($status === 'Pending' || $status === 'Confirmed') {
                                        $pill = 'pending';
                                    }
                                ?>
                                <section class="visit-section">
                                    <div class="follow-up-card">
                                        <i class="fas fa-calendar-check"></i>
                                        <div style="flex:1;min-width:0;">
                                            <div class="follow-up-title">Follow-up</div>
                                            <div class="follow-up-date-text"><?= htmlspecialchars($follow_up_date_formatted_with_time) ?></div>
                                            <?php if ($has_appointment): ?>
                                                <span class="status-pill <?= $pill ?>"><i class="fas fa-circle" style="font-size:6px;"></i> <?= htmlspecialchars((string) $status) ?></span>
                                                <div>
                                                    <a class="btn-appt-link" href="<?= htmlspecialchars($appointments_page_href, ENT_QUOTES, 'UTF-8') ?>"><i class="fas fa-external-link-alt"></i> View appointment</a>
                                                </div>
                                            <?php else: ?>
                                                <div class="follow-up-pending"><i class="fas fa-info-circle"></i> Follow-up appointment pending confirmation</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </section>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endwhile; ?>
                    </div>
                    <?php if ($history_total_pages > 1): ?>
                    <nav class="history-pagination" aria-label="Consultation history pages">
                        <?php
                        $cur = (int) $history_page;
                        for ($p = 1; $p <= (int) $history_total_pages; $p++):
                            if ($p === $cur):
                        ?>
                        <span class="history-page-num is-active" aria-current="page"><?= $p ?></span>
                        <?php else: ?>
                        <a class="history-page-num" href="<?= htmlspecialchars($history_page_url($p), ENT_QUOTES, 'UTF-8') ?>"><?= $p ?></a>
                        <?php
                            endif;
                        endfor;
                        ?>
                    </nav>
                    <?php endif; ?>
                <?php elseif (!$staff_select_patient && $consultation_total_count === 0): ?>
                    <div class="empty-state">
                        <i class="fas fa-file-medical-alt"></i>
                        <h3>No Medical Records Yet</h3>
                        <p><?php echo $is_staff
                            ? 'This patient has no consultation records yet. Records appear after a doctor completes an appointment.'
                            : 'Your consultation records will appear here after your doctor completes your appointment.'; ?></p>
                    </div>
                <?php endif; ?>
            </div>
            </div>
        </div>
    </body>
    </html>
