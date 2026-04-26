<?php
// notification_helper.php - Helper functions for notifications

/**
 * Add a notification for a user
 * @param mysqli $conn Database connection
 * @param int $user_id User ID to notify
 * @param string $type Type of notification (appointment, appointment_rescheduled, appointment_cancelled, support, etc.)
 * @param int $reference_id ID of the related record (appointment_id, ticket_id, etc.)
 * @param string|null $extra Optional extra context (e.g., formatted date)
 * @return bool Success status
 */
function addNotification($conn, $user_id, $type, $reference_id, $extra = null) {
    $message = '';
    $link = '';
    
    switch ($type) {
        case 'appointment':
            $message = "\ud83d\udcc5 New appointment request — open Appointments to confirm or decline.";
            $link = "appointments.php?appointment_id=" . $reference_id;
            break;
        case 'appointment_rescheduled':
            $message = "\u2705 Appointment #{$reference_id} was rescheduled" . ($extra ? " to {$extra}" : '') . ".";
            $link = "appointments.php?appointment_id=" . $reference_id;
            break;
        case 'appointment_cancelled':
            $message = "\u274c Appointment #{$reference_id} was cancelled.";
            $link = "appointments.php?appointment_id=" . $reference_id;
            break;
        case 'support':
            $message = "\ud83d\udce9 You have a new support ticket.";
            $link = "view_ticket.php?id=" . $reference_id;
            break;
        case 'message':
            $message = "You have a new message.";
            break;
        default:
            $message = "You have a new notification.";
    }
    
    $stmt = $conn->prepare("
        INSERT INTO notifications (user_id, message, link, type, is_read, created_at) 
        VALUES (?, ?, ?, ?, 0, NOW())
    ");
    
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param("isss", $user_id, $message, $link, $type);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Mark notification as read
 * @param mysqli $conn Database connection
 * @param int $notification_id Notification ID
 * @param int $user_id User ID (for security)
 * @return bool Success status
 */
function markNotificationAsRead($conn, $notification_id, $user_id) {
    $stmt = $conn->prepare("
        UPDATE notifications 
        SET is_read = 1 
        WHERE id = ? AND user_id = ?
    ");
    
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param("ii", $notification_id, $user_id);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Get unread notification count for a user
 * @param mysqli $conn Database connection
 * @param int $user_id User ID
 * @return int Unread count
 */
function getUnreadNotificationCount($conn, $user_id) {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM notifications 
        WHERE user_id = ? AND is_read = 0
    ");
    
    if (!$stmt) {
        return 0;
    }
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    return $result['count'] ?? 0;
}

/**
 * Get notifications for a user
 * @param mysqli $conn Database connection
 * @param int $user_id User ID
 * @param int $limit Limit number of notifications
 * @return array Array of notifications
 */
function getUserNotifications($conn, $user_id, $limit = 10) {
    $stmt = $conn->prepare("
        SELECT id, message, link, type, is_read, created_at
        FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT ?
    ");
    
    if (!$stmt) {
        return [];
    }
    
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $notifications = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    return $notifications;
}
?>
