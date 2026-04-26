<?php
// reset_password.php
session_start();
require '../config/db_connect.php';

$token = $_GET['token'] ?? '';
$error = "";
$success = false;
$valid_token = false;

// Check if token is valid when page loads
function validateToken($token_string, $servername, $username, $password, $dbname) {
    if (!$token_string) return false;
    
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) return false;
    
    $stmt = $conn->prepare("SELECT email, expires FROM password_resets WHERE token = ?");
    $stmt->bind_param("s", $token_string);
    $stmt->execute();
    $res = $stmt->get_result();
    
    $is_valid = false;
    if ($row = $res->fetch_assoc()) {
        if ($row['expires'] >= time()) {
            $is_valid = true;
        }
    }
    $conn->close();
    return $is_valid;
}

if ($token) {
    $valid_token = validateToken($token, $servername, $username, $password, $dbname);
    if (!$valid_token && !$error) {
        $error = "❌ Invalid or expired token!";
    }
} else {
    $error = "❌ Invalid reset link!";
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $post_token = $_POST['token'] ?? '';
    $new_pass = $_POST['password'];
    $confirm_pass = $_POST['confirm_password'];
    $error = ""; // Reset error for POST

    // Check password strength with regex
    if (!preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/", $new_pass)) {
        $error = "❌ Password must be at least 8 characters, include uppercase, lowercase, and a number!";
    } elseif ($new_pass !== $confirm_pass) {
        $error = "❌ Passwords do not match!";
    } else {
        // Re-validate token before processing
        if (!validateToken($post_token, $servername, $username, $password, $dbname)) {
            $error = "❌ Invalid or expired token!";
            $valid_token = false;
        } else {
            $conn = new mysqli($servername, $username, $password, $dbname);
            if ($conn->connect_error) {
                $error = "❌ Database connection failed!";
            } else {
                $stmt = $conn->prepare("SELECT email, expires FROM password_resets WHERE token = ?");
                $stmt->bind_param("s", $post_token);
                $stmt->execute();
                $res = $stmt->get_result();

                if ($row = $res->fetch_assoc()) {
                    if ($row['expires'] < time()) {
                        $error = "❌ Link expired!";
                    } else {
                        $email = $row['email'];
                        $hashed = password_hash($new_pass, PASSWORD_BCRYPT);

                        $update = $conn->prepare("UPDATE users SET password=? WHERE email=?");
                        $update->bind_param("ss", $hashed, $email);
                        $update->execute();

                        $conn->query("DELETE FROM password_resets WHERE email='$email'");

                        $success = true;
                    }
                } else {
                    $error = "❌ Invalid or expired token!";
                }
                $conn->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Reset Password - HealthBase</title>
  <style>
    body { 
        font-family: 'Segoe UI', sans-serif; 
        background-color: #f4f6f8; 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        height: 100vh; 
        margin: 0; 
    }
    .box { 
        background: white; 
        padding: 30px 40px; 
        border-radius: 10px; 
        box-shadow: 0 4px 12px rgba(0,0,0,0.1); 
        max-width: 400px; 
        width: 100%; 
        text-align: center; 
    }
    h2 { color: #00695c; margin-bottom: 20px; }
    
    .input-container { 
        position: relative; 
        margin-bottom: 20px; 
        display: flex;
        align-items: center;
    }
    .input-container input { 
        width: 100%; 
        padding: 10px 40px 10px 12px; /* space for eye */
        border-radius: 6px; 
        border: 1px solid #ccc; 
        font-size: 14px; 
        box-sizing: border-box; 
    }
    .eye-icon { 
        position: absolute; 
        right: 12px; 
        font-size: 16px; 
        color: #555; 
        cursor: pointer; 
    }

    button { 
        width: 100%; 
        padding: 10px; 
        border-radius: 6px; 
        background-color: #00695c; 
        color: white; 
        border: none; 
        font-weight: bold; 
        cursor: pointer; 
    }
    p { font-size: 0.9rem; color: red; }
    .success { color: #00695c; font-size: 1rem; }
    .check-icon { font-size: 50px; color: green; margin-bottom: 15px; }

    #strengthBar { height: 6px; width: 100%; background: #ddd; border-radius: 3px; margin-top: 5px; }
    #strengthBar div { height: 100%; width: 0%; border-radius: 3px; transition: width 0.3s; }
    #strengthText { font-size: 0.8rem; margin-top: 5px; text-align: left; }
  </style>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<div class="box">
    <?php if($success): ?>
        <div class="check-icon">✅</div>
        <h2>Password Reset Successful</h2>
        <p class="success">Your password has been reset.<br>Redirecting to login page...</p>
        <script>
            setTimeout(function() {
                window.location.href = "login.php";
            }, 3000);
        </script>
    <?php else: ?>
        <?php if (!$token || !$valid_token): ?>
            <h2>Reset Password</h2>
            <p style="color: red; font-size: 14px; margin: 10px 0;"><?= $error ?></p>
            <p style="margin-top: 15px;"><a href="forgot_password.php" style="color: #00695c; text-decoration: none; font-weight: bold;">Request a new reset link</a></p>
        <?php else: ?>
        <h2>Reset Password</h2>
        <?php if($error): ?>
            <p style="color: red; font-size: 14px; margin-bottom: 15px; padding: 10px; background: #ffe5e5; border-radius: 5px;"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        <form method="POST" action="" id="resetForm" autocomplete="off" onsubmit="return validatePassword()">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
            <div class="input-container">
                <input type="password" name="password" id="password" placeholder="Enter new password" required onkeyup="checkStrength(this.value)">
                <i class="fa-solid fa-eye eye-icon" onclick="togglePassword('password', this)"></i>
            </div>
            <div id="strengthBar"><div></div></div>
            <div id="strengthText"></div>

            <div class="input-container">
                <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm new password" required>
                <i class="fa-solid fa-eye eye-icon" onclick="togglePassword('confirm_password', this)"></i>
            </div>
            <button type="submit">✅ Reset Password</button>
        </form>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
function togglePassword(fieldId, icon) {
    let field = document.getElementById(fieldId);
    if (field.type === "password") {
        field.type = "text";
        icon.classList.remove("fa-eye");
        icon.classList.add("fa-eye-slash");
    } else {
        field.type = "password";
        icon.classList.remove("fa-eye-slash");
        icon.classList.add("fa-eye");
    }
}

function checkStrength(password) {
    let strengthBar = document.getElementById("strengthBar").firstElementChild;
    let strengthText = document.getElementById("strengthText");
    let strength = 0;

    if (password.match(/[a-z]/)) strength++;
    if (password.match(/[A-Z]/)) strength++;
    if (password.match(/[0-9]/)) strength++;
    if (password.length >= 8) strength++;

    switch (strength) {
        case 0: strengthBar.style.width = "0%"; strengthBar.style.background = "#ddd"; strengthText.textContent = ""; break;
        case 1: strengthBar.style.width = "25%"; strengthBar.style.background = "red"; strengthText.textContent = "Weak"; strengthText.style.color = "red"; break;
        case 2: strengthBar.style.width = "50%"; strengthBar.style.background = "orange"; strengthText.textContent = "Fair"; strengthText.style.color = "orange"; break;
        case 3: strengthBar.style.width = "75%"; strengthBar.style.background = "#ffcc00"; strengthText.textContent = "Strong"; strengthText.style.color = "#ffcc00"; break;
        case 4: strengthBar.style.width = "100%"; strengthBar.style.background = "green"; strengthText.textContent = "Very Strong"; strengthText.style.color = "green"; break;
    }
}

function validatePassword() {
    let password = document.getElementById("password").value;
    let regex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/;
    if (!regex.test(password)) {
        alert("❌ Password must be at least 8 characters, include uppercase, lowercase, and a number!");
        return false;
    }
    return true;
}
</script>
</body>
</html>
