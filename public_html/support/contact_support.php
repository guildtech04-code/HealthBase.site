<?php
require_once __DIR__ . '/../includes/auth_guard.php';
ensure_role(['user', 'doctor', 'assistant']);
include("../config/db_connect.php");
require_once __DIR__ . '/../includes/security.php';

$user_id = $_SESSION['user_id'];

// Fetch user info from DB
$query = $conn->prepare("SELECT first_name, last_name, email, username, role FROM users WHERE id = ?");
$query->bind_param("i", $user_id);
$query->execute();
$result = $query->get_result();
$user = $result->fetch_assoc();
$username = htmlspecialchars($user['username']);
$email = htmlspecialchars($user['email']);
$role = htmlspecialchars($user['role']);

// Pass user data to sidebar
$sidebar_user_data = [
    'username' => $username,
    'email' => $email,
    'role' => $role
];

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    require_post_csrf();
    $subject = sanitize_string(trim($_POST['subject']), 255);
    $message = sanitize_string(trim($_POST['message']), 1000);

    if (!empty($subject) && !empty($message)) {
        $stmt = $conn->prepare("INSERT INTO support_tickets (user_id, subject, message, status, created_at) VALUES (?, ?, ?, 'open', NOW())");
        $stmt->bind_param("iss", $user_id, $subject, $message);
        if ($stmt->execute()) {
            $success = "✅ Your message has been sent to support!";
        } else {
            $error = "❌ Something went wrong. Please try again.";
        }
    } else {
        $error = "⚠️ Please fill in all fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Contact Support - HealthBase</title>
    <link rel="icon" type="image/x-icon" href="../assets/icons/favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../patient/css/patient_dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="patient-dashboard-page">
    <?php include '../patient/includes/patient_sidebar.php'; ?>
    
    <div class="patient-main-content">
        <!-- Header -->
        <header class="patient-header">
            <div class="patient-header-left">
                <h1 class="patient-welcome">Contact Support</h1>
                <p class="patient-subtitle">Get help with your account or appointments</p>
    </div>
</header>

        <!-- Dashboard Content -->
        <div class="patient-dashboard-content">
            <div class="patient-card">
                <div class="patient-card-header">
                    <h3 class="patient-card-title">
                        <i class="fas fa-headset"></i>
                        Send Support Message
                    </h3>
                </div>
                
                <div style="padding: 25px;">
                    <?php if (!empty($success)): ?>
                        <div style="background: #dcfce7; color: #166534; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #bbf7d0;">
                            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($error)): ?>
                        <div style="background: #fee2e2; color: #dc2626; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #fecaca;">
                            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" style="display: flex; flex-direction: column; gap: 20px;">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                        <div>
                            <label style="display: block; margin-bottom: 8px; color: #374151; font-weight: 500; font-family: 'Inter', sans-serif;">Your Name</label>
                            <input type="text" value="<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>" disabled
                                   style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 8px; font-family: 'Inter', sans-serif; font-size: 14px; background: #f9fafb;">
                        </div>

                        <div>
                            <label style="display: block; margin-bottom: 8px; color: #374151; font-weight: 500; font-family: 'Inter', sans-serif;">Email Address</label>
                            <input type="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled
                                   style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 8px; font-family: 'Inter', sans-serif; font-size: 14px; background: #f9fafb;">
                        </div>

                        <div>
                            <label style="display: block; margin-bottom: 8px; color: #374151; font-weight: 500; font-family: 'Inter', sans-serif;">Subject *</label>
                            <input type="text" name="subject" required placeholder="Brief description of your issue"
                                   style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 8px; font-family: 'Inter', sans-serif; font-size: 14px;">
                        </div>

                        <div>
                            <label style="display: block; margin-bottom: 8px; color: #374151; font-weight: 500; font-family: 'Inter', sans-serif;">Message *</label>
                            <textarea name="message" rows="6" required placeholder="Please describe your issue in detail..."
                                      style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 8px; font-family: 'Inter', sans-serif; font-size: 14px; resize: vertical;"></textarea>
                        </div>

                        <button type="submit" class="btn-primary" style="align-self: flex-start;">
                            <i class="fas fa-paper-plane"></i> Send Message
                        </button>
            </form>
                </div>
            </div>
    </div>
</div>

    <script src="/patient/js/patient_sidebar.js"></script>
</body>
</html>