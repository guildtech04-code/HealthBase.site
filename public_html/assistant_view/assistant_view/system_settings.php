<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

$user_role = $_SESSION['role'] ?? '';
if (!in_array($user_role, ['assistant', 'admin'])) {
    header('Location: ../dashboard/healthbase_dashboard.php');
    exit();
}

$user_query = $conn->prepare("SELECT username, email, role FROM users WHERE id = ?");
$user_query->bind_param("i", $_SESSION['user_id']);
$user_query->execute();
$user_result = $user_query->get_result()->fetch_assoc();

$sidebar_user_data = [
    'username' => htmlspecialchars($user_result['username']),
    'email' => htmlspecialchars($user_result['email']),
    'role' => htmlspecialchars($user_result['role'])
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - HealthBase</title>
    <link rel="icon" type="image/x-icon" href="/assets/icons/favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/assistant.css">
</head>
<body class="assistant-dashboard-page">
<?php include 'includes/assistant_sidebar.php'; ?>

<div class="assistant-main-content">
    <header class="assistant-header">
        <div class="assistant-header-left">
            <h1 class="assistant-welcome">System Settings</h1>
            <p class="assistant-subtitle">Configure system-wide settings and preferences</p>
        </div>
    </header>

    <div class="assistant-dashboard-content">
        <div class="content-card">
            <h3><i class="fas fa-cog"></i> General Settings</h3>
            <div style="display: grid; gap: 20px; max-width: 600px;">
                <div style="padding: 20px; background: #f8fafc; border-radius: 8px;">
                    <h4 style="margin: 0 0 10px 0; color: #334155;">Site Name</h4>
                    <input type="text" value="HealthBase" style="width: 100%; padding: 10px; border: 1px solid #e2e8f0; border-radius: 6px;">
                </div>
                <div style="padding: 20px; background: #f8fafc; border-radius: 8px;">
                    <h4 style="margin: 0 0 10px 0; color: #334155;">Maintenance Mode</h4>
                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                        <input type="checkbox">
                        <span>Enable maintenance mode</span>
                    </label>
                </div>
                <div style="padding: 20px; background: #f8fafc; border-radius: 8px;">
                    <h4 style="margin: 0 0 10px 0; color: #334155;">Email Notifications</h4>
                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                        <input type="checkbox" checked>
                        <span>Enable email notifications</span>
                    </label>
                </div>
                <button style="padding: 12px 24px; background: #3b82f6; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; width: 200px;">
                    Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

</body>
</html>

