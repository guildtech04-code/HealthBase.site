<?php
// Simple test login form to debug the redirect issue
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Debug Login Test</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; }
        input[type="email"], input[type="password"] { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; }
        button { background: #007cba; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        .debug { background: #f0f0f0; padding: 15px; margin: 20px 0; border-radius: 4px; }
    </style>
</head>
<body>
    <h1>Debug Login Test</h1>
    
    <div class="debug">
        <h3>Debug Information</h3>
        <p><strong>Current URL:</strong> <?= $_SERVER['REQUEST_URI'] ?></p>
        <p><strong>Current Directory:</strong> <?= getcwd() ?></p>
        <p><strong>Session ID:</strong> <?= session_id() ?></p>
        <p><strong>Session Data:</strong></p>
        <pre><?= print_r($_SESSION, true) ?></pre>
    </div>

    <h2>Test Login Form</h2>
    <form action="auth.php" method="POST">
        <div class="form-group">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required>
        </div>
        
        <div class="form-group">
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required>
        </div>
        
        <div class="form-group">
            <label>
                <input type="checkbox" name="remember"> Remember Me
            </label>
        </div>
        
        <button type="submit">Test Login</button>
    </form>

    <div class="debug">
        <h3>Expected Flow</h3>
        <ol>
            <li>Submit this form to <code>auth.php</code></li>
            <li><code>auth.php</code> should redirect to <code>verify_2fa.php</code></li>
            <li>You should see the 2FA verification form</li>
        </ol>
        
        <h3>Test Links</h3>
        <p><a href="login.php">Go to Original Login</a></p>
        <p><a href="verify_2fa.php">Direct Access to verify_2fa.php</a></p>
        <p><a href="debug_verify_2fa.php">Debug verify_2fa.php</a></p>
    </div>
</body>
</html>




