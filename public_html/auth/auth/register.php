<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require '../PHPMailer/src/Exception.php';
require '../PHPMailer/src/PHPMailer.php';
require '../PHPMailer/src/SMTP.php';

require '../config/db_connect.php';

$errors = [];
$first_name = $last_name = $email = $username = $gender = $date_of_birth = $phone = "";
$confirm = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $username   = trim($_POST['username'] ?? '');
    $gender     = trim($_POST['gender'] ?? '');
    $date_of_birth = trim($_POST['date_of_birth'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $password   = $_POST['password'] ?? '';
    $confirm    = $_POST['confirm_password'] ?? '';

    // Validation
    if ($password !== $confirm) {
        $errors[] = "Passwords do not match.";
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $password)) {
        $errors[] = "Password must be at least 8 characters, include uppercase, lowercase, and number.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    } elseif ($gender === '') {
        $errors[] = "Please select your gender.";
    } elseif (empty($date_of_birth)) {
        $errors[] = "Please enter your date of birth.";
    } elseif ($date_of_birth && strtotime($date_of_birth) > strtotime('today')) {
        $errors[] = "Date of birth cannot be in the future.";
    } elseif ($phone && !preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) {
        $errors[] = "Please enter a valid phone number.";
    }

    // Check duplicates
    if (empty($errors)) {
        $checks = [
            ["SELECT id FROM users WHERE email=?", "s", [$email], "Email already registered."],
            ["SELECT id FROM users WHERE username=?", "s", [$username], "Username already taken."],
            ["SELECT id FROM users WHERE first_name=? AND last_name=?", "ss", [$first_name, $last_name], "This name is already registered."]
        ];
        foreach ($checks as [$sql, $types, $params, $msg]) {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) $errors[] = $msg;
            $stmt->close();
        }
    }

    // Send verification email
    if (empty($errors)) {
        $verification_code = rand(100000, 999999);
        $_SESSION['reg_data'] = [
            'first_name' => $first_name,
            'last_name'  => $last_name,
            'email'      => $email,
            'username'   => $username,
            'gender'     => $gender,
            'date_of_birth' => $date_of_birth,
            'phone'      => $phone,
            'password'   => password_hash($password, PASSWORD_BCRYPT),
            'code'       => $verification_code
        ];

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'guildtech21@gmail.com';
            $mail->Password = 'fokb qhkm xvxz qvnd';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom('guildtech21@gmail.com', 'HealthBase');
            $mail->addAddress($email, "$first_name $last_name");

            $mail->isHTML(true);
            $mail->Subject = "Your Verification Code";
            $mail->Body = "Hello <b>$first_name $last_name</b>,<br><br>Your verification code is:
                           <h2>$verification_code</h2>";

            $mail->send();
            header("Location: verify.php");
            exit;
        } catch (Exception $e) {
            $errors[] = "Mailer Error: " . $mail->ErrorInfo;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Account - HealthBase</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <link rel="stylesheet" href="../css/style.css">
    <style>
        /* Enhanced Register Page Styles with Animations */
        body.register-page {
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 50%, #60a5fa 100%);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
            position: relative;
            overflow: hidden;
            min-height: 100vh;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        html, body.register-page {
            height: 100%;
        }

        body.register-page *,
        body.register-page input,
        body.register-page select,
        body.register-page button,
        body.register-page label,
        body.register-page p,
        body.register-page h2 {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
        }

        body.register-page::before {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at 20% 80%, rgba(30, 64, 175, 0.3), transparent 50%),
                        radial-gradient(circle at 80% 20%, rgba(255, 255, 255, 0.2), transparent 50%);
            pointer-events: none;
            animation: pulse 8s ease-in-out infinite;
        }

        body.register-page::after {
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

        body.register-page .main-content {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 10px 0;
            box-sizing: border-box;
        }

        body.register-page .box {
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
            width: min(440px, 92vw);
            max-height: calc(100vh - 20px);
            overflow: hidden;
            padding: 16px 20px;
            box-sizing: border-box;
        }

        body.register-page .box::before {
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

        body.register-page .box:hover {
            transform: translateY(-8px) scale(1.01);
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.3),
                        0 0 0 1px rgba(255, 255, 255, 0.7) inset,
                        0 12px 40px rgba(30, 64, 175, 0.4),
                        0 0 60px rgba(59, 130, 246, 0.2);
        }

        body.register-page .box:hover::before {
            opacity: 0.5;
            animation: shimmer 2s ease-in-out infinite;
        }

        body.register-page .logo-container {
            animation: logoPulse 2s ease-in-out infinite;
        }

        @keyframes logoPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        body.register-page .logo-container img {
            filter: drop-shadow(0 4px 8px rgba(0, 45, 192, 0.2));
            transition: all 0.3s ease;
        }

        body.register-page .logo-container img:hover {
            transform: rotate(5deg) scale(1.1);
        }

        body.register-page h2 {
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: textShine 3s ease-in-out infinite;
            font-weight: 700;
            letter-spacing: -0.5px;
            font-size: 26px;
            margin: 8px 0 12px;
        }

        @keyframes textShine {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        body.register-page input,
        body.register-page input[type="date"],
        body.register-page input[type="tel"],
        body.register-page .gender-select {
            width: 100%;
            padding: 10px 14px;
            margin-bottom: 10px;
            border-radius: 12px;
            border: 2px solid #e2e8f0;
            font-size: 14px;
            box-sizing: border-box;
            background: rgba(255, 255, 255, 0.9);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            font-family: 'Inter', 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: #1e293b;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            height: auto;
            min-height: 42px;
            line-height: 1.5;
        }

        body.register-page .gender-select {
            padding-right: 46px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%23334155' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 16px center;
            background-size: 14px;
            cursor: pointer;
        }
        
        /* Date input specific styling to match other inputs */
        body.register-page input[type="date"] {
            color: #1e293b;
            position: relative;
            cursor: text;
        }
        
        body.register-page input[type="date"]:invalid:not(:focus) {
            color: #94a3b8;
        }
        
        body.register-page input[type="date"]::-webkit-calendar-picker-indicator {
            cursor: pointer;
            opacity: 0.6;
            transition: opacity 0.3s ease;
            filter: brightness(0.5);
            margin-left: 5px;
            padding: 5px;
        }
        
        body.register-page input[type="date"]::-webkit-calendar-picker-indicator:hover {
            opacity: 1;
            filter: brightness(0.7);
        }
        
        body.register-page input[type="date"]::-webkit-datetime-edit {
            padding: 0;
            color: inherit;
        }
        
        body.register-page input[type="date"]::-webkit-datetime-edit-text {
            color: inherit;
            padding: 0 2px;
        }
        
        body.register-page input[type="date"]::-webkit-datetime-edit-month-field,
        body.register-page input[type="date"]::-webkit-datetime-edit-day-field,
        body.register-page input[type="date"]::-webkit-datetime-edit-year-field {
            color: inherit;
            padding: 0 2px;
        }
        
        body.register-page input[type="date"]::-webkit-inner-spin-button,
        body.register-page input[type="date"]::-webkit-clear-button {
            display: none;
        }
        
        /* Tel input specific styling */
        body.register-page input[type="tel"] {
            -webkit-appearance: none;
            -moz-appearance: textfield;
        }
        
        body.register-page input[type="tel"]::-webkit-inner-spin-button,
        body.register-page input[type="tel"]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        body.register-page input:focus,
        body.register-page input[type="date"]:focus,
        body.register-page input[type="tel"]:focus,
        body.register-page .gender-select:focus {
            border-color: #3b82f6;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15),
                        0 4px 12px rgba(59, 130, 246, 0.2),
                        0 0 20px rgba(59, 130, 246, 0.1);
            transform: translateY(-2px);
            outline: none;
        }

        body.register-page input::placeholder {
            color: #94a3b8;
            transition: all 0.3s ease;
        }

        body.register-page .field-label {
            display: block;
            margin: 0 0 6px;
            font-size: 12px;
            font-weight: 600;
            color: #334155;
        }

        body.register-page input:focus::placeholder {
            color: #cbd5e1;
            transform: translateX(5px);
        }

        body.register-page .error {
            background: linear-gradient(135deg, #ff6b6b, #ee5a6f);
            color: white;
            padding: 12px 16px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(238, 90, 111, 0.3);
            animation: shakeError 0.5s ease;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        @keyframes shakeError {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }

        body.register-page .error::before {
            content: '⚠️';
            font-size: 18px;
        }

        body.register-page .toggle-password {
            position: absolute;
            top: 50%;
            right: 8px;
            width: 38px;
            height: 38px;
            min-width: 38px;
            min-height: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: background-color 0.2s ease;
            transform: translateY(-50%);
        }

        body.register-page .toggle-password i {
            width: 16px;
            text-align: center;
            pointer-events: none;
        }

        /* Prevent Edge/IE native reveal icon from duplicating our custom eye button. */
        body.register-page input[type="password"]::-ms-reveal,
        body.register-page input[type="password"]::-ms-clear {
            display: none;
        }

        body.register-page .toggle-password:hover {
            background: rgba(59, 130, 246, 0.1);
            transform: translateY(-50%);
        }

        body.register-page .toggle-password:active {
            transform: translateY(-50%);
        }

        /* Keep Font Awesome icons from being replaced by the global Inter override. */
        body.register-page .fa-solid,
        body.register-page .fas {
            font-family: "Font Awesome 6 Free" !important;
            font-weight: 900 !important;
        }

        body.register-page .back-btn {
            transition: all 0.3s ease;
        }

        body.register-page .back-btn:hover {
            transform: translateX(-3px) scale(1.1);
        }

        body.register-page button[type="submit"] {
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

        body.register-page button[type="submit"]::before {
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

        body.register-page button[type="submit"]::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s;
        }

        body.register-page button[type="submit"]:hover {
            transform: translateY(-4px);
            box-shadow: 0 15px 40px rgba(30, 64, 175, 0.6);
        }

        body.register-page button[type="submit"]:hover::before {
            width: 350px;
            height: 350px;
        }

        body.register-page button[type="submit"]:hover::after {
            left: 100%;
        }

        body.register-page button[type="submit"]:active {
            transform: translateY(-1px);
            box-shadow: 0 5px 15px rgba(30, 64, 175, 0.4);
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

        body.register-page .strength-bar {
            height: 6px;
            background: #ddd;
            border-radius: 4px;
            margin: -4px 0 6px 0;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        body.register-page #strength {
            height: 100%;
            width: 0%;
            background: red;
            transition: width 0.3s ease, background 0.3s ease;
            border-radius: 4px;
        }

        body.register-page #strength-text {
            display: block;
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 4px;
            min-height: 16px;
            text-align: left;
            transition: color 0.3s ease;
        }

        body.register-page .logo-container img {
            width: 66px;
            height: auto;
        }

        body.register-page .password-container {
            margin-bottom: 8px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .bg-decoration {
                display: none;
            }

            body.register-page .box {
                width: 90%;
                padding: 14px 16px;
            }

            body.register-page h2 {
                font-size: 22px;
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
<body class="register-page">
    <div class="bg-decoration"></div>
    <div class="main-content">
<div class="box">
    <a href="login.php" class="back-btn">&lt;</a>
    <div class="logo-container"><img src="../assets/images/Logo.png" alt="HealthBase Logo" /></div>
    <h2>Create an Account</h2>

    <?php foreach($errors as $e): ?>
        <div class="error">❌ <?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <form method="POST" novalidate>
        <input type="text" name="first_name" placeholder="First Name" value="<?= htmlspecialchars($first_name) ?>" required />
        <input type="text" name="last_name" placeholder="Last Name" value="<?= htmlspecialchars($last_name) ?>" required />
        <input type="email" name="email" placeholder="Email Address" value="<?= htmlspecialchars($email) ?>" required />
        <input type="text" name="username" placeholder="Username" value="<?= htmlspecialchars($username) ?>" required />
        <label for="reg_date_of_birth" class="field-label">Birthday</label>
        <input type="date" name="date_of_birth" id="reg_date_of_birth" value="<?= htmlspecialchars($date_of_birth) ?>" max="<?= date('Y-m-d') ?>" required />
        <p id="reg_age_preview" style="margin: -6px 0 8px 0; font-size: 12px; color: #475569; min-height: 16px;"></p>
        <input type="tel" name="phone" placeholder="Phone Number" value="<?= htmlspecialchars($phone) ?>" required />

        <select name="gender" class="gender-select" required>
            <option value="" disabled <?= $gender=="" ? "selected" : "" ?>>Select Gender</option>
            <option value="Male" <?= $gender=="Male" ? "selected" : "" ?>>Male</option>
            <option value="Female" <?= $gender=="Female" ? "selected" : "" ?>>Female</option>
        </select>

        <div class="password-container">
            <input type="password" name="password" placeholder="Password" id="password" required />
            <button type="button" class="toggle-password" onclick="togglePassword('password', this)">
                <i class="fa-solid fa-eye-slash"></i>
            </button>
        </div>
        <div class="strength-bar"><div id="strength"></div></div>
        <span id="strength-text"></span>

        <div class="password-container">
            <input type="password" name="confirm_password" placeholder="Confirm Password" id="confirm_password" value="<?= htmlspecialchars($confirm ?? '') ?>" required />
            <button type="button" class="toggle-password" onclick="togglePassword('confirm_password', this)">
                <i class="fa-solid fa-eye-slash"></i>
            </button>
        </div>

        <button type="submit" class="btn btn-primary">Signup</button>
    </form>
</div>
</div>

<script>
function togglePassword(fieldId, btn) {
    const input = document.getElementById(fieldId);
    const icon = btn.querySelector('i');
    if (input.type === "password") {
        input.type = "text";
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    } else {
        input.type = "password";
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    }
}

const passwordInput = document.getElementById("password");
const strengthFill  = document.getElementById("strength");
const strengthText  = document.getElementById("strength-text");

function updateRegisterAgePreview() {
    const dob = document.getElementById('reg_date_of_birth');
    const out = document.getElementById('reg_age_preview');
    if (!dob || !out) return;
    if (!dob.value) {
        out.textContent = '';
        return;
    }
    const birth = new Date(dob.value + 'T12:00:00');
    const today = new Date();
    if (isNaN(birth.getTime()) || birth > today) {
        out.textContent = '';
        return;
    }
    let age = today.getFullYear() - birth.getFullYear();
    const m = today.getMonth() - birth.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) {
        age--;
    }
    if (age >= 0 && age <= 120) {
        out.innerHTML = '<i class="fa-solid fa-cake-candles" style="color:#3b82f6;"></i> Age <strong>' + age + '</strong> — same birthday will prefill booking &amp; profile forms so you don\'t enter it again.';
    } else {
        out.textContent = '';
    }
}

const regDob = document.getElementById('reg_date_of_birth');
if (regDob) {
    regDob.addEventListener('change', updateRegisterAgePreview);
    regDob.addEventListener('input', updateRegisterAgePreview);
    updateRegisterAgePreview();
}

passwordInput.addEventListener("input", () => {
    const val = passwordInput.value;
    let score = 0;
    if (val.length >= 8) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[a-z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;

    const widths  = ["5%","25%","50%","75%","100%"];
    const colors  = ["#ddd","red","orange","gold","green"];
    const labels  = ["","Too Weak","Weak","Fair","Strong"];

    strengthFill.style.width = widths[score];
    strengthFill.style.background = colors[score];
    strengthText.textContent = labels[score];
    strengthText.style.color = colors[score];
});
</script>
</body>
</html>
