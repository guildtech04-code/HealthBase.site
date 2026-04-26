<?php
session_start();

// Handle "Remember Me" cookie
$rememberedUser = $_COOKIE['rememberUser'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - HealthBase</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        /* Enhanced Login Page Styles with Animations */
        body.login-page {
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 50%, #60a5fa 100%);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
            position: relative;
            overflow: hidden;
            min-height: 100vh;
        }

        body.login-page::before {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at 20% 80%, rgba(30, 64, 175, 0.3), transparent 50%),
                        radial-gradient(circle at 80% 20%, rgba(255, 255, 255, 0.2), transparent 50%);
            pointer-events: none;
            animation: pulse 8s ease-in-out infinite;
        }

        /* Floating particles animation */
        body.login-page::after {
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

        .login-box {
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

        .login-box::before {
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

        .login-box:hover {
            transform: translateY(-8px) scale(1.01);
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.3),
                        0 0 0 1px rgba(255, 255, 255, 0.7) inset,
                        0 12px 40px rgba(30, 64, 175, 0.4),
                        0 0 60px rgba(59, 130, 246, 0.2);
        }

        .login-box:hover::before {
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
            background: linear-gradient(135deg, #002dc0 0%, #667eea 100%);
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

        input[type="email"],
        input[type="password"],
        input[type="text"] {
            background: rgba(255, 255, 255, 0.9);
            border: 2px solid #e2e8f0;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            animation: inputFadeIn 0.5s ease-out backwards;
        }

        input[type="email"]:nth-of-type(1) { animation-delay: 0.1s; }
        .password-container { animation: inputFadeIn 0.5s ease-out 0.2s backwards; }

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

        input[type="email"]:focus,
        input[type="password"]:focus,
        input[type="text"]:focus {
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

        .password-container {
            position: relative;
        }

        .toggle-password {
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
            transition: background-color 0.2s ease;
            border-radius: 50%;
            transform: translateY(-50%);
        }

        .toggle-password:hover {
            background: rgba(59, 130, 246, 0.1);
            transform: translateY(-50%);
        }

        .toggle-password:active {
            transform: translateY(-50%);
        }

        .toggle-password i {
            width: 16px;
            text-align: center;
            pointer-events: none;
        }

        /* Avoid duplicate native reveal icon on Edge/IE. */
        input[type="password"]::-ms-reveal,
        input[type="password"]::-ms-clear {
            display: none;
        }

        .error-text {
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

        .error-text::before {
            content: '⚠️';
            font-size: 18px;
        }

        @keyframes shakeError {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }

        .checkbox {
            transition: all 0.3s ease;
            animation: fadeIn 0.6s ease-out 0.4s backwards;
        }

        .checkbox:hover {
            transform: translateX(5px);
        }

        .checkbox input[type="checkbox"] {
            cursor: pointer;
            width: 18px;
            height: 18px;
            margin-right: 8px;
        }

        .checkbox label {
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .checkbox:hover label {
            color: #3b82f6;
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
            animation: fadeIn 0.6s ease-out 0.5s backwards;
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

        .bottom-links a {
            position: relative;
            color: #3b82f6;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .bottom-links a::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 2px;
            background: linear-gradient(90deg, #3b82f6, #1e40af);
            transition: width 0.3s ease;
        }

        .bottom-links a:hover::after {
            width: 100%;
        }

        .bottom-links a:hover {
            color: #1e40af;
            transform: translateY(-2px);
        }

        /* Loading State */
        .login-box.loading button[type="submit"] {
            pointer-events: none;
            opacity: 0.8;
            position: relative;
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%) !important;
        }

        .login-box.loading button[type="submit"] .btn-text {
            opacity: 0;
        }

        .login-box.loading button[type="submit"]::after {
            content: '';
            position: absolute;
            width: 24px;
            height: 24px;
            top: 50%;
            left: 50%;
            margin-left: -12px;
            margin-top: -12px;
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Success Animation */
        @keyframes successCheckmark {
            0% {
                transform: scale(0) rotate(45deg);
                opacity: 0;
            }
            50% {
                transform: scale(1.2) rotate(45deg);
            }
            100% {
                transform: scale(1) rotate(45deg);
                opacity: 1;
            }
        }

        /* Cloudflare Turnstile styling */
        .cf-turnstile {
            margin: 20px 0;
            border-radius: 10px;
            overflow: hidden;
        }

        /* Keep the original background image */
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

        /* Responsive Design */
        @media (max-width: 768px) {
            .login-box {
                width: 90%;
                padding: 30px 20px;
            }

            h2 {
                font-size: 24px;
            }

            .bottom-links {
                flex-direction: column;
                gap: 10px;
            }

            .bg-decoration {
                display: none;
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
<body class="login-page">
    <div class="bg-decoration"></div>
    <div class="main-content">
        <div class="login-box" id="loginBox">
            <div class="logo-container">
                <img src="../assets/images/Logo.png" alt="HealthBase Logo" class="logo">
            </div>

            <h2>Login to HealthBase</h2>

            <form action="auth.php" method="POST" id="loginForm">
                <input type="email" name="email" placeholder="Email Address" required 
                       value="<?= htmlspecialchars($rememberedUser) ?>"
                       autocomplete="email">

                <div class="password-container">
                    <input type="password" name="password" placeholder="Password" id="password" required
                           autocomplete="current-password">
                    <button type="button" class="toggle-password" onclick="togglePassword('password', this)"
                            aria-label="Toggle password visibility">
                        <i class="fa-solid fa-eye-slash"></i>
                    </button>
                </div>
                
                <?php if (isset($_GET['error'])): ?>
                    <p class="error-text">
                    <?php
                        switch ($_GET['error']) {
                            case 'captcha':   echo 'Captcha verification failed. Please try again.'; break;
                            case 'cooldown':  echo 'Too many failed attempts. Please wait before trying again.'; break;
                            case 'inactive':  echo 'Your account has been deactivated. Please contact the administrator.'; break;
                            default:          echo 'Invalid email or password';
                        }
                    ?>
                    </p>
                <?php endif; ?>

                <div class="cf-turnstile" data-sitekey="0x4AAAAAABudWHW--zHGHb7w"></div>
                <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>

                <div class="checkbox">
                    <input type="checkbox" name="remember" id="remember" <?= $rememberedUser ? 'checked' : '' ?>>
                    <label for="remember">Remember Me</label>
                </div>

                <button type="submit" id="submitBtn">
                    <span class="btn-text">Login</span>
                </button>

                <div class="bottom-links">
                    <a href="forgot_password.php">
                        <i class="fa-solid fa-key"></i> Forgot Password?
                    </a>
                    <a href="register.php">
                        <i class="fa-solid fa-user-plus"></i> Sign up
                    </a>
                </div>
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

// Form submission with loading state
document.getElementById('loginForm').addEventListener('submit', function(e) {
    const loginBox = document.getElementById('loginBox');
    const submitBtn = document.getElementById('submitBtn');
    
    loginBox.classList.add('loading');
    submitBtn.querySelector('.btn-text').textContent = 'Logging in...';
    
    // Reset after timeout (in case of redirect delay)
    setTimeout(() => {
        if (document.body.contains(loginBox)) {
            loginBox.classList.remove('loading');
            submitBtn.querySelector('.btn-text').textContent = 'Login';
        }
    }, 10000);
});

// Input animation on focus
const inputs = document.querySelectorAll('input');
inputs.forEach(input => {
    input.addEventListener('focus', function() {
        if (this.parentElement && this.parentElement.classList.contains('password-container')) return;
        this.parentElement.style.transform = 'scale(1.02)';
    });
    
    input.addEventListener('blur', function() {
        if (this.parentElement && this.parentElement.classList.contains('password-container')) return;
        this.parentElement.style.transform = 'scale(1)';
    });
});
</script>
</body>
</html>
