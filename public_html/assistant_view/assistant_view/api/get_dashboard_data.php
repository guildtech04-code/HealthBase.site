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
    $today = date('Y-m-d');
    
    // Get today's appointments count
    $sql = "SELECT COUNT(*) as count FROM appointments WHERE DATE(appointment_date) = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$today]);
    $todayAppointments = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get urgent cases count (using status as priority indicator)
    $sql = "SELECT COUNT(*) as count FROM appointments 
            WHERE DATE(appointment_date) = ? 
            AND status IN ('Pending', 'Confirmed')";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$today]);
    $urgentCases = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Calculate average waiting time (mock calculation)
    $sql = "SELECT COUNT(*) as total_appointments FROM appointments 
            WHERE DATE(appointment_date) = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$today]);
    $totalAppointments = $stmt->fetch(PDO::FETCH_ASSOC)['total_appointments'];
    $avgRisk = $totalAppointments > 0 ? 5 : 0; // Default risk score
    
    // Calculate average waiting time based on risk score
    $avgWaitingTime = $avgRisk ? round($avgRisk * 3) : 15; // 3 minutes per risk point
    
    // Get notifications count
    $sql = "SELECT COUNT(*) as count FROM notifications 
            WHERE user_id = ? AND is_read = 0";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$_SESSION['user_id']]);
    $notificationsCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo json_encode([
        'todayAppointments' => (int)$todayAppointments,
        'urgentCases' => (int)$urgentCases,
        'avgWaitingTime' => $avgWaitingTime,
        'notificationsCount' => (int)$notificationsCount
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
