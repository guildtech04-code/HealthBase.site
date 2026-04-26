<?php
/**
 * Send patient receipt email after booking an appointment (scheduling flow).
 * Failures are logged; booking is never blocked.
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/appointment_schema_flags.php';

/**
 * @return bool True if mail was sent successfully
 */
function hb_send_patient_appointment_receipt_email(mysqli $conn, int $appointmentId, ?string $bookedRelationship = null): bool
{
    if ($appointmentId < 1) {
        return false;
    }

    $flags = hb_appointments_column_flags($conn);
    $extraCols = '';
    if ($flags['guest']) {
        $extraCols .= ', IFNULL(a.guest_first_name, \'\') AS gfp, IFNULL(a.guest_last_name, \'\') AS glp';
    }
    if ($flags['notes']) {
        $extraCols .= ', a.notes AS anotes';
    }

    $stmt = $conn->prepare('
        SELECT a.appointment_date, a.status,
               p.first_name AS pf, p.last_name AS pl, p.health_concern
               ' . $extraCols . '
               , u.email AS patient_email,
               du.first_name AS df, du.last_name AS dl, COALESCE(du.specialization, \'\') AS spec
        FROM appointments a
        INNER JOIN patients p ON p.id = a.patient_id
        INNER JOIN users u ON u.id = p.user_id
        INNER JOIN users du ON du.id = a.doctor_id
        WHERE a.id = ?
        LIMIT 1
    ');
    if (!$stmt) {
        error_log('appointment_email_helper: prepare failed: ' . $conn->error);

        return false;
    }
    $stmt->bind_param('i', $appointmentId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return false;
    }

    $to = trim((string) ($row['patient_email'] ?? ''));
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        error_log("appointment_email_helper: invalid or missing patient email for appointment {$appointmentId}");

        return false;
    }

    $patientName = trim(($row['pf'] ?? '') . ' ' . ($row['pl'] ?? ''));
    if ($patientName === '') {
        $patientName = 'Patient';
    }

    $visitFor = '';
    $visitTypeLabel = '';
    if ($flags['guest']) {
        $visitFor = trim(($row['gfp'] ?? '') . ' ' . ($row['glp'] ?? ''));
        $visitFor = trim($visitFor);
        if ($visitFor !== '') {
            $visitTypeLabel = 'Other';
        }
    }

    $doctorName = trim('Dr. ' . ($row['df'] ?? '') . ' ' . ($row['dl'] ?? ''));
    $spec = trim((string) ($row['spec'] ?? ''));
    $health = '';
    if ($visitFor !== '') {
        $health = ($flags['notes'] ? trim((string) ($row['anotes'] ?? '')) : '');
    } elseif ($flags['notes'] && trim((string) ($row['anotes'] ?? '')) !== '') {
        $health = trim((string) $row['anotes']);
    } else {
        $health = trim((string) ($row['health_concern'] ?? ''));
    }
    $statusRaw = strtolower(trim((string) ($row['status'] ?? 'pending')));
    $statusLabel = $statusRaw === 'pending'
        ? 'For Approval'
        : ucfirst($statusRaw);

    $apptTs = strtotime((string) $row['appointment_date']);
    $when = $apptTs ? date('l, F j, Y \a\t g:i A', $apptTs) : (string) $row['appointment_date'];

    $host = $_SERVER['HTTP_HOST'] ?? 'healthbase.site';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $base = $scheme . '://' . $host;
    $appointmentsUrl = $base . '/patient/patient_appointments.php';

    $subject = 'HealthBase — Appointment booking receipt';
    $bookedRelationship = trim((string) $bookedRelationship);

    $html = '
<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;line-height:1.6;color:#1e293b;">
  <p>Hello <strong>' . htmlspecialchars($patientName, ENT_QUOTES, 'UTF-8') . '</strong>,</p>
  <p>Your appointment request was received successfully. Here is your receipt:</p>
  <table style="border-collapse:collapse;margin:16px 0;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px;max-width:520px;">
    <tr><td style="padding:4px 8px;color:#64748b;">Appointment Number</td><td style="padding:4px 8px;font-weight:600;">#' . (int) $appointmentId . '</td></tr>
    <tr><td style="padding:4px 8px;color:#64748b;">Booked by</td><td style="padding:4px 8px;">' . htmlspecialchars($patientName, ENT_QUOTES, 'UTF-8') . '</td></tr>
    <tr><td style="padding:4px 8px;color:#64748b;">Doctor in Charge</td><td style="padding:4px 8px;">' . htmlspecialchars($doctorName, ENT_QUOTES, 'UTF-8') . ($spec !== '' ? ' — ' . htmlspecialchars($spec, ENT_QUOTES, 'UTF-8') : '') . '</td></tr>
    <tr><td style="padding:4px 8px;color:#64748b;">Visit date &amp; time</td><td style="padding:4px 8px;font-weight:600;">' . htmlspecialchars($when, ENT_QUOTES, 'UTF-8') . '</td></tr>
    ' . ($visitTypeLabel !== '' ? '<tr><td style="padding:4px 8px;color:#64748b;">Visit type</td><td style="padding:4px 8px;">' . htmlspecialchars($visitTypeLabel, ENT_QUOTES, 'UTF-8') . '</td></tr>' : '') . '
    ' . ($bookedRelationship !== '' ? '<tr><td style="padding:4px 8px;color:#64748b;">Relationship</td><td style="padding:4px 8px;">' . htmlspecialchars($bookedRelationship, ENT_QUOTES, 'UTF-8') . '</td></tr>' : '') . '
    <tr><td style="padding:4px 8px;color:#64748b;">Status</td><td style="padding:4px 8px;">' . htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') . '</td></tr>'
        . ($health !== '' ? '<tr><td style="padding:4px 8px;color:#64748b;vertical-align:top;">Health concern</td><td style="padding:4px 8px;">' . htmlspecialchars($health, ENT_QUOTES, 'UTF-8') . '</td></tr>' : '') . '
  </table>
  <p style="font-size:14px;color:#64748b;">You can review your appointments anytime:</p>
  <p><a href="' . htmlspecialchars($appointmentsUrl, ENT_QUOTES, 'UTF-8') . '" style="color:#2563eb;">View my appointments</a></p>
  <p style="font-size:13px;color:#94a3b8;margin-top:24px;">This is an automated message from HealthBase. If you did not book this appointment, please contact support.</p>
</body></html>';

    $plain = "Hello {$patientName},\n\n"
        . "Your appointment request was received.\n\n"
        . "Appointment Number: #{$appointmentId}\n"
        . "Booked by: {$patientName}\n"
        . "Doctor in Charge: {$doctorName}" . ($spec !== '' ? " ({$spec})" : '') . "\n"
        . "Visit: {$when}\n"
        . ($visitTypeLabel !== '' ? "Visit type: {$visitTypeLabel}\n" : '')
        . ($bookedRelationship !== '' ? "Relationship: {$bookedRelationship}\n" : '')
        . "Status: {$statusLabel}\n"
        . ($health !== '' ? "Health concern: {$health}\n" : '')
        . "\nView appointments: {$appointmentsUrl}\n";

    require_once __DIR__ . '/../PHPMailer/src/Exception.php';
    require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
    require_once __DIR__ . '/../PHPMailer/src/SMTP.php';

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
        error_log('appointment_email_helper send failed: ' . $mail->ErrorInfo);

        return false;
    }
}
