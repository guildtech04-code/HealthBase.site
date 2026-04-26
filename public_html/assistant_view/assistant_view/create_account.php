<?php
require_once '../config/db_connect.php';

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Create Assistant Account</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; background: #f5f7fa; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .success { color: #38a169; font-weight: bold; }
        .error { color: #e53e3e; font-weight: bold; }
        .info { color: #2b6cb0; font-weight: bold; }
        .credentials { background: #f7fafc; padding: 20px; border-radius: 8px; border-left: 4px solid #667eea; margin: 20px 0; }
        .btn { background: #667eea; color: white; padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer; text-decoration: none; display: inline-block; margin: 10px 5px; }
        .btn:hover { background: #5a67d8; }
        h1 { color: #4a5568; text-align: center; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🤖 Create Assistant Account</h1>";

try {
    // Check if assistant account already exists
    $sql = "SELECT id FROM users WHERE username = 'smart_assistant' AND role = 'assistant'";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo "<p class='info'>ℹ️ Assistant account already exists!</p>";
        echo "<div class='credentials'>
            <h3>Existing Assistant Account:</h3>
            <p><strong>Username:</strong> smart_assistant</p>
            <p><strong>Password:</strong> SmartAssistant2024</p>
            <p><strong>Email:</strong> assistant@healthbase.com</p>
            <p><strong>Role:</strong> assistant</p>
        </div>";
    } else {
        // Create assistant account
        $username = 'smart_assistant';
        $email = 'assistant@healthbase.com';
        $password = 'SmartAssistant2024';
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $role = 'assistant';
        
        $sql = "INSERT INTO users (username, email, password, role, first_name, last_name, gender, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW())";
        
        $stmt = $conn->prepare($sql);
        $result = $stmt->execute([$username, $email, $hashedPassword, $role, 'SMART', 'Assistant', 'Male']);
        
        if ($result) {
            echo "<p class='success'>✅ Assistant account created successfully!</p>";
            echo "<div class='credentials'>
                <h3>Assistant Account Credentials:</h3>
                <p><strong>Username:</strong> smart_assistant</p>
                <p><strong>Password:</strong> SmartAssistant2024</p>
                <p><strong>Email:</strong> assistant@healthbase.com</p>
                <p><strong>Role:</strong> assistant</p>
                <p><strong>Name:</strong> SMART Assistant</p>
            </div>";
        } else {
            echo "<p class='error'>❌ Error creating assistant account</p>";
        }
    }
    
    echo "<div style='text-align: center; margin-top: 30px;'>
        <a href='index.php' class='btn'>🚀 Access Assistant Dashboard</a>
        <a href='../auth/login.php' class='btn'>🔐 Login Page</a>
    </div>";
    
} catch (Exception $e) {
    echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "</div></body></html>";
?>
