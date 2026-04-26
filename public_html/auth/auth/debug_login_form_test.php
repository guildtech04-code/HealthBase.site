<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Debug Login Form</title>
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
    <h1>Debug Login Form</h1>
    
    <div class="debug">
        <h3>Current URL Information</h3>
        <p><strong>Current URL:</strong> <span id="current-url"></span></p>
        <p><strong>Form Action:</strong> <span id="form-action"></span></p>
    </div>

    <h2>Test Login Form</h2>
    <form id="login-form" action="debug_login_flow.php" method="POST">
        <div class="form-group">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" value="test@example.com" required>
        </div>
        
        <div class="form-group">
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" value="test123" required>
        </div>
        
        <div class="form-group">
            <label>
                <input type="checkbox" name="remember"> Remember Me
            </label>
        </div>
        
        <button type="submit">Test Login</button>
    </form>

    <div class="debug">
        <h3>Test Links</h3>
        <p><a href="login.php">Original Login</a></p>
        <p><a href="auth.php">Original Auth Handler</a></p>
        <p><a href="verify_2fa.php">Direct 2FA Access</a></p>
    </div>

    <script>
        // Show current URL information
        document.getElementById('current-url').textContent = window.location.href;
        document.getElementById('form-action').textContent = document.getElementById('login-form').action;
    </script>
</body>
</html>




