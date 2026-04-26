<?php
session_start();
require_once '../../config/db_connect.php';

// Check if user is logged in and has appropriate role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['assistant', 'admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $appointmentId = $input['appointmentId'] ?? null;
    
    if (!$appointmentId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Appointment ID required']);
        exit();
    }
    
    // Get current appointment details
    $sql = "SELECT * FROM appointments WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$appointmentId]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$appointment) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Appointment not found']);
        exit();
    }
    
    // Find next available slot
    $newTime = findNextAvailableSlot($appointment['doctor_id'], $appointment['appointment_time']);
    
    if (!$newTime) {
        echo json_encode(['success' => false, 'message' => 'No available slots found']);
        exit();
    }
    
    // Update appointment
    $sql = "UPDATE appointments 
            SET appointment_time = ?, 
                status = 'rescheduled',
                updated_at = NOW()
            WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    $result = $stmt->execute([$newTime, $appointmentId]);
    
    if ($result) {
        // Log the rescheduling
        $sql = "INSERT INTO notification_logs (appointment_id, action, message, created_at) 
                VALUES (?, 'rescheduled', ?, NOW())";
        $stmt = $conn->prepare($sql);
        $message = "Appointment rescheduled from " . $appointment['appointment_time'] . " to " . $newTime;
        $stmt->execute([$appointmentId, $message]);
        
        // Send notification to patient
        sendRescheduleNotification($appointmentId, $appointment['appointment_time'], $newTime);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Appointment rescheduled successfully',
            'new_time' => $newTime
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to reschedule appointment']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

function findNextAvailableSlot($doctorId, $currentTime) {
    global $conn;
    
    // Get doctor's availability
    $dayOfWeek = date('w', strtotime($currentTime)); // 0=Sunday, 1=Monday, etc.
    
    $sql = "SELECT start_time, end_time FROM doctor_availability 
            WHERE doctor_id = ? AND day_of_week = ? AND is_available = 1
            ORDER BY start_time ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$doctorId, $dayOfWeek]);
    $availability = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($availability)) {
        return null;
    }
    
    // Find next available slot (simplified logic)
    $currentDateTime = new DateTime($currentTime);
    $currentDateTime->add(new DateInterval('PT1H')); // Add 1 hour
    
    // Check for conflicts with existing appointments
    $sql = "SELECT appointment_time FROM appointments 
            WHERE doctor_id = ? AND DATE(appointment_time) = ? AND id != ?
            ORDER BY appointment_time ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$doctorId, $currentDateTime->format('Y-m-d'), $_POST['appointmentId'] ?? 0]);
    $conflicts = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Find next available slot
    $slotTime = clone $currentDateTime;
    $slotTime->setTime(9, 0); // Start from 9 AM
    
    for ($hour = 9; $hour < 17; $hour++) {
        $slotTime->setTime($hour, 0);
        $slotString = $slotTime->format('Y-m-d H:i:s');
        
        $hasConflict = false;
        foreach ($conflicts as $conflict) {
            $conflictTime = new DateTime($conflict);
            if (abs($slotTime->getTimestamp() - $conflictTime->getTimestamp()) < 3600) { // 1 hour gap
                $hasConflict = true;
                break;
            }
        }
        
        if (!$hasConflict) {
            return $slotString;
        }
    }
    
    return null;
}

function sendRescheduleNotification($appointmentId, $oldTime, $newTime) {
    global $conn;
    
    // Get appointment details
    $sql = "SELECT 
                a.patient_id,
                p.name as patient_name,
                d.name as doctor_name
            FROM appointments a
            LEFT JOIN patients p ON a.patient_id = p.id
            LEFT JOIN doctors d ON a.doctor_id = d.id
            WHERE a.id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$appointmentId]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($appointment) {
        $message = "Your appointment with Dr. {$appointment['doctor_name']} has been rescheduled from " . 
                   date('M j, Y \a\t g:i A', strtotime($oldTime)) . " to " . 
                   date('M j, Y \a\t g:i A', strtotime($newTime)) . ". Please confirm your availability.";
        
        // Insert notification
        $sql = "INSERT INTO notifications (user_id, user_type, message, type, appointment_id, created_at) 
                VALUES (?, 'patient', ?, 'reschedule', ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$appointment['patient_id'], $message, $appointmentId]);
    }
}
?>
