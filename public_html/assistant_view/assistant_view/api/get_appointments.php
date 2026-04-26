<?php
session_start();
require_once '../../config/db_connect.php';

// Check if user is logged in and has assistant/admin role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['assistant', 'admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $date = $input['date'] ?? date('Y-m-d');
    $doctor_id = $input['doctor'] ?? '';
    $priority = $input['priority'] ?? '';
    
    $sql = "SELECT 
                a.id,
                a.appointment_date as appointment_time,
                a.status,
                COALESCE(a.priority, 'medium') as priority,
                COALESCE(a.health_risk_score, 5) as health_risk_score,
                '' as notes,
                CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                u.email as patient_email,
                '' as patient_phone,
                CONCAT(d.first_name, ' ', d.last_name) as doctor_name,
                d.specialization,
                'available' as availability_status
            FROM appointments a
            LEFT JOIN patients p ON a.patient_id = p.id
            LEFT JOIN users u ON p.user_id = u.id
            LEFT JOIN users d ON a.doctor_id = d.id
            WHERE DATE(a.appointment_date) = ?";
    
    $params = [$date];
    
    if (!empty($doctor_id)) {
        $sql .= " AND a.doctor_id = ?";
        $params[] = $doctor_id;
    }
    
    if (!empty($priority)) {
        $sql .= " AND a.priority = ?";
        $params[] = $priority;
    }
    
    $sql .= " ORDER BY a.appointment_date ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate health risk scores for appointments without them
    foreach ($appointments as &$appointment) {
        if (empty($appointment['health_risk_score'])) {
            $appointment['health_risk_score'] = calculateHealthRiskScore($appointment);
        }
    }
    
    echo json_encode($appointments);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

function calculateHealthRiskScore($appointment) {
    $riskScore = 5; // Base score
    
    // Adjust based on priority
    switch ($appointment['priority']) {
        case 'critical':
            $riskScore += 4;
            break;
        case 'high':
            $riskScore += 3;
            break;
        case 'medium':
            $riskScore += 1;
            break;
        case 'low':
            $riskScore -= 1;
            break;
    }
    
    // Adjust based on appointment age (if rescheduled multiple times)
    $appointmentAge = (time() - strtotime($appointment['appointment_date'])) / (24 * 60 * 60);
    if ($appointmentAge > 7) {
        $riskScore += 2;
    }
    
    // Ensure score is between 1-10
    return max(1, min(10, $riskScore));
}
?>
