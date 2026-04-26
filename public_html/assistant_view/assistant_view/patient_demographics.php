<?php
/**
 * Staff: extended patient demographics (emergency contact, physicians, HMO, etc.)
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/patient_profile_extra.php';
hb_ensure_patient_profile_extra_table($conn);

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$role = strtolower(trim((string) ($_SESSION['role'] ?? '')));
if (!in_array($role, ['assistant', 'admin', 'doctor'], true)) {
    header('Location: ../dashboard/healthbase_dashboard.php');
    exit();
}

$patient_id = isset($_GET['patient_id']) ? (int) $_GET['patient_id'] : 0;
if ($patient_id < 1) {
    header('Location: patient_management.php');
    exit();
}

$patient_stmt = $conn->prepare('
    SELECT p.id, p.first_name, p.last_name, u.email
    FROM patients p
    INNER JOIN users u ON p.user_id = u.id
    WHERE p.id = ?
    LIMIT 1
');
$patient_stmt->bind_param('i', $patient_id);
$patient_stmt->execute();
$patient_row = $patient_stmt->get_result()->fetch_assoc();
if (!$patient_row) {
    header('Location: patient_management.php');
    exit();
}

if ($role === 'doctor') {
    $doc_chk = $conn->prepare("
        SELECT 1 FROM patients p WHERE p.id = ?
        AND (
            EXISTS (SELECT 1 FROM consultations c WHERE c.patient_id = p.id AND c.doctor_id = ? LIMIT 1)
            OR EXISTS (SELECT 1 FROM appointments a WHERE a.patient_id = p.id AND a.doctor_id = ? AND (a.status IS NULL OR a.status NOT IN ('Declined','Cancelled','declined','cancelled','Canceled','canceled')) LIMIT 1)
        )
        LIMIT 1
    ");
    $doc_chk->bind_param('iii', $patient_id, $user_id, $user_id);
    $doc_chk->execute();
    if ($doc_chk->get_result()->num_rows === 0) {
        header('HTTP/1.1 403 Forbidden');
        exit('You do not have access to this patient.');
    }
}

$user_query = $conn->prepare('SELECT username, email, role, specialization FROM users WHERE id = ?');
$user_query->bind_param('i', $user_id);
$user_query->execute();
$staff = $user_query->get_result()->fetch_assoc();
$sidebar_user_data = [
    'username' => htmlspecialchars($staff['username'] ?? ''),
    'email' => htmlspecialchars($staff['email'] ?? ''),
    'role' => htmlspecialchars($staff['role'] ?? ''),
    'specialization' => trim((string) ($staff['specialization'] ?? '')) ?: 'General',
];

$extra_table = hb_patient_profile_extra_table_exists($conn);
$ppe = hb_get_patient_profile_extra($conn, $patient_id);

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    if ($extra_table) {
        $ok = hb_save_patient_profile_extra($conn, $patient_id, $_POST, function ($str, $max) {
            return sanitize_string($str, $max);
        });
        if ($ok) {
            $message = 'Extended patient profile saved.';
            $message_type = 'success';
            $ppe = hb_get_patient_profile_extra($conn, $patient_id);
        } else {
            $message = 'Could not save extended profile.';
            $message_type = 'error';
        }
    } else {
        $message = 'Database table missing. Run sql/patient_profile_extra.sql first.';
        $message_type = 'error';
    }
}

$is_doctor = ($role === 'doctor');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient demographics - HealthBase</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <?php if ($is_doctor): ?>
    <link rel="stylesheet" href="../css/dashboard.css">
    <?php else: ?>
    <link rel="stylesheet" href="css/assistant.css">
    <?php endif; ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* One column: back link + cards share the same width (fixes misaligned “gutter” back link) */
        .pdemo-page {
            max-width: 1200px;
            width: 100%;
            margin: 0;
        }
        .pdemo-patient {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 16px 18px;
            margin-bottom: 20px;
        }
        .pdemo-patient h2 { margin: 0 0 4px; font-size: 1.15rem; color: #0f172a; }
        .pdemo-patient p { margin: 0; font-size: 14px; color: #64748b; }
        .pdemo-msg { padding: 12px 14px; border-radius: 10px; margin-bottom: 16px; font-size: 14px; }
        .pdemo-msg.success { background: #ecfdf5; color: #065f46; border: 1px solid #6ee7b7; }
        .pdemo-msg.error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .pdemo-back--header {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            white-space: nowrap;
            background: #f1f5f9;
            color: #1e40af;
            border: 1px solid #e2e8f0;
            transition: background 0.2s, border-color 0.2s;
        }
        .pdemo-back--header:hover {
            background: #e2e8f0;
            border-color: #cbd5e1;
            text-decoration: none;
            color: #1d4ed8;
        }
        .assistant-header .pdemo-back--header {
            background: #eff6ff;
            border-color: #bfdbfe;
            color: #1d4ed8;
        }
        .assistant-header .pdemo-back--header:hover {
            background: #dbeafe;
        }
        .pdemo-form-card {
            max-width: none;
            margin: 0;
            padding: 24px;
            background: #fff;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
        }
        .pdemo-actions { margin-top: 24px; display: flex; gap: 12px; flex-wrap: wrap; }
        .pdemo-btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 12px 20px; border-radius: 10px; border: none; font-weight: 600; cursor: pointer;
            background: linear-gradient(135deg, #2563eb, #1d4ed8); color: #fff; font-size: 14px;
        }
        .pdemo-btn-sec { background: #f1f5f9; color: #334155; text-decoration: none; }
        /*
         * Pull title to the left: .assistant-header uses padding: 20px 30px in assistant.css
         * — trim left padding on this page only.
         */
        body.page-patient-demographics.assistant-dashboard-page .assistant-header {
            padding-left: 12px;
        }
        body.page-patient-demographics.assistant-dashboard-page .assistant-dashboard-content {
            padding-left: 20px;
            padding-right: 20px;
        }
        .pdemo-title-block {
            margin-left: 0;
        }
        body.page-patient-demographics.dashboard-page .main-content > .main-header {
            padding-left: 12px;
        }
        .pdemo-body--content {
            padding-top: 8px;
        }
        @media (max-width: 720px) {
            .assistant-header,
            .main-header {
                flex-wrap: wrap;
                align-items: flex-start;
                gap: 12px;
            }
            .assistant-header .assistant-header-left,
            .main-header .header-left {
                flex: 1 1 100%;
            }
            .assistant-header .assistant-header-right,
            .main-header .header-right {
                width: 100%;
                justify-content: flex-start;
            }
        }
    </style>
</head>
<body class="page-patient-demographics <?= $is_doctor ? 'dashboard-page' : 'assistant-dashboard-page'; ?>">
<?php
if ($is_doctor) {
    include __DIR__ . '/../includes/doctor_sidebar.php';
} else {
    include __DIR__ . '/includes/assistant_sidebar.php';
}
?>
<div class="<?= $is_doctor ? 'main-content' : 'assistant-main-content'; ?>">
    <header class="<?= $is_doctor ? 'main-header' : 'assistant-header'; ?>">
        <div class="<?= $is_doctor ? 'header-left' : 'assistant-header-left'; ?>">
            <?php if ($is_doctor): ?>
            <div class="pdemo-title-block">
                <h1 class="header-title">Patient demographics</h1>
                <p class="header-subtitle">Emergency contacts, physicians, employment, and HMO (optional fields)</p>
            </div>
            <?php else: ?>
            <div class="pdemo-title-block">
                <h1 class="assistant-welcome">Patient demographics</h1>
                <p class="assistant-subtitle">Emergency contacts, physicians, employment, and HMO (optional fields)</p>
            </div>
            <?php endif; ?>
        </div>
        <div class="<?= $is_doctor ? 'header-right' : 'assistant-header-right'; ?>">
            <a class="pdemo-back--header" href="patient_management.php"><i class="fas fa-arrow-left"></i> Back to patient management</a>
        </div>
    </header>
    <div class="<?= $is_doctor ? 'dashboard-content' : 'assistant-dashboard-content'; ?> pdemo-body--content">
        <div class="pdemo-page">
            <div class="pdemo-patient">
                <h2><?= htmlspecialchars(trim($patient_row['first_name'] . ' ' . $patient_row['last_name'])) ?></h2>
                <p><i class="fas fa-envelope"></i> <?= htmlspecialchars($patient_row['email']) ?> · Patient ID <?= (int) $patient_row['id'] ?></p>
            </div>

            <?php if ($message): ?>
                <div class="pdemo-msg <?= $message_type === 'success' ? 'success' : 'error' ?>"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <form method="post" class="edit-profile-container pdemo-form-card">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <?php
                $table_exists = $extra_table;
                $ppe_css_href = '/patient/css/patient_extended_form.css';
                include __DIR__ . '/../patient/partials/patient_extended_profile_fields.php';
                ?>
                <div class="pdemo-actions">
                    <button type="submit" class="pdemo-btn"><i class="fas fa-save"></i> Save extended profile</button>
                    <a href="assistant_appointments.php?patient_id=<?= (int) $patient_id ?>" class="pdemo-btn pdemo-btn-sec"><i class="fas fa-calendar-alt"></i> Appointments for this patient</a>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>
