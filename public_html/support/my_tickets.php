<?php
session_start();
include("../config/db_connect.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user info
$user_query = $conn->prepare("SELECT username, email, role FROM users WHERE id = ?");
$user_query->bind_param("i", $user_id);
$user_query->execute();
$user_result = $user_query->get_result()->fetch_assoc();
$username = htmlspecialchars($user_result['username']);
$email = htmlspecialchars($user_result['email']);
$role = $user_result['role'];

// For doctors, get specialization
if ($role === 'doctor') {
    $spec_query = $conn->prepare("SELECT specialization FROM users WHERE id = ?");
    $spec_query->bind_param("i", $user_id);
    $spec_query->execute();
    $spec_result = $spec_query->get_result()->fetch_assoc();
    $specialization = htmlspecialchars($spec_result['specialization'] ?? 'General');
} else {
    $specialization = null;
}

// Fetch user's tickets
$tickets_query = $conn->prepare("
    SELECT id, subject, message, status, created_at, is_read
    FROM support_tickets 
    WHERE user_id = ? AND is_deleted = 0
    ORDER BY created_at DESC
");
$tickets_query->bind_param("i", $user_id);
$tickets_query->execute();
$tickets_result = $tickets_query->get_result();

// Prepare sidebar user data
$sidebar_user_data = [
    'username' => $username,
    'email' => $email,
    'role' => $role,
    'specialization' => $specialization
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Tickets - HealthBase</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo (strpos($_SERVER['PHP_SELF'], '/appointments/') !== false || strpos($_SERVER['PHP_SELF'], '/dashboard/') !== false) ? '../support/my_tickets.css' : 'my_tickets.css'; ?>">
    <link rel="stylesheet" href="../css/dashboard.css">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="dashboard-page my-tickets-page">
    <?php 
    // Include appropriate sidebar based on role
    if ($role === 'doctor') {
        include '../includes/doctor_sidebar.php';
        echo '<div id="doctorSidebarBackdrop" class="doctor-sidebar-backdrop"></div>';
    } else {
        include '../includes/sidebar.php';
    }
    ?>
    
    <header class="main-header">
        <div class="header-left">
            <h1 class="page-title">
                <i class="fas fa-ticket-alt"></i>
                My Support Tickets
            </h1>
            <p class="page-subtitle">Manage your support requests and communications</p>
        </div>
        
        <div class="header-right">
            <div class="notifications">
                <button class="notification-icon" id="notificationIcon">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge" id="notificationBadge">0</span>
                </button>
                <div class="notif-dropdown" id="notifDropdown">
                    <div class="notif-header">
                        <h3>Notifications</h3>
                        <button class="mark-all-read" id="markAllRead">Mark All Read</button>
                    </div>
                    <div class="notif-list" id="notifList">
                        <div class="notif-item">
                            <div class="notif-content">Loading notifications...</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="profile">
                <button class="profile-icon" id="profileIcon">
                    <div class="profile-avatar">
                        <?php echo strtoupper(substr($username, 0, 1)); ?>
                    </div>
                </button>
                <div class="profile-dropdown" id="profileDropdown">
                    <div class="profile-info">
                        <div class="profile-name"><?php echo $username; ?></div>
                        <div class="profile-email"><?php echo $email; ?></div>
                        <div class="profile-role"><?php echo ucfirst($role); ?></div>
                    </div>
                    <div class="profile-actions">
                        <a href="../dashboard/doctor_dashboard.php" class="profile-link">
                            <i class="fas fa-tachometer-alt"></i>
                            Dashboard
                        </a>
                        <a href="../appointments/appointments.php" class="profile-link">
                            <i class="fas fa-calendar-check"></i>
                            Appointments
                        </a>
                        <a href="../auth/logout.php" class="profile-link logout-link">
                            <i class="fas fa-sign-out-alt"></i>
                            Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="main-content">
        <a href="../dashboard/doctor_dashboard.php" class="back-btn">
            <i class="fas fa-arrow-left"></i>
            Back to Dashboard
        </a>

        <!-- Tickets Overview -->
        <div class="tickets-overview">
            <div class="overview-card">
                <div class="overview-icon">
                    <i class="fas fa-ticket-alt"></i>
                </div>
                <div class="overview-content">
                    <div class="overview-number"><?php echo $tickets_result->num_rows; ?></div>
                    <div class="overview-label">Total Tickets</div>
                </div>
            </div>
            
            <div class="overview-card">
                <div class="overview-icon pending">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="overview-content">
                    <div class="overview-number"><?php 
                        $pending_query = $conn->prepare("SELECT COUNT(*) as count FROM support_tickets WHERE user_id = ? AND status = 'open' AND is_deleted = 0");
                        $pending_query->bind_param("i", $user_id);
                        $pending_query->execute();
                        echo $pending_query->get_result()->fetch_assoc()['count'];
                    ?></div>
                    <div class="overview-label">Open Tickets</div>
                </div>
            </div>
            
            <div class="overview-card">
                <div class="overview-icon resolved">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="overview-content">
                    <div class="overview-number"><?php 
                        $resolved_query = $conn->prepare("SELECT COUNT(*) as count FROM support_tickets WHERE user_id = ? AND status = 'closed' AND is_deleted = 0");
                        $resolved_query->bind_param("i", $user_id);
                        $resolved_query->execute();
                        echo $resolved_query->get_result()->fetch_assoc()['count'];
                    ?></div>
                    <div class="overview-label">Resolved</div>
                </div>
            </div>
        </div>

        <!-- Tickets List -->
        <div class="tickets-section">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-list"></i>
                    Your Support Tickets
                </h2>
                <a href="new_ticket.php" class="new-ticket-btn">
                    <i class="fas fa-plus"></i>
                    New Ticket
                </a>
            </div>

            <div class="tickets-list">
                <?php if ($tickets_result->num_rows > 0): ?>
                    <?php while ($ticket = $tickets_result->fetch_assoc()): ?>
                        <div class="ticket-card <?php echo $ticket['status']; ?>">
                            <div class="ticket-header">
                                <div class="ticket-id">#<?php echo $ticket['id']; ?></div>
                                <div class="ticket-status status-<?php echo $ticket['status']; ?>">
                                    <?php echo ucfirst($ticket['status']); ?>
                                </div>
                            </div>
                            
                            <div class="ticket-content">
                                <h3 class="ticket-subject"><?php echo htmlspecialchars($ticket['subject']); ?></h3>
                                <p class="ticket-message"><?php echo htmlspecialchars(substr($ticket['message'], 0, 150)) . (strlen($ticket['message']) > 150 ? '...' : ''); ?></p>
                            </div>
                            
                            <div class="ticket-footer">
                                <div class="ticket-date">
                                    <i class="fas fa-calendar"></i>
                                    <?php echo date('M d, Y h:i A', strtotime($ticket['created_at'])); ?>
                                </div>
                                <a href="my_ticket.php?id=<?php echo $ticket['id']; ?>" class="view-ticket-btn">
                                    <i class="fas fa-eye"></i>
                                    View Details
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="no-tickets">
                        <div class="no-tickets-icon">
                            <i class="fas fa-ticket-alt"></i>
                        </div>
                        <h3>No Support Tickets</h3>
                        <p>You haven't created any support tickets yet.</p>
                        <a href="new_ticket.php" class="create-first-ticket-btn">
                            <i class="fas fa-plus"></i>
                            Create Your First Ticket
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="<?php echo (strpos($_SERVER['PHP_SELF'], '/appointments/') !== false || strpos($_SERVER['PHP_SELF'], '/dashboard/') !== false) ? '../support/my_tickets.js' : 'my_tickets.js'; ?>"></script>
    <?php if ($role === 'doctor'): ?>
    <script src="../js/doctor_sidebar.js"></script>
    <?php endif; ?>
</body>
</html>
