<?php
require_once '../config/db_connect.php';

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>SMART Scheduling Setup</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; background: #f5f7fa; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .success { color: #38a169; font-weight: bold; }
        .error { color: #e53e3e; font-weight: bold; }
        .info { color: #2b6cb0; font-weight: bold; }
        .credentials { background: #f7fafc; padding: 20px; border-radius: 8px; border-left: 4px solid #667eea; margin: 20px 0; }
        .btn { background: #667eea; color: white; padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer; text-decoration: none; display: inline-block; margin: 10px 5px; }
        .btn:hover { background: #5a67d8; }
        h1 { color: #4a5568; text-align: center; }
        h2 { color: #2d3748; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🤖 SMART Scheduling System Setup</h1>";

try {
    echo "<h2>Step 1: Creating Database Tables</h2>";
    
    // Create notifications table
    $sql = "CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        user_type ENUM('patient', 'doctor', 'assistant', 'admin', 'staff') NOT NULL,
        message TEXT NOT NULL,
        type ENUM('reminder', 'alert', 'urgent', 'optimization', 'reschedule') NOT NULL,
        is_read BOOLEAN DEFAULT FALSE,
        appointment_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_user_type (user_type),
        INDEX idx_is_read (is_read),
        INDEX idx_created_at (created_at)
    )";
    
    $conn->exec($sql);
    echo "<p class='success'>✅ Notifications table created successfully</p>";
    
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
    echo "<p class='success'>✅ Notification logs table created successfully</p>";
    
    // Create doctor_availability table
    $sql = "CREATE TABLE IF NOT EXISTS doctor_availability (
        id INT AUTO_INCREMENT PRIMARY KEY,
        doctor_id INT NOT NULL,
        day_of_week TINYINT NOT NULL,
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
    echo "<p class='success'>✅ Doctor availability table created successfully</p>";
    
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
    echo "<p class='success'>✅ Appointment optimization logs table created successfully</p>";
    
    echo "<h2>Step 2: Updating Appointments Table</h2>";
    
    // Add health_risk_score column to appointments table if it doesn't exist
    try {
        $sql = "ALTER TABLE appointments ADD COLUMN health_risk_score TINYINT DEFAULT 5";
        $conn->exec($sql);
        echo "<p class='success'>✅ Health risk score column added to appointments table</p>";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "<p class='info'>ℹ️ Health risk score column already exists</p>";
        } else {
            throw $e;
        }
    }
    
    // Add priority column to appointments table if it doesn't exist
    try {
        $sql = "ALTER TABLE appointments ADD COLUMN priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium'";
        $conn->exec($sql);
        echo "<p class='success'>✅ Priority column added to appointments table</p>";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "<p class='info'>ℹ️ Priority column already exists</p>";
        } else {
            throw $e;
        }
    }
    
    echo "<h2>Step 3: Creating Assistant Account</h2>";
    
    // Check if assistant account already exists
    $sql = "SELECT id FROM users WHERE username = 'smart_assistant' AND role = 'assistant'";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo "<p class='info'>ℹ️ Assistant account already exists</p>";
    } else {
        // Create assistant account
        $username = 'smart_assistant';
        $email = 'assistant@healthbase.com';
        $password = 'SmartAssistant2024!';
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $role = 'assistant';
        $fullName = 'SMART Scheduling Assistant';
        
        $sql = "INSERT INTO users (username, email, password, role, first_name, last_name, gender, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW())";
        
        $stmt = $conn->prepare($sql);
        $result = $stmt->execute([$username, $email, $hashedPassword, $role, 'SMART', 'Assistant', 'Male']);
        
        if ($result) {
            echo "<p class='success'>✅ Assistant account created successfully</p>";
        } else {
            echo "<p class='error'>❌ Error creating assistant account</p>";
        }
    }
    
    echo "<h2>Step 4: Setting Up Sample Data</h2>";
    
    // Insert sample doctor availability
    $sampleAvailability = [
        [1, 1, '09:00:00', '17:00:00'], // Monday
        [1, 2, '09:00:00', '17:00:00'], // Tuesday
        [1, 3, '09:00:00', '17:00:00'], // Wednesday
        [1, 4, '09:00:00', '17:00:00'], // Thursday
        [1, 5, '09:00:00', '17:00:00'], // Friday
    ];
    
    $sql = "INSERT IGNORE INTO doctor_availability (doctor_id, day_of_week, start_time, end_time) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    $insertedCount = 0;
    foreach ($sampleAvailability as $availability) {
        try {
            $stmt->execute($availability);
            $insertedCount++;
        } catch (Exception $e) {
            // Ignore duplicate entries
        }
    }
    
    echo "<p class='success'>✅ Sample doctor availability data inserted ({$insertedCount} records)</p>";
    
    echo "<h2>🎉 Setup Complete!</h2>";
    
    echo "<div class='credentials'>
        <h3>Assistant Account Credentials</h3>
        <p><strong>Username:</strong> smart_assistant</p>
        <p><strong>Password:</strong> SmartAssistant2024!</p>
        <p><strong>Email:</strong> assistant@healthbase.com</p>
        <p><strong>Role:</strong> assistant</p>
        <p><strong>Full Name:</strong> SMART Scheduling Assistant</p>
    </div>";
    
    echo "<h3>Access Points:</h3>";
    echo "<p><a href='index.php' class='btn'>🚀 Access Assistant Dashboard</a></p>";
    echo "<p><a href='doctor_integration.php' class='btn'>👨‍⚕️ Doctor Integration View</a></p>";
    echo "<p><a href='../auth/login.php' class='btn'>🔐 Login Page</a></p>";
    
    echo "<h3>Features Available:</h3>";
    echo "<ul>
        <li>✅ SMART scheduling with AI optimization</li>
        <li>✅ Patient priority management</li>
        <li>✅ Health risk assessment</li>
        <li>✅ Automated notifications</li>
        <li>✅ Doctor integration</li>
        <li>✅ Real-time analytics</li>
        <li>✅ Adaptive scheduling algorithms</li>
    </ul>";
    
} catch (Exception $e) {
    echo "<p class='error'>❌ Setup Error: " . $e->getMessage() . "</p>";
}

echo "</div></body></html>";
?>
