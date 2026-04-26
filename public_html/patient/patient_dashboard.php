<?php
// patient_dashboard.php - Simplified Patient Dashboard
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include("../config/db_connect.php");
require_once __DIR__ . '/../appointments/notification_helper.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../PHPMailer/src/Exception.php';
require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_notifications_read'])) {
    require_post_csrf();
    $mark_all_stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    if ($mark_all_stmt) {
        $mark_all_stmt->bind_param("i", $user_id);
        $mark_all_stmt->execute();
        $mark_all_stmt->close();
    }
    header("Location: patient_dashboard.php");
    exit();
}

// Fetch patient info
$query = $conn->prepare("SELECT username, email, role FROM users WHERE id = ?");
$query->bind_param("i", $user_id);
$query->execute();
$result = $query->get_result();
$user = $result->fetch_assoc();
$username = htmlspecialchars($user['username']);
$emailRaw = trim((string) ($user['email'] ?? ''));
$email = htmlspecialchars($emailRaw);
$role = htmlspecialchars($user['role']);

/**
 * Send patient appointment reminder email (non-blocking).
 */
function hb_send_patient_dashboard_appointment_reminder_email(
    string $to,
    string $patientName,
    int $appointmentId,
    string $doctorName,
    string $specialization,
    string $whenLabel
): bool {
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $subject = 'HealthBase — Appointment reminder';
    $appointmentsUrl = 'https://healthbase.site/patient/patient_appointments.php';
    $html = '
<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;line-height:1.6;color:#1e293b;">
  <p>Hello <strong>' . htmlspecialchars($patientName, ENT_QUOTES, 'UTF-8') . '</strong>,</p>
  <p>This is a friendly reminder for your upcoming appointment:</p>
  <table style="border-collapse:collapse;margin:16px 0;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px;max-width:520px;">
    <tr><td style="padding:4px 8px;color:#64748b;">Appointment Number</td><td style="padding:4px 8px;font-weight:600;">#' . (int) $appointmentId . '</td></tr>
    <tr><td style="padding:4px 8px;color:#64748b;">Doctor in Charge</td><td style="padding:4px 8px;">' . htmlspecialchars($doctorName, ENT_QUOTES, 'UTF-8') . ($specialization !== '' ? ' — ' . htmlspecialchars($specialization, ENT_QUOTES, 'UTF-8') : '') . '</td></tr>
    <tr><td style="padding:4px 8px;color:#64748b;">Schedule</td><td style="padding:4px 8px;font-weight:600;">' . htmlspecialchars($whenLabel, ENT_QUOTES, 'UTF-8') . '</td></tr>
  </table>
  <p><a href="' . htmlspecialchars($appointmentsUrl, ENT_QUOTES, 'UTF-8') . '" style="color:#2563eb;">View my appointments</a></p>
</body></html>';
    $plain = "Hello {$patientName},\n\n"
        . "This is a reminder for your upcoming appointment.\n\n"
        . "Appointment Number: #{$appointmentId}\n"
        . "Doctor in Charge: {$doctorName}" . ($specialization !== '' ? " ({$specialization})" : '') . "\n"
        . "Schedule: {$whenLabel}\n"
        . "View appointments: {$appointmentsUrl}\n";

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'guildtech21@gmail.com';
        $mail->Password = 'fokb qhkm xvxz qvnd';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->setFrom('guildtech21@gmail.com', 'HealthBase');
        $mail->addAddress($to, $patientName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html;
        $mail->AltBody = $plain;
        $mail->send();

        return true;
    } catch (Exception $e) {
        error_log('patient_dashboard reminder email send failed: ' . $mail->ErrorInfo);
        return false;
    }
}

// Pass user data to sidebar
$sidebar_user_data = [
    'username' => $username,
    'email' => $email,
    'role' => $role
];

// Today date
$today = date("Y-m-d");

// Get patient ID from patients table
$patient_query = $conn->prepare("SELECT id FROM patients WHERE user_id = ?");
$patient_query->bind_param("i", $user_id);
$patient_query->execute();
$patient_result = $patient_query->get_result();
$patient_data = $patient_result->fetch_assoc();
$patient_id = $patient_data ? $patient_data['id'] : null;

// Check if user has patient record
if (!$patient_id) {
    // User doesn't have a patient record, redirect to creation page
    header("Location: create_patient_record.php");
    exit();
}

// Auto-create reminder notifications for upcoming confirmed appointments (next 24 hours).
$upcoming_reminders_stmt = $conn->prepare("
    SELECT a.id, a.appointment_date,
           CONCAT(TRIM(COALESCE(u.first_name, '')), ' ', TRIM(COALESCE(u.last_name, ''))) AS doctor_name,
           COALESCE(u.specialization, '') AS specialization
    FROM appointments a
    JOIN users u ON a.doctor_id = u.id
    WHERE a.patient_id = ?
      AND LOWER(a.status) = 'confirmed'
      AND a.appointment_date >= NOW()
      AND a.appointment_date <= DATE_ADD(NOW(), INTERVAL 24 HOUR)
");
$upcoming_reminders_stmt->bind_param("i", $patient_id);
$upcoming_reminders_stmt->execute();
$upcoming_reminders_rs = $upcoming_reminders_stmt->get_result();
while ($rem = $upcoming_reminders_rs->fetch_assoc()) {
    $appt_id = (int) ($rem['id'] ?? 0);
    if ($appt_id < 1) {
        continue;
    }
    $appt_ts = strtotime((string) ($rem['appointment_date'] ?? ''));
    $appt_label = $appt_ts ? date('M j, Y g:i A', $appt_ts) : (string) ($rem['appointment_date'] ?? '');
    $doc_name = trim((string) ($rem['doctor_name'] ?? ''));
    $spec = trim((string) ($rem['specialization'] ?? ''));
    $msg = 'Appointment reminder: You have an appointment'
        . ($doc_name !== '' ? ' with Dr. ' . $doc_name : '')
        . ($spec !== '' ? ' (' . $spec . ')' : '')
        . ' on ' . $appt_label . '.';
    $link = '/patient/patient_appointments.php?appointment_id=' . $appt_id;

    $ins = $conn->prepare("
        INSERT INTO notifications (user_id, message, link, type, is_read, created_at)
        SELECT ?, ?, ?, 'appointment_reminder', 0, NOW()
        FROM DUAL
        WHERE NOT EXISTS (
            SELECT 1 FROM notifications
            WHERE user_id = ?
              AND type = 'appointment_reminder'
              AND link = ?
        )
    ");
    $ins->bind_param("issis", $user_id, $msg, $link, $user_id, $link);
    $ins->execute();
    $newReminderCreated = $ins->affected_rows > 0;
    $ins->close();

    // Send reminder email once per appointment when notification is first created.
    if ($newReminderCreated) {
        $patientDisplayName = trim((string) ($user['username'] ?? 'Patient'));
        $doctorDisplayName = trim((string) ($rem['doctor_name'] ?? 'Doctor'));
        $specializationLabel = trim((string) ($rem['specialization'] ?? ''));
        hb_send_patient_dashboard_appointment_reminder_email(
            $emailRaw,
            $patientDisplayName !== '' ? $patientDisplayName : 'Patient',
            $appt_id,
            $doctorDisplayName !== '' ? ('Dr. ' . $doctorDisplayName) : 'Doctor',
            $specializationLabel,
            $appt_label
        );
    }
}
$upcoming_reminders_stmt->close();

$unread_notifications_count = getUnreadNotificationCount($conn, (int) $user_id);
$recent_notifications = getUserNotifications($conn, (int) $user_id, 6);

/** @param string|null $raw DB datetime */
function patient_dashboard_format_created_at(?string $raw): string
{
    if ($raw === null || $raw === '') {
        return '—';
    }
    $ts = strtotime($raw);

    return $ts ? date('M j, Y \a\t g:i A', $ts) : '—';
}

// Patient's Appointments
$appointments = $conn->prepare("
    SELECT a.id, a.appointment_date, a.status,
           COALESCE(a.created_at, a.updated_at) AS created_at,
           CONCAT(u.first_name, ' ', u.last_name) AS doctor_name, u.specialization
    FROM appointments a
    JOIN users u ON a.doctor_id = u.id
    WHERE a.patient_id=? AND DATE(a.appointment_date) >= ?
    ORDER BY a.appointment_date ASC
    LIMIT 5
");
$appointments->bind_param("is", $patient_id, $today);
$appointments->execute();
$appointments_result = $appointments->get_result();

// Today's Appointments
$todays_appointments = $conn->prepare("
    SELECT a.id, a.appointment_date, a.status,
           COALESCE(a.created_at, a.updated_at) AS created_at,
           CONCAT(u.first_name, ' ', u.last_name) AS doctor_name, u.specialization
    FROM appointments a
    JOIN users u ON a.doctor_id = u.id
    WHERE a.patient_id=? AND DATE(a.appointment_date)=? AND a.status='Confirmed'
    ORDER BY a.appointment_date
");
$todays_appointments->bind_param("is", $patient_id, $today);
$todays_appointments->execute();
$todays_result = $todays_appointments->get_result();

// Count appointments by status
$status_counts = $conn->prepare("
    SELECT status, COUNT(*) as count 
    FROM appointments 
    WHERE patient_id=? AND DATE(appointment_date) >= ?
    GROUP BY status
");
$status_counts->bind_param("is", $patient_id, $today);
$status_counts->execute();
$status_result = $status_counts->get_result();

$appointment_stats = [
    'confirmed' => 0,
    'pending' => 0,
    'completed' => 0
];

while ($row = $status_result->fetch_assoc()) {
    $status = strtolower($row['status']);
    if (isset($appointment_stats[$status])) {
        $appointment_stats[$status] = $row['count'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Patient Dashboard - HealthBase</title>
    <link rel="icon" type="image/x-icon" href="/assets/icons/favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="css/patient_dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="patient-dashboard-page">
    <?php include 'includes/patient_sidebar.php'; ?>
    
    <div class="patient-main-content">
        <!-- Header -->
        <header class="patient-header">
            <div class="patient-header-left">
                <h1 class="patient-welcome">Welcome back, <?php echo $username; ?>!</h1>
                <p class="patient-subtitle">Here's your health overview</p>
            </div>
            <div class="patient-header-right">
                <div class="patient-date-info">
                    <i class="fas fa-calendar-day"></i>
                    <span><?php echo date("l, F j, Y"); ?></span>
                </div>
                <div class="hb-pd-notif-wrap">
                    <button
                        type="button"
                        id="hbNotifBellBtn"
                        class="hb-pd-notif-bell"
                        aria-expanded="false"
                        aria-controls="hbNotifBellDropdown"
                        aria-label="Toggle notifications"
                    >
                        <i class="fas fa-bell"></i>
                        <?php if ((int) $unread_notifications_count > 0): ?>
                            <span class="hb-pd-notif-badge"><?php echo (int) $unread_notifications_count; ?></span>
                        <?php endif; ?>
                    </button>
                    <div id="hbNotifBellDropdown" class="hb-pd-notif-dropdown" style="display:none;">
                        <div class="hb-pd-notif-dropdown-head">
                            <strong>Notifications &amp; Reminders</strong>
                            <?php if ((int) $unread_notifications_count > 0): ?>
                                <form method="post" style="margin: 0;">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                                    <button type="submit" name="mark_all_notifications_read" value="1" class="view-all-link" style="background:none;border:none;cursor:pointer;padding:0;">
                                        Mark all as read
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                        <div class="hb-pd-notif-list">
                            <?php if (!empty($recent_notifications)): ?>
                                <?php foreach ($recent_notifications as $notif): ?>
                                    <div class="hb-pd-notif-item" style="<?php echo ((int) ($notif['is_read'] ?? 0) === 0) ? 'border-left: 4px solid #f59e0b;' : ''; ?>">
                                        <div class="hb-pd-notif-item-body">
                                            <div class="hb-pd-notif-item-message"><?php echo htmlspecialchars((string) ($notif['message'] ?? 'Notification')); ?></div>
                                            <small><?php echo htmlspecialchars(patient_dashboard_format_created_at($notif['created_at'] ?? null)); ?></small>
                                        </div>
                                        <button
                                            type="button"
                                            class="view-all-link js-open-notif-modal"
                                            style="white-space: nowrap; background:none; border:none; cursor:pointer; padding:0;"
                                            data-notif-message="<?php echo htmlspecialchars((string) ($notif['message'] ?? 'Notification'), ENT_QUOTES, 'UTF-8'); ?>"
                                            data-notif-created="<?php echo htmlspecialchars(patient_dashboard_format_created_at($notif['created_at'] ?? null), ENT_QUOTES, 'UTF-8'); ?>"
                                        >
                                            Open
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="hb-pd-notif-empty">No notifications yet.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Dashboard Content -->
        <div class="patient-dashboard-content">
            <!-- Stats Cards -->
            <div class="patient-stats-grid">
                <div class="patient-stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $appointment_stats['confirmed']; ?></h3>
                        <p>Confirmed Appointments</p>
                    </div>
                </div>
                
                <div class="patient-stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $appointment_stats['pending']; ?></h3>
                        <p>Pending Appointments</p>
                    </div>
                </div>
                
                <div class="patient-stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $appointment_stats['completed']; ?></h3>
                        <p>Completed Visits</p>
                    </div>
                </div>
                
                <div class="patient-stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-ticket-alt"></i>
                    </div>
                    <div class="stat-content">
                        <h3>0</h3>
                        <p>Open Support Tickets</p>
                    </div>
                </div>
            </div>

            <!-- Main Content Grid -->
            <div class="patient-content-grid">
                <!-- Today's Schedule -->
                <div class="patient-card today-schedule-card">
                    <div class="patient-card-header">
                        <h3 class="patient-card-title">
                            <i class="fas fa-calendar-day"></i>
                            Today's Schedule
                        </h3>
                    </div>
                    <div class="schedule-list">
                        <?php if ($todays_result->num_rows > 0): ?>
                            <?php while ($row = $todays_result->fetch_assoc()) { ?>
                                <div class="patient-appointment-item">
                                    <div class="patient-appointment-info">
                                        <h4><?php echo htmlspecialchars($row['doctor_name']); ?></h4>
                                        <p><?php echo htmlspecialchars($row['specialization']); ?></p>
                                        <p><?php echo date("h:i A", strtotime($row['appointment_date'])); ?></p>
                                        <p class="appointment-created-meta">
                                            <i class="fas fa-plus-circle" aria-hidden="true"></i>
                                            <span>Appointment created: <?php echo htmlspecialchars(patient_dashboard_format_created_at($row['created_at'] ?? null)); ?></span>
                                        </p>
                                    </div>
                                    <span class="patient-status-badge patient-status-confirmed">Confirmed</span>
                                </div>
                            <?php } ?>
                        <?php else: ?>
                            <div class="patient-appointment-item">
                                <div class="patient-appointment-info">
                                    <h4>No appointments today</h4>
                                    <p>You're all caught up!</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="patient-card quick-actions-card">
                    <div class="patient-card-header">
                        <h3 class="patient-card-title">
                            <i class="fas fa-bolt"></i>
                            Quick Actions
                        </h3>
                    </div>
                    <div class="quick-actions-grid">
                        <a href="/appointments/scheduling.php" class="quick-action-btn">
                            <i class="fas fa-calendar-plus"></i>
                            <span>Book Appointment</span>
                        </a>
                        <a href="/patient/patient_appointments.php" class="quick-action-btn">
                            <i class="fas fa-calendar-check"></i>
                            <span>View Appointments</span>
                        </a>
                        <a href="/patient/patient_tickets.php" class="quick-action-btn">
                            <i class="fas fa-ticket-alt"></i>
                            <span>My Tickets</span>
                        </a>
                    </div>
                </div>

                <!-- Recent Appointments -->
                <div class="patient-card recent-appointments-card">
                    <div class="patient-card-header">
                        <h3 class="patient-card-title">
                            <i class="fas fa-history"></i>
                            Recent Appointments
                        </h3>
                        <a href="/patient/patient_appointments.php" class="view-all-link">View All</a>
                    </div>
                    <div class="appointment-list">
                        <?php while ($row = $appointments_result->fetch_assoc()) { ?>
                            <div class="patient-appointment-item">
                                <div class="patient-appointment-info">
                                    <h4><?php echo htmlspecialchars($row['doctor_name']); ?></h4>
                                    <p><?php echo htmlspecialchars($row['specialization']); ?></p>
                                    <p><strong>Visit:</strong> <?php echo date("d M Y h:i A", strtotime($row['appointment_date'])); ?></p>
                                    <p class="appointment-created-meta">
                                        <i class="fas fa-plus-circle" aria-hidden="true"></i>
                                        <span>Appointment created: <?php echo htmlspecialchars(patient_dashboard_format_created_at($row['created_at'] ?? null)); ?></span>
                                    </p>
                                </div>
                                <span class="patient-status-badge patient-status-<?php echo strtolower($row['status']); ?>">
                                    <?php echo htmlspecialchars(ucfirst($row['status'])); ?>
                                </span>
                            </div>
                        <?php } ?>
                        
                        <?php if ($appointments_result->num_rows == 0): ?>
                            <div class="patient-appointment-item">
                                <div class="patient-appointment-info">
                                    <h4>No upcoming appointments</h4>
                                    <p>Book your next appointment to get started</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Health Tips -->
                <div class="patient-card health-tips-card">
                    <div class="patient-card-header">
                        <h3 class="patient-card-title">
                            <i class="fas fa-lightbulb"></i>
                            Health Tips
                        </h3>
                    </div>
                    <div class="health-tips-content">
                        <div class="health-tip">
                            <i class="fas fa-heart"></i>
                            <div>
                                <h4>Stay Hydrated</h4>
                                <p>Drink at least 8 glasses of water daily for optimal health</p>
                            </div>
                        </div>
                        <div class="health-tip">
                            <i class="fas fa-running"></i>
                            <div>
                                <h4>Regular Exercise</h4>
                                <p>30 minutes of moderate exercise daily improves overall wellness</p>
                            </div>
                        </div>
                        <div class="health-tip">
                            <i class="fas fa-moon"></i>
                            <div>
                                <h4>Quality Sleep</h4>
                                <p>7-9 hours of sleep helps your body recover and stay healthy</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="notifReadModal" class="hb-notif-modal" role="dialog" aria-modal="true" aria-labelledby="hbNotifModalTitle" aria-hidden="true" style="display:none;">
        <div class="hb-notif-modal-backdrop" data-close-notif-modal="1"></div>
        <div class="hb-notif-modal-dialog">
            <button type="button" class="hb-notif-modal-close" data-close-notif-modal="1" aria-label="Close">&times;</button>
            <div class="hb-notif-modal-head">
                <span class="hb-notif-modal-badge"><i class="fas fa-bell"></i> Reminder</span>
                <h3 id="hbNotifModalTitle" class="hb-notif-modal-title">Notification details</h3>
            </div>
            <p id="hbNotifModalMessage" class="hb-notif-modal-message">—</p>
            <p id="hbNotifModalTime" class="hb-notif-modal-time">—</p>
            <div class="hb-notif-modal-actions">
                <button type="button" data-close-notif-modal="1" class="hb-notif-btn hb-notif-btn-secondary">Close</button>
            </div>
        </div>
    </div>

    <style>
        .patient-header-right {
            margin-left: auto;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 12px;
        }
        .hb-pd-notif-wrap {
            position: relative;
            margin-left: 0;
        }
        .hb-pd-notif-bell {
            position: relative;
            width: 60px;
            height: 60px;
            border-radius: 12px;
            border: 1px solid #dbeafe;
            background: #ffffff;
            color: #2563eb;
            cursor: pointer;
        }
        .hb-pd-notif-bell i {
            font-size: 24px;
        }
        .hb-pd-notif-badge {
            position: absolute;
            top: -6px;
            right: -6px;
            min-width: 18px;
            height: 18px;
            padding: 0 5px;
            border-radius: 999px;
            background: #ef4444;
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .hb-pd-notif-dropdown {
            position: absolute;
            right: 0;
            top: calc(100% + 10px);
            width: min(420px, calc(100vw - 32px));
            max-height: 420px;
            overflow: hidden;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 16px 36px rgba(2, 6, 23, 0.25);
            z-index: 11000;
        }
        .hb-pd-notif-dropdown-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 14px;
            border-bottom: 1px solid #eef2f7;
            background: #f8fafc;
        }
        .hb-pd-notif-list {
            max-height: 360px;
            overflow: auto;
        }
        .hb-pd-notif-item {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            padding: 10px 12px;
            border-bottom: 1px solid #f1f5f9;
        }
        .hb-pd-notif-item:last-child {
            border-bottom: none;
        }
        .hb-pd-notif-item-message {
            font-size: 13px;
            color: #1e293b;
            line-height: 1.45;
            margin-bottom: 4px;
        }
        .hb-pd-notif-item-body small {
            color: #64748b;
            font-size: 11px;
        }
        .hb-pd-notif-empty {
            padding: 16px;
            color: #64748b;
            font-size: 13px;
            text-align: center;
        }
        .hb-notif-modal {
            position: fixed;
            inset: 0;
            z-index: 12000;
        }
        .hb-notif-modal-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, 0.55);
        }
        .hb-notif-modal-dialog {
            position: relative;
            width: min(520px, calc(100vw - 32px));
            margin: 12vh auto 0;
            background: #fff;
            border-radius: 18px;
            border: 1px solid #dbeafe;
            box-shadow: 0 28px 64px rgba(2, 6, 23, 0.35);
            padding: 20px;
            overflow: hidden;
        }
        .hb-notif-modal-dialog::before {
            content: "";
            position: absolute;
            inset: 0 0 auto 0;
            height: 4px;
            background: linear-gradient(90deg, #0ea5e9 0%, #3b82f6 55%, #10b981 100%);
        }
        .hb-notif-modal-head {
            margin: 8px 0 10px;
        }
        .hb-notif-modal-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            background: #eff6ff;
            color: #1d4ed8;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .hb-notif-modal-title {
            margin: 10px 0 0;
            color: #0f172a;
            font-size: 1.15rem;
        }
        .hb-notif-modal-message {
            margin: 0 0 12px;
            color: #1e293b;
            line-height: 1.6;
            font-size: 15px;
            font-weight: 500;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px;
        }
        .hb-notif-modal-time {
            margin: 0 0 16px;
            font-size: 12px;
            color: #64748b;
        }
        .hb-notif-modal-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        .hb-notif-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            height: 36px;
            min-width: 150px;
            padding: 0 16px;
            border-radius: 10px;
            border: 1px solid transparent;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .hb-notif-btn-primary {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            border-color: #2563eb;
            color: #fff;
        }
        .hb-notif-btn-primary:hover {
            filter: brightness(1.05);
            color: #fff;
        }
        .hb-notif-btn-secondary {
            background: #f8fafc;
            border-color: #cbd5e1;
            color: #334155;
        }
        .hb-notif-btn-secondary:hover {
            background: #eef2f7;
        }
        .hb-notif-modal-close {
            position: absolute;
            top: 12px;
            right: 12px;
            border: none;
            background: #f1f5f9;
            color: #64748b;
            width: 34px;
            height: 34px;
            border-radius: 9px;
            font-size: 22px;
            line-height: 1;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .hb-notif-modal-close:hover {
            background: #e2e8f0;
            color: #0f172a;
        }
    </style>

    <script src="js/patient_sidebar.js"></script>
    <script>
        (function setupNotifBellDropdown() {
            const bell = document.getElementById('hbNotifBellBtn');
            const dropdown = document.getElementById('hbNotifBellDropdown');
            if (!bell || !dropdown) return;

            function setOpen(isOpen) {
                dropdown.style.display = isOpen ? 'block' : 'none';
                bell.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            }

            setOpen(false);
            bell.addEventListener('click', function (e) {
                e.stopPropagation();
                const isOpen = bell.getAttribute('aria-expanded') === 'true';
                setOpen(!isOpen);
            });

            document.addEventListener('click', function (e) {
                if (!dropdown.contains(e.target) && !bell.contains(e.target)) {
                    setOpen(false);
                }
            });
        })();

        (function setupPatientNotifReadModal() {
            const modal = document.getElementById('notifReadModal');
            const messageEl = document.getElementById('hbNotifModalMessage');
            const timeEl = document.getElementById('hbNotifModalTime');
            if (!modal || !messageEl || !timeEl) return;

            function closeModal() {
                modal.style.display = 'none';
                modal.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
            }

            function openModal(message, created) {
                messageEl.textContent = message || 'Notification';
                timeEl.textContent = created ? ('Received: ' + created) : '';
                modal.style.display = 'block';
                modal.setAttribute('aria-hidden', 'false');
                document.body.style.overflow = 'hidden';
            }

            document.querySelectorAll('.js-open-notif-modal').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    openModal(
                        btn.getAttribute('data-notif-message') || '',
                        btn.getAttribute('data-notif-created') || ''
                    );
                });
            });

            modal.querySelectorAll('[data-close-notif-modal="1"]').forEach(function (el) {
                el.addEventListener('click', closeModal);
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && modal.getAttribute('aria-hidden') === 'false') closeModal();
            });
        })();
    </script>
</body>
</html>