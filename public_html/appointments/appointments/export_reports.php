<?php
// export_reports.php - Handle report exports (CSV/PDF)
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/auth_guard.php';
ensure_role(['doctor', 'assistant', 'admin']);
include("../config/db_connect.php");
require_once __DIR__ . '/../includes/security.php';

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$export_type = $_GET['type'] ?? 'csv'; // csv or pdf

// Get report parameters
$start_date = $_GET['start_date'] ?? date('Y-m-d');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$doctor_id = $_GET['doctor_id'] ?? null;

// Build query
$query = "
    SELECT a.id, a.appointment_date, a.status, a.urgency,
           CONCAT(p.first_name, ' ', p.last_name) as patient_name,
           p.age, p.gender, p.health_concern,
           CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
           d.specialization
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN users d ON a.doctor_id = d.id
    WHERE DATE(a.appointment_date) BETWEEN ? AND ?
";

$params = [$start_date, $end_date];
$types = "ss";

if ($doctor_id && $role !== 'user') {
    $query .= " AND a.doctor_id = ?";
    $params[] = $doctor_id;
    $types .= "i";
}

if ($role === 'doctor') {
    $query .= " AND a.doctor_id = ?";
    $params[] = $user_id;
    $types .= "i";
}

$query .= " ORDER BY a.appointment_date DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$appointments = $stmt->get_result();

if ($export_type === 'csv') {
    // Export to CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="appointments_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Headers
    fputcsv($output, ['Date & Time', 'Patient', 'Age', 'Gender', 'Health Concern', 'Doctor', 'Specialization', 'Status', 'Urgency']);
    
    while ($row = $appointments->fetch_assoc()) {
        fputcsv($output, [
            $row['appointment_date'],
            $row['patient_name'],
            $row['age'],
            $row['gender'],
            $row['health_concern'],
            $row['doctor_name'],
            $row['specialization'],
            $row['status'],
            $row['urgency'] ?? 'Normal'
        ]);
    }
    
    fclose($output);
    exit;
    
} elseif ($export_type === 'pdf') {
    // Generate PDF using inline data URI
    header('Content-Type: text/html; charset=utf-8');
    
    // Build data for JavaScript PDF generation
    $appointments_data = [];
    $appointments->data_seek(0);
    while ($row = $appointments->fetch_assoc()) {
        $appointments_data[] = $row;
    }
    
    $doctor_name = null;
    if ($doctor_id) {
        $doc_query = $conn->prepare("SELECT CONCAT(first_name, ' ', last_name) as name FROM users WHERE id = ?");
        $doc_query->bind_param("i", $doctor_id);
        $doc_query->execute();
        $doctor_name = $doc_query->get_result()->fetch_assoc()['name'];
    }
    
    // Output HTML with JS to generate PDF using jsPDF (already loaded in main page)
    echo '<!DOCTYPE html><html><head><title>Generating PDF...</title></head><body>';
    echo '<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>';
    echo '<script>';
    echo 'const { jsPDF } = window.jspdf;';
    echo 'const doc = new jsPDF({ orientation: "landscape", unit: "mm", format: "a4" });';
    echo 'const data = ' . json_encode($appointments_data) . ';';
    echo 'const startDate = ' . json_encode(date('M j, Y', strtotime($start_date))) . ';';
    echo 'const endDate = ' . json_encode(date('M j, Y', strtotime($end_date))) . ';';
    echo 'const doctorName = ' . json_encode($doctor_name) . ';';
    echo 'const generatedAt = ' . json_encode(date('F j, Y g:i A')) . ';';
    echo 'const totalCount = ' . count($appointments_data) . ';';
    
    echo '
    // Helper function to wrap text
    function wrapText(text, maxWidth) {
        if (text.length * 2 <= maxWidth) return text;
        return text.substring(0, maxWidth / 2) + "...";
    }
    
    // Set up PDF - Header Section
    doc.setFontSize(18);
    doc.setFont("helvetica", "bold");
    doc.text("Appointment Reports", 14, 15);
    
    doc.setFontSize(10);
    doc.setFont("helvetica", "normal");
    doc.text("HealthBase Medical Clinic", 14, 22);
    
    doc.setFontSize(9);
    doc.text("Period: " + startDate + " - " + endDate, 14, 28);
    if (doctorName) {
        doc.text("Doctor: " + doctorName, 14, 33);
    }
    doc.text("Total Appointments: " + totalCount, 14, 38);
    doc.text("Generated: " + generatedAt, 200, 38);
    
    // Draw header line
    doc.line(14, 42, 278, 42);
    
    // Table headers - Landscape layout
    let y = 50;
    doc.setFontSize(8);
    doc.setFont("helvetica", "bold");
    
    // Column positions for landscape
    const cols = {
        date: 14,
        patient: 50,
        age: 85,
        gender: 95,
        concern: 110,
        doctor: 165,
        spec: 210,
        status: 245,
        urgency: 260
    };
    
    doc.text("Date & Time", cols.date, y);
    doc.text("Patient Name", cols.patient, y);
    doc.text("Age", cols.age, y);
    doc.text("Gender", cols.gender, y);
    doc.text("Health Concern", cols.concern, y);
    doc.text("Doctor", cols.doctor, y);
    doc.text("Specialization", cols.spec, y);
    doc.text("Status", cols.status, y);
    doc.text("Urgency", cols.urgency, y);
    
    y += 8;
    doc.line(14, y, 278, y);
    y += 5;
    
    // Table rows
    doc.setFont("helvetica", "normal");
    doc.setFontSize(7);
    
    data.forEach((row, index) => {
        if (y > 190) {
            doc.addPage("landscape");
            y = 20;
        }
        
        const dateStr = row.appointment_date.substring(0, 19);
        const patientStr = wrapText(row.patient_name, 30);
        const ageStr = row.age.toString();
        const genderStr = row.gender;
        const concernStr = wrapText(row.health_concern, 50);
        const doctorStr = wrapText(row.doctor_name, 40);
        const specStr = wrapText(row.specialization, 30);
        const statusStr = row.status;
        const urgencyStr = row.urgency || "Normal";
        
        doc.text(dateStr, cols.date, y);
        doc.text(patientStr, cols.patient, y);
        doc.text(ageStr, cols.age, y);
        doc.text(genderStr, cols.gender, y);
        doc.text(concernStr, cols.concern, y);
        doc.text(doctorStr, cols.doctor, y);
        doc.text(specStr, cols.spec, y);
        doc.text(statusStr, cols.status, y);
        doc.text(urgencyStr, cols.urgency, y);
        
        y += 6;
    });
    
    // Footer
    doc.setFontSize(7);
    doc.setFont("helvetica", "italic");
    doc.text("This report was generated by HealthBase System", 14, 200);
    doc.text("Page " + doc.internal.getCurrentPageInfo().pageNumber, 260, 200);
    
    // Save PDF
    doc.save("appointments_' . date('Y-m-d') . '.pdf");
    
    // Close window after download
    setTimeout(() => {
        window.close();
    }, 500);
    ';
    echo '</script>';
    echo '<p style="text-align: center; padding: 50px; color: #64748b;">Generating PDF... Download should start automatically.</p>';
    echo '</body></html>';
    exit;
}

