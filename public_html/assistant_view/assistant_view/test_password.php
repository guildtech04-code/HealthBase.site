<?php
require_once '../config/db_connect.php';

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Test Password</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; background: #f5f7fa; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .success { color: #38a169; font-weight: bold; }
        .error { color: #e53e3e; font-weight: bold; }
        .info { color: #2b6cb0; font-weight: bold; }
        .test-form { background: #f7fafc; padding: 20px; border-radius: 8px; margin: 20px 0; }
        input, button { padding: 10px; margin: 5px; border-radius: 4px; border: 1px solid #ccc; }
        button { background: #667eea; color: white; cursor: pointer; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🧪 Test Password Login</h1>";

try {
    if ($_POST) {
        $email = $_POST['email'];
        $password = $_POST['password'];
        
        echo "<h3>Testing Login for: {$email}</h3>";
        
        // Check user in database
        $sql = "SELECT id, email, password, role, first_name, last_name FROM users WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            echo "<p class='error'>❌ User not found with email: {$email}</p>";
        } else {
            echo "<p class='info'>✅ User found:</p>";
            echo "<ul>
                <li>ID: {$user['id']}</li>
                <li>Name: {$user['first_name']} {$user['last_name']}</li>
                <li>Role: {$user['role']}</li>
                <li>Email: {$user['email']}</li>
            </ul>";
            
            // Test password verification
            $passwordMatch = password_verify($password, $user['password']);
            
            if ($passwordMatch) {
                echo "<p class='success'>✅ Password verification SUCCESSFUL!</p>";
                echo "<p class='info'>You can now login with these credentials.</p>";
            } else {
                echo "<p class='error'>❌ Password verification FAILED!</p>";
                echo "<p class='info'>The password '{$password}' does not match the stored hash.</p>";
                
                // Check if password is hashed
                $passwordInfo = password_get_info($user['password']);
                if ($passwordInfo['algo'] === null) {
                    echo "<p class='error'>⚠️ Password is stored as plain text, not hashed!</p>";
                } else {
                    echo "<p class='info'>Password is properly hashed, but doesn't match.</p>";
                }
            }
        }
    }
    
    echo "<div class='test-form'>
        <h3>Test Login Credentials</h3>
        <form method='POST'>
            <input type='email' name='email' placeholder='Email' required style='width: 100%;'><br>
            <input type='password' name='password' placeholder='Password' required style='width: 100%;'><br>
            <button type='submit'>Test Login</button>
        </form>
    </div>";
    
    echo "<div style='text-align: center; margin-top: 30px;'>
        <a href='../auth/login.php' class='btn'>🔐 Go to Login</a>
        <a href='fix_password_hash.php' class='btn'>🔧 Fix Password Hash</a>
    </div>";
    
} catch (Exception $e) {
    echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "</div></body></html>";
?>
