<?php
/**
 * Patient calendar: month view with appointment markers and upcoming reminders.
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../appointments/appointment_schema_flags.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

$user_id = (int) $_SESSION['user_id'];

$query = $conn->prepare('SELECT username, email, role FROM users WHERE id = ?');
$query->bind_param('i', $user_id);
$query->execute();
$user = $query->get_result()->fetch_assoc();
if (!$user) {
    header('Location: ../auth/login.php');
    exit();
}

$sidebar_user_data = [
    'username' => htmlspecialchars($user['username']),
    'email' => htmlspecialchars($user['email']),
    'role' => htmlspecialchars($user['role']),
];

$patient_query = $conn->prepare('SELECT id FROM patients WHERE user_id = ?');
$patient_query->bind_param('i', $user_id);
$patient_query->execute();
$patient_row = $patient_query->get_result()->fetch_assoc();
$patient_id = $patient_row ? (int) $patient_row['id'] : null;

if (!$patient_id) {
    header('Location: create_patient_record.php');
    exit();
}

$month = isset($_GET['month']) ? (int) $_GET['month'] : (int) date('n');
$year = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');
if ($month < 1 || $month > 12) {
    $month = (int) date('n');
}
if ($year < 2000 || $year > 2100) {
    $year = (int) date('Y');
}

$first_of_month = sprintf('%04d-%02d-01', $year, $month);
$dt = new DateTime($first_of_month);
$days_in_month = (int) $dt->format('t');
$start_weekday = (int) $dt->format('w');

$range_start = (clone $dt)->modify('-7 days')->format('Y-m-d 00:00:00');
$range_end = (clone $dt)->modify('last day of this month')->modify('+7 days')->format('Y-m-d 23:59:59');

$col_flags = hb_appointments_column_flags($conn);
$appt_extra_cols = [];
if ($col_flags['guest']) {
    $appt_extra_cols[] = 'a.guest_first_name';
    $appt_extra_cols[] = 'a.guest_last_name';
}
if ($col_flags['notes']) {
    $appt_extra_cols[] = 'a.notes';
}
$appt_extra_sql = $appt_extra_cols !== [] ? implode(', ', $appt_extra_cols) . ', ' : '';

$appt_sql = "
    SELECT a.id, a.appointment_date, a.status,
           COALESCE(a.created_at, a.updated_at) AS appt_created_at,
           {$appt_extra_sql}
           CONCAT(TRIM(u.first_name), ' ', TRIM(u.last_name)) AS doctor_name, u.specialization,
           TRIM(p.first_name) AS patient_first_name, TRIM(p.last_name) AS patient_last_name,
           CONCAT(TRIM(pu.first_name), ' ', TRIM(pu.last_name)) AS booker_display_name,
           pu.username AS booker_username
    FROM appointments a
    JOIN users u ON a.doctor_id = u.id
    JOIN patients p ON a.patient_id = p.id
    JOIN users pu ON p.user_id = pu.id
    WHERE a.patient_id = ?
      AND a.appointment_date >= ?
      AND a.appointment_date <= ?
    ORDER BY a.appointment_date ASC
";
$appt_stmt = $conn->prepare($appt_sql);
$appt_stmt->bind_param('iss', $patient_id, $range_start, $range_end);
$appt_stmt->execute();
$appt_rows = $appt_stmt->get_result();

$by_day = [];
$cal_appts_js = [];
while ($row = $appt_rows->fetch_assoc()) {
    $day_key = date('Y-m-d', strtotime($row['appointment_date']));
    if (!isset($by_day[$day_key])) {
        $by_day[$day_key] = [];
    }
    $by_day[$day_key][] = $row;
    $aid = (int) $row['id'];
    $ts = strtotime($row['appointment_date']);
    $visit_row = [
        'guest_first_name' => $row['guest_first_name'] ?? '',
        'guest_last_name' => $row['guest_last_name'] ?? '',
        'appointment_notes' => $col_flags['notes'] ? ($row['notes'] ?? null) : null,
        'first_name' => $row['patient_first_name'] ?? '',
        'last_name' => $row['patient_last_name'] ?? '',
    ];
    $visit_patient = hb_appointments_display_patient_name($visit_row);
    $booker = trim((string) ($row['booker_display_name'] ?? ''));
    $buser = trim((string) ($row['booker_username'] ?? ''));
    $booked_by_label = $booker !== '' ? ($buser !== '' ? $booker . ' (' . $buser . ')' : $booker) : '—';

    $cal_appts_js[$aid] = [
        'id' => $aid,
        'appointment_date' => $row['appointment_date'],
        'status' => $row['status'],
        'status_key' => hb_cal_status_key((string) ($row['status'] ?? '')),
        'doctor_name' => $row['doctor_name'],
        'specialization' => $row['specialization'],
        'date_label' => date('l, F j, Y', $ts),
        'time_label' => date('g:i A', $ts),
        'created_label' => hb_cal_format_created_at($row['appt_created_at'] ?? null),
        'booked_by' => $booked_by_label,
        'visit_patient' => $visit_patient !== '' ? $visit_patient : '—',
    ];
}

$cal_by_day_ids = [];
foreach ($by_day as $dk => $rows) {
    $cal_by_day_ids[$dk] = array_map(static function ($r) {
        return (int) $r['id'];
    }, $rows);
}

$reminder_stmt = $conn->prepare("
    SELECT a.id, a.appointment_date, a.status,
           CONCAT(u.first_name, ' ', u.last_name) AS doctor_name, u.specialization
    FROM appointments a
    JOIN users u ON a.doctor_id = u.id
    WHERE a.patient_id = ?
      AND a.appointment_date >= NOW()
      AND a.appointment_date <= DATE_ADD(NOW(), INTERVAL 7 DAY)
      AND LOWER(a.status) IN ('pending', 'confirmed')
    ORDER BY a.appointment_date ASC
");
$reminder_stmt->bind_param('i', $patient_id);
$reminder_stmt->execute();
$reminders = $reminder_stmt->get_result();

$prev = (clone $dt)->modify('-1 month');
$next = (clone $dt)->modify('+1 month');

$month_names = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
];
$title_month = $month_names[$month] . ' ' . $year;

/** @param string|null $raw DB datetime */
function hb_cal_format_created_at(?string $raw): string
{
    if ($raw === null || $raw === '') {
        return '—';
    }
    $ts = strtotime($raw);

    return $ts ? date('M j, Y \a\t g:i A', $ts) : '—';
}

/** Calendar cell: time + doctor name (HTML button). */
function hb_cal_appt_pill_markup(array $ap): string
{
    $eid = (int) $ap['id'];
    $t = date('g:i A', strtotime($ap['appointment_date']));
    $name = htmlspecialchars($ap['doctor_name'], ENT_QUOTES, 'UTF-8');
    $tl = htmlspecialchars($ap['doctor_name'] . ' — ' . $t, ENT_QUOTES, 'UTF-8');
    $timeHtml = htmlspecialchars($t, ENT_QUOTES, 'UTF-8');
    $statusKey = hb_cal_status_key((string) ($ap['status'] ?? ''));

    return '<button type="button" class="cal-appt-pill cal-appt-pill--' . htmlspecialchars($statusKey, ENT_QUOTES, 'UTF-8') . ' js-cal-appt-pill" data-appt-id="' . $eid . '" title="' . $tl . '">'
        . '<span class="cal-appt-pill-time">' . $timeHtml . '</span>'
        . '<span class="cal-appt-pill-name">' . $name . '</span>'
        . '</button>';
}

/** Map raw appointment status to a normalized key used by UI styles. */
function hb_cal_status_key(string $status): string
{
    $s = strtolower(trim($status));
    if ($s === 'confirmed') return 'confirmed';
    if ($s === 'completed') return 'completed';
    if ($s === 'declined') return 'declined';
    if ($s === 'cancelled' || $s === 'canceled') return 'cancelled';
    return 'pending';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Calendar · Appointment Reminders - HealthBase</title>
    <link rel="icon" type="image/x-icon" href="/assets/icons/favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="css/patient_dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .cal-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 300px;
            gap: 28px;
            align-items: start;
        }
        @media (min-width: 1200px) {
            .cal-layout {
                grid-template-columns: minmax(0, 1fr) 280px;
                gap: 32px;
            }
        }
        @media (max-width: 1024px) {
            .cal-layout { grid-template-columns: 1fr; }
        }
        .cal-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }
        .cal-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 24px;
            border-bottom: 1px solid #e2e8f0;
            flex-wrap: wrap;
            gap: 12px;
        }
        .cal-toolbar h2 {
            margin: 0;
            font-size: 1.25rem;
            color: #0f172a;
            font-family: 'Inter', sans-serif;
        }
        .cal-nav {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .cal-nav a {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 8px;
            background: #f1f5f9;
            color: #334155;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: background 0.15s;
        }
        .cal-nav a:hover { background: #e2e8f0; }
        .cal-grid-wrap { padding: 18px 22px 28px; }
        .cal-weekdays {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 6px;
            margin-bottom: 10px;
            text-align: center;
            font-size: 13px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .cal-days {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 8px;
        }
        .cal-cell {
            min-height: 122px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 10px 9px;
            background: #fafafa;
            font-size: 14px;
            color: #334155;
        }
        .cal-cell.muted {
            opacity: 0.45;
            background: #f8fafc;
        }
        .cal-cell.today {
            border-color: #0ea5e9;
            box-shadow: 0 0 0 1px rgba(14, 165, 233, 0.25);
            background: #f0f9ff;
        }
        .cal-cell.has-appt {
            background: #eff6ff;
            border-color: #93c5fd;
        }
        .cal-day-num {
            font-weight: 700;
            font-size: 15px;
            color: #0f172a;
            margin-bottom: 8px;
        }
        .cal-appt-pill {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 3px;
            font-size: 11px;
            line-height: 1.25;
            padding: 7px 8px;
            border-radius: 6px;
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: white;
            margin-top: 6px;
            text-align: left;
            box-shadow: 0 1px 3px rgba(37, 99, 235, 0.35);
        }
        .cal-appt-pill--pending {
            background: linear-gradient(135deg, #ca8a04 0%, #a16207 100%);
            box-shadow: 0 1px 3px rgba(161, 98, 7, 0.35);
        }
        .cal-appt-pill--confirmed {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            box-shadow: 0 1px 3px rgba(37, 99, 235, 0.35);
        }
        .cal-appt-pill--completed {
            background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
            box-shadow: 0 1px 3px rgba(21, 128, 61, 0.35);
        }
        .cal-appt-pill--declined,
        .cal-appt-pill--cancelled {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            box-shadow: 0 1px 3px rgba(220, 38, 38, 0.35);
        }
        .cal-appt-pill-time {
            font-weight: 700;
            font-size: 12px;
            letter-spacing: 0.02em;
        }
        .cal-appt-pill-name {
            font-size: 10px;
            font-weight: 500;
            opacity: 0.96;
            width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            line-height: 1.2;
        }
        .cal-appt-more {
            font-size: 11px;
            color: #1d4ed8;
            margin-top: 6px;
            font-weight: 700;
        }
        .reminder-panel {
            padding: 20px;
        }
        .reminder-panel h3 {
            margin: 0 0 16px;
            font-size: 1rem;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .reminder-item {
            padding: 12px;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            margin-bottom: 10px;
            background: #fffbeb;
            border-color: #fcd34d;
        }
        .reminder-item strong { display: block; color: #0f172a; font-size: 14px; }
        .reminder-item span { font-size: 12px; color: #64748b; }
        .reminder-empty {
            color: #64748b;
            font-size: 14px;
            padding: 12px;
            background: #f8fafc;
            border-radius: 10px;
            border: 1px dashed #cbd5e1;
        }
        .cal-legend {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            padding: 0 20px 16px;
            font-size: 12px;
            color: #64748b;
        }
        .cal-legend span { display: inline-flex; align-items: center; gap: 6px; }
        .dot { width: 8px; height: 8px; border-radius: 50%; background: #3b82f6; }
        button.cal-appt-pill {
            font-family: inherit;
            cursor: pointer;
            border: none;
            width: 100%;
            text-align: left;
        }
        button.cal-appt-pill:hover {
            filter: brightness(1.08);
        }
        button.cal-appt-more {
            font-family: inherit;
            cursor: pointer;
            background: transparent;
            border: none;
            padding: 0;
            width: 100%;
            text-align: left;
        }
        button.cal-appt-more:hover {
            text-decoration: underline;
        }
        .cal-appt-modal-overlay {
            position: fixed;
            inset: 0;
            z-index: 10050;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 16px;
            box-sizing: border-box;
        }
        .cal-appt-modal-overlay.is-open { display: flex; }
        .cal-appt-modal-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, 0.55);
        }
        .cal-appt-modal-dialog {
            position: relative;
            background: #fff;
            border-radius: 18px;
            max-width: 520px;
            width: 100%;
            padding: 28px 28px 22px;
            box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.35);
            border: 1px solid #e2e8f0;
        }
        .cal-appt-modal-dialog h3 {
            margin: 0 0 18px;
            padding-right: 44px;
            font-size: 1.35rem;
            font-weight: 600;
            color: #0f172a;
            line-height: 1.3;
        }
        .cal-appt-modal-close {
            position: absolute;
            top: 14px;
            right: 16px;
            width: 40px;
            height: 40px;
            border: none;
            border-radius: 8px;
            background: #f1f5f9;
            color: #64748b;
            font-size: 1.35rem;
            cursor: pointer;
            line-height: 1;
        }
        .cal-appt-modal-close:hover { background: #e2e8f0; color: #0f172a; }
        .cal-appt-modal-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            padding: 11px 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 15px;
            line-height: 1.45;
        }
        .cal-appt-modal-row:last-child { border-bottom: none; }
        .cal-appt-modal-k {
            color: #64748b;
            flex-shrink: 0;
            max-width: 46%;
            font-size: 14px;
        }
        .cal-appt-modal-v {
            color: #0f172a;
            font-weight: 600;
            text-align: right;
            flex: 1;
            min-width: 0;
            word-break: break-word;
        }
        .cal-appt-modal-list-item {
            padding: 14px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            margin-bottom: 12px;
            cursor: pointer;
            text-align: left;
            width: 100%;
            background: #f8fafc;
            font-family: inherit;
            font-size: 15px;
        }
        .cal-appt-modal-list-item:hover { background: #eff6ff; border-color: #93c5fd; }
        .cal-appt-modal-list-item strong { display: block; color: #0f172a; font-size: 15px; }
        .cal-appt-modal-list-item span { font-size: 13px; color: #64748b; }
        .reminder-item.js-cal-appt-trigger {
            cursor: pointer;
            transition: background 0.15s, border-color 0.15s;
        }
        .reminder-item.js-cal-appt-trigger:hover {
            background: #fff;
            border-color: #93c5fd;
        }
    </style>
</head>
<body class="patient-dashboard-page">
    <?php include 'includes/patient_sidebar.php'; ?>

    <div class="patient-main-content">
        <header class="patient-header">
            <div class="patient-header-left">
                <h1 class="patient-welcome">Calendar · Appointment Reminders</h1>
                <p class="patient-subtitle">See visits on the calendar and what is coming up in the next 7 days</p>
            </div>
            <div class="patient-header-right">
                <a href="/appointments/scheduling.php" class="btn-primary">
                    <i class="fas fa-calendar-plus"></i>
                    Book appointment
                </a>
            </div>
        </header>

        <div class="patient-dashboard-content">
            <div class="cal-layout">
                <div class="cal-card">
                    <div class="cal-toolbar">
                        <h2><i class="fas fa-calendar-alt" style="color:#0ea5e9;margin-right:8px;"></i><?php echo htmlspecialchars($title_month); ?></h2>
                        <div class="cal-nav">
                            <a href="?month=<?php echo (int) $prev->format('n'); ?>&year=<?php echo (int) $prev->format('Y'); ?>">
                                <i class="fas fa-chevron-left"></i> Prev
                            </a>
                            <a href="patient_appointment_calendar.php">Today</a>
                            <a href="?month=<?php echo (int) $next->format('n'); ?>&year=<?php echo (int) $next->format('Y'); ?>">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        </div>
                    </div>
                    <div class="cal-legend">
                        <span><span class="dot"></span> Day with at least one appointment</span>
                        <span><i class="fas fa-sun" style="color:#0ea5e9;"></i> Today</span>
                    </div>
                    <div class="cal-grid-wrap">
                        <div class="cal-weekdays">
                            <div>Sun</div><div>Mon</div><div>Tue</div><div>Wed</div><div>Thu</div><div>Fri</div><div>Sat</div>
                        </div>
                        <div class="cal-days">
                            <?php
                            $today_str = date('Y-m-d');
                            $lead = $start_weekday;
                            for ($i = 0; $i < $lead; $i++) {
                                $pad = (clone $dt)->modify('-' . ($lead - $i) . ' days');
                                $pk = $pad->format('Y-m-d');
                                $extra = isset($by_day[$pk]) ? $by_day[$pk] : [];
                                echo '<div class="cal-cell muted' . (!empty($extra) ? ' has-appt' : '') . '">';
                                echo '<div class="cal-day-num">' . (int) $pad->format('j') . '</div>';
                                if (!empty($extra)) {
                                    foreach (array_slice($extra, 0, 2) as $ex) {
                                        echo hb_cal_appt_pill_markup($ex);
                                    }
                                    if (count($extra) > 2) {
                                        echo '<button type="button" class="cal-appt-more js-cal-day-more" data-day="' . htmlspecialchars($pk, ENT_QUOTES, 'UTF-8') . '">+' . (count($extra) - 2) . ' more</button>';
                                    }
                                }
                                echo '</div>';
                            }
                            for ($d = 1; $d <= $days_in_month; $d++) {
                                $pk = sprintf('%04d-%02d-%02d', $year, $month, $d);
                                $list = $by_day[$pk] ?? [];
                                $is_today = ($pk === $today_str);
                                $cls = 'cal-cell';
                                if ($is_today) {
                                    $cls .= ' today';
                                }
                                if (!empty($list)) {
                                    $cls .= ' has-appt';
                                }
                                echo '<div class="' . $cls . '">';
                                echo '<div class="cal-day-num">' . $d . '</div>';
                                if (!empty($list)) {
                                    foreach (array_slice($list, 0, 2) as $ap) {
                                        echo hb_cal_appt_pill_markup($ap);
                                    }
                                    if (count($list) > 2) {
                                        echo '<button type="button" class="cal-appt-more js-cal-day-more" data-day="' . htmlspecialchars($pk, ENT_QUOTES, 'UTF-8') . '">+' . (count($list) - 2) . ' more</button>';
                                    }
                                }
                                echo '</div>';
                            }
                            $total_cells = $lead + $days_in_month;
                            $tail = (7 - ($total_cells % 7)) % 7;
                            for ($i = 1; $i <= $tail; $i++) {
                                $pad = (clone $dt)->modify('last day of this month')->modify("+$i days");
                                $pk = $pad->format('Y-m-d');
                                $extra = $by_day[$pk] ?? [];
                                echo '<div class="cal-cell muted' . (!empty($extra) ? ' has-appt' : '') . '">';
                                echo '<div class="cal-day-num">' . (int) $pad->format('j') . '</div>';
                                if (!empty($extra)) {
                                    foreach (array_slice($extra, 0, 2) as $ex) {
                                        echo hb_cal_appt_pill_markup($ex);
                                    }
                                    if (count($extra) > 2) {
                                        echo '<button type="button" class="cal-appt-more js-cal-day-more" data-day="' . htmlspecialchars($pk, ENT_QUOTES, 'UTF-8') . '">+' . (count($extra) - 2) . ' more</button>';
                                    }
                                }
                                echo '</div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <div class="cal-card">
                    <div class="reminder-panel">
                        <h3><i class="fas fa-bell" style="color:#d97706;"></i> Next 7 days</h3>
                        <?php if ($reminders->num_rows === 0): ?>
                            <div class="reminder-empty">
                                No pending or confirmed appointments in the next week. Book one anytime.
                            </div>
                        <?php else: ?>
                            <?php while ($r = $reminders->fetch_assoc()): ?>
                                <div class="reminder-item js-cal-appt-trigger" data-appt-id="<?php echo (int) $r['id']; ?>" role="button" tabindex="0">
                                    <strong><?php echo htmlspecialchars($r['doctor_name']); ?></strong>
                                    <span><?php echo htmlspecialchars($r['specialization']); ?></span><br>
                                    <span>
                                        <i class="fas fa-clock"></i>
                                        <?php echo date('D, M j · g:i A', strtotime($r['appointment_date'])); ?>
                                        · <em><?php echo htmlspecialchars(ucfirst(strtolower($r['status']))); ?></em>
                                    </span>
                                </div>
                            <?php endwhile; ?>
                        <?php endif; ?>
                        <p style="margin:16px 0 0;font-size:12px;color:#94a3b8;">
                            Reminders include visits that are still pending or confirmed. Reschedule or cancel from My Appointments.
                        </p>
                        <a href="patient_appointments.php" class="btn-secondary" style="margin-top:14px;display:inline-block;text-decoration:none;">
                            <i class="fas fa-list"></i> My Appointments
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="calApptModal" class="cal-appt-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="calApptModalTitle" aria-hidden="true">
        <div class="cal-appt-modal-backdrop" id="calApptModalBackdrop"></div>
        <div class="cal-appt-modal-dialog">
            <button type="button" class="cal-appt-modal-close" id="calApptModalClose" aria-label="Close">&times;</button>
            <h3 id="calApptModalTitle">Appointment</h3>
            <div id="calApptModalBody"></div>
        </div>
    </div>

    <script src="js/patient_sidebar.js"></script>
    <script>
    (function () {
        var CAL = <?php echo json_encode(['appointments' => $cal_appts_js, 'byDay' => $cal_by_day_ids], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;

        function esc(s) {
            if (s == null) return '';
            var d = document.createElement('div');
            d.textContent = s;
            return d.innerHTML;
        }

        function statusLabel(st) {
            if (!st) return '—';
            return st.charAt(0).toUpperCase() + String(st).slice(1).toLowerCase();
        }

        function renderDetail(a) {
            if (!a) return '<p style="color:#64748b;">Appointment not found.</p>';
            var created = esc(a.created_label != null ? a.created_label : '—');
            var bookedBy = esc(a.booked_by != null ? a.booked_by : '—');
            var visitFor = esc(a.visit_patient != null ? a.visit_patient : '—');
            return (
                '<div class="cal-appt-modal-row"><span class="cal-appt-modal-k">Reference</span><span class="cal-appt-modal-v">#' + esc(a.id) + '</span></div>' +
                '<div class="cal-appt-modal-row"><span class="cal-appt-modal-k">Visit date</span><span class="cal-appt-modal-v">' + esc(a.date_label) + '</span></div>' +
                '<div class="cal-appt-modal-row"><span class="cal-appt-modal-k">Time</span><span class="cal-appt-modal-v">' + esc(a.time_label) + '</span></div>' +
                '<div class="cal-appt-modal-row"><span class="cal-appt-modal-k">Patient to be seen</span><span class="cal-appt-modal-v">' + visitFor + '</span></div>' +
                '<div class="cal-appt-modal-row"><span class="cal-appt-modal-k">Appointment created</span><span class="cal-appt-modal-v">' + created + '</span></div>' +
                '<div class="cal-appt-modal-row"><span class="cal-appt-modal-k">Booked by</span><span class="cal-appt-modal-v">' + bookedBy + '</span></div>' +
                '<div class="cal-appt-modal-row"><span class="cal-appt-modal-k">Doctor</span><span class="cal-appt-modal-v">' + esc(a.doctor_name) + '</span></div>' +
                '<div class="cal-appt-modal-row"><span class="cal-appt-modal-k">Specialty</span><span class="cal-appt-modal-v">' + esc(a.specialization) + '</span></div>' +
                '<div class="cal-appt-modal-row"><span class="cal-appt-modal-k">Status</span><span class="cal-appt-modal-v">' + esc(statusLabel(a.status)) + '</span></div>'
            );
        }

        function renderDayList(dayKey) {
            var ids = CAL.byDay[dayKey] || [];
            if (!ids.length) return '<p style="color:#64748b;">No appointments.</p>';
            var html = '<p style="margin:0 0 12px;font-size:13px;color:#64748b;">Select an appointment:</p>';
            ids.forEach(function (id) {
                var a = CAL.appointments[String(id)] || CAL.appointments[id];
                if (!a) return;
                html += '<button type="button" class="cal-appt-modal-list-item js-cal-appt-pick" data-appt-id="' + id + '">' +
                    '<strong>' + esc(a.time_label) + ' · ' + esc(a.doctor_name) + '</strong>' +
                    '<span>' + esc(a.specialization) + ' · ' + esc(statusLabel(a.status)) + '</span></button>';
            });
            return html;
        }

        var modal = document.getElementById('calApptModal');
        var modalBody = document.getElementById('calApptModalBody');
        var modalTitle = document.getElementById('calApptModalTitle');

        function openModal() {
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
        }

        function showAppointment(id) {
            var a = CAL.appointments[String(id)] || CAL.appointments[id];
            modalTitle.textContent = 'Appointment details';
            modalBody.innerHTML = renderDetail(a);
            openModal();
        }

        function formatDayHeading(dayKey) {
            var d = new Date(dayKey + 'T12:00:00');
            if (isNaN(d.getTime())) return 'Appointments';
            return 'Appointments · ' + d.toLocaleDateString(undefined, { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        }

        function showDayPicker(dayKey) {
            modalTitle.textContent = formatDayHeading(dayKey);
            modalBody.innerHTML = renderDayList(dayKey);
            openModal();
            modalBody.querySelectorAll('.js-cal-appt-pick').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var aid = parseInt(btn.getAttribute('data-appt-id'), 10);
                    showAppointment(aid);
                });
            });
        }

        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.js-cal-appt-pill').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    var id = parseInt(btn.getAttribute('data-appt-id'), 10);
                    showAppointment(id);
                });
            });
            document.querySelectorAll('.js-cal-day-more').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    var day = btn.getAttribute('data-day');
                    if (day) showDayPicker(day);
                });
            });
            document.querySelectorAll('.js-cal-appt-trigger').forEach(function (el) {
                function go() {
                    var id = parseInt(el.getAttribute('data-appt-id'), 10);
                    showAppointment(id);
                }
                el.addEventListener('click', go);
                el.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        go();
                    }
                });
            });
            document.getElementById('calApptModalClose').addEventListener('click', closeModal);
            document.getElementById('calApptModalBackdrop').addEventListener('click', closeModal);
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
            });
        });
    })();
    </script>
</body>
</html>
