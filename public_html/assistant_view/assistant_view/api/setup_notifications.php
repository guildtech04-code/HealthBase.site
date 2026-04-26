<?php
require_once '../../config/db_connect.php';

try {
    // Create notifications table
    $sql = "CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        user_type ENUM('patient', 'doctor', 'assistant', 'admin', 'staff') NOT NULL,
        message TEXT NOT NULL,
        type ENUM('reminder', 'alert', 'urgent', 'optimization', 'reschedule') NOT NULL,
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_user_type (user_type),
        INDEX idx_is_read (is_read),
        INDEX idx_created_at (created_at)
    )";
    
    $conn->exec($sql);
    echo "Notifications table created successfully.\n";
    
    // Create notification_logs table
    $sql = "CREATE TABLE IF NOT EXISTS notification_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        appointment_id INT,
        action VARCHAR(100) NOT NULL,
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_appointment_id (appointment_id),
        INDEX idx_action (action),
        INDEX idx_created_at (created_at)
    )";
    
    $conn->exec($sql);
    echo "Notification logs table created successfully.\n";
    
    // Create doctor_availability table
    $sql = "CREATE TABLE IF NOT EXISTS doctor_availability (
        id INT AUTO_INCREMENT PRIMARY KEY,
        doctor_id INT NOT NULL,
        day_of_week TINYINT NOT NULL, -- 0=Sunday, 1=Monday, etc.
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        is_available BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_doctor_day_time (doctor_id, day_of_week, start_time),
        INDEX idx_doctor_id (doctor_id),
        INDEX idx_day_of_week (day_of_week)
    )";
    
    $conn->exec($sql);
    echo "Doctor availability table created successfully.\n";
    
    // Create appointment_optimization_logs table
    $sql = "CREATE TABLE IF NOT EXISTS appointment_optimization_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        optimization_date DATE NOT NULL,
        appointments_rescheduled INT DEFAULT 0,
        waiting_time_reduction INT DEFAULT 0,
        priority_adjustments INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_optimization_date (optimization_date)
    )";
    
    $conn->exec($sql);
    echo "Appointment optimization logs table created successfully.\n";
    
    // Add health_risk_score column to appointments table if it doesn't exist
    $sql = "ALTER TABLE appointments ADD COLUMN IF NOT EXISTS health_risk_score TINYINT DEFAULT 5";
    $conn->exec($sql);
    echo "Health risk score column added to appointments table.\n";
    
    // Add priority column to appointments table if it doesn't exist
    $sql = "ALTER TABLE appointments ADD COLUMN IF NOT EXISTS priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium'";
    $conn->exec($sql);
    echo "Priority column added to appointments table.\n";
    
    // Insert sample doctor availability
    $sampleAvailability = [
        [1, 1, '09:00:00', '17:00:00'], // Monday
        [1, 2, '09:00:00', '17:00:00'], // Tuesday
        [1, 3, '09:00:00', '17:00:00'], // Wednesday
        [1, 4, '09:00:00', '17:00:00'], // Thursday
        [1, 5, '09:00:00', '17:00:00'], // Friday
        [2, 1, '08:00:00', '16:00:00'], // Monday
        [2, 2, '08:00:00', '16:00:00'], // Tuesday
        [2, 3, '08:00:00', '16:00:00'], // Wednesday
        [2, 4, '08:00:00', '16:00:00'], // Thursday
        [2, 5, '08:00:00', '16:00:00'], // Friday
    ];
    
    $sql = "INSERT IGNORE INTO doctor_availability (doctor_id, day_of_week, start_time, end_time) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    foreach ($sampleAvailability as $availability) {
        $stmt->execute($availability);
    }
    
    echo "Sample doctor availability inserted successfully.\n";
    
    echo "\nAll database tables created successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
