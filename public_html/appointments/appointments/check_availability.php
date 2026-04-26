<?php
// check_availability.php - AJAX endpoint to check doctor availability (schedules, overrides, holidays)
require_once __DIR__ . '/../includes/auth_guard.php';
ensure_logged_in();
header('Content-Type: application/json');

require '../config/db_connect.php';
require_once __DIR__ . '/../includes/security.php';

if (!isset($_GET['doctor_id']) || !isset($_GET['date'])) {
    echo json_encode(['booked_times' => []]);
    exit;
}

$doctor_id = require_int($_GET['doctor_id']);
$date = sanitize_string($_GET['date'], 50);

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['error' => 'Invalid date format']);
    exit;
}

// Helper: default UI hours 09:00-17:00
$default_hours = range(9, 17);
$working_hours = [];

// 1) Clinic holiday?
$holiday_stmt = $conn->prepare("SELECT 1 FROM clinic_holidays WHERE date = ? LIMIT 1");
$holiday_stmt->bind_param("s", $date);
$holiday_stmt->execute();
$is_holiday = $holiday_stmt->get_result()->num_rows > 0;
if ($is_holiday) {
    echo json_encode(['booked_times' => $default_hours]);
    exit;
}

// 2) Provider overrides for the date
$override_stmt = $conn->prepare("SELECT is_available, start_time, end_time FROM provider_overrides WHERE doctor_id = ? AND date = ?");
$override_stmt->bind_param("is", $doctor_id, $date);
$override_stmt->execute();
$override_res = $override_stmt->get_result();

$override_windows = [];
$full_day_blocked = false;
while ($ov = $override_res->fetch_assoc()) {
    $is_available = (int)($ov['is_available'] ?? 0);
    $start_time = $ov['start_time'];
    $end_time = $ov['end_time'];
    if ($is_available === 0 && $start_time === null && $end_time === null) {
        $full_day_blocked = true;
    }
    if ($is_available === 1 && $start_time !== null && $end_time !== null) {
        $override_windows[] = [$start_time, $end_time];
    }
}

if ($full_day_blocked) {
    echo json_encode(['booked_times' => $default_hours]);
    exit;
}

// 3) Build working hours from overrides or doctor schedules
$dow = (int)date('w', strtotime($date)); // 0..6
if (count($override_windows) > 0) {
    foreach ($override_windows as $w) {
        list($s, $e) = $w;
        $start_h = (int)substr($s, 0, 2);
        $end_h = (int)substr($e, 0, 2);
        for ($h = $start_h; $h < $end_h; $h++) { $working_hours[$h] = true; }
    }
} else {
    // Use new doctor_schedules table
    $sched_stmt = $conn->prepare("SELECT start_time, end_time FROM doctor_schedules WHERE doctor_id = ? AND day_of_week = ? AND is_available = 1 AND (effective_from IS NULL OR effective_from <= ?) AND (effective_to IS NULL OR effective_to >= ?)");
    $sched_stmt->bind_param("iiss", $doctor_id, $dow, $date, $date);
    $sched_stmt->execute();
    $sched_res = $sched_stmt->get_result();
    while ($row = $sched_res->fetch_assoc()) {
        $s = $row['start_time'];
        $e = $row['end_time'];
        $start_h = (int)substr($s, 0, 2);
        $end_h = (int)substr($e, 0, 2);
        for ($h = $start_h; $h < $end_h; $h++) { $working_hours[$h] = true; }
    }
}

// If no working hours, fall back to 9..17
if (empty($working_hours)) {
    foreach ($default_hours as $h) { $working_hours[$h] = true; }
}

// 4) Existing bookings
$appt_stmt = $conn->prepare("SELECT HOUR(appointment_date) as hour FROM appointments WHERE doctor_id = ? AND DATE(appointment_date) = ? AND LOWER(status) IN ('pending','confirmed')");
$appt_stmt->bind_param("is", $doctor_id, $date);
$appt_stmt->execute();
$appt_res = $appt_stmt->get_result();
$booked_times = [];
while ($row = $appt_res->fetch_assoc()) {
    $booked_times[] = (int)$row['hour'];
}

// 5) Mark non-working UI hours as unavailable in 9..17 range
foreach ($default_hours as $h) {
    if (!isset($working_hours[$h])) {
        $booked_times[] = $h;
    }
}

// Deduplicate and sort
$booked_times = array_values(array_unique($booked_times));
sort($booked_times);

// Get available hours from working_hours
$available_hours = [];
foreach ($working_hours as $hour => $available) {
    if ($available) {
        $available_hours[] = $hour;
    }
}
sort($available_hours);

echo json_encode([
    'booked_times' => $booked_times,
    'available_hours' => $available_hours
]);

