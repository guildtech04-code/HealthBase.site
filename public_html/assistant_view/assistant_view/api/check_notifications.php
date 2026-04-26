<?php
session_start();
require_once '../../config/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

try {
    $userId = $_SESSION['user_id'];
    $userRole = $_SESSION['role'];
    
    // Get unread notifications for the user
    $sql = "SELECT 
                n.id,
                n.message,
                n.type,
                n.created_at,
                a.appointment_time,
                a.priority,
                p.name as patient_name
            FROM notifications n
            LEFT JOIN appointments a ON n.appointment_id = a.id
            LEFT JOIN patients p ON a.patient_id = p.id
            WHERE n.user_id = ? AND n.is_read = 0
            ORDER BY n.created_at DESC
            LIMIT 5";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$userId]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format notifications
    $formattedNotifications = [];
    foreach ($notifications as $notification) {
        $formattedNotifications[] = [
            'id' => $notification['id'],
            'message' => $notification['message'],
            'type' => $notification['type'],
            'time_ago' => getTimeAgo($notification['created_at']),
            'appointment_time' => $notification['appointment_time'],
            'priority' => $notification['priority'],
            'patient_name' => $notification['patient_name']
        ];
    }
    
    echo json_encode($formattedNotifications);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

function getTimeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    
    return date('M j, Y', strtotime($datetime));
}
?>
