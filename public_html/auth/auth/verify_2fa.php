<?php
session_start();
require '../config/db_connect.php';

// Redirect if no pending login
if (!isset($_SESSION['pending_user_id'])) {
    header("Location: login.php?error=session_expired");
    exit();
}

$errors = [];
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entered_code = trim($_POST['code'] ?? '');
    
    if ($entered_code === '') {
        $errors[] = "Please enter the verification code.";
    } elseif (!isset($_SESSION['otp_code'])) {
        $errors[] = "Verification code expired. Please login again.";
    } elseif (time() > $_SESSION['otp_expiry']) {
        $errors[] = "Verification code has expired. Please login again.";
        unset($_SESSION['otp_code']);
        unset($_SESSION['pending_user_id']);
    } elseif ($entered_code == $_SESSION['otp_code']) {
        // ✅ OTP is correct - Complete login
        
        // Get user ID and role
        $user_id = $_SESSION['pending_user_id'];
        $role = $_SESSION['pending_role'];
        
        // Set trusted device cookie (valid for 20 minutes)
        $trustedToken = bin2hex(random_bytes(32));
        setcookie('trusted_device_' . $user_id, $trustedToken, time() + (20 * 60), '/');
        setcookie('trusted_until_' . $user_id, time() + (20 * 60), time() + (20 * 60), '/');
        
        // Get user email from database
        $stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        // Set session variables
        $_SESSION['user_id'] = $user_id;
        $_SESSION['role'] = $role;
        $_SESSION['email'] = $user['email'];
        $_SESSION['logged_in'] = true;
        $_SESSION['last_activity'] = time();
        
        // Handle remember me
        if (!empty($_SESSION['remember_me'])) {
            $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
            setcookie('rememberUser', $_SESSION['remember_me'], [
                'expires' => time() + (30 * 24 * 60 * 60),
                'path' => '/',
                'secure' => $secure,
                'httponly' => false,
                'samesite' => 'Lax',
            ]);
        }
        
        // Clear pending login data
        unset($_SESSION['pending_user_id']);
        unset($_SESSION['pending_role']);
        unset($_SESSION['otp_code']);
        unset($_SESSION['otp_expiry']);
        unset($_SESSION['remember_me']);
        
        // Redirect based on role
        $role = $role;
        if ($role === 'admin') {
            header("Location: ../dashboard/healthbase_dashboard.php");
        } elseif ($role === 'doctor') {
            header("Location: ../appointments/doctor_dashboard.php");
        } elseif ($role === 'assistant') {
            header("Location: ../assistant_view/index.php");
        } else {
            header("Location: ../patient/patient_dashboard.php");
        }
        exit();
} else {
        $errors[] = "Invalid verification code.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Email Verification - HealthBase</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { 
            font-family: 'Inter', 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 50%, #60a5fa 100%);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
            display: flex; 
            align-items: center; 
            justify-content: center; 
            min-height: 100vh; 
            margin: 0;
            position: relative;
            overflow: hidden;
        }

        body::before {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at 20% 80%, rgba(30, 64, 175, 0.2), transparent 50%),
                        radial-gradient(circle at 80% 20%, rgba(255, 255, 255, 0.2), transparent 50%);
            pointer-events: none;
            animation: pulse 8s ease-in-out infinite;
        }

        body::after {
            content: '';
            position: fixed;
            top: 0;
            right: 0;
            width: 50%;
            height: 100%;
            background: url("../assets/images/LoginPic.png") center center no-repeat;
            background-size: cover;
            z-index: 0;
        }

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; }
        }

        .box { 
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            padding: 45px 40px; 
            border-radius: 20px; 
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25),
                        0 0 0 1px rgba(255, 255, 255, 0.7) inset,
                        0 8px 32px rgba(30, 64, 175, 0.2);
            width: 100%;
            max-width: 450px;
            text-align: center;
            position: relative;
            z-index: 1;
            animation: slideUp 0.8s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .verification-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            font-size: 36px;
            color: white;
            box-shadow: 0 8px 25px rgba(30, 64, 175, 0.4);
            animation: iconPulse 2s ease-in-out infinite;
        }

        @keyframes iconPulse {
            0%, 100% { transform: scale(1); box-shadow: 0 8px 25px rgba(30, 64, 175, 0.4); }
            50% { transform: scale(1.05); box-shadow: 0 12px 35px rgba(30, 64, 175, 0.6); }
        }

        h2 { 
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 25px;
            font-size: 28px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .hint {
            font-size: 15px; 
            color: #64748b; 
            margin-bottom: 25px;
            padding: 15px;
            background: rgba(59, 130, 246, 0.05);
            border-radius: 12px;
            border: 1px solid rgba(59, 130, 246, 0.1);
        }

        .hint b {
            color: #3b82f6;
            font-weight: 600;
        }

        input {
            width: 100%; 
            padding: 14px 20px; 
            margin-bottom: 20px; 
            border-radius: 12px; 
            border: 2px solid transparent; 
            font-size: 16px;
            box-sizing: border-box;
            background: rgba(255, 255, 255, 0.8);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        input:focus { 
            outline: none; 
            border-color: #3b82f6; 
            background: #fff;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1),
                        0 4px 12px rgba(59, 130, 246, 0.15);
            transform: translateY(-2px);
        }

        input::placeholder {
            color: #94a3b8;
        }

        button { 
            width: 100%;
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%); 
            color: white; 
            font-weight: 600; 
            border: none; 
            cursor: pointer;
            padding: 14px 20px;
            border-radius: 12px;
            font-size: 16px;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 8px 25px rgba(30, 64, 175, 0.4);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        button::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        button:hover::before {
            width: 300px;
            height: 300px;
        }

        button:hover { 
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(30, 64, 175, 0.5);
        }

        button:active {
            transform: translateY(-1px);
        }

        .error {
            color: white;
            background: linear-gradient(135deg, #ff6b6b, #ee5a6f);
            padding: 14px 18px;
            border-radius: 12px;
            font-size: 14px;
            margin-bottom: 20px;
            text-align: left;
            box-shadow: 0 4px 12px rgba(238, 90, 111, 0.3);
            display: flex;
            align-items: center;
            gap: 10px;
            animation: shakeError 0.5s ease;
        }

        @keyframes shakeError {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }

        .error::before {
            content: '⚠️';
            font-size: 20px;
        }

        .back-link {
            margin-top: 20px;
            text-align: center;
        }

        .back-link a {
            color: #3b82f6;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .back-link a:hover {
            color: #1e40af;
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            body::after {
                display: none;
            }

            .box {
                width: 90%;
                padding: 35px 25px;
            }

            h2 {
                font-size: 24px;
            }

            .verification-icon {
                width: 60px;
                height: 60px;
                font-size: 28px;
            }
        }
    </style>
</head>
<body>
<div class="box">
    <div class="verification-icon">
        <i class="fa-solid fa-shield-halved"></i>
    </div>
    
    <h2>Two-Factor Verification</h2>

    <?php foreach($errors as $e): ?>
        <div class="error"><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <div class="hint">
        <i class="fa-solid fa-envelope" style="margin-right: 8px; color: #3b82f6;"></i>
        We sent a 6-digit verification code to your email
    </div>

    <form method="POST">
        <input type="text" 
               name="code" 
               placeholder="Enter 6-Digit Code" 
               required
               autocomplete="off"
               maxlength="6"
               pattern="[0-9]{6}"
               style="letter-spacing: 8px; text-align: center; font-size: 24px; font-weight: 700;">
        <button type="submit">
            <i class="fa-solid fa-check-circle" style="margin-right: 8px;"></i>
            Verify & Login
        </button>
    </form>

    <div class="back-link">
        <a href="login.php">
            <i class="fa-solid fa-arrow-left" style="margin-right: 5px;"></i>
            Back to Login
        </a>
    </div>
</div>

<script>
// Auto-focus on input
const codeInput = document.querySelector('input[name="code"]');
if (codeInput) {
    setTimeout(() => codeInput.focus(), 300);
    
    // Auto-submit when 6 digits are entered
    codeInput.addEventListener('input', function(e) {
        this.value = this.value.replace(/[^0-9]/g, '');
        if (this.value.length === 6) {
            this.form.submit();
        }
    });
}
</script>
</body>
</html>
