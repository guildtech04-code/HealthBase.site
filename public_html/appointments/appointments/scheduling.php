<?php
// scheduling.php - Improved with doctor availability and better design
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/auth_guard.php';
ensure_role(['user']);
require '../config/db_connect.php';
require 'notification_helper.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/appointment_schema_flags.php';
require_once __DIR__ . '/appointment_patient_overlap.php';
require_once __DIR__ . '/../includes/patient_profile_extra.php';
hb_ensure_patient_profile_extra_table($conn);

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $firstName     = sanitize_string(trim($_POST['first_name']), 100);
    $lastName      = sanitize_string(trim($_POST['last_name']), 100);
    $date_of_birth = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
    
    // Calculate age from date of birth
    $age = null;
    if ($date_of_birth) {
        $birthDate = new DateTime($date_of_birth);
        $today = new DateTime();
        $age = $today->diff($birthDate)->y;
        if ($age < 1 || $age > 120) { 
            $age = null; // Invalid age, will need to be set manually
        }
    }
    
    // Fallback to manual age input if date_of_birth not provided or invalid
    if ($age === null && !empty($_POST['age'])) {
        $age = (int)$_POST['age'];
        if ($age < 1 || $age > 120) { $age = 25; }
    } elseif ($age === null) {
        $age = 25; // Default fallback
    }
    
    $gender        = $_POST['gender'] ?? 'Male';
    if (!in_array($gender, ['Male','Female'], true)) { $gender = 'Male'; }
    $category      = sanitize_string($_POST['category'] ?? '', 100);
    $healthConcern = sanitize_string(trim($_POST['healthConcern']), 255);
    $isPwd = (isset($_POST['is_pwd']) && strtolower((string) $_POST['is_pwd']) === 'yes') ? 'yes' : 'no';
    $pwdDisabilityType = sanitize_string(trim((string) ($_POST['pwd_disability_type'] ?? '')), 150);
    
    // If "Others" is selected, combine with custom text
    if (strpos($healthConcern, 'OTHER -') === 0) {
        $othersText = sanitize_string(trim($_POST['others_health_concern'] ?? ''), 255);
        if ($othersText) {
            $healthConcern = 'OTHER - ' . $othersText;
        } else {
            $_SESSION['error'] = "Please specify your health concern.";
            header("Location: scheduling.php?error=health_concern_required");
            exit();
        }
    }
    if ($isPwd === 'yes' && $pwdDisabilityType === '') {
        $_SESSION['error'] = 'Please specify the type of disability for PWD.';
        header("Location: scheduling.php?error=pwd_disability_required");
        exit();
    }
    // Persist PWD metadata using existing health_concern field for backward compatibility.
    $healthConcern = preg_replace('/\s*\|\s*PWD:\s*.*$/i', '', $healthConcern);
    if ($isPwd === 'yes' && $pwdDisabilityType !== '') {
        $healthConcern = trim($healthConcern) . ' | PWD: ' . $pwdDisabilityType;
    }
    $date          = $_POST['date'];
    $hour          = intval($_POST['hour']);
    $ampm          = $_POST['ampm'];
    $doctor_id     = intval($_POST['doctor_id']);
    
    // Validate date is not in the past
    $selected_datetime = strtotime($date);
    if ($selected_datetime === false || $selected_datetime < strtotime('today')) {
        $_SESSION['error'] = "Cannot schedule appointments in the past. Please select a future date.";
        header("Location: scheduling.php?error=past_date");
        exit();
    }
    
    // Validate hour range
    if ($hour < 1 || $hour > 12) {
        $_SESSION['error'] = "Invalid time selected.";
        header("Location: scheduling.php?error=invalid_time");
        exit();
    }

    $formFirstName = $firstName;
    $formLastName = $lastName;

    $visitFor = (isset($_POST['visit_for']) && $_POST['visit_for'] === 'other') ? 'other' : 'self';
    $visitRelationship = sanitize_string(trim($_POST['visit_relationship'] ?? ''), 100);
    if ($visitFor === 'other' && $visitRelationship === '') {
        $_SESSION['error'] = 'Please specify your relationship (e.g., Child).';
        header('Location: scheduling.php?error=relationship_required');
        exit();
    }

    // Registration names — one patients row per user (uniq_patients_user); guest details go on the appointment
    $regStmt = $conn->prepare('SELECT first_name, last_name FROM users WHERE id = ? LIMIT 1');
    $regStmt->bind_param('i', $user_id);
    $regStmt->execute();
    $userRegRow = $regStmt->get_result()->fetch_assoc();
    $regFirst = trim((string) ($userRegRow['first_name'] ?? ''));
    $regLast = trim((string) ($userRegRow['last_name'] ?? ''));

    $nameMatchesReg = strcasecmp($formFirstName, $regFirst) === 0 && strcasecmp($formLastName, $regLast) === 0;
    // Self only when names match registration AND user did not pick "someone else".
    // If names differ from the account (e.g. user typed another person but left "Myself"), still book as guest so the typed name is stored on the appointment.
    $bookingForSelf = ($visitFor !== 'other') && $nameMatchesReg;

    if ($bookingForSelf) {
        $firstName = $regFirst;
        $lastName = $regLast;
    }

    $holderStmt = $conn->prepare('SELECT id FROM patients WHERE user_id = ? ORDER BY id ASC LIMIT 1');
    $holderStmt->bind_param('i', $user_id);
    $holderStmt->execute();
    $holderRow = $holderStmt->get_result()->fetch_assoc();
    $patient_id = $holderRow ? (int) $holderRow['id'] : null;

    if ($patient_id === null) {
        if ($bookingForSelf) {
            $insertPatient = $conn->prepare('
                INSERT INTO patients (user_id, first_name, last_name, date_of_birth, age, gender, health_concern)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ');
            $insertPatient->bind_param('isssiss', $user_id, $regFirst, $regLast, $date_of_birth, $age, $gender, $healthConcern);
        } else {
            $holderDob = null;
            $holderAge = 0;
            $holderGender = 'Male';
            $holderHc = '';
            $insertPatient = $conn->prepare('
                INSERT INTO patients (user_id, first_name, last_name, date_of_birth, age, gender, health_concern)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ');
            $insertPatient->bind_param('isssiss', $user_id, $regFirst, $regLast, $holderDob, $holderAge, $holderGender, $holderHc);
        }
        if (!$insertPatient->execute()) {
            die('Patient insert failed: ' . $insertPatient->error);
        }
        $patient_id = (int) $insertPatient->insert_id;
    } elseif ($bookingForSelf) {
        $updatePatient = $conn->prepare('
            UPDATE patients
            SET first_name = ?, last_name = ?, date_of_birth = ?, age = ?, gender = ?, health_concern = ?
            WHERE id = ? AND user_id = ?
        ');
        $updatePatient->bind_param('sssissii', $regFirst, $regLast, $date_of_birth, $age, $gender, $healthConcern, $patient_id, $user_id);
        $updatePatient->execute();
    }

    if (!$patient_id) {
        $_SESSION['error'] = 'Could not resolve patient record. Please try again.';
        header('Location: scheduling.php?error=patient');
        exit();
    }

    if (hb_patient_profile_extra_table_exists($conn)) {
        hb_save_patient_profile_extra($conn, $patient_id, $_POST, function (string $str, int $max): string {
            return sanitize_string($str, $max);
        });
    }

    // Convert time to 24-hour format
    if ($ampm === "PM" && $hour != 12) $hour += 12;
    elseif ($ampm === "AM" && $hour == 12) $hour = 0;
    $timeFormatted = sprintf("%02d:00:00", $hour);

    $appointment_date = $date . " " . $timeFormatted;

    // ==== Server-side availability validation (holidays, overrides, schedules) ====
    // 1) Clinic holiday check
    $holiday_stmt = $conn->prepare("SELECT 1 FROM clinic_holidays WHERE date = ? LIMIT 1");
    $holiday_stmt->bind_param("s", $date);
    $holiday_stmt->execute();
    $is_holiday = $holiday_stmt->get_result()->num_rows > 0;
    if ($is_holiday) {
        $_SESSION['error'] = "Selected date is a clinic holiday. Please choose another date.";
        header("Location: scheduling.php?error=holiday");
        exit();
    }

    // 2) Provider overrides on that date
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
        $_SESSION['error'] = "Doctor is unavailable for the selected date. Please choose another date.";
        header("Location: scheduling.php?error=unavailable");
        exit();
    }

    // 3) Recurring schedules (if no positive override window)
    $hour24 = (int)substr($timeFormatted, 0, 2);
    $is_within_working = false;
    if (count($override_windows) > 0) {
        foreach ($override_windows as $w) {
            list($s, $e) = $w;
            $start_h = (int)substr($s, 0, 2);
            $end_h = (int)substr($e, 0, 2);
            if ($hour24 >= $start_h && $hour24 < $end_h) { $is_within_working = true; break; }
        }
    } else {
        $dow = (int)date('w', strtotime($date));
        $sched_stmt = $conn->prepare("SELECT start_time, end_time FROM doctor_schedules WHERE doctor_id = ? AND day_of_week = ? AND is_available = 1 AND (effective_from IS NULL OR effective_from <= ?) AND (effective_to IS NULL OR effective_to >= ?)");
        $sched_stmt->bind_param("iiss", $doctor_id, $dow, $date, $date);
        $sched_stmt->execute();
        $sched_res = $sched_stmt->get_result();
        $schedule_rows = [];
        while ($row = $sched_res->fetch_assoc()) {
            $schedule_rows[] = $row;
            $s = $row['start_time'];
            $e = $row['end_time'];
            $start_h = (int)substr($s, 0, 2);
            $end_h = (int)substr($e, 0, 2);
            if ($hour24 >= $start_h && $hour24 < $end_h) { $is_within_working = true; break; }
        }
        // Must match check_availability.php: if no schedule rows for this weekday, allow default 9:00–17:00 (same as UI)
        if (!$is_within_working && count($schedule_rows) === 0) {
            if ($hour24 >= 9 && $hour24 <= 17) {
                $is_within_working = true;
            }
        }
    }
    if (!$is_within_working) {
        $_SESSION['error'] = "Selected time is outside doctor's working hours. Please choose another slot.";
        header("Location: scheduling.php?error=outside_hours");
        exit();
    }

    // 3b) Same patient: overlapping visits — cancel older pending; block if overlaps confirmed
    $overlap_result = hb_resolve_patient_appointment_overlap($conn, $patient_id, $appointment_date, 30, true);
    if (!$overlap_result['ok']) {
        $_SESSION['error'] = $overlap_result['error'] ?? 'Scheduling conflict for this patient.';
        header('Location: scheduling.php?error=patient_overlap_confirmed');
        exit();
    }

    // 4) Prevent double-booking at DB and app level
    $conf_stmt = $conn->prepare("SELECT 1 FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND status IN ('pending','confirmed') LIMIT 1");
    $conf_stmt->bind_param("is", $doctor_id, $appointment_date);
    $conf_stmt->execute();
    if ($conf_stmt->get_result()->num_rows > 0) {
        $_SESSION['error'] = "This time slot has just been booked. Please choose another time.";
        header("Location: scheduling.php?error=slot_booked");
        exit();
    }

    $apptFlags = hb_appointments_column_flags($conn);
    $notesVal = null;
    if (!$bookingForSelf) {
        if ($apptFlags['guest']) {
            $notesVal = ($apptFlags['notes'] && $healthConcern !== '') ? mb_substr($healthConcern, 0, 255) : null;
        } elseif ($apptFlags['notes']) {
            $parts = [];
            $nm = trim($formFirstName . ' ' . $formLastName);
            if ($nm !== '') {
                $parts[] = 'Visit for: ' . $nm;
            }
            if ($healthConcern !== '') {
                $parts[] = $healthConcern;
            }
            $notesVal = $parts ? mb_substr(implode(HB_APPT_NOTES_VISIT_SEP, $parts), 0, 255) : null;
        }
    }

    if (!$bookingForSelf && $apptFlags['guest']) {
        $gf = $formFirstName !== '' ? $formFirstName : null;
        $gl = $formLastName !== '' ? $formLastName : null;
        if ($apptFlags['notes']) {
            $insertAppt = $conn->prepare("
                INSERT INTO appointments (doctor_id, patient_id, guest_first_name, guest_last_name, appointment_date, status, notes)
                VALUES (?,?,?,?,?, 'pending', ?)
            ");
            $insertAppt->bind_param('iissss', $doctor_id, $patient_id, $gf, $gl, $appointment_date, $notesVal);
        } else {
            $insertAppt = $conn->prepare("
                INSERT INTO appointments (doctor_id, patient_id, guest_first_name, guest_last_name, appointment_date, status)
                VALUES (?,?,?,?,?, 'pending')
            ");
            $insertAppt->bind_param('iisss', $doctor_id, $patient_id, $gf, $gl, $appointment_date);
        }
    } elseif (!$bookingForSelf && $apptFlags['notes']) {
        $insertAppt = $conn->prepare("
            INSERT INTO appointments (doctor_id, patient_id, appointment_date, status, notes)
            VALUES (?,?,?, 'pending', ?)
        ");
        $insertAppt->bind_param('iiss', $doctor_id, $patient_id, $appointment_date, $notesVal);
    } else {
        $insertAppt = $conn->prepare("
            INSERT INTO appointments (doctor_id, patient_id, appointment_date, status)
            VALUES (?, ?, ?, 'pending')
        ");
        $insertAppt->bind_param('iis', $doctor_id, $patient_id, $appointment_date);
    }

    if ($insertAppt->execute()) {
        $appointment_id = $insertAppt->insert_id;

        // Automatically create consultation record for this appointment
        // Check if consultation_status column exists
        $check_cols = $conn->query("
            SELECT COLUMN_NAME 
            FROM information_schema.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'consultations' 
            AND COLUMN_NAME = 'consultation_status'
        ");
        $has_consultation_status = $check_cols->num_rows > 0;
        
        // Check if consultation already exists (prevent duplicates)
        $check_consultation = $conn->prepare("SELECT id FROM consultations WHERE appointment_id = ? LIMIT 1");
        $check_consultation->bind_param("i", $appointment_id);
        $check_consultation->execute();
        $existing_consultation = $check_consultation->get_result()->fetch_assoc();
        $check_consultation->close();
        
        if (!$existing_consultation) {
            // Create consultation record with minimal data (to be filled later by doctor)
            if ($has_consultation_status) {
                $create_consultation = $conn->prepare("
                    INSERT INTO consultations (appointment_id, patient_id, doctor_id, visit_date, consultation_status)
                    VALUES (?, ?, ?, ?, 'Pending')
                ");
                $create_consultation->bind_param("iiis", $appointment_id, $patient_id, $doctor_id, $appointment_date);
            } else {
                $create_consultation = $conn->prepare("
                    INSERT INTO consultations (appointment_id, patient_id, doctor_id, visit_date)
                    VALUES (?, ?, ?, ?)
                ");
                $create_consultation->bind_param("iiis", $appointment_id, $patient_id, $doctor_id, $appointment_date);
            }
            
            if (!$create_consultation->execute()) {
                // Log error but don't fail the appointment creation
                error_log("Failed to auto-create consultation record for appointment #$appointment_id: " . $create_consultation->error);
            }
            $create_consultation->close();
        }

        // Notify the doctor about the new appointment
        addNotification($conn, $doctor_id, "appointment", $appointment_id);

        // Email receipt to patient (non-blocking; failures logged only)
        require_once __DIR__ . '/appointment_email_helper.php';
        hb_send_patient_appointment_receipt_email(
            $conn,
            (int) $appointment_id,
            (!$bookingForSelf ? $visitRelationship : null)
        );

        header('Location: scheduling.php?booked=1');
        exit();
    } else {
        // Handle duplicate unique constraint gracefully
        if (strpos($conn->error, 'uniq_doctor_timeslot') !== false) {
            $_SESSION['error'] = "This time slot is no longer available. Please pick another.";
            header("Location: scheduling.php?error=slot_unavailable");
            exit();
        }
        $_SESSION['error'] = "Error scheduling appointment. Please try again.";
        error_log("Appointment insert error: " . $insertAppt->error);
        header("Location: scheduling.php?error=insert_failed");
        exit();
    }
}

// Get user info for sidebar and form auto-population (include date_of_birth from registration when column exists)
$has_user_dob_col = false;
$col_chk = $conn->query("
    SELECT 1 FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'date_of_birth' 
    LIMIT 1
");
if ($col_chk && $col_chk->num_rows > 0) {
    $has_user_dob_col = true;
}
$user_select = 'SELECT username, email, role, first_name, last_name, gender';
if ($has_user_dob_col) {
    $user_select .= ', date_of_birth';
}
$user_select .= ' FROM users WHERE id = ?';
$user_query = $conn->prepare($user_select);
$user_query->bind_param('i', $user_id);
$user_query->execute();
$user_result = $user_query->get_result()->fetch_assoc();

$sidebar_user_data = [
    'username' => htmlspecialchars($user_result['username']),
    'email' => htmlspecialchars($user_result['email']),
    'role' => htmlspecialchars($user_result['role'])
];

// Get existing patient record if available (for date_of_birth, age, etc.)
$patient_query = $conn->prepare("SELECT id, first_name, last_name, date_of_birth, age, gender, health_concern FROM patients WHERE user_id = ? LIMIT 1");
$patient_query->bind_param("i", $user_id);
$patient_query->execute();
$patient_result = $patient_query->get_result()->fetch_assoc();

$sched_patient_id = $patient_result ? (int) $patient_result['id'] : 0;
$sched_extra_table = hb_patient_profile_extra_table_exists($conn);
$ppe = ($sched_extra_table && $sched_patient_id > 0)
    ? hb_get_patient_profile_extra($conn, $sched_patient_id)
    : hb_patient_profile_extra_defaults();

// Names: always default from registration (users) so the form shows your account name, not another dependent's
$default_first_name = trim((string) ($user_result['first_name'] ?? ''));
$default_last_name = trim((string) ($user_result['last_name'] ?? ''));
if ($default_first_name === '' && $default_last_name === '' && $patient_result) {
    $default_first_name = (string) ($patient_result['first_name'] ?? '');
    $default_last_name = (string) ($patient_result['last_name'] ?? '');
}
$default_gender = $patient_result['gender'] ?? $user_result['gender'] ?? 'Male';
$default_date_of_birth = '';
if (!empty($patient_result['date_of_birth'])) {
    $default_date_of_birth = $patient_result['date_of_birth'];
} elseif ($has_user_dob_col && !empty($user_result['date_of_birth'])) {
    $default_date_of_birth = $user_result['date_of_birth'];
}
$default_age = isset($patient_result['age']) && $patient_result['age'] !== '' && $patient_result['age'] !== null
    ? (int) $patient_result['age'] : null;
$default_is_pwd = 'no';
$default_pwd_disability_type = '';
if (!empty($patient_result['health_concern']) && preg_match('/\|\s*PWD:\s*(.+)\s*$/i', (string) $patient_result['health_concern'], $pwdMatch)) {
    $default_is_pwd = 'yes';
    $default_pwd_disability_type = trim((string) ($pwdMatch[1] ?? ''));
}

// Calculate age from date_of_birth when patient age not set (covers registration-only DOB)
if ($default_date_of_birth && $default_age === null) {
    try {
        $birthDate = new DateTime($default_date_of_birth);
        $today = new DateTime();
        $default_age = $today->diff($birthDate)->y;
        if ($default_age < 1 || $default_age > 120) {
            $default_age = null;
        }
    } catch (Exception $e) {
        $default_age = null;
    }
}

$patient_dob_empty = empty($patient_result) || empty($patient_result['date_of_birth'] ?? '');
$dob_prefilled_from_registration = $default_date_of_birth !== ''
    && $has_user_dob_col
    && !empty($user_result['date_of_birth'])
    && $patient_dob_empty;

// Get pre-selected doctor from URL
$selected_doctor_id = $_GET['doctor_id'] ?? null;

// Normalize legacy doctor profile to "Sarrosa, Edward" for assistant/patient views.
$cruz_email = 'cruzmarkjabez14@gmail.com';
$normalize_dr_cruz = $conn->prepare("UPDATE users SET specialization = 'Orthopaedic Surgery', first_name = 'Edward', last_name = 'Sarrosa', status = 'active' WHERE LOWER(TRIM(email)) = ? AND role = 'doctor'");
if ($normalize_dr_cruz) {
    $normalize_dr_cruz->bind_param('s', $cruz_email);
    $normalize_dr_cruz->execute();
    $normalize_dr_cruz->close();
}

// Demo clinic hours for Dr. Sarrosa, Edward (only if no rows yet — does not overwrite assistant edits)
$jid = $conn->prepare("SELECT id FROM users WHERE LOWER(TRIM(email)) = ? AND role = 'doctor' LIMIT 1");
if ($jid) {
    $jid->bind_param('s', $cruz_email);
    $jid->execute();
    $jr = $jid->get_result()->fetch_assoc();
    $jid->close();
    if ($jr) {
        $jabez_doctor_id = (int) $jr['id'];
        $cnt_sched = $conn->prepare("SELECT COUNT(*) AS c FROM doctor_schedules WHERE doctor_id = ?");
        if ($cnt_sched) {
            $cnt_sched->bind_param('i', $jabez_doctor_id);
            $cnt_sched->execute();
            $n = (int) ($cnt_sched->get_result()->fetch_assoc()['c'] ?? 0);
            $cnt_sched->close();
            if ($n === 0) {
                $ins = $conn->prepare(
                    "INSERT INTO doctor_schedules (doctor_id, schedule_type, day_of_week, time_period, start_time, end_time, appointment_type, is_available)
                     VALUES (?, 'clinic', ?, 'Any', ?, ?, 'By Appointment', 1)"
                );
                if ($ins) {
                    // Made-up schedule: explicit hour slots (10:00, 11:00, …) — one DB row per hour block
                    $weekdays = [1, 2, 3, 4, 5];
                    $weekday_start_hours = [10, 11, 12, 13, 14, 15, 16]; // 10:00–11:00 … 16:00–17:00
                    $saturday_start_hours = [10, 11, 12];               // Sat: 10:00–13:00 only

                    foreach ($weekdays as $dow) {
                        foreach ($weekday_start_hours as $h) {
                            $st = sprintf('%02d:00:00', $h);
                            $en = sprintf('%02d:00:00', $h + 1);
                            $ins->bind_param('iiss', $jabez_doctor_id, $dow, $st, $en);
                            $ins->execute();
                        }
                    }
                    foreach ($saturday_start_hours as $h) {
                        $dow = 6;
                        $st = sprintf('%02d:00:00', $h);
                        $en = sprintf('%02d:00:00', $h + 1);
                        $ins->bind_param('iiss', $jabez_doctor_id, $dow, $st, $en);
                        $ins->execute();
                    }
                    $ins->close();
                }
            }
        }
    }
}

// Get all doctors
$doctorQuery = $conn->query("SELECT id, first_name, last_name, specialization, email FROM users WHERE role='doctor' AND status='active'");

// Function to get doctor's available times for a specific date
function getDoctorAvailableTimes($conn, $doctor_id, $date) {
    $dow = (int)date('w', strtotime($date)); // 0=Sunday, 1=Monday, etc.
    
    // Get doctor's schedule for this day
    $schedule_stmt = $conn->prepare("
        SELECT start_time, end_time 
        FROM doctor_schedules 
        WHERE doctor_id = ? AND day_of_week = ? AND is_available = 1
        AND (effective_from IS NULL OR effective_from <= ?)
        AND (effective_to IS NULL OR effective_to >= ?)
    ");
    $schedule_stmt->bind_param("iiss", $doctor_id, $dow, $date, $date);
    $schedule_stmt->execute();
    $schedule_result = $schedule_stmt->get_result();
    
    $available_hours = [];
    while ($row = $schedule_result->fetch_assoc()) {
        $start_h = (int)date('G', strtotime($row['start_time']));
        $end_h = (int)date('G', strtotime($row['end_time']));
        for ($h = $start_h; $h < $end_h; $h++) {
            $available_hours[] = $h;
        }
    }
    
    return $available_hours;
}

// Function to get booked times for a doctor on a specific date
function getDoctorBookedTimes($conn, $doctor_id, $date) {
    $stmt = $conn->prepare("
        SELECT HOUR(appointment_date) as hour 
        FROM appointments 
        WHERE doctor_id = ? AND DATE(appointment_date) = ? 
        AND status IN ('Confirmed', 'Pending')
    ");
    $stmt->bind_param("is", $doctor_id, $date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $booked = [];
    while ($row = $result->fetch_assoc()) {
        $booked[] = (int)$row['hour'];
    }
    
    return $booked;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Book Appointment - HealthBase</title>
  <link rel="icon" type="image/x-icon" href="../assets/icons/favicon.ico">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../patient/css/patient_dashboard.css">
  <style>
    .booking-container {
        background: transparent;
        border-radius: 0;
        padding: 0;
        margin-bottom: 30px;
        box-shadow: none;
    }

    .sched-flow-intro {
        font-size: 15px;
        color: #475569;
        line-height: 1.55;
        margin: 0 0 18px 0;
        padding: 14px 16px;
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        border: 1px solid #e2e8f0;
        border-radius: 12px;
    }
    .sched-flow-steps {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 6px 10px;
        list-style: none;
        margin: 0 0 20px 0;
        padding: 0;
        font-size: 12px;
        font-weight: 600;
        color: #64748b;
    }
    .sched-flow-steps li {
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .sched-flow-steps .sched-flow-n {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 22px;
        height: 22px;
        border-radius: 50%;
        background: #e2e8f0;
        color: #475569;
        font-size: 11px;
    }
    .sched-flow-steps .sched-flow-sep {
        color: #cbd5e1;
        font-weight: 400;
        user-select: none;
    }

    .sched-flow-card {
        margin-bottom: 14px;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        background: #fff;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
        overflow: hidden;
    }
    .sched-flow-card[open] {
        box-shadow: 0 4px 20px rgba(15, 23, 42, 0.08);
    }
    .sched-flow-card__summary {
        list-style: none;
        cursor: pointer;
        padding: 16px 18px;
        display: flex;
        align-items: flex-start;
        gap: 14px;
        background: linear-gradient(180deg, #fafbfc 0%, #fff 100%);
        border-bottom: 1px solid transparent;
    }
    .sched-flow-card[open] > .sched-flow-card__summary {
        border-bottom-color: #f1f5f9;
    }
    .sched-flow-card__summary::-webkit-details-marker { display: none; }
    .sched-flow-card__badge {
        flex-shrink: 0;
        width: 32px;
        height: 32px;
        border-radius: 10px;
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: #fff;
        font-weight: 700;
        font-size: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-family: 'Poppins', 'Inter', sans-serif;
    }
    .sched-flow-card__summary-text {
        display: flex;
        flex-direction: column;
        gap: 4px;
        min-width: 0;
    }
    .sched-flow-card__title {
        font-size: 16px;
        font-weight: 700;
        color: #0f172a;
    }
    .sched-flow-card__sub {
        font-size: 12px;
        font-weight: 500;
        color: #94a3b8;
        line-height: 1.35;
    }
    .sched-flow-card__inner {
        padding: 8px 18px 20px;
    }
    .sched-flow-card__inner .form-group:first-child {
        margin-top: 8px;
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
        margin-bottom: 20px;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #334155;
        font-size: 14px;
    }

    .form-group input,
    .form-group select {
        width: 100%;
        padding: 12px 15px;
        border: 1.5px solid #e2e8f0;
        border-radius: 8px;
        font-size: 15px;
        transition: all 0.3s ease;
        font-family: 'Inter', sans-serif;
        background: white;
    }

    .form-group input:focus,
    .form-group select:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .form-group input:disabled,
    .form-group select:disabled {
        background: #f8fafc;
        cursor: not-allowed;
        color: #64748b;
    }

    .time-slots {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
        gap: 10px;
        margin-top: 10px;
    }

    .time-slot {
        padding: 12px;
        text-align: center;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
        font-weight: 500;
        font-size: 14px;
        background: white;
    }

    .time-slot:hover {
        border-color: #3b82f6;
        background: #eff6ff;
    }

    .time-slot.selected {
        background: #3b82f6;
        border-color: #3b82f6;
        color: white;
    }

    .time-slot.unavailable {
        background: #f1f5f9;
        border-color: #cbd5e1;
        color: #94a3b8;
        cursor: not-allowed;
        opacity: 0.6;
    }

    .doctor-info-card {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: white;
        padding: 20px;
        border-radius: 12px;
        margin-bottom: 20px;
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    }

    .doctor-info-card h4 {
        margin: 0 0 10px 0;
        font-size: 18px;
        font-weight: 600;
    }

    .doctor-info-card p {
        margin: 5px 0;
        opacity: 0.9;
        font-size: 14px;
    }

    .availability-indicator {
        display: inline-block;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        margin-right: 5px;
    }

    .available { background: #10b981; }
    .busy { background: #ef4444; }

    .btn-submit {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: white;
        border: none;
        padding: 14px 28px;
        font-size: 16px;
        font-weight: 600;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
        width: 100%;
        margin-top: 20px;
    }

    .btn-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(59, 130, 246, 0.4);
    }

    .sched-flow-card.sched-ppe-wrap {
        margin-top: 14px;
    }
    .sched-ppe-summary {
        list-style: none;
    }
    .sched-flow-card__summary.sched-ppe-summary {
        flex-direction: row;
        align-items: flex-start;
        gap: 14px;
        padding: 16px 18px;
    }
    .sched-ppe-summary::-webkit-details-marker { display: none; }
    .sched-flow-card__summary .sched-flow-card__title em { font-style: normal; font-weight: 500; color: #94a3b8; }
    .sched-ppe-summary-hint {
        font-size: 12px;
        font-weight: 500;
        color: #94a3b8;
    }
    .sched-ppe-body {
        padding: 0 18px 18px;
        border-top: 1px solid #f1f5f9;
        background: #fff;
    }
    .sched-ppe-body .ppe-sheet {
        margin-top: 0;
        padding-top: 16px;
        border-top: none;
    }
    .sched-ppe-note {
        font-size: 13px;
        color: #64748b;
        line-height: 1.5;
        margin: 0 0 16px 0;
        padding: 10px 12px;
        background: #f0f9ff;
        border: 1px solid #bae6fd;
        border-radius: 8px;
    }

    .btn-submit:disabled {
        background: #cbd5e1;
        cursor: not-allowed;
        transform: none;
    }

    .calendar-wrapper {
        background: white;
        padding: 20px;
        border-radius: 12px;
        border: 1.5px solid #e2e8f0;
        margin-bottom: 20px;
    }

    .info-message {
        background: #dbeafe;
        border-left: 4px solid #3b82f6;
        padding: 12px 15px;
        border-radius: 6px;
        margin-bottom: 20px;
        font-size: 14px;
        color: #1e40af;
    }

    /* Success modal — same palette as booking UI (blue / slate / emerald) */
    .hb-booked-modal {
        position: fixed;
        inset: 0;
        z-index: 10040;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 16px;
        box-sizing: border-box;
    }
    .hb-booked-modal.is-open {
        display: flex;
    }
    .hb-booked-modal-backdrop {
        position: absolute;
        inset: 0;
        background: rgba(15, 23, 42, 0.55);
    }
    .hb-booked-modal-dialog {
        position: relative;
        background: #fff;
        border-radius: 16px;
        max-width: 440px;
        width: 100%;
        padding: 28px 24px 22px;
        box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.35);
        border: 1px solid #e2e8f0;
        text-align: center;
    }
    .hb-booked-modal-iconwrap {
        margin-bottom: 16px;
    }
    .hb-booked-modal-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 56px;
        height: 56px;
        border-radius: 50%;
        background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
        color: #047857;
        font-size: 22px;
    }
    .hb-booked-modal-dialog h2 {
        margin: 0 0 8px;
        font-size: 1.35rem;
        font-weight: 700;
        color: #0f172a;
        font-family: 'Inter', 'Poppins', sans-serif;
    }
    .hb-booked-modal-lead {
        margin: 0 0 10px;
        font-size: 15px;
        color: #334155;
        line-height: 1.5;
    }
    .hb-booked-modal-note {
        margin: 0 0 22px;
        font-size: 13px;
        color: #64748b;
        line-height: 1.45;
    }
    .hb-booked-modal-note i {
        color: #3b82f6;
        margin-right: 6px;
    }
    .hb-booked-modal-actions {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .hb-booked-btn-primary {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        width: 100%;
        padding: 12px 18px;
        font-size: 15px;
        font-weight: 600;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: #fff;
        font-family: inherit;
        box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35);
    }
    .hb-booked-btn-primary:hover {
        filter: brightness(1.05);
        box-shadow: 0 6px 18px rgba(37, 99, 235, 0.4);
    }
    .hb-booked-btn-secondary {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        padding: 10px 16px;
        font-size: 14px;
        font-weight: 600;
        border-radius: 8px;
        text-decoration: none;
        color: #2563eb;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
    }
    .hb-booked-btn-secondary:hover {
        background: #eff6ff;
        border-color: #93c5fd;
    }
    .hb-booked-modal-x {
        position: absolute;
        top: 10px;
        right: 12px;
        width: 36px;
        height: 36px;
        border: none;
        border-radius: 8px;
        background: #f1f5f9;
        color: #64748b;
        font-size: 1.25rem;
        line-height: 1;
        cursor: pointer;
    }
    .hb-booked-modal-x:hover {
        background: #e2e8f0;
        color: #0f172a;
    }

    @media (max-width: 768px) {
        .form-grid {
            grid-template-columns: 1fr;
        }
        
        .time-slots {
            grid-template-columns: repeat(auto-fill, minmax(70px, 1fr));
        }
        
        .booking-container {
            padding: 20px;
        }
    }
  </style>
</head>
<body class="patient-dashboard-page">
<?php include '../patient/includes/patient_sidebar.php'; ?>

<div class="patient-main-content">
    <header class="patient-header">
        <div class="patient-header-left">
            <h1 class="patient-welcome">Book Appointment</h1>
            <p class="patient-subtitle">Schedule your medical appointment</p>
        </div>
    </header>

    <div class="patient-dashboard-content">
        <?php if (isset($_SESSION['error'])): ?>
            <div style="background: #fee2e2; border-left: 4px solid #dc2626; padding: 15px; border-radius: 8px; margin-bottom: 20px; color: #dc2626;">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
        
        <div class="info-message">
            <i class="fas fa-info-circle"></i> Work through each section — expand <strong>Doctor &amp; schedule</strong> when you are ready to choose a provider and time slot.
        </div>

        <div class="booking-container">
            <p class="sched-flow-intro">
                <i class="fas fa-route" style="color: #3b82f6; margin-right: 8px;" aria-hidden="true"></i>
                Booking is split into short steps so the page stays easy to scan. Nothing is submitted until you press <strong>Book Appointment</strong> at the bottom.
            </p>
            <ul class="sched-flow-steps" aria-label="Booking steps overview">
                <li><span class="sched-flow-n" aria-hidden="true">1</span> Visit details</li>
                <li class="sched-flow-sep" aria-hidden="true">·</li>
                <li><span class="sched-flow-n" aria-hidden="true">2</span> Doctor &amp; time</li>
                <li class="sched-flow-sep" aria-hidden="true">·</li>
                <li><span class="sched-flow-n" aria-hidden="true">3</span> Optional extras</li>
            </ul>

            <form method="POST" id="appointmentForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">

                <details class="sched-flow-card" open>
                    <summary class="sched-flow-card__summary">
                        <span class="sched-flow-card__badge" aria-hidden="true">1</span>
                        <span class="sched-flow-card__summary-text">
                            <span class="sched-flow-card__title">Visit &amp; clinical details</span>
                            <span class="sched-flow-card__sub">Who the visit is for, legal name, birthday, department, and reason for visit</span>
                        </span>
                    </summary>
                    <div class="sched-flow-card__inner">
                <div class="form-group" style="margin-bottom: 22px; padding: 16px 18px; background: #f8fafc; border-radius: 10px; border: 1px solid #e2e8f0;">
                    <span style="display:block; font-weight: 600; margin-bottom: 10px; color: #1e293b;">Who is this appointment for?</span>
                    <label style="display: inline-flex; align-items: center; margin-right: 24px; cursor: pointer; font-weight: 500;">
                        <input type="radio" name="visit_for" value="self" checked style="margin-right: 8px;"> Myself
                    </label>
                    <label style="display: inline-flex; align-items: center; cursor: pointer; font-weight: 500;">
                        <input type="radio" name="visit_for" value="other" style="margin-right: 8px;"> Other
                    </label>
                    <div class="form-group" id="visitRelationshipWrapper" style="margin-top: 12px; display: none;">
                        <label for="visit_relationship"><i class="fas fa-users"></i> Relationship</label>
                        <input type="text" name="visit_relationship" id="visit_relationship" placeholder="e.g Child" style="width: 100%; max-width: 320px; padding: 10px 12px; border: 1.5px solid #e2e8f0; border-radius: 8px; font-size: 14px;">
                    </div>
                    <p style="margin: 10px 0 0 0; font-size: 13px; color: #64748b; line-height: 1.45;">Names that differ from your account are treated as <strong>another person</strong> for this visit (shown to your doctor).</p>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> First Name</label>
                        <input type="text" name="first_name" value="<?= htmlspecialchars($default_first_name) ?>" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Last Name</label>
                        <input type="text" name="last_name" value="<?= htmlspecialchars($default_last_name) ?>" required>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label><i class="fas fa-calendar-alt"></i> Date of Birth *</label>
                        <input type="date" name="date_of_birth" id="date_of_birth" value="<?= htmlspecialchars($default_date_of_birth) ?>" required max="<?= date('Y-m-d') ?>">
                        <small style="color: #64748b; font-size: 12px; margin-top: 5px; display: block;">
                            <i class="fas fa-info-circle"></i> Age will be automatically calculated.
                            <?php if (!empty($dob_prefilled_from_registration)): ?>
                            <span style="display:block;margin-top:4px;">Birthday loaded from your account (registration) — adjust only if needed.</span>
                            <?php endif; ?>
                        </small>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-birthday-cake"></i> Age (Auto-calculated)</label>
                        <input type="number" name="age" id="calculated_age" value="<?= $default_age !== null && $default_age !== '' ? (int) $default_age : '' ?>" readonly style="background: #f8fafc; cursor: not-allowed;" min="1" max="120">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-venus-mars"></i> Gender</label>
                        <select name="gender" required>
                            <option value="">-- Select Gender --</option>
                            <option value="Male" <?= $default_gender === 'Male' ? 'selected' : '' ?>>Male</option>
                            <option value="Female" <?= $default_gender === 'Female' ? 'selected' : '' ?>>Female</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-wheelchair"></i> PWD</label>
                        <select name="is_pwd" id="is_pwd">
                            <option value="no" <?= $default_is_pwd === 'no' ? 'selected' : '' ?>>No</option>
                            <option value="yes" <?= $default_is_pwd === 'yes' ? 'selected' : '' ?>>Yes</option>
                        </select>
                    </div>
                </div>
                <div class="form-group" id="pwdDisabilityWrapper" style="display: <?= $default_is_pwd === 'yes' ? 'block' : 'none' ?>;">
                    <label><i class="fas fa-notes-medical"></i> Type of Disability</label>
                    <input type="text" name="pwd_disability_type" id="pwd_disability_type" value="<?= htmlspecialchars($default_pwd_disability_type) ?>" placeholder="e.g. Visual impairment, Hearing impairment, Orthopedic disability">
                </div>

                <div class="form-group">
                    <label><i class="fas fa-stethoscope"></i> Category</label>
                    <select name="category" id="category" required>
                        <option value="">-- Select Category --</option>
                        <option value="DERMATOLOGY">Dermatology</option>
                        <option value="ORTHOPEDIC SURGERY">Orthopedic Surgery</option>
                        <option value="INTERNAL MEDICINE - GASTROENTEROLOGY">Internal Medicine - Gastroenterology</option>
                    </select>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-clipboard"></i> Health Concern</label>
                    <select name="healthConcern" id="healthConcern" required disabled>
                        <option value="">-- Select Health Concern --</option>
                        <option value="CONSULTATION : DERMATOLOGY - Acne" data-category="DERMATOLOGY">Acne</option>
                        <option value="CONSULTATION : DERMATOLOGY - Psoriasis" data-category="DERMATOLOGY">Psoriasis</option>
                        <option value="CONSULTATION : DERMATOLOGY - Eczema" data-category="DERMATOLOGY">Eczema</option>
                        <option value="CONSULTATION : DERMATOLOGY - Skin Allergies" data-category="DERMATOLOGY">Skin Allergies</option>
                        <option value="CONSULTATION : DERMATOLOGY - Fungal Infections" data-category="DERMATOLOGY">Fungal Infections</option>
                        <option value="CONSULTATION : ORTHOPEDIC SURGERY - Bone Fracture" data-category="ORTHOPEDIC SURGERY">Bone Fracture</option>
                        <option value="CONSULTATION : ORTHOPEDIC SURGERY - Arthritis" data-category="ORTHOPEDIC SURGERY">Arthritis</option>
                        <option value="CONSULTATION : ORTHOPEDIC SURGERY - Sports Injuries" data-category="ORTHOPEDIC SURGERY">Sports Injuries</option>
                        <option value="CONSULTATION : INTERNAL MEDICINE - GASTROENTEROLOGY - Acid Reflux" data-category="INTERNAL MEDICINE - GASTROENTEROLOGY">Acid Reflux</option>
                        <option value="CONSULTATION : INTERNAL MEDICINE - GASTROENTEROLOGY - Stomach Ulcer" data-category="INTERNAL MEDICINE - GASTROENTEROLOGY">Stomach Ulcer</option>
                        <option value="CONSULTATION : INTERNAL MEDICINE - GASTROENTEROLOGY - Hepatitis" data-category="INTERNAL MEDICINE - GASTROENTEROLOGY">Hepatitis</option>
                        <option value="OTHER - " data-category="ALL">Others (Please specify)</option>
                    </select>
                    <div class="form-group" id="othersHealthConcernWrapper" style="margin-top: 15px; display: none;">
                        <label><i class="fas fa-edit"></i> Specify Your Health Concern</label>
                        <input type="text" name="others_health_concern" id="othersHealthConcern" placeholder="Enter your health concern..." style="width: 100%; padding: 12px 15px; border: 1.5px solid #e2e8f0; border-radius: 8px; font-size: 15px;">
                    </div>
                </div>
                    </div>
                </details>

                <details class="sched-flow-card">
                    <summary class="sched-flow-card__summary">
                        <span class="sched-flow-card__badge" aria-hidden="true">2</span>
                        <span class="sched-flow-card__summary-text">
                            <span class="sched-flow-card__title">Doctor, date &amp; time</span>
                            <span class="sched-flow-card__sub">Choose a provider, then pick an available date and slot</span>
                        </span>
                    </summary>
                    <div class="sched-flow-card__inner">

                <div class="form-group" id="doctorSection">
                    <label><i class="fas fa-user-md"></i> Choose Doctor</label>
                    <select name="doctor_id" id="doctorSelect" required>
                        <option value="">-- Select Doctor --</option>
                        <?php
                        while ($doc = $doctorQuery->fetch_assoc()):
                            $rawName = trim($doc['first_name'] . ' ' . $doc['last_name']);
                            $fullName = (isset($doc['email']) && strcasecmp($doc['email'], $cruz_email) === 0)
                                ? ('Dr. ' . $rawName)
                                : $rawName;
                            $is_selected = ($selected_doctor_id && $doc['id'] == $selected_doctor_id);
                        ?>
                            <option value="<?= $doc['id'] ?>" 
                                    data-category="<?= strtoupper($doc['specialization']) ?>" 
                                    data-name="<?= htmlspecialchars($fullName) ?>"
                                    <?= $is_selected ? 'selected' : '' ?>>
                                <?= htmlspecialchars($fullName) ?> — <?= htmlspecialchars($doc['specialization']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="calendar-wrapper">
                    <label style="display: block; font-weight: 600; margin-bottom: 15px; color: #334155;">
                        <i class="fas fa-calendar-alt"></i> Select Date
                    </label>
                    <input type="text" id="calendar" name="date" placeholder="Select Date" required>
                </div>

                <div class="form-group" id="timeSlotSection">
                    <label><i class="fas fa-clock"></i> Select Time</label>
                    <div class="time-slots" id="timeSlots">
                        <!-- Time slots will be generated by JavaScript -->
                    </div>
                    <input type="hidden" name="hour" id="selectedHour" required>
                    <input type="hidden" name="ampm" id="selectedAmpm" required>
                </div>
                    </div>
                </details>

                <?php
                $table_exists = $sched_extra_table;
                $ppe_css_href = '/patient/css/patient_extended_form.css';
                $ppe_hide_outer_heading = true;
                ?>
                <details class="sched-flow-card sched-ppe-wrap">
                    <summary class="sched-flow-card__summary sched-ppe-summary">
                        <span class="sched-flow-card__badge" style="background: linear-gradient(135deg, #64748b 0%, #475569 100%);" aria-hidden="true">3</span>
                        <span class="sched-flow-card__summary-text">
                            <span class="sched-flow-card__title"><i class="fas fa-id-card" aria-hidden="true"></i> Additional details <em style="font-style: normal; font-weight: 500; color: #94a3b8;">(optional)</em></span>
                            <span class="sched-flow-card__sub">Emergency contact, physicians, employment, HMO, consent — updates your saved profile</span>
                        </span>
                    </summary>
                    <div class="sched-ppe-body sched-flow-card__inner">
                        <p class="sched-ppe-note">
                            <i class="fas fa-info-circle" aria-hidden="true"></i>
                            These fields update your <strong>saved patient profile</strong> on file for this account. If you book for someone else, details still attach to your account&rsquo;s patient record unless staff updates them separately.
                        </p>
                        <?php include __DIR__ . '/../patient/partials/patient_extended_profile_fields.php'; ?>
                    </div>
                </details>

                <button type="submit" class="btn-submit" id="submitBtn" disabled>
                    <i class="fas fa-check"></i> Book Appointment
                </button>
            </form>
        </div>
    </div>
</div>

<div id="hbBookedModal" class="hb-booked-modal" role="dialog" aria-modal="true" aria-labelledby="hbBookedModalTitle" aria-hidden="true">
    <div class="hb-booked-modal-backdrop" id="hbBookedModalBackdrop" tabindex="-1"></div>
    <div class="hb-booked-modal-dialog">
        <button type="button" class="hb-booked-modal-x" id="hbBookedModalClose" aria-label="Close">&times;</button>
        <div class="hb-booked-modal-iconwrap">
            <span class="hb-booked-modal-icon" aria-hidden="true"><i class="fas fa-check"></i></span>
        </div>
        <h2 id="hbBookedModalTitle">Appointment recorded</h2>
        <p class="hb-booked-modal-lead">Your request was submitted successfully.</p>
        <p class="hb-booked-modal-note"><i class="fas fa-hourglass-half"></i> Please wait for your provider to confirm.</p>
        <div class="hb-booked-modal-actions">
            <button type="button" class="hb-booked-btn-primary" id="hbBookedModalOk">Continue</button>
            <a href="../patient/patient_dashboard.php" class="hb-booked-btn-secondary">Back to dashboard</a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="/patient/js/patient_sidebar.js"></script>
<script>
const HB_REG_FIRST = <?= json_encode($default_first_name, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const HB_REG_LAST = <?= json_encode($default_last_name, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
(function hbSyncVisitForRadios() {
    function toggleVisitRelationshipField() {
        const otherR = document.querySelector('input[name="visit_for"][value="other"]');
        const wrap = document.getElementById('visitRelationshipWrapper');
        const relInput = document.getElementById('visit_relationship');
        if (!otherR || !wrap || !relInput) return;
        const show = !!otherR.checked;
        wrap.style.display = show ? 'block' : 'none';
        relInput.required = show;
        if (!show) relInput.value = '';
    }
    function syncVisitForFromNames() {
        const f = document.querySelector('input[name="first_name"]');
        const l = document.querySelector('input[name="last_name"]');
        const selfR = document.querySelector('input[name="visit_for"][value="self"]');
        const otherR = document.querySelector('input[name="visit_for"][value="other"]');
        if (!f || !l || !selfR || !otherR) return;
        const match = f.value.trim().toLowerCase() === String(HB_REG_FIRST).trim().toLowerCase()
            && l.value.trim().toLowerCase() === String(HB_REG_LAST).trim().toLowerCase();
        (match ? selfR : otherR).checked = true;
        toggleVisitRelationshipField();
    }
    document.addEventListener('DOMContentLoaded', function() {
        const f = document.querySelector('input[name="first_name"]');
        const l = document.querySelector('input[name="last_name"]');
        const radios = document.querySelectorAll('input[name="visit_for"]');
        if (f) f.addEventListener('input', syncVisitForFromNames);
        if (l) l.addEventListener('input', syncVisitForFromNames);
        radios.forEach(function (r) {
            r.addEventListener('change', toggleVisitRelationshipField);
        });
        syncVisitForFromNames();
        toggleVisitRelationshipField();
    });
})();
// Flatpickr initialization
const calendar = flatpickr("#calendar", {
    altInput: true,
    altFormat: "F j, Y",
    dateFormat: "Y-m-d",
    minDate: "today",
    inline: true,
    defaultDate: "today",
    onChange: function(selectedDates) {
        const doctorSelect = document.getElementById("doctorSelect");
        if (doctorSelect.value) {
            checkDoctorAvailability();
        } else {
            // Show message if no doctor selected yet
            document.getElementById("timeSlots").innerHTML = "<p style='color: #64748b; padding: 20px; text-align: center;'>Please select a doctor first</p>";
        }
    }
});

// Category change handler
document.getElementById("category").addEventListener("change", function() {
    let category = this.value;
    let healthSelect = document.getElementById("healthConcern");
    let doctorSelect = document.getElementById("doctorSelect");

    if (!category) {
        healthSelect.value = "";
        healthSelect.disabled = true;
        return;
    }
    healthSelect.disabled = false;

    let options = healthSelect.querySelectorAll("option");
    options.forEach(opt => {
        if (opt.dataset.category) {
            opt.style.display = (opt.dataset.category === category || opt.dataset.category === "ALL") ? "block" : "none";
        }
    });
    healthSelect.value = "";
    
    // Hide "others" input when changing category
    document.getElementById("othersHealthConcernWrapper").style.display = "none";

    // Filter doctors based on selected category/specialization
    doctorSelect.value = "";
    let doctorOptions = doctorSelect.querySelectorAll("option");
    let hasVisibleDoctors = false;
    
    // Mapping from dropdown categories to database specializations
    const categoryMapping = {
        "DERMATOLOGY": "DERMATOLOGY",
        "ORTHOPEDIC SURGERY": "ORTHOPAEDIC SURGERY",
        "INTERNAL MEDICINE - GASTROENTEROLOGY": "GASTROENTEROLOGY"
    };
    
    const mappedCategory = categoryMapping[category] || category;
    
    doctorOptions.forEach(opt => {
        // Always show the "-- Select Doctor --" option
        if (opt.value === "") {
            opt.style.display = "block";
            return;
        }
        
        // Show/hide doctors based on their specialization matching the selected category
        const doctorCategory = (opt.dataset.category || "").toUpperCase();
        const normalizedCategory = mappedCategory.toUpperCase();
        
        if (doctorCategory === normalizedCategory) {
            opt.style.display = "block";
            hasVisibleDoctors = true;
        } else {
            opt.style.display = "none";
        }
    });
    
    // Clear any selection if no matching doctors are available
    if (!hasVisibleDoctors) {
        doctorSelect.value = "";
    }
    
    // Clear time slots when doctor selection changes
    document.getElementById("timeSlots").innerHTML = "<p style='color: #64748b; padding: 20px; text-align: center;'>Please select a doctor and date to see available times</p>";
    document.getElementById("submitBtn").disabled = true;
});

// Health concern change handler
document.getElementById("healthConcern").addEventListener("change", function() {
    const selectedValue = this.value;
    const othersWrapper = document.getElementById("othersHealthConcernWrapper");
    
    if (selectedValue && selectedValue.startsWith("OTHER -")) {
        othersWrapper.style.display = "block";
        document.getElementById("othersHealthConcern").required = true;
    } else {
        othersWrapper.style.display = "none";
        document.getElementById("othersHealthConcern").required = false;
        document.getElementById("othersHealthConcern").value = "";
    }
});

// 24h (0–23) → 12h label and parts for the form (hour 1–12 + AM/PM)
function hour24To12Parts(hour24) {
    const ampm = hour24 >= 12 ? "PM" : "AM";
    let h = hour24 % 12;
    if (h === 0) h = 12;
    return { label: h + ":00 " + ampm, clockHour: h, ampm: ampm };
}

// Time slot selection - dynamically generate based on doctor's schedule
function generateTimeSlots() {
    const selectedDoctor = document.getElementById("doctorSelect").value;
    const selectedDate = document.getElementById("calendar").value;
    const timeSlotsContainer = document.getElementById("timeSlots");
    
    if (!selectedDoctor || !selectedDate) {
        timeSlotsContainer.innerHTML = "<p style='color: #64748b; padding: 20px; text-align: center;'>Please select a doctor and date</p>";
        return;
    }
    
    // Fetch available times from API
    fetch(`check_availability.php?doctor_id=${selectedDoctor}&date=${selectedDate}`)
        .then(response => response.json())
        .then(data => {
            timeSlotsContainer.innerHTML = "";
            
            if (data.error) {
                timeSlotsContainer.innerHTML = `<p style='color: #dc2626; padding: 20px; text-align: center;'>${data.error}</p>`;
                return;
            }
            
            // Use available hours from doctor's schedule or default to 9-17
            const hours = data.available_hours && data.available_hours.length > 0 
                ? data.available_hours 
                : [9, 10, 11, 12, 13, 14, 15, 16, 17]; // fallback
            let hasAvailableSlots = false;
            
            hours.forEach(hour => {
                const slot = document.createElement("div");
                const isBooked = data.booked_times && data.booked_times.includes(hour);
                const parts = hour24To12Parts(hour);
                
                slot.className = "time-slot";
                slot.dataset.hour = String(parts.clockHour);
                slot.dataset.ampm = parts.ampm;
                slot.dataset.fullHour = hour;
                
                slot.textContent = parts.label;
                
                if (isBooked) {
                    slot.classList.add("unavailable");
                } else {
                    hasAvailableSlots = true;
                    slot.addEventListener("click", function() {
                        document.querySelectorAll(".time-slot.selected").forEach(s => s.classList.remove("selected"));
                        this.classList.add("selected");
                        document.getElementById("selectedHour").value = this.dataset.hour;
                        document.getElementById("selectedAmpm").value = this.dataset.ampm;
                        document.getElementById("submitBtn").disabled = false;
                    });
                }
                
                timeSlotsContainer.appendChild(slot);
            });
            
            if (!hasAvailableSlots) {
                timeSlotsContainer.innerHTML = "<p style='color: #dc2626; padding: 20px; text-align: center;'>No available time slots for this doctor on the selected date</p>";
            }
        })
        .catch(error => {
            console.error("Error fetching availability:", error);
            timeSlotsContainer.innerHTML = "<p style='color: #dc2626; padding: 20px; text-align: center;'>Error loading available times</p>";
        });
}

// Check doctor availability - now integrated into generateTimeSlots
function checkDoctorAvailability() {
    generateTimeSlots();
}

// Initialize on doctor selection
document.getElementById("doctorSelect").addEventListener("change", function() {
    // Show message if no doctor/date selected
    const timeSlotsContainer = document.getElementById("timeSlots");
    if (!this.value || !document.getElementById("calendar").value) {
        timeSlotsContainer.innerHTML = "<p style='color: #64748b; padding: 20px; text-align: center;'>Please select a doctor and date to see available times</p>";
        return;
    }
    checkDoctorAvailability();
});

// Age calculation from date of birth
function calculateAge() {
    const dobInput = document.getElementById('date_of_birth');
    const ageInput = document.getElementById('calculated_age');
    
    if (dobInput && ageInput && dobInput.value) {
        const birthDate = new Date(dobInput.value);
        const today = new Date();
        let age = today.getFullYear() - birthDate.getFullYear();
        const monthDiff = today.getMonth() - birthDate.getMonth();
        
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
            age--;
        }
        
        if (age >= 1 && age <= 120) {
            ageInput.value = age;
        } else {
            ageInput.value = '';
        }
    }
}

// Initialize time slots on page load if doctor is pre-selected
window.addEventListener('DOMContentLoaded', function() {
    function togglePwdDisabilityField() {
        const pwdSelect = document.getElementById('is_pwd');
        const wrapper = document.getElementById('pwdDisabilityWrapper');
        const input = document.getElementById('pwd_disability_type');
        if (!pwdSelect || !wrapper || !input) return;
        const show = pwdSelect.value === 'yes';
        wrapper.style.display = show ? 'block' : 'none';
        input.required = show;
        if (!show) input.value = '';
    }
    const pwdSelect = document.getElementById('is_pwd');
    if (pwdSelect) {
        pwdSelect.addEventListener('change', togglePwdDisabilityField);
        togglePwdDisabilityField();
    }

    // Set up age calculation
    const dobInput = document.getElementById('date_of_birth');
    if (dobInput) {
        // Calculate age on page load if date_of_birth is already set
        if (dobInput.value) {
            calculateAge();
        }
        dobInput.addEventListener('change', calculateAge);
        dobInput.addEventListener('input', calculateAge);
    }
    const doctorSelect = document.getElementById("doctorSelect");
    const dateInput = document.getElementById("calendar").value;
    
    // Show all doctors on initial load
    let doctorOptions = doctorSelect.querySelectorAll("option");
    doctorOptions.forEach(opt => {
        if (opt.value !== "") {
            opt.style.display = "block";
        }
    });
    
    if (doctorSelect.value && dateInput) {
        checkDoctorAvailability();
    } else {
        document.getElementById("timeSlots").innerHTML = "<p style='color: #64748b; padding: 20px; text-align: center;'>Please select a doctor and date to see available times</p>";
    }
    
    // Add form validation before submit
    document.getElementById("appointmentForm").addEventListener("submit", function(e) {
        const healthConcern = document.getElementById("healthConcern").value;
        const othersInput = document.getElementById("othersHealthConcern");
        const pwdSelect = document.getElementById('is_pwd');
        const pwdTypeInput = document.getElementById('pwd_disability_type');
        
        // Validate "Others" option
        if (healthConcern && healthConcern.startsWith("OTHER -")) {
            if (!othersInput.value.trim()) {
                e.preventDefault();
                alert("Please specify your health concern in the text field.");
                othersInput.focus();
                return false;
            }
        }
        if (pwdSelect && pwdSelect.value === 'yes' && pwdTypeInput && !pwdTypeInput.value.trim()) {
            e.preventDefault();
            alert("Please specify the type of disability for PWD.");
            pwdTypeInput.focus();
            return false;
        }
        
        return true;
    });
});
</script>
<script>
(function () {
    function stripBookedQuery() {
        var u = new URL(window.location.href);
        if (u.searchParams.get('booked') !== '1' && u.searchParams.get('success') !== '1') return;
        u.searchParams.delete('booked');
        u.searchParams.delete('success');
        var q = u.searchParams.toString();
        history.replaceState({}, '', u.pathname + (q ? '?' + q : '') + u.hash);
    }
    function closeBookedModal() {
        var m = document.getElementById('hbBookedModal');
        if (!m) return;
        m.classList.remove('is-open');
        m.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }
    function openBookedModal() {
        var m = document.getElementById('hbBookedModal');
        if (!m) return;
        m.classList.add('is-open');
        m.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }
    document.addEventListener('DOMContentLoaded', function () {
        var params = new URLSearchParams(window.location.search);
        if (params.get('booked') === '1' || params.get('success') === '1') {
            openBookedModal();
            stripBookedQuery();
        }
        var bd = document.getElementById('hbBookedModalBackdrop');
        var ok = document.getElementById('hbBookedModalOk');
        var x = document.getElementById('hbBookedModalClose');
        if (bd) bd.addEventListener('click', closeBookedModal);
        if (ok) ok.addEventListener('click', closeBookedModal);
        if (x) x.addEventListener('click', closeBookedModal);
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                var m = document.getElementById('hbBookedModal');
                if (m && m.classList.contains('is-open')) closeBookedModal();
            }
        });
    });
})();
</script>

</body>
</html>
