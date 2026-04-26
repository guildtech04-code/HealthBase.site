<?php
require_once '../config/db_connect.php';

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Fix Password Hash</title>
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
        .code { background: #f1f5f9; padding: 10px; border-radius: 4px; font-family: monospace; margin: 10px 0; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔐 Fix Password Hash</h1>";

try {
    // Check if user ID 24 exists
    $sql = "SELECT id, username, email, password, role, first_name, last_name FROM users WHERE id = 24";
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
            <p><strong>Current Password (raw):</strong> {$user['password']}</p>
        </div>";
        
        // Check if password is already hashed
        $isHashed = password_get_info($user['password']);
        
        if ($isHashed['algo'] === null) {
            echo "<p class='info'>🔍 Password is NOT hashed (stored as plain text)</p>";
            
            // Hash the password
            $plainPassword = $user['password']; // This is 'admin123'
            $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);
            
            echo "<p class='info'>🔧 Hashing password: <strong>{$plainPassword}</strong></p>";
            echo "<div class='code'>Hashed: {$hashedPassword}</div>";
            
            // Update the password in database
            $sql = "UPDATE users SET password = ? WHERE id = 24";
            $stmt = $conn->prepare($sql);
            $result = $stmt->execute([$hashedPassword]);
            
            if ($result) {
                echo "<p class='success'>✅ Password hashed and updated successfully!</p>";
                echo "<div class='credentials'>
                    <h3>Updated Login Credentials:</h3>
                    <p><strong>Username:</strong> {$user['username']}</p>
                    <p><strong>Password:</strong> {$plainPassword}</p>
                    <p><strong>Email:</strong> {$user['email']}</p>
                    <p><strong>Role:</strong> {$user['role']}</p>
                </div>";
            } else {
                echo "<p class='error'>❌ Failed to update password</p>";
            }
            
        } else {
            echo "<p class='success'>✅ Password is already properly hashed</p>";
            echo "<p class='info'>The password should work. If login still fails, try:</p>";
            echo "<ul>
                <li>Clear browser cache and cookies</li>
                <li>Try a different browser</li>
                <li>Check if the email is correct</li>
            </ul>";
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
