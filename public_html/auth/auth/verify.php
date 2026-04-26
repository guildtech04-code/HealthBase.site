<?php
session_start();

require '../config/db_connect.php';

$errors = [];
$success = "";

// Redirect if registration data missing
if (!isset($_SESSION['reg_data'])) {
    header("Location: register.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entered_code = trim($_POST['code'] ?? '');

    if ($entered_code === '') {
        $errors[] = "Please enter the verification code.";
    } elseif ($entered_code == $_SESSION['reg_data']['code']) {

        // Ensure username is unique
        $stmt_check = $conn->prepare("SELECT id FROM users WHERE username=?");
        $stmt_check->bind_param("s", $_SESSION['reg_data']['username']);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $errors[] = "Username already taken. Please register again.";
            $stmt_check->close();
        } else {
            $stmt_check->close();

            // ✅ Insert verified user into DB with gender, date_of_birth, and phone
            // Check if date_of_birth and phone columns exist
            $check_cols = $conn->query("
                SELECT COLUMN_NAME 
                FROM information_schema.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'users' 
                AND COLUMN_NAME IN ('date_of_birth', 'phone')
            ");
            $existing_cols = [];
            while ($row = $check_cols->fetch_assoc()) {
                $existing_cols[] = $row['COLUMN_NAME'];
            }
            
            $has_dob = in_array('date_of_birth', $existing_cols);
            $has_phone = in_array('phone', $existing_cols);
            
            if ($has_dob && $has_phone) {
                $stmt = $conn->prepare("INSERT INTO users 
                    (first_name, last_name, email, username, gender, date_of_birth, phone, password) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param(
                    "ssssssss",
                    $_SESSION['reg_data']['first_name'],
                    $_SESSION['reg_data']['last_name'],
                    $_SESSION['reg_data']['email'],
                    $_SESSION['reg_data']['username'],
                    $_SESSION['reg_data']['gender'],
                    $_SESSION['reg_data']['date_of_birth'] ?? null,
                    $_SESSION['reg_data']['phone'] ?? null,
                    $_SESSION['reg_data']['password']
                );
            } elseif ($has_dob) {
                $stmt = $conn->prepare("INSERT INTO users 
                    (first_name, last_name, email, username, gender, date_of_birth, password) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param(
                    "sssssss",
                    $_SESSION['reg_data']['first_name'],
                    $_SESSION['reg_data']['last_name'],
                    $_SESSION['reg_data']['email'],
                    $_SESSION['reg_data']['username'],
                    $_SESSION['reg_data']['gender'],
                    $_SESSION['reg_data']['date_of_birth'] ?? null,
                    $_SESSION['reg_data']['password']
                );
            } elseif ($has_phone) {
                $stmt = $conn->prepare("INSERT INTO users 
                    (first_name, last_name, email, username, gender, phone, password) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param(
                    "sssssss",
                    $_SESSION['reg_data']['first_name'],
                    $_SESSION['reg_data']['last_name'],
                    $_SESSION['reg_data']['email'],
                    $_SESSION['reg_data']['username'],
                    $_SESSION['reg_data']['gender'],
                    $_SESSION['reg_data']['phone'] ?? null,
                    $_SESSION['reg_data']['password']
                );
            } else {
                // Fallback to original query if columns don't exist
                $stmt = $conn->prepare("INSERT INTO users 
                    (first_name, last_name, email, username, gender, password) 
                    VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param(
                    "ssssss",
                    $_SESSION['reg_data']['first_name'],
                    $_SESSION['reg_data']['last_name'],
                    $_SESSION['reg_data']['email'],
                    $_SESSION['reg_data']['username'],
                    $_SESSION['reg_data']['gender'],
                    $_SESSION['reg_data']['password']
                );
            }

            if ($stmt->execute()) {
                unset($_SESSION['reg_data']); // Clear session data

                $success = "🎉 Verification complete! Redirecting to login page...";
                // Auto redirect after 3 seconds
                echo "<meta http-equiv='refresh' content='3;url=login.php?registered=1'>";
            } else {
                $errors[] = "Database error: " . $stmt->error;
            }

            $stmt->close();
        }
    } else {
        $errors[] = "Incorrect verification code.";
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
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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
            0%, 100% { 
                transform: scale(1);
                box-shadow: 0 8px 25px rgba(30, 64, 175, 0.4);
            }
            50% { 
                transform: scale(1.05);
                box-shadow: 0 12px 35px rgba(30, 64, 175, 0.6);
            }
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

        .success {
            color: white;
            background: linear-gradient(135deg, #10b981, #059669);
            padding: 16px 20px;
            border-radius: 12px;
            font-weight: 600;
            margin-bottom: 20px;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            animation: successPulse 0.6s ease;
        }

        @keyframes successPulse {
            0% { transform: scale(0.95); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }

        .success::before {
            content: '✓';
            font-size: 24px;
        }

        .countdown {
            margin-top: 20px;
            color: #64748b;
            font-size: 14px;
            font-weight: 500;
        }

        .countdown b {
            color: #3b82f6;
            font-weight: 700;
        }

        .code-input-group {
            position: relative;
            display: flex;
            gap: 10px;
            margin: 20px 0;
        }

        .code-input {
            width: 55px;
            height: 65px;
            text-align: center;
            font-size: 24px;
            font-weight: 700;
            border-radius: 12px;
            border: 2px solid #e2e8f0;
            background: rgba(255, 255, 255, 0.8);
            transition: all 0.3s ease;
        }

        .code-input:focus {
            border-color: #3b82f6;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1),
                        0 4px 12px rgba(59, 130, 246, 0.15);
            transform: scale(1.05);
        }

        /* Mobile Responsive */
        @media (max-width: 480px) {
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
        <i class="fa-solid fa-envelope-circle-check"></i>
    </div>
    
    <h2>Email Verification</h2>

    <?php foreach($errors as $e): ?>
        <div class="error"><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <?php if ($success): ?>
        <div class="success"><?= htmlspecialchars($success) ?></div>
        <div class="countdown">Redirecting to login page in <b>3 seconds</b>...</div>
    <?php else: ?>
        <div class="hint">
            <i class="fa-solid fa-envelope" style="margin-right: 8px; color: #3b82f6;"></i>
            We sent a 6-digit verification code to <b><?= htmlspecialchars($_SESSION['reg_data']['email']) ?></b>
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
                Verify & Continue
            </button>
        </form>
    <?php endif; ?>
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
