<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../includes/security.php';
require '../config/db_connect.php';

// Replace with your real Cloudflare secret key
$secretKey = "0x4AAAAAABudWBNk_kct5OIGHX3X6-EiVK0";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $remember = isset($_POST['remember']);
    $captchaResponse = $_POST['cf-turnstile-response'] ?? '';

    // ✅ Verify captcha
    $verifyUrl = "https://challenges.cloudflare.com/turnstile/v0/siteverify";
    $data = [
        'secret' => $secretKey,
        'response' => $captchaResponse,
        'remoteip' => $_SERVER['REMOTE_ADDR']
    ];

    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data)
        ]
    ];

    $context  = stream_context_create($options);
    $result = file_get_contents($verifyUrl, false, $context);
    $resultData = json_decode($result, true);

    if (!$resultData || empty($resultData['success'])) {
        header("Location: login.php?error=captcha");
        exit();
    }

    // ✅ Brute-force protection
    if (!isset($_SESSION['failed_attempts'])) {
        $_SESSION['failed_attempts'] = 0;
        $_SESSION['lockout_time'] = 0;
    }

    if (time() < $_SESSION['lockout_time']) {
        header("Location: login.php?error=cooldown");
        exit();
    }

    // ✅ Check user in DB with status + role
    $stmt = $conn->prepare("SELECT id, email, password, status, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user) {
        $_SESSION['failed_attempts']++;
        header("Location: login.php?error=invalid");
        exit();
    }

    if ($user['status'] !== 'active') {
        header("Location: login.php?error=inactive");
        exit();
    }

    if (password_verify($password, $user['password'])) {
        // ✅ Success – move to OTP verification
        $_SESSION['failed_attempts'] = 0;
        $_SESSION['lockout_time'] = 0;

		// ✅ Bypass OTP for doctor and assistant roles
        if ($user['role'] === 'doctor' || $user['role'] === 'assistant') {
            session_regenerate_id(true);
			$_SESSION['user_id'] = $user['id'];
			$_SESSION['role'] = $user['role'];
			$_SESSION['email'] = $user['email'];
			$_SESSION['logged_in'] = true;

			if ($remember) {
                $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
                setcookie('rememberUser', $email, [
                    'expires' => time() + (30 * 24 * 60 * 60),
                    'path' => '/',
                    'secure' => $secure,
                    'httponly' => false,
                    'samesite' => 'Lax',
                ]);
			} else {
                setcookie('rememberUser', '', time() - 3600, '/');
            }

			if ($user['role'] === 'doctor') {
				header("Location: ../appointments/doctor_dashboard.php");
			} else { // assistant
				header("Location: ../assistant_view/index.php");
			}
			exit();
		}

        // Check if device is trusted (logged in within 20 minutes)
        $trustedCookie = $_COOKIE['trusted_device_' . $user['id']] ?? '';
        $trustedUntil = $_COOKIE['trusted_until_' . $user['id']] ?? 0;
        
        // If trusted cookie exists and is still valid (within 20 minutes)
        if ($trustedCookie && $trustedUntil > time()) {
            // Skip OTP verification - trusted device
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['logged_in'] = true;
            
            // Handle remember me
            if ($remember) {
                $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
                setcookie('rememberUser', $email, [
                    'expires' => time() + (30 * 24 * 60 * 60),
                    'path' => '/',
                    'secure' => $secure,
                    'httponly' => false,
                    'samesite' => 'Lax',
                ]);
            } else {
                setcookie('rememberUser', '', time() - 3600, '/');
            }
            
            // Redirect based on role
            $role = $user['role'];
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
        }

        // Store pending login data
        $_SESSION['pending_user_id'] = $user['id'];
        $_SESSION['pending_role'] = $user['role'];
        $_SESSION['remember_me'] = $remember ? $email : '';

        // Generate OTP
        $otp = rand(100000, 999999);
        $_SESSION['otp_code'] = $otp;
        $_SESSION['otp_expiry'] = time() + 300; // 5 mins

        // ✅ Load PHPMailer
        require '../PHPMailer/src/PHPMailer.php';
        require '../PHPMailer/src/SMTP.php';
        require '../PHPMailer/src/Exception.php';

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com'; 
            $mail->SMTPAuth = true;
            $mail->Username = 'guildtech21@gmail.com'; 
            $mail->Password = 'fokb qhkm xvxz qvnd'; // ⚠️ App password, not Gmail password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom('guildtech21@gmail.com', 'HealthBase Security');
            $mail->addAddress($user['email']);
            $mail->isHTML(true);
            $mail->Subject = 'Your HealthBase Verification Code';
            $mail->Body = "Your verification code is <b>{$otp}</b>. It expires in 5 minutes.";

            $mail->send();
        } catch (Exception $e) {
            // You can log $mail->ErrorInfo for debugging
            header("Location: login.php?error=mailfail");
            exit();
        }

        // Redirect to verification page
        header("Location: /auth/verify_2fa.php");
        exit();

    } else {
        // ❌ Wrong password
        $_SESSION['failed_attempts']++;

        switch ($_SESSION['failed_attempts']) {
            case 5: $_SESSION['lockout_time'] = time() + 15; break;
            case 6: $_SESSION['lockout_time'] = time() + 30; break;
            case 7: $_SESSION['lockout_time'] = time() + 60; break;
            case 8: $_SESSION['lockout_time'] = time() + 180; break;
            case 9: $_SESSION['lockout_time'] = time() + 300; break;
        }

        header("Location: login.php?error=invalid");
        exit();
    }
}
?>
