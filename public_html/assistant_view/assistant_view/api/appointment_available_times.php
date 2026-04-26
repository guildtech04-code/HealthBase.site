<?php
/**
 * JSON: available appointment start times for a doctor on a date
 * Based on clinic + teleconsultation rows in doctor_schedules (or legacy doctor_availability).
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/db_connect.php';

$role = $_SESSION['role'] ?? '';
if (!isset($_SESSION['user_id']) || !in_array($role, ['assistant', 'admin', 'doctor'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}

$doctor_id = (int) ($_GET['doctor_id'] ?? 0);
$date = $_GET['date'] ?? '';
$exclude_appt_id = (int) ($_GET['exclude_appointment_id'] ?? 0);

if ($doctor_id < 1 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['ok' => true, 'times' => [], 'message' => 'Select a doctor and date.']);
    exit;
}

if ($role === 'doctor' && $doctor_id !== (int) $_SESSION['user_id']) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden', 'times' => []]);
    exit;
}

/** @var mysqli $conn */

function hb_table_exists(mysqli $conn, string $name): bool
{
    $safe = $conn->real_escape_string($name);
    $r = $conn->query("SHOW TABLES LIKE '$safe'");
    return $r && $r->num_rows > 0;
}

function hb_column_exists(mysqli $conn, string $table, string $col): bool
{
    $dbRes = $conn->query('SELECT DATABASE()');
    if (!$dbRes) {
        return false;
    }
    $db = $conn->real_escape_string((string) $dbRes->fetch_row()[0]);
    $t = $conn->real_escape_string($table);
    $c = $conn->real_escape_string($col);
    $r = $conn->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$db' AND TABLE_NAME='$t' AND COLUMN_NAME='$c' LIMIT 1");
    return $r && $r->num_rows > 0;
}

/**
 * @return list<string> HH:MM slots, [start, end) in steps of $stepMinutes
 */
function hb_slots_between(string $start, string $end, int $stepMinutes = 60): array
{
    $out = [];
    $base = '1970-01-01 ';
    $t = strtotime($base . $start);
    $endTs = strtotime($base . $end);
    if ($t === false || $endTs === false || $t >= $endTs) {
        return $out;
    }
    $step = $stepMinutes * 60;
    while ($t < $endTs) {
        $out[] = date('H:i', $t);
        $t += $step;
    }
    return $out;
}

// Clinic holiday → no slots
if (hb_table_exists($conn, 'clinic_holidays')) {
    $h = $conn->prepare('SELECT 1 FROM clinic_holidays WHERE `date` = ? LIMIT 1');
    $h->bind_param('s', $date);
    $h->execute();
    if ($h->get_result()->num_rows > 0) {
        echo json_encode(['ok' => true, 'times' => [], 'message' => 'Clinic is closed on this date.']);
        exit;
    }
}

$dow = (int) date('w', strtotime($date));
$windows = [];

// Provider overrides for this calendar day
if (hb_table_exists($conn, 'provider_overrides')) {
    $ov = $conn->prepare('SELECT is_available, start_time, end_time FROM provider_overrides WHERE doctor_id = ? AND `date` = ?');
    $ov->bind_param('is', $doctor_id, $date);
    $ov->execute();
    $or = $ov->get_result();
    $override_windows = [];
    $full_block = false;
    while ($row = $or->fetch_assoc()) {
        $ia = (int) ($row['is_available'] ?? 0);
        $st = $row['start_time'];
        $en = $row['end_time'];
        if ($ia === 0 && $st === null && $en === null) {
            $full_block = true;
            break;
        }
        if ($ia === 1 && $st !== null && $en !== null) {
            $override_windows[] = [$st, $en];
        }
    }
    if ($full_block) {
        echo json_encode(['ok' => true, 'times' => [], 'message' => 'Doctor is unavailable this day.']);
        exit;
    }
    if (count($override_windows) > 0) {
        $windows = $override_windows;
    }
}

if (count($windows) === 0 && hb_table_exists($conn, 'doctor_schedules')) {
    $has_eff = hb_column_exists($conn, 'doctor_schedules', 'effective_from');
    $has_st = hb_column_exists($conn, 'doctor_schedules', 'schedule_type');
    $sql = 'SELECT start_time, end_time FROM doctor_schedules WHERE doctor_id = ? AND day_of_week = ? AND is_available = 1';
    if ($has_st) {
        $sql .= " AND schedule_type IN ('clinic','teleconsultation')";
    }
    if ($has_eff) {
        $sql .= ' AND (effective_from IS NULL OR effective_from <= ?) AND (effective_to IS NULL OR effective_to >= ?)';
    }
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        // Table or query mismatch — try legacy
        $windows = [];
    } else {
        if ($has_eff) {
            $stmt->bind_param('iiss', $doctor_id, $dow, $date, $date);
        } else {
            $stmt->bind_param('ii', $doctor_id, $dow);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $st = $row['start_time'] ?? null;
            $en = $row['end_time'] ?? null;
            if ($st !== null && $en !== null && $st !== '' && $en !== '') {
                $windows[] = [$st, $en];
            }
        }
        $stmt->close();
    }
}

if (count($windows) === 0 && hb_table_exists($conn, 'doctor_availability')) {
    $stmt = $conn->prepare('SELECT start_time, end_time FROM doctor_availability WHERE doctor_id = ? AND day_of_week = ? AND is_available = 1');
    if ($stmt) {
        $stmt->bind_param('ii', $doctor_id, $dow);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $st = $row['start_time'] ?? '09:00:00';
            $en = $row['end_time'] ?? '17:00:00';
            if ($st === '00:00:00' && $en === '00:00:00') {
                continue;
            }
            if ($en === '00:00:00') {
                $en = '23:59:00';
            }
            $windows[] = [$st, $en];
        }
        $stmt->close();
    }
}

$slotSet = [];
foreach ($windows as $w) {
    $s = substr((string) $w[0], 0, 5);
    $e = substr((string) $w[1], 0, 5);
    foreach (hb_slots_between($s, $e, 60) as $hm) {
        $slotSet[$hm] = true;
    }
}
ksort($slotSet);
$slots = array_keys($slotSet);

// Booked slots (same doctor, same calendar day)
$booked = [];
$sql = "SELECT appointment_date FROM appointments WHERE doctor_id = ? AND DATE(appointment_date) = ? AND LOWER(status) IN ('pending','confirmed')";
if ($exclude_appt_id > 0) {
    $sql .= ' AND id != ?';
}
$stmt = $conn->prepare($sql);
if ($stmt) {
    if ($exclude_appt_id > 0) {
        $stmt->bind_param('isi', $doctor_id, $date, $exclude_appt_id);
    } else {
        $stmt->bind_param('is', $doctor_id, $date);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $booked[] = date('H:i', strtotime($row['appointment_date']));
    }
    $stmt->close();
}

$slots = array_values(array_filter($slots, static function ($hm) use ($booked) {
    return !in_array($hm, $booked, true);
}));

$today = date('Y-m-d');
if ($date === $today) {
    $nowHm = date('H:i');
    $slots = array_values(array_filter($slots, static function ($hm) use ($nowHm) {
        return $hm >= $nowHm;
    }));
}

$display = [];
foreach ($slots as $hm) {
    $ts = strtotime('2000-01-01 ' . $hm);
    $display[] = [
        'value' => $hm,
        'label' => $ts ? date('g:i A', $ts) : $hm,
    ];
}

$msg = '';
if (count($windows) === 0) {
    $msg = 'No clinic or online hours for this day. Add schedules in Doctor Availability.';
} elseif (count($display) === 0) {
    $msg = 'No open slots left for this date.';
}

echo json_encode([
    'ok' => true,
    'times' => $display,
    'message' => $msg,
]);
