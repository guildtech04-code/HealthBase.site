<?php
require_once '../../config/db_connect.php';

try {
    // Check if assistant account already exists
    $sql = "SELECT id FROM users WHERE username = 'smart_assistant' AND role = 'assistant'";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo "Assistant account already exists!\n";
        echo "Username: smart_assistant\n";
        echo "Password: SmartAssistant2024!\n";
        echo "Role: assistant\n";
        exit();
    }
    
    // Create assistant account
    $username = 'smart_assistant';
    $email = 'assistant@healthbase.com';
    $password = 'SmartAssistant2024';
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $role = 'assistant';
    $fullName = 'SMART Scheduling Assistant';
    
    $sql = "INSERT INTO users (username, email, password, role, first_name, last_name, gender, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW())";
    
    $stmt = $conn->prepare($sql);
    $result = $stmt->execute([$username, $email, $hashedPassword, $role, 'SMART', 'Assistant', 'Male']);
    
    if ($result) {
        echo "✅ Assistant account created successfully!\n\n";
        echo "Login Credentials:\n";
        echo "Username: smart_assistant\n";
        echo "Password: SmartAssistant2024!\n";
        echo "Email: assistant@healthbase.com\n";
        echo "Role: assistant\n";
        echo "Full Name: SMART Scheduling Assistant\n\n";
        echo "You can now access the assistant view at: assistant_view/index.php\n";
    } else {
        echo "❌ Error creating assistant account\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
