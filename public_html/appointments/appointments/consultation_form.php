<?php
// consultation_form.php - EHR Interface for Doctors to Record Consultations
error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/../includes/auth_guard.php';
ensure_role(['doctor']);
include("../config/db_connect.php");
require_once __DIR__ . '/../includes/security.php';

$user_id = $_SESSION['user_id'];
$appointment_id = isset($_GET['appointment_id']) ? intval($_GET['appointment_id']) : 0;

$is_modal_embed = ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['modal_embed']))
    || (isset($_GET['modal']) && $_GET['modal'] === '1');

/** @return string query suffix to preserve modal embed */
function hb_consult_form_modal_qs(bool $modal): string
{
    return $modal ? '&modal=1' : '';
}

// Fetch appointment details
$stmt = $conn->prepare("
    SELECT a.id, a.appointment_date, a.status, a.patient_id,
           p.first_name, p.last_name, p.age, p.gender
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    WHERE a.id = ? AND a.doctor_id = ?
");
$stmt->bind_param("ii", $appointment_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$appointment = $result->fetch_assoc();

if (!$appointment) {
    if ($is_modal_embed) {
        header('Location: ehr_consultation_iframe_done.php?error=appointment_not_found');
    } else {
        header('Location: appointments.php?error=appointment_not_found');
    }
    exit();
}

$doctorSpecialization = 'General';
$docSpecStmt = $conn->prepare("SELECT specialization FROM users WHERE id = ? LIMIT 1");
if ($docSpecStmt) {
    $docSpecStmt->bind_param("i", $user_id);
    if ($docSpecStmt->execute()) {
        $docSpecRow = $docSpecStmt->get_result()->fetch_assoc();
        if (!empty($docSpecRow['specialization'])) {
            $doctorSpecialization = trim((string) $docSpecRow['specialization']);
        }
    }
    $docSpecStmt->close();
}

// Cancelled / declined visits cannot accept a consultation on this row — send user to book a new appointment instead
$apptStatusLower = strtolower((string) ($appointment['status'] ?? ''));
if (in_array($apptStatusLower, ['cancelled', 'declined'], true)) {
    $pid = (int) ($appointment['patient_id'] ?? 0);
    $did = (int) ($user_id);
    if ($is_modal_embed) {
        header('Location: ehr_consultation_iframe_done.php?error=appointment_cancelled');
    } else {
        header('Location: ../assistant_view/assistant_appointments.php?create=true&patient_id=' . $pid . '&doctor_id=' . $did);
    }
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'record_consultation') {
    require_post_csrf();
    
    // Validate appointment status - only allow consultation for confirmed or pending appointments
    if (!in_array(strtolower($appointment['status']), ['confirmed', 'pending'])) {
        if ($is_modal_embed) {
            header('Location: ehr_consultation_iframe_done.php?error=invalid_appointment_status');
        } else {
            header('Location: appointments.php?error=invalid_appointment_status');
        }
        exit();
    }
    
    // Check if consultation already exists for this appointment.
    // Assistant workflows may pre-create a draft row, so we should update it instead of blocking.
    $check_consultation = $conn->prepare("SELECT id FROM consultations WHERE appointment_id = ? LIMIT 1");
    $check_consultation->bind_param("i", $appointment_id);
    $check_consultation->execute();
    $existing_consultation = $check_consultation->get_result()->fetch_assoc();
    $check_consultation->close();
    $consultation_id = (int) ($existing_consultation['id'] ?? 0);
    
    $chief_complaint = trim($_POST['chief_complaint'] ?? '');
    $consultation_notes = trim($_POST['consultation_notes'] ?? '');
    $diagnosis = trim($_POST['diagnosis'] ?? '');
    $treatment_plan = trim($_POST['treatment_plan'] ?? '');
    $report_summary = trim($_POST['report_summary'] ?? '');
    $follow_up_date = !empty($_POST['follow_up_date']) ? $_POST['follow_up_date'] : null;
    $consultation_status = trim($_POST['consultation_status'] ?? 'Pending');
    if (!in_array($consultation_status, ['Pending', 'Cleared', 'Cancelled'])) {
        $consultation_status = 'Pending';
    }

    // Laboratory results (enforce specialization server-side)
    $lab_specialization = $doctorSpecialization;
    $lab_type = trim((string) ($_POST['lab_type'] ?? ''));
    $lab_result_summary = trim((string) ($_POST['lab_result_summary'] ?? ''));
    $derma_skin_scraping_koh = trim((string) ($_POST['derma_skin_scraping_koh'] ?? ''));
    $derma_patch_test_result = trim((string) ($_POST['derma_patch_test_result'] ?? ''));
    $derma_allergy_panel_result = trim((string) ($_POST['derma_allergy_panel_result'] ?? ''));
    $gastro_stool_exam_result = trim((string) ($_POST['gastro_stool_exam_result'] ?? ''));
    $gastro_h_pylori_test_result = trim((string) ($_POST['gastro_h_pylori_test_result'] ?? ''));
    $gastro_abdominal_ultrasound_result = trim((string) ($_POST['gastro_abdominal_ultrasound_result'] ?? ''));
    $ortho_xray_result = trim((string) ($_POST['ortho_xray_result'] ?? ''));
    $ortho_mri_result = trim((string) ($_POST['ortho_mri_result'] ?? ''));
    $ortho_bone_density_result = trim((string) ($_POST['ortho_bone_density_result'] ?? ''));
    
    // Vital signs
    $blood_pressure = trim((string) ($_POST['blood_pressure'] ?? ''));
    $systolic_bp = null;
    $diastolic_bp = null;
    if ($blood_pressure !== '' && preg_match('/^\s*(\d{2,3})\s*\/\s*(\d{2,3})\s*$/', $blood_pressure, $bpParts)) {
        $sys = (int) $bpParts[1];
        $dia = (int) $bpParts[2];
        if ($sys >= 50 && $sys <= 250 && $dia >= 30 && $dia <= 150) {
            $systolic_bp = $sys;
            $diastolic_bp = $dia;
        }
    }
    $heart_rate = !empty($_POST['heart_rate']) ? intval($_POST['heart_rate']) : null;
    $temperature_c = !empty($_POST['temperature_c']) ? floatval($_POST['temperature_c']) : null;
    $respiratory_rate = null;
    $oxygen_saturation = null;
    $weight_kg = !empty($_POST['weight_kg']) ? floatval($_POST['weight_kg']) : null;
    $height_cm = !empty($_POST['height_cm']) ? floatval($_POST['height_cm']) : null;
    $bmi = null;
    
    // Calculate BMI if weight and height are provided
    if ($weight_kg && $height_cm && $height_cm > 0) {
        $height_m = $height_cm / 100;
        $bmi = round($weight_kg / ($height_m * $height_m), 2);
    }
    
    // Validate required fields
    if (empty($chief_complaint) || empty($consultation_notes) || empty($diagnosis)) {
        header('Location: consultation_form.php?appointment_id=' . $appointment_id . '&error=required_fields' . hb_consult_form_modal_qs($is_modal_embed));
        exit();
    }
    
    // Ensure laboratory results table exists BEFORE transaction.
    // DDL can implicitly commit in MySQL, so keep it outside transactional writes.
    $createLabTableSql = "
        CREATE TABLE IF NOT EXISTS consultation_laboratory_results (
            id INT AUTO_INCREMENT PRIMARY KEY,
            consultation_id INT NOT NULL,
            appointment_id INT NOT NULL,
            patient_id INT NOT NULL,
            doctor_id INT NOT NULL,
            specialization VARCHAR(120) DEFAULT NULL,
            lab_type VARCHAR(120) DEFAULT NULL,
            lab_result_summary TEXT DEFAULT NULL,
            derma_skin_scraping_koh TEXT DEFAULT NULL,
            derma_patch_test_result TEXT DEFAULT NULL,
            derma_allergy_panel_result TEXT DEFAULT NULL,
            gastro_stool_exam_result TEXT DEFAULT NULL,
            gastro_h_pylori_test_result TEXT DEFAULT NULL,
            gastro_abdominal_ultrasound_result TEXT DEFAULT NULL,
            ortho_xray_result TEXT DEFAULT NULL,
            ortho_mri_result TEXT DEFAULT NULL,
            ortho_bone_density_result TEXT DEFAULT NULL,
            attachment_path VARCHAR(255) DEFAULT NULL,
            attachment_name VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_consultation_lab (consultation_id),
            KEY idx_lab_appointment (appointment_id),
            KEY idx_lab_patient (patient_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    if (!$conn->query($createLabTableSql)) {
        header('Location: consultation_form.php?appointment_id=' . $appointment_id . '&error=save_failed' . hb_consult_form_modal_qs($is_modal_embed));
        exit();
    }

    // Begin transaction for data integrity
    $conn->begin_transaction();

    // Detect available consultation columns (for backward-compatible save on older schemas).
    $consultCols = [];
    $ccStmt = $conn->prepare("
        SELECT COLUMN_NAME
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'consultations'
    ");
    $ccStmt->execute();
    $ccRs = $ccStmt->get_result();
    while ($ccRow = $ccRs->fetch_assoc()) {
        $consultCols[(string) $ccRow['COLUMN_NAME']] = true;
    }
    $ccStmt->close();
    $hasCol = static function (string $name) use ($consultCols): bool {
        return isset($consultCols[$name]);
    };

    $bindExec = static function (mysqli_stmt $stmt, string $types, array $values): bool {
        $bind = array_merge([$types], $values);
        $refs = [];
        foreach ($bind as $k => &$v) {
            $refs[$k] = &$v;
        }
        if (!call_user_func_array([$stmt, 'bind_param'], $refs)) {
            return false;
        }

        return $stmt->execute();
    };
    
    try {
        if ($consultation_id > 0) {
            // Update draft/existing consultation for this appointment.
            $setParts = [
                'patient_id = ?', 'doctor_id = ?', 'visit_date = ?',
                'chief_complaint = ?', 'consultation_notes = ?', 'diagnosis = ?', 'treatment_plan = ?',
            ];
            $types = 'iisssss';
            $values = [
                $appointment['patient_id'],
                $user_id,
                $appointment['appointment_date'],
                $chief_complaint,
                $consultation_notes,
                $diagnosis,
                $treatment_plan,
            ];

            $optional = [
                'report_summary' => ['type' => 's', 'value' => $report_summary],
                'follow_up_date' => ['type' => 's', 'value' => $follow_up_date],
                'consultation_status' => ['type' => 's', 'value' => $consultation_status],
                'systolic_bp' => ['type' => 'i', 'value' => $systolic_bp],
                'diastolic_bp' => ['type' => 'i', 'value' => $diastolic_bp],
                'heart_rate' => ['type' => 'i', 'value' => $heart_rate],
                'temperature_c' => ['type' => 'd', 'value' => $temperature_c],
                'respiratory_rate' => ['type' => 'i', 'value' => $respiratory_rate],
                'oxygen_saturation' => ['type' => 'i', 'value' => $oxygen_saturation],
                'weight_kg' => ['type' => 'd', 'value' => $weight_kg],
                'height_cm' => ['type' => 'd', 'value' => $height_cm],
                'bmi' => ['type' => 'd', 'value' => $bmi],
            ];
            foreach ($optional as $col => $meta) {
                if ($hasCol($col)) {
                    $setParts[] = $col . ' = ?';
                    $types .= $meta['type'];
                    $values[] = $meta['value'];
                }
            }

            $sql = "UPDATE consultations SET " . implode(', ', $setParts) . " WHERE id = ? AND appointment_id = ?";
            $types .= 'ii';
            $values[] = $consultation_id;
            $values[] = $appointment_id;

            $stmt = $conn->prepare($sql);
            if (!$stmt || !$bindExec($stmt, $types, $values)) {
                throw new Exception("Failed to update consultation: " . ($stmt ? $stmt->error : $conn->error));
            }
        } else {
            // Insert consultation record (works with both legacy and enhanced schemas).
            $cols = [
                'appointment_id', 'patient_id', 'doctor_id', 'visit_date',
                'chief_complaint', 'consultation_notes', 'diagnosis', 'treatment_plan',
            ];
            $types = 'iiiissss';
            $values = [
                $appointment_id,
                $appointment['patient_id'],
                $user_id,
                $appointment['appointment_date'],
                $chief_complaint,
                $consultation_notes,
                $diagnosis,
                $treatment_plan,
            ];

            $optional = [
                'report_summary' => ['type' => 's', 'value' => $report_summary],
                'follow_up_date' => ['type' => 's', 'value' => $follow_up_date],
                'consultation_status' => ['type' => 's', 'value' => $consultation_status],
                'systolic_bp' => ['type' => 'i', 'value' => $systolic_bp],
                'diastolic_bp' => ['type' => 'i', 'value' => $diastolic_bp],
                'heart_rate' => ['type' => 'i', 'value' => $heart_rate],
                'temperature_c' => ['type' => 'd', 'value' => $temperature_c],
                'respiratory_rate' => ['type' => 'i', 'value' => $respiratory_rate],
                'oxygen_saturation' => ['type' => 'i', 'value' => $oxygen_saturation],
                'weight_kg' => ['type' => 'd', 'value' => $weight_kg],
                'height_cm' => ['type' => 'd', 'value' => $height_cm],
                'bmi' => ['type' => 'd', 'value' => $bmi],
            ];
            foreach ($optional as $col => $meta) {
                if ($hasCol($col)) {
                    $cols[] = $col;
                    $types .= $meta['type'];
                    $values[] = $meta['value'];
                }
            }

            $placeholders = implode(', ', array_fill(0, count($cols), '?'));
            $sql = "INSERT INTO consultations (" . implode(', ', $cols) . ") VALUES ($placeholders)";
            $stmt = $conn->prepare($sql);
            if (!$stmt || !$bindExec($stmt, $types, $values)) {
                throw new Exception("Failed to insert consultation: " . ($stmt ? $stmt->error : $conn->error));
            }
            $consultation_id = (int) $stmt->insert_id;
        }
        $stmt->close();

        $existingAttachmentPath = null;
        $existingAttachmentName = null;
        $existingLabStmt = $conn->prepare("SELECT attachment_path, attachment_name FROM consultation_laboratory_results WHERE consultation_id = ? LIMIT 1");
        if ($existingLabStmt) {
            $existingLabStmt->bind_param("i", $consultation_id);
            if ($existingLabStmt->execute()) {
                $existingLab = $existingLabStmt->get_result()->fetch_assoc();
                $existingAttachmentPath = $existingLab['attachment_path'] ?? null;
                $existingAttachmentName = $existingLab['attachment_name'] ?? null;
            }
            $existingLabStmt->close();
        }

        $labAttachmentPath = $existingAttachmentPath;
        $labAttachmentName = $existingAttachmentName;
        if (isset($_FILES['lab_attachment']) && is_array($_FILES['lab_attachment']) && ($_FILES['lab_attachment']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $uploadError = (int) ($_FILES['lab_attachment']['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($uploadError !== UPLOAD_ERR_OK) {
                throw new Exception("Lab file upload failed with code " . $uploadError);
            }

            $tmpName = (string) ($_FILES['lab_attachment']['tmp_name'] ?? '');
            $origName = trim((string) ($_FILES['lab_attachment']['name'] ?? ''));
            $fileSize = (int) ($_FILES['lab_attachment']['size'] ?? 0);
            $extension = strtolower((string) pathinfo($origName, PATHINFO_EXTENSION));
            $allowedExt = ['pdf', 'png', 'jpg', 'jpeg', 'webp', 'doc', 'docx'];
            if ($fileSize > (8 * 1024 * 1024)) {
                throw new Exception("Lab attachment must be 8MB or less.");
            }
            if ($origName === '' || !in_array($extension, $allowedExt, true)) {
                throw new Exception("Invalid lab attachment type. Allowed: PDF, PNG, JPG, WEBP, DOC, DOCX.");
            }
            if (!is_uploaded_file($tmpName)) {
                throw new Exception("Invalid uploaded lab attachment.");
            }

            $safeBaseName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($origName, PATHINFO_FILENAME));
            $safeBaseName = $safeBaseName !== '' ? $safeBaseName : 'lab_file';
            $uploadDir = __DIR__ . '/../uploads/laboratory_results';
            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
                throw new Exception("Failed to create laboratory upload directory.");
            }

            $finalName = 'lab_' . $appointment_id . '_' . $consultation_id . '_' . time() . '_' . $safeBaseName . '.' . $extension;
            $targetPath = $uploadDir . '/' . $finalName;
            if (!move_uploaded_file($tmpName, $targetPath)) {
                throw new Exception("Failed to save lab attachment.");
            }

            $labAttachmentPath = 'uploads/laboratory_results/' . $finalName;
            $labAttachmentName = $origName;
        }

        $labUpsert = $conn->prepare("
            INSERT INTO consultation_laboratory_results (
                consultation_id, appointment_id, patient_id, doctor_id,
                specialization, lab_type, lab_result_summary,
                derma_skin_scraping_koh, derma_patch_test_result, derma_allergy_panel_result,
                gastro_stool_exam_result, gastro_h_pylori_test_result, gastro_abdominal_ultrasound_result,
                ortho_xray_result, ortho_mri_result, ortho_bone_density_result,
                attachment_path, attachment_name
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                appointment_id = VALUES(appointment_id),
                patient_id = VALUES(patient_id),
                doctor_id = VALUES(doctor_id),
                specialization = VALUES(specialization),
                lab_type = VALUES(lab_type),
                lab_result_summary = VALUES(lab_result_summary),
                derma_skin_scraping_koh = VALUES(derma_skin_scraping_koh),
                derma_patch_test_result = VALUES(derma_patch_test_result),
                derma_allergy_panel_result = VALUES(derma_allergy_panel_result),
                gastro_stool_exam_result = VALUES(gastro_stool_exam_result),
                gastro_h_pylori_test_result = VALUES(gastro_h_pylori_test_result),
                gastro_abdominal_ultrasound_result = VALUES(gastro_abdominal_ultrasound_result),
                ortho_xray_result = VALUES(ortho_xray_result),
                ortho_mri_result = VALUES(ortho_mri_result),
                ortho_bone_density_result = VALUES(ortho_bone_density_result),
                attachment_path = VALUES(attachment_path),
                attachment_name = VALUES(attachment_name)
        ");
        if (!$labUpsert) {
            throw new Exception("Failed to prepare lab upsert: " . $conn->error);
        }
        $labUpsert->bind_param(
            "iiiissssssssssssss",
            $consultation_id,
            $appointment_id,
            $appointment['patient_id'],
            $user_id,
            $lab_specialization,
            $lab_type,
            $lab_result_summary,
            $derma_skin_scraping_koh,
            $derma_patch_test_result,
            $derma_allergy_panel_result,
            $gastro_stool_exam_result,
            $gastro_h_pylori_test_result,
            $gastro_abdominal_ultrasound_result,
            $ortho_xray_result,
            $ortho_mri_result,
            $ortho_bone_density_result,
            $labAttachmentPath,
            $labAttachmentName
        );
        if (!$labUpsert->execute()) {
            throw new Exception("Failed to save laboratory results: " . $labUpsert->error);
        }
        $labUpsert->close();
        
        // Handle prescriptions with validation.
        // For edits, replace previous prescription lines only if payload is present.
        $hasPrescriptionPayload = isset($_POST['prescriptions']) && is_array($_POST['prescriptions']);
        if ($consultation_id > 0 && $hasPrescriptionPayload) {
            $del_rx = $conn->prepare("DELETE FROM prescriptions WHERE consultation_id = ?");
            $del_rx->bind_param("i", $consultation_id);
            if (!$del_rx->execute()) {
                throw new Exception("Failed to clear prescriptions: " . $del_rx->error);
            }
            $del_rx->close();
        }
        if ($hasPrescriptionPayload) {
            foreach ($_POST['prescriptions'] as $presc) {
                $medication_name = trim($presc['medication_name'] ?? '');
                if (!empty($medication_name)) {
                    // Validate prescription data
                    $dosage = trim($presc['dosage'] ?? '');
                    $frequency = trim($presc['frequency'] ?? '');
                    $duration = trim($presc['duration'] ?? '');
                    $instructions = trim($presc['instructions'] ?? '');
                    $quantity = trim($presc['quantity'] ?? '');
                    
                    // Validate quantity is positive if provided
                    if (!empty($quantity) && is_numeric($quantity) && intval($quantity) <= 0) {
                        continue; // Skip invalid prescriptions
                    }
                    
                    $stmt = $conn->prepare("
                        INSERT INTO prescriptions 
                        (consultation_id, appointment_id, patient_id, doctor_id, medication_name, dosage, frequency, duration, instructions, quantity)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->bind_param("iiiissssss", $consultation_id, $appointment_id, $appointment['patient_id'], $user_id,
                        $medication_name, $dosage, $frequency, $duration, $instructions, $quantity);
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to insert prescription: " . $stmt->error);
                    }
                    $stmt->close();
                }
            }
        }
        
        // Update appointment status to completed (standardized to lowercase)
        $stmt = $conn->prepare("UPDATE appointments SET status='completed' WHERE id=?");
        $stmt->bind_param("i", $appointment_id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to update appointment status: " . $stmt->error);
        }
        $stmt->close();
        
        // If follow-up date is set, create a follow-up appointment automatically
        if ($follow_up_date) {
            // Get the original appointment time to use for follow-up
            $time_stmt = $conn->prepare("SELECT TIME(appointment_date) as appointment_time FROM appointments WHERE id = ?");
            $time_stmt->bind_param("i", $appointment_id);
            $time_stmt->execute();
            $time_result = $time_stmt->get_result();
            $time_row = $time_result->fetch_assoc();
            $appointment_time = $time_row['appointment_time'] ?? '09:00:00';
            $time_stmt->close();
            
            // Combine follow-up date with original appointment time
            $follow_up_datetime = $follow_up_date . ' ' . $appointment_time;
            
            // Check if follow-up appointment already exists
            $check_followup = $conn->prepare("
                SELECT id FROM appointments 
                WHERE patient_id = ? AND doctor_id = ? AND DATE(appointment_date) = ? 
                AND status NOT IN ('Cancelled', 'declined')
                LIMIT 1
            ");
            $check_followup->bind_param("iis", $appointment['patient_id'], $user_id, $follow_up_date);
            $check_followup->execute();
            $followup_exists = $check_followup->get_result()->num_rows > 0;
            $check_followup->close();
            
            // Create follow-up appointment if it doesn't exist
            if (!$followup_exists) {
                $followup_notes = "Follow-up appointment from consultation #" . $consultation_id;
                $followup_stmt = $conn->prepare("
                    INSERT INTO appointments (doctor_id, patient_id, appointment_date, status, notes)
                    VALUES (?, ?, ?, 'Confirmed', ?)
                ");
                $followup_stmt->bind_param("iiss", $user_id, $appointment['patient_id'], $follow_up_datetime, $followup_notes);
                if (!$followup_stmt->execute()) {
                    // Log error but don't fail the transaction
                    error_log("Failed to create follow-up appointment: " . $followup_stmt->error);
                }
                $followup_stmt->close();
            }
        }
        
        // Commit transaction
        $conn->commit();
        
        if ($is_modal_embed) {
            header('Location: ehr_consultation_iframe_done.php');
        } else {
            header('Location: appointments.php?success=consultation_recorded');
        }
        exit();
        
    } catch (Exception $e) {
        // Rollback on any error
        $conn->rollback();
        error_log('Consultation recording error: ' . $e->getMessage());
        header('Location: consultation_form.php?appointment_id=' . $appointment_id . '&error=save_failed' . hb_consult_form_modal_qs($is_modal_embed));
        exit();
    }
}

// Get user info for sidebar
$query = $conn->prepare("SELECT username, email, specialization FROM users WHERE id = ?");
$query->bind_param("i", $user_id);
$query->execute();
$user_result = $query->get_result();
$user = $user_result->fetch_assoc();

$sidebar_user_data = [
    'username' => htmlspecialchars($user['username']),
    'email' => htmlspecialchars($user['email']),
    'role' => 'doctor',
    'specialization' => htmlspecialchars($user['specialization'] ?? 'General')
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Record Consultation - HealthBase</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="appointments.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .consultation-form-container {
            max-width: 1000px;
            margin: 20px auto;
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        
        .form-header {
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .form-header h2 {
            color: #1e293b;
            font-size: 24px;
            margin: 0 0 10px;
        }
        
        .form-header .patient-info {
            color: #64748b;
            font-size: 14px;
        }
        
        .form-section {
            margin-bottom: 30px;
        }
        
        .form-section h3 {
            color: #3b82f6;
            font-size: 18px;
            margin-bottom: 15px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            color: #475569;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .form-group input[type="text"],
        .form-group input[type="date"],
        .form-group input[type="number"],
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1.5px solid #cbd5e1;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.2s;
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .vitals-grid .form-group input {
            min-height: 44px;
            box-sizing: border-box;
        }

        .vitals-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px 15px;
            align-items: start;
        }

        .vitals-grid .form-group {
            margin-bottom: 0;
        }

        .vitals-grid .form-group small {
            display: block;
            margin-top: 6px;
            color: #64748b;
            font-size: 12px;
            line-height: 1.35;
        }

        .vitals-grid .vitals-span-3 {
            grid-column: span 3;
        }

        .vitals-grid #bmi-display {
            margin-top: 0 !important;
        }

        @media (max-width: 900px) {
            .vitals-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .vitals-grid .vitals-span-3 {
                grid-column: span 2;
            }
        }

        @media (max-width: 560px) {
            .vitals-grid {
                grid-template-columns: 1fr;
            }

            .vitals-grid .vitals-span-3 {
                grid-column: span 1;
            }
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .prescription-row {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border: 1px solid #e2e8f0;
        }
        
        .prescription-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        
        @media (max-width: 768px) {
            .prescription-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .btn-add-prescription {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            margin-top: 10px;
        }
        
        .btn-remove-prescription {
            background: #ef4444;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .btn-submit {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            border: none;
            padding: 14px 30px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 16px;
            width: 100%;
            margin-top: 20px;
        }
        
        .btn-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(59, 130, 246, 0.3);
        }

        .lab-specialization-tabs {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }

        .lab-tab-chip {
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            color: #334155;
            border-radius: 999px;
            padding: 7px 14px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.01em;
        }

        .lab-tab-chip.is-active {
            background: #dbeafe;
            border-color: #93c5fd;
            color: #1d4ed8;
        }

        .lab-fieldset {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 14px;
            background: #f8fafc;
            margin-top: 10px;
        }

        .lab-fieldset.is-hidden {
            display: none;
        }

        .lab-note {
            margin: 4px 0 0;
            color: #64748b;
            font-size: 12px;
        }

        body.consultation-form-embed #doctorSidebar,
        body.consultation-form-embed .sidebar-pin-toggle {
            display: none !important;
        }
        body.consultation-form-embed .main-content {
            margin-left: 0 !important;
            padding: 12px 16px !important;
            width: 100% !important;
            max-width: 100% !important;
            box-sizing: border-box;
        }
        body.consultation-form-embed .consultation-form-container {
            margin: 0 auto;
            box-shadow: none;
            max-width: 100%;
        }
    </style>
</head>
<body class="dashboard-page<?= $is_modal_embed ? ' consultation-form-embed' : '' ?>">
    <?php include '../includes/doctor_sidebar.php'; ?>
    
    <div class="main-content">
        <div class="consultation-form-container">
            <div class="form-header">
                <h2><i class="fas fa-stethoscope"></i> Record Consultation</h2>
                <div class="patient-info">
                    <strong>Patient:</strong> <?= htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']) ?> 
                    (<?= $appointment['age'] ?> years, <?= $appointment['gender'] ?>) |
                    <strong>Date:</strong> <?= date('F j, Y', strtotime($appointment['appointment_date'])) ?>
                </div>
            </div>
            
            <form method="POST" id="consultationForm" enctype="multipart/form-data" action="consultation_form.php?appointment_id=<?= (int) $appointment_id ?><?= $is_modal_embed ? '&amp;modal=1' : '' ?>">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="record_consultation">
                <input type="hidden" name="lab_specialization" id="lab_specialization" value="<?= htmlspecialchars($doctorSpecialization) ?>">
                <?php if ($is_modal_embed): ?>
                <input type="hidden" name="modal_embed" value="1">
                <?php endif; ?>
                
                <div class="form-section">
                    <h3><i class="fas fa-user-injured"></i> Chief Complaint</h3>
                    <div class="form-group">
                        <label>Main Complaint *</label>
                        <textarea name="chief_complaint" required placeholder="Enter the patient's main complaint..."></textarea>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3><i class="fas fa-clipboard-list"></i> Consultation Notes</h3>
                    <div class="form-group">
                        <label>Clinical Observations *</label>
                        <textarea name="consultation_notes" required placeholder="Document physical examination, symptoms, and clinical findings..."></textarea>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3><i class="fas fa-diagnoses"></i> Diagnosis</h3>
                    <div class="form-group">
                        <label>Diagnosis *</label>
                        <textarea name="diagnosis" required placeholder="Enter primary and secondary diagnoses..."></textarea>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3><i class="fas fa-heartbeat"></i> Treatment Plan</h3>
                    <div class="form-group">
                        <label>Treatment Plan</label>
                        <textarea name="treatment_plan" placeholder="Document recommended treatment, lifestyle changes, and care instructions..."></textarea>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3><i class="fas fa-file-medical-alt"></i> Report Summary</h3>
                    <div class="form-group">
                        <label>Report Summary</label>
                        <textarea name="report_summary" placeholder="Enter a summary of the consultation report..."></textarea>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3><i class="fas fa-heartbeat"></i> Vital Signs</h3>
                    <div class="vitals-grid">
                        <div class="form-group">
                            <label>Blood Pressure (mmHg)</label>
                            <input type="text" name="blood_pressure" placeholder="e.g., 120/80">
                            <small>Enter as systolic/diastolic (e.g., 120/80).</small>
                        </div>
                        <div class="form-group">
                            <label>Heart Rate (bpm)</label>
                            <input type="number" name="heart_rate" min="40" max="200" placeholder="e.g., 72">
                        </div>
                        <div class="form-group">
                            <label>Temperature (°C)</label>
                            <input type="number" name="temperature_c" step="0.1" min="30" max="45" placeholder="e.g., 36.5">
                        </div>
                        <div class="form-group">
                            <label>Weight (kg)</label>
                            <input type="number" name="weight_kg" step="0.1" min="1" max="300" placeholder="e.g., 70">
                        </div>
                        <div class="form-group">
                            <label>Height (cm)</label>
                            <input type="number" name="height_cm" step="0.1" min="50" max="250" placeholder="e.g., 170">
                        </div>
                        <div class="form-group vitals-span-3" id="bmi-display" style="padding: 10px; background: #f0f9ff; border-radius: 6px; display: none;">
                        <strong>Calculated BMI: <span id="bmi-value"></span></strong>
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3><i class="fas fa-pills"></i> Prescriptions</h3>
                    <div id="prescriptions-container">
                        <!-- Prescriptions will be added dynamically -->
                    </div>
                    <button type="button" class="btn-add-prescription" onclick="addPrescription()">
                        <i class="fas fa-plus"></i> Add Prescription
                    </button>
                </div>

                <div class="form-section">
                    <h3><i class="fas fa-flask"></i> Laboratory Results</h3>
                    <div class="lab-specialization-tabs" id="labSpecializationTabs">
                        <span class="lab-tab-chip" data-specialization="derma">Derma</span>
                        <span class="lab-tab-chip" data-specialization="gastro">Gastro</span>
                        <span class="lab-tab-chip" data-specialization="ortho">Ortho</span>
                    </div>
                    <p class="lab-note">Laboratory inputs automatically adapt to this doctor specialization.</p>

                    <div class="form-group">
                        <label>Laboratory Test Type</label>
                        <input type="text" name="lab_type" placeholder="e.g., CBC, Skin scraping, X-ray, H. pylori test">
                    </div>
                    <div class="form-group">
                        <label>Laboratory Result Summary</label>
                        <textarea name="lab_result_summary" placeholder="Write the key findings and interpretation of the result..."></textarea>
                    </div>

                    <div class="form-group">
                        <label>Attach Laboratory File (Optional)</label>
                        <input type="file" name="lab_attachment" accept=".pdf,.png,.jpg,.jpeg,.webp,.doc,.docx">
                        <small style="color: #64748b; font-size: 12px; margin-top: 5px; display: block;">
                            Allowed: PDF, PNG, JPG, WEBP, DOC, DOCX (max 8MB)
                        </small>
                    </div>

                    <div class="lab-fieldset is-hidden" data-lab-group="derma">
                        <div class="form-group">
                            <label>Skin Scraping / KOH Result</label>
                            <textarea name="derma_skin_scraping_koh" placeholder="Document KOH prep findings..."></textarea>
                        </div>
                        <div class="form-group">
                            <label>Patch Test Result</label>
                            <textarea name="derma_patch_test_result" placeholder="Document patch test interpretation..."></textarea>
                        </div>
                        <div class="form-group">
                            <label>Allergy Panel Result</label>
                            <textarea name="derma_allergy_panel_result" placeholder="Document allergy panel results..."></textarea>
                        </div>
                    </div>

                    <div class="lab-fieldset is-hidden" data-lab-group="gastro">
                        <div class="form-group">
                            <label>Stool Examination Result</label>
                            <textarea name="gastro_stool_exam_result" placeholder="Document stool exam findings..."></textarea>
                        </div>
                        <div class="form-group">
                            <label>H. Pylori Test Result</label>
                            <textarea name="gastro_h_pylori_test_result" placeholder="Document H. pylori test findings..."></textarea>
                        </div>
                        <div class="form-group">
                            <label>Abdominal Ultrasound Result</label>
                            <textarea name="gastro_abdominal_ultrasound_result" placeholder="Document ultrasound interpretation..."></textarea>
                        </div>
                    </div>

                    <div class="lab-fieldset is-hidden" data-lab-group="ortho">
                        <div class="form-group">
                            <label>X-ray Result</label>
                            <textarea name="ortho_xray_result" placeholder="Document X-ray findings..."></textarea>
                        </div>
                        <div class="form-group">
                            <label>MRI Result</label>
                            <textarea name="ortho_mri_result" placeholder="Document MRI findings..."></textarea>
                        </div>
                        <div class="form-group">
                            <label>Bone Density Result</label>
                            <textarea name="ortho_bone_density_result" placeholder="Document bone density findings..."></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3><i class="fas fa-calendar-check"></i> Follow-up</h3>
                    <div class="form-group">
                        <label>Follow-up Date (Optional)</label>
                        <input type="date" name="follow_up_date" id="follow_up_date">
                        <small style="color: #64748b; font-size: 12px; margin-top: 5px; display: block;">
                            <i class="fas fa-info-circle"></i> A follow-up appointment will be automatically created if a date is selected.
                        </small>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3><i class="fas fa-clipboard-check"></i> Consultation Status</h3>
                    <div class="form-group">
                        <label>Status *</label>
                        <select name="consultation_status" required>
                            <option value="Pending" selected>Pending</option>
                            <option value="Cleared">Cleared</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>
                
                <button type="submit" class="btn-submit">
                    <i class="fas fa-save"></i> Save Consultation Record
                </button>
            </form>
        </div>
    </div>
    
    <script>
        let prescriptionCount = 0;
        
        function addPrescription() {
            const container = document.getElementById('prescriptions-container');
            const count = prescriptionCount++;
            
            const prescriptionHtml = `
                <div class="prescription-row" id="presc-${count}">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <strong>Prescription ${count + 1}</strong>
                        <button type="button" class="btn-remove-prescription" onclick="removePrescription(${count})">
                            <i class="fas fa-times"></i> Remove
                        </button>
                    </div>
                    <div class="prescription-grid">
                        <div class="form-group">
                            <label>Medication Name *</label>
                            <input type="text" name="prescriptions[${count}][medication_name]" required>
                        </div>
                        <div class="form-group">
                            <label>Dosage</label>
                            <input type="text" name="prescriptions[${count}][dosage]" placeholder="e.g., 500mg">
                        </div>
                        <div class="form-group">
                            <label>Frequency</label>
                            <input type="text" name="prescriptions[${count}][frequency]" placeholder="e.g., Twice daily">
                        </div>
                        <div class="form-group">
                            <label>Duration</label>
                            <input type="text" name="prescriptions[${count}][duration]" placeholder="e.g., 7 days">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Instructions</label>
                        <textarea name="prescriptions[${count}][instructions]" placeholder="Additional instructions for the patient..."></textarea>
                    </div>
                    <div class="form-group">
                        <label>Quantity</label>
                        <input type="text" name="prescriptions[${count}][quantity]" placeholder="e.g., 14 tablets">
                    </div>
                </div>
            `;
            
            container.insertAdjacentHTML('beforeend', prescriptionHtml);
        }
        
        function removePrescription(id) {
            const presc = document.getElementById('presc-' + id);
            if (presc) presc.remove();
        }
        
        // Add one prescription row by default
        window.addEventListener('DOMContentLoaded', function() {
            addPrescription();
            
            // BMI calculation
            const weightInput = document.querySelector('input[name="weight_kg"]');
            const heightInput = document.querySelector('input[name="height_cm"]');
            const bmiDisplay = document.getElementById('bmi-display');
            const bmiValue = document.getElementById('bmi-value');
            
            function calculateBMI() {
                const weight = parseFloat(weightInput.value);
                const height = parseFloat(heightInput.value);
                
                if (weight && height && height > 0) {
                    const heightM = height / 100;
                    const bmi = (weight / (heightM * heightM)).toFixed(2);
                    bmiValue.textContent = bmi;
                    bmiDisplay.style.display = 'block';
                } else {
                    bmiDisplay.style.display = 'none';
                }
            }
            
            if (weightInput && heightInput) {
                weightInput.addEventListener('input', calculateBMI);
                heightInput.addEventListener('input', calculateBMI);
            }

            const specializationRaw = <?= json_encode(strtolower($doctorSpecialization)) ?> || 'general';
            const specializationInput = document.getElementById('lab_specialization');
            const chips = Array.from(document.querySelectorAll('#labSpecializationTabs .lab-tab-chip'));
            const groups = Array.from(document.querySelectorAll('.lab-fieldset[data-lab-group]'));

            function normalizeSpec(spec) {
                const val = (spec || '').toLowerCase();
                if (val.includes('derma') || val.includes('dermat')) return 'derma';
                if (val.includes('gastro')) return 'gastro';
                if (val.includes('ortho') || val.includes('orthop')) return 'ortho';
                return 'derma';
            }

            function activateLabSpecialization(spec) {
                const normalized = normalizeSpec(spec);
                if (specializationInput) {
                    specializationInput.value = normalized.charAt(0).toUpperCase() + normalized.slice(1);
                }
                chips.forEach((chip) => {
                    chip.classList.toggle('is-active', chip.dataset.specialization === normalized);
                });
                groups.forEach((group) => {
                    group.classList.toggle('is-hidden', group.dataset.labGroup !== normalized);
                });
            }

            activateLabSpecialization(specializationRaw);
        });
    </script>
</body>
</html>

