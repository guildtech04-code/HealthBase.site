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
    $sql = "SELECT id, CONCAT(first_name, ' ', last_name) as name, specialization, email, 'available' as availability_status 
            FROM users 
            WHERE role = 'doctor' AND status = 'active' 
            ORDER BY first_name ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($doctors);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
