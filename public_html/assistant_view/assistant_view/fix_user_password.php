<?php
require_once '../config/db_connect.php';

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Fix User Password</title>
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
        <h1>🔧 Fix User Password</h1>";

try {
    // Check if user ID 24 exists
    $sql = "SELECT id, username, email, role, first_name, last_name FROM users WHERE id = 24";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo "<p class='error'>❌ User ID 24 not found in database</p>";
        echo "<p class='info'>Let me check what users exist...</p>";
        
        // Show all users
        $sql = "SELECT id, username, email, role, first_name, last_name FROM users ORDER BY id DESC LIMIT 10";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h3>Recent Users:</h3>";
        echo "<table border='1' style='width: 100%; border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Name</th></tr>";
        foreach ($users as $u) {
            echo "<tr>";
            echo "<td>{$u['id']}</td>";
            echo "<td>{$u['username']}</td>";
            echo "<td>{$u['email']}</td>";
            echo "<td>{$u['role']}</td>";
            echo "<td>{$u['first_name']} {$u['last_name']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
    } else {
        echo "<p class='success'>✅ Found user ID 24:</p>";
        echo "<div class='credentials'>
            <p><strong>ID:</strong> {$user['id']}</p>
            <p><strong>Username:</strong> {$user['username']}</p>
            <p><strong>Email:</strong> {$user['email']}</p>
            <p><strong>Role:</strong> {$user['role']}</p>
            <p><strong>Name:</strong> {$user['first_name']} {$user['last_name']}</p>
        </div>";
        
        // Fix the password
        $newPassword = 'admin123';
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $sql = "UPDATE users SET password = ? WHERE id = 24";
        $stmt = $conn->prepare($sql);
        $result = $stmt->execute([$hashedPassword]);
        
        if ($result) {
            echo "<p class='success'>✅ Password updated successfully!</p>";
            echo "<div class='credentials'>
                <h3>Updated Login Credentials:</h3>
                <p><strong>Username:</strong> {$user['username']}</p>
                <p><strong>Password:</strong> admin123</p>
                <p><strong>Email:</strong> {$user['email']}</p>
                <p><strong>Role:</strong> {$user['role']}</p>
            </div>";
        } else {
            echo "<p class='error'>❌ Failed to update password</p>";
        }
    }
    
    echo "<div style='text-align: center; margin-top: 30px;'>
        <a href='../auth/login.php' class='btn'>🔐 Go to Login</a>
        <a href='index.php' class='btn'>🚀 Assistant Dashboard</a>
    </div>";
    
} catch (Exception $e) {
    echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "</div></body></html>";
?>
