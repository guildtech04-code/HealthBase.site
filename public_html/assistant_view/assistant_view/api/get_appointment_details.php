<?php
session_start();
require_once '../../config/db_connect.php';

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['assistant', 'admin', 'doctor'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

try {
    $appointmentId = $_GET['id'] ?? null;
    
    if (!$appointmentId) {
        http_response_code(400);
        echo json_encode(['error' => 'Appointment ID required']);
        exit();
    }
    
    $sql = "SELECT 
                a.id,
                a.appointment_time,
                a.status,
                a.priority,
                a.health_risk_score,
                a.notes,
                p.name as patient_name,
                p.email as patient_email,
                p.phone as patient_phone,
                p.date_of_birth,
                p.gender,
                d.name as doctor_name,
                d.specialization,
                d.email as doctor_email,
                d.phone as doctor_phone
            FROM appointments a
            LEFT JOIN patients p ON a.patient_id = p.id
            LEFT JOIN doctors d ON a.doctor_id = d.id
            WHERE a.id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$appointmentId]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$appointment) {
        http_response_code(404);
        echo json_encode(['error' => 'Appointment not found']);
        exit();
    }
    
    // Calculate age if date of birth is available
    if ($appointment['date_of_birth']) {
        $birthDate = new DateTime($appointment['date_of_birth']);
        $today = new DateTime();
        $age = $today->diff($birthDate)->y;
        $appointment['patient_age'] = $age;
    }
    
    // Format appointment time
    $appointment['formatted_time'] = date('M j, Y \a\t g:i A', strtotime($appointment['appointment_time']));
    
    echo json_encode($appointment);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
