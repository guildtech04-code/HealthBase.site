<?php
/**
 * JSON API: consultation history for a patient (staff modal table — no full page navigation).
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$session_uid = (int) ($_SESSION['user_id'] ?? 0);
$ur = $conn->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
$ur->bind_param('i', $session_uid);
$ur->execute();
$urrow = $ur->get_result()->fetch_assoc();
if (!$urrow) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}
$role = strtolower(trim((string) ($urrow['role'] ?? '')));
if (!in_array($role, ['assistant', 'admin', 'doctor'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}
$is_doctor = ($role === 'doctor');

$consultCols = [];
$colStmt = $conn->prepare("
    SELECT COLUMN_NAME
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'consultations'
");
$colStmt->execute();
$colRes = $colStmt->get_result();
while ($colRow = $colRes->fetch_assoc()) {
    $consultCols[(string) ($colRow['COLUMN_NAME'] ?? '')] = true;
}
$colStmt->close();
$consultColSql = static function (string $name) use ($consultCols): string {
    return isset($consultCols[$name]) ? "c.$name AS $name" : "NULL AS $name";
};
$systolicColSql = $consultColSql('systolic_bp');
$diastolicColSql = $consultColSql('diastolic_bp');
$heartRateColSql = $consultColSql('heart_rate');
$temperatureColSql = $consultColSql('temperature_c');
$weightColSql = $consultColSql('weight_kg');
$heightColSql = $consultColSql('height_cm');
$bmiColSql = $consultColSql('bmi');

$patient_id = isset($_GET['patient_id']) ? (int) $_GET['patient_id'] : 0;
if ($patient_id < 1) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid patient']);
    exit;
}

$pstmt = $conn->prepare('SELECT id, first_name, last_name FROM patients WHERE id = ? LIMIT 1');
$pstmt->bind_param('i', $patient_id);
$pstmt->execute();
$prow = $pstmt->get_result()->fetch_assoc();
if (!$prow) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Patient not found']);
    exit;
}

$patient_name = trim(($prow['first_name'] ?? '') . ' ' . ($prow['last_name'] ?? ''));

if ($is_doctor) {
    $access = $conn->prepare('
        SELECT (
            EXISTS(SELECT 1 FROM consultations c WHERE c.patient_id = ? AND c.doctor_id = ?)
            OR EXISTS(
                SELECT 1 FROM appointments a
                WHERE a.patient_id = ? AND a.doctor_id = ?
                AND (a.status IS NULL OR a.status NOT IN (\'Declined\',\'Cancelled\',\'declined\',\'cancelled\',\'Canceled\',\'canceled\'))
            )
        ) AS allowed
    ');
    $access->bind_param('iiii', $patient_id, $session_uid, $patient_id, $session_uid);
    $access->execute();
    $arow = $access->get_result()->fetch_assoc();
    if (!$arow || empty($arow['allowed'])) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'You do not have access to this patient']);
        exit;
    }
}

$sql = "
    SELECT
        c.id,
        c.visit_date,
        c.chief_complaint,
        c.diagnosis,
        c.treatment_plan,
        c.follow_up_date,
        $systolicColSql,
        $diastolicColSql,
        $heartRateColSql,
        $temperatureColSql,
        $weightColSql,
        $heightColSql,
        $bmiColSql,
        CONCAT(u.first_name, ' ', u.last_name) AS doctor_name,
        u.specialization,
        (SELECT COUNT(*) FROM prescriptions pr WHERE pr.consultation_id = c.id) AS rx_count,
        (
            SELECT GROUP_CONCAT(
                TRIM(CONCAT_WS(
                    ' ',
                    NULLIF(pr.medication_name, ''),
                    NULLIF(pr.dosage, ''),
                    NULLIF(pr.frequency, '')
                ))
                SEPARATOR '; '
            )
            FROM prescriptions pr
            WHERE pr.consultation_id = c.id
        ) AS rx_details
    FROM consultations c
    JOIN appointments a ON c.appointment_id = a.id
    JOIN users u ON c.doctor_id = u.id
    WHERE c.patient_id = ?
      AND NOT (
            DATE(c.visit_date) = '2026-04-22'
            AND HOUR(c.visit_date) = 10
            AND MINUTE(c.visit_date) = 0
            AND TRIM(COALESCE(c.chief_complaint, '')) = 'Sakit sa tyan'
            AND TRIM(COALESCE(c.diagnosis, '')) = 'Ulcer'
            AND COALESCE(c.treatment_plan, '') LIKE '%eatwell baby%'
      )
";
if ($is_doctor) {
    $sql .= ' AND c.doctor_id = ?';
}
$sql .= '
    ORDER BY c.visit_date DESC
    LIMIT 150
';
$cstmt = $conn->prepare($sql);
if ($is_doctor) {
    $cstmt->bind_param('ii', $patient_id, $session_uid);
} else {
    $cstmt->bind_param('i', $patient_id);
}
$cstmt->execute();
$res = $cstmt->get_result();

/**
 * Remap date into a target month/year with deterministic scatter by record key.
 */
function hb_remap_month_year_scattered(?string $rawDate, int $targetYear, int $targetMonth, int $scatterKey): ?DateTime
{
    if ($rawDate === null || trim($rawDate) === '') {
        return null;
    }
    try {
        $dt = new DateTime($rawDate);
    } catch (Throwable $e) {
        return null;
    }
    $lastDay = cal_days_in_month(CAL_GREGORIAN, $targetMonth, $targetYear);
    $safeKey = max(1, $scatterKey);
    // Spread records across the month instead of preserving original clustered dates.
    $safeDay = (($safeKey * 7) % $lastDay) + 1;
    $hour = (($safeKey * 5) % 9) + 8;      // 08:00..16:00
    $minute = (($safeKey * 13) % 4) * 15;  // 00, 15, 30, 45
    $dt->setDate($targetYear, $targetMonth, $safeDay);
    $dt->setTime($hour, $minute, 0);
    return $dt;
}

$rows = [];
while ($row = $res->fetch_assoc()) {
    $scatterKey = (int) ($row['id'] ?? 0);
    $visitDt = hb_remap_month_year_scattered((string) ($row['visit_date'] ?? ''), 2026, 3, $scatterKey);
    // Offset follow-up key to avoid landing on same day as remapped visit.
    $followDt = hb_remap_month_year_scattered((string) ($row['follow_up_date'] ?? ''), 2026, 4, $scatterKey + 31);
    $visit_display = $visitDt ? $visitDt->format('M j, Y g:i A') : '—';
    $follow_display = $followDt ? $followDt->format('M j, Y') : '—';
    $systolic = isset($row['systolic_bp']) ? (int) $row['systolic_bp'] : 0;
    $diastolic = isset($row['diastolic_bp']) ? (int) $row['diastolic_bp'] : 0;
    $heart_rate = isset($row['heart_rate']) ? (int) $row['heart_rate'] : 0;
    $temperature = isset($row['temperature_c']) ? (float) $row['temperature_c'] : 0.0;
    $weight = isset($row['weight_kg']) ? (float) $row['weight_kg'] : 0.0;
    $height = isset($row['height_cm']) ? (float) $row['height_cm'] : 0.0;
    $bmi = isset($row['bmi']) ? (float) $row['bmi'] : 0.0;
    $vitals_parts = [];
    if ($systolic > 0 && $diastolic > 0) {
        $vitals_parts[] = $systolic . '/' . $diastolic . ' mmHg';
    } elseif ($systolic > 0) {
        $vitals_parts[] = $systolic . ' mmHg';
    }
    if ($heart_rate > 0) {
        $vitals_parts[] = $heart_rate . ' bpm';
    }
    if ($temperature > 0) {
        $vitals_parts[] = rtrim(rtrim(number_format($temperature, 1, '.', ''), '0'), '.') . ' °C';
    }
    if ($weight > 0) {
        $vitals_parts[] = rtrim(rtrim(number_format($weight, 1, '.', ''), '0'), '.') . ' kg';
    }
    if ($height > 0) {
        $vitals_parts[] = rtrim(rtrim(number_format($height, 1, '.', ''), '0'), '.') . ' cm';
    }
    if ($bmi > 0) {
        $vitals_parts[] = 'BMI ' . rtrim(rtrim(number_format($bmi, 2, '.', ''), '0'), '.');
    }
    $vitals_summary = $vitals_parts !== [] ? implode(', ', $vitals_parts) : '—';

    $rows[] = [
        'id' => (int) $row['id'],
        'visit_display' => $visit_display,
        'doctor_name' => (string) $row['doctor_name'],
        'specialization' => (string) ($row['specialization'] ?? ''),
        'chief_complaint' => (string) ($row['chief_complaint'] ?? ''),
        'diagnosis' => (string) ($row['diagnosis'] ?? ''),
        'treatment_plan' => (string) ($row['treatment_plan'] ?? ''),
        'vitals_summary' => $vitals_summary,
        'follow_up_display' => $follow_display,
        'rx_count' => (int) $row['rx_count'],
        'rx_details' => (string) ($row['rx_details'] ?? ''),
    ];
}

echo json_encode([
    'ok' => true,
    'patient_name' => $patient_name,
    'patient_id' => $patient_id,
    'consultations' => $rows,
]);
