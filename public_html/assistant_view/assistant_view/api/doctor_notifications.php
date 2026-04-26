<?php
session_start();
require_once '../../config/db_connect.php';

// Check if user is logged in and has doctor role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

$doctor_id = $_SESSION['user_id'];

try {
    $action = $_GET['action'] ?? 'get_notifications';
    
    switch ($action) {
        case 'get_notifications':
            getDoctorNotifications($doctor_id);
            break;
            
        case 'mark_read':
            markNotificationAsRead($_POST['notification_id']);
            break;
            
        case 'get_upcoming_appointments':
            getUpcomingAppointments($doctor_id);
            break;
            
        case 'send_reminder':
            sendAppointmentReminder($_POST['appointment_id']);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}

function getDoctorNotifications($doctor_id) {
    global $conn;
    
    $sql = "SELECT 
                n.*,
                a.appointment_time,
                a.priority,
                a.health_risk_score,
                p.name as patient_name
            FROM notifications n
            LEFT JOIN appointments a ON n.appointment_id = a.id
            LEFT JOIN patients p ON a.patient_id = p.id
            WHERE n.user_id = ? AND n.user_type = 'doctor'
            ORDER BY n.created_at DESC
            LIMIT 20";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$doctor_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format notifications for display
    $formattedNotifications = [];
    foreach ($notifications as $notification) {
        $formattedNotifications[] = [
            'id' => $notification['id'],
            'message' => $notification['message'],
            'type' => $notification['type'],
            'is_read' => (bool)$notification['is_read'],
            'created_at' => $notification['created_at'],
            'appointment_time' => $notification['appointment_time'],
            'priority' => $notification['priority'],
            'health_risk_score' => $notification['health_risk_score'],
            'patient_name' => $notification['patient_name'],
            'time_ago' => getTimeAgo($notification['created_at'])
        ];
    }
    
    echo json_encode([
        'success' => true,
        'notifications' => $formattedNotifications,
        'unread_count' => count(array_filter($formattedNotifications, function($n) { return !$n['is_read']; }))
    ]);
}

function getUpcomingAppointments($doctor_id) {
    global $conn;
    
    $sql = "SELECT 
                a.id,
                a.appointment_time,
                a.priority,
                a.health_risk_score,
                a.status,
                p.name as patient_name,
                p.phone as patient_phone,
                p.email as patient_email
            FROM appointments a
            LEFT JOIN patients p ON a.patient_id = p.id
            WHERE a.doctor_id = ? 
            AND DATE(a.appointment_time) = CURDATE()
            AND a.status IN ('scheduled', 'confirmed')
            ORDER BY a.appointment_time ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$doctor_id]);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format appointments for display
    $formattedAppointments = [];
    foreach ($appointments as $appointment) {
        $timeUntil = getTimeUntil($appointment['appointment_time']);
        $isUrgent = $appointment['priority'] === 'critical' || $appointment['health_risk_score'] >= 8;
        
        $formattedAppointments[] = [
            'id' => $appointment['id'],
            'patient_name' => $appointment['patient_name'],
            'appointment_time' => $appointment['appointment_time'],
            'formatted_time' => date('g:i A', strtotime($appointment['appointment_time'])),
            'priority' => $appointment['priority'],
            'health_risk_score' => $appointment['health_risk_score'],
            'status' => $appointment['status'],
            'patient_phone' => $appointment['patient_phone'],
            'patient_email' => $appointment['patient_email'],
            'time_until' => $timeUntil,
            'is_urgent' => $isUrgent,
            'priority_class' => 'priority-' . $appointment['priority']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'appointments' => $formattedAppointments,
        'urgent_count' => count(array_filter($formattedAppointments, function($a) { return $a['is_urgent']; }))
    ]);
}

function markNotificationAsRead($notification_id) {
    global $conn;
    
    $sql = "UPDATE notifications SET is_read = TRUE WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $result = $stmt->execute([$notification_id]);
    
    echo json_encode(['success' => $result]);
}

function sendAppointmentReminder($appointment_id) {
    global $conn;
    
    // Get appointment details
    $sql = "SELECT 
                a.*,
                p.name as patient_name,
                p.email as patient_email,
                p.phone as patient_phone,
                d.name as doctor_name
            FROM appointments a
            LEFT JOIN patients p ON a.patient_id = p.id
            LEFT JOIN doctors d ON a.doctor_id = d.id
            WHERE a.id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$appointment_id]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$appointment) {
        echo json_encode(['success' => false, 'message' => 'Appointment not found']);
        return;
    }
    
    // Send notification to patient
    $message = "Reminder: You have an appointment with Dr. {$appointment['doctor_name']} at " . 
               date('g:i A', strtotime($appointment['appointment_time'])) . " today. Please arrive 15 minutes early.";
    
    $sql = "INSERT INTO notifications (user_id, user_type, message, type, created_at) 
            VALUES (?, 'patient', ?, 'reminder', NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$appointment['patient_id'], $message]);
    
    // Log the reminder
    $sql = "INSERT INTO notification_logs (appointment_id, action, message, created_at) 
            VALUES (?, 'patient_reminder_sent', ?, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$appointment_id, $message]);
    
    echo json_encode(['success' => true, 'message' => 'Reminder sent successfully']);
}

function getTimeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    
    return date('M j, Y', strtotime($datetime));
}

function getTimeUntil($datetime) {
    $time = strtotime($datetime) - time();
    
    if ($time < 0) return 'Overdue';
    if ($time < 3600) return floor($time/60) . ' minutes';
    if ($time < 86400) return floor($time/3600) . ' hours';
    
    return date('M j, g:i A', strtotime($datetime));
}
?>
