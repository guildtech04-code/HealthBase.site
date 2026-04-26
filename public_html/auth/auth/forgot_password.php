<?php
// forgot_password.php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../PHPMailer/src/Exception.php';
require '../PHPMailer/src/PHPMailer.php';
require '../PHPMailer/src/SMTP.php';

$success = $error = "";

require '../config/db_connect.php';

// Handle cooldown
if (isset($_SESSION['last_reset_time']) && (time() - $_SESSION['last_reset_time']) < 30) {
    $remaining = 30 - (time() - $_SESSION['last_reset_time']);
    $error = "⏳ Please wait {$remaining} seconds before requesting another reset link.";
} elseif ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);

    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

    $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        $token = bin2hex(random_bytes(32));
        $expires = date("U") + 1800; // 30 mins

        $conn->query("DELETE FROM password_resets WHERE email='$email'");
        $stmt = $conn->prepare("INSERT INTO password_resets (email, token, expires) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $email, $token, $expires);
        $stmt->execute();

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = "smtp.gmail.com";
            $mail->SMTPAuth = true;
            $mail->Username = "guildtech21@gmail.com";
            $mail->Password = "fokb qhkm xvxz qvnd"; // App Password
            $mail->SMTPSecure = "tls";
            $mail->Port = 587;

            $mail->setFrom("guildtech21@gmail.com", "HealthBase Support");
            $mail->addAddress($email);

            $url = "https://healthbase.site/auth/reset_password.php?token=" . urlencode($token);

            $mail->isHTML(true);
            $mail->Subject = "Password Reset Request - HealthBase";
            $mail->Body = "
                <p>Hello,</p>
                <p>We received a request to reset your HealthBase account password.</p>
                <p>
                    <a href=\"$url\" style=\"display:inline-block;background:#00695c;
                       color:#fff;padding:10px 15px;text-decoration:none;border-radius:5px;\">
                       🔑 Reset Password
                    </a>
                </p>
                <p>If the button doesn’t work, copy this link:</p>
                <p><a href=\"$url\">$url</a></p>
                <p>This link will expire in 30 minutes.</p>
                <p>If you didn’t request this, ignore it.</p>
            ";
            $mail->AltBody = "Password reset: $url (expires in 30 minutes).";

            $mail->send();
            $_SESSION['last_reset_time'] = time();
            $success = "✅ Reset link sent! Please check your email.";
        } catch (Exception $e) {
            $error = "❌ Mailer Error: {$mail->ErrorInfo}";
        }
    } else {
        $error = "❌ No account found with that email.";
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password - HealthBase</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        /* Enhanced Forgot Password Page Styles with Animations */
        body.forgot-page {
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 50%, #60a5fa 100%);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
            position: relative;
            overflow: hidden;
            min-height: 100vh;
        }

        body.forgot-page::before {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at 20% 80%, rgba(30, 64, 175, 0.3), transparent 50%),
                        radial-gradient(circle at 80% 20%, rgba(255, 255, 255, 0.2), transparent 50%);
            pointer-events: none;
            animation: pulse 8s ease-in-out infinite;
        }

        body.forgot-page::after {
            content: '';
            position: fixed;
            width: 200%;
            height: 200%;
            top: -50%;
            left: -50%;
            background-image: 
                radial-gradient(circle at 20% 50%, transparent 10%, rgba(255, 255, 255, 0.05) 10.5%, transparent 11%),
                radial-gradient(circle at 80% 80%, transparent 10%, rgba(255, 255, 255, 0.05) 10.5%, transparent 11%),
                radial-gradient(circle at 40% 20%, transparent 10%, rgba(255, 255, 255, 0.05) 10.5%, transparent 11%);
            background-size: 50% 50%;
            animation: floatParticles 20s linear infinite;
            z-index: 0;
            pointer-events: none;
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

        @keyframes floatParticles {
            0% { transform: translate(0, 0) rotate(0deg); }
            50% { transform: translate(-50px, -50px) rotate(180deg); }
            100% { transform: translate(0, 0) rotate(360deg); }
        }

        .main-content {
            position: relative;
            z-index: 1;
        }

        .box {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25),
                        0 0 0 1px rgba(255, 255, 255, 0.7) inset,
                        0 8px 32px rgba(30, 64, 175, 0.2),
                        0 0 40px rgba(59, 130, 246, 0.1);
            animation: slideUp 0.8s cubic-bezier(0.16, 1, 0.3, 1),
                       fadeIn 0.8s ease-out;
            transition: all 0.3s ease;
            position: relative;
        }

        .box::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(135deg, #3b82f6, #60a5fa, #3b82f6);
            border-radius: inherit;
            z-index: -1;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .box:hover {
            transform: translateY(-8px) scale(1.01);
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.3),
                        0 0 0 1px rgba(255, 255, 255, 0.7) inset,
                        0 12px 40px rgba(30, 64, 175, 0.4),
                        0 0 60px rgba(59, 130, 246, 0.2);
        }

        .box:hover::before {
            opacity: 0.5;
            animation: shimmer 2s ease-in-out infinite;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(40px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes shimmer {
            0% { background-position: 0% 50%; }
            100% { background-position: 200% 50%; }
        }

        .logo-container {
            animation: logoPulse 2s ease-in-out infinite;
        }

        @keyframes logoPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .logo {
            filter: drop-shadow(0 4px 8px rgba(0, 45, 192, 0.2));
            transition: all 0.3s ease;
        }

        .logo:hover {
            transform: rotate(5deg) scale(1.1);
        }

        h2 {
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: textShine 3s ease-in-out infinite;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        @keyframes textShine {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        input[type="email"] {
            background: rgba(255, 255, 255, 0.9);
            border: 2px solid #e2e8f0;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            animation: inputFadeIn 0.5s ease-out 0.1s backwards;
        }

        @keyframes inputFadeIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        input[type="email"]:focus {
            border-color: #3b82f6;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15),
                        0 4px 12px rgba(59, 130, 246, 0.2),
                        0 0 20px rgba(59, 130, 246, 0.1);
            transform: translateY(-2px);
            outline: none;
        }

        input::placeholder {
            color: #94a3b8;
            transition: all 0.3s ease;
        }

        input:focus::placeholder {
            color: #cbd5e1;
            transform: translateX(5px);
        }

        .bg-decoration {
            content: '';
            position: fixed;
            top: 0;
            right: 0;
            width: 50%;
            height: 100%;
            background: url("../assets/images/LoginPic.png") center center no-repeat;
            background-size: cover;
            z-index: 0;
            opacity: 0.3;
        }

        label {
            color: #1e293b;
            margin-bottom: 8px;
            display: block;
            font-weight: 600;
        }

        button[type="submit"] {
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
            color: white;
            border: none;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(30, 64, 175, 0.4);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }

        button[type="submit"]::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.8s, height 0.8s;
        }

        button[type="submit"]::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s;
        }

        button[type="submit"]:hover {
            transform: translateY(-4px);
            box-shadow: 0 15px 40px rgba(30, 64, 175, 0.6);
        }

        button[type="submit"]:hover::before {
            width: 350px;
            height: 350px;
        }

        button[type="submit"]:hover::after {
            left: 100%;
        }

        button[type="submit"]:active {
            transform: translateY(-1px);
            box-shadow: 0 5px 15px rgba(30, 64, 175, 0.4);
        }

        .back-btn {
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            transform: translateX(-3px) scale(1.1);
        }

        .msg {
            padding: 14px 18px;
            border-radius: 12px;
            font-size: 14px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.5s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .msg.success {
            color: white;
            background: linear-gradient(135deg, #10b981, #059669);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .msg.success::before {
            content: '✓';
            font-size: 20px;
        }

        .msg.error {
            color: white;
            background: linear-gradient(135deg, #ff6b6b, #ee5a6f);
            box-shadow: 0 4px 12px rgba(238, 90, 111, 0.3);
            animation: shakeError 0.5s ease;
        }

        .msg.error::before {
            content: '⚠️';
            font-size: 18px;
        }

        @keyframes shakeError {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .bg-decoration {
                display: none;
            }

            .box {
                width: 90%;
                max-width: 380px;
                padding: 30px 20px;
            }

            h2 {
                font-size: 24px;
            }
        }

        /* Accessibility Improvements */
        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>
<body class="forgot-page">
    <div class="bg-decoration"></div>
    <div class="main-content">
        <div class="box">
            <a href="login.php" class="back-btn"><</a>
            <div class="logo-container">
                <img src="../assets/images/Logo.png" alt="HealthBase Logo" class="logo">
            </div>
            <h2>Forgot Password</h2>
            
            <?php if($success): ?>
                <p class="msg success"><?= $success ?></p>
            <?php else: ?>
                <form action="" method="POST">
                    <label for="email">
                        <i class="fa-solid fa-envelope" style="margin-right: 8px; color: #3b82f6;"></i>
                        Enter Email Address
                    </label>
                    <input type="email" id="email" name="email" placeholder="Enter your email address" required autocomplete="email">
                    <button type="submit">
                        <i class="fa-solid fa-paper-plane" style="margin-right: 8px;"></i>
                        Send Reset Link
                    </button>
                </form>

                <?php if($error): ?>
                    <p class="msg error"><?= $error ?></p>
                <?php endif; ?>
            <?php endif; ?>
            
            <div style="margin-top: 20px; text-align: center;">
                <a href="login.php" style="color: #3b82f6; text-decoration: none; font-weight: 600; font-size: 14px;">
                    <i class="fa-solid fa-arrow-left" style="margin-right: 5px;"></i>
                    Back to Login
                </a>
            </div>
        </div>
    </div>

    <script>
        // Add smooth input focus animation
        const emailInput = document.getElementById('email');
        if (emailInput) {
            emailInput.addEventListener('focus', function() {
                this.parentElement.style.transform = 'scale(1.02)';
            });
            
            emailInput.addEventListener('blur', function() {
                this.parentElement.style.transform = 'scale(1)';
            });
        }

        // Auto-focus on email input
        setTimeout(() => {
            if (emailInput) emailInput.focus();
        }, 300);
    </script>
</body>
</html>
