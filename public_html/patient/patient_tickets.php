<?php
// patient_tickets.php - Patient's own support tickets
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include("../config/db_connect.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch patient info
$query = $conn->prepare("SELECT username, email, role FROM users WHERE id = ?");
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

// Fetch patient's support tickets
$tickets_query = $conn->prepare("
    SELECT id, subject, message, status, created_at, is_read
    FROM support_tickets 
    WHERE user_id = ? AND is_deleted = 0
    ORDER BY created_at DESC
");
$tickets_query->bind_param("i", $user_id);
$tickets_query->execute();
$tickets_result = $tickets_query->get_result();

// Count tickets by status
$status_counts = $conn->prepare("
    SELECT status, COUNT(*) as count 
    FROM support_tickets 
    WHERE user_id = ? AND is_deleted = 0
    GROUP BY status
");
$status_counts->bind_param("i", $user_id);
$status_counts->execute();
$status_result = $status_counts->get_result();

$ticket_stats = [
    'open' => 0,
    'closed' => 0,
    'pending' => 0
];

while ($row = $status_result->fetch_assoc()) {
    $status = strtolower($row['status']);
    if (isset($ticket_stats[$status])) {
        $ticket_stats[$status] = $row['count'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Support Tickets - HealthBase</title>
    <link rel="icon" type="image/x-icon" href="/assets/icons/favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="css/patient_dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="patient-dashboard-page">
    <?php include 'includes/patient_sidebar.php'; ?>
    
    <div class="patient-main-content">
        <!-- Header -->
        <header class="patient-header">
            <div class="patient-header-left">
                <h1 class="patient-welcome">My Support Tickets</h1>
                <p class="patient-subtitle">View and manage your support requests</p>
            </div>
        </header>

        <!-- Dashboard Content -->
        <div class="patient-dashboard-content">
            <!-- Stats Cards -->
            <div class="patient-stats-grid">
                <div class="patient-stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-ticket-alt"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $ticket_stats['open']; ?></h3>
                        <p>Open Tickets</p>
                    </div>
                </div>
                
                <div class="patient-stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $ticket_stats['pending']; ?></h3>
                        <p>Pending</p>
                    </div>
                </div>
                
                <div class="patient-stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $ticket_stats['closed']; ?></h3>
                        <p>Closed</p>
                    </div>
                </div>
                
                <div class="patient-stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-question-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $tickets_result->num_rows; ?></h3>
                        <p>Total Tickets</p>
                    </div>
                </div>
            </div>

            <!-- Tickets List -->
            <div class="patient-card tickets-list-card">
                <div class="patient-card-header">
                    <h3 class="patient-card-title">
                        <i class="fas fa-ticket-alt"></i>
                        All Support Tickets
                    </h3>
                </div>
                <div class="tickets-list">
                    <?php if ($tickets_result->num_rows > 0): ?>
                        <?php while ($ticket = $tickets_result->fetch_assoc()): ?>
                            <div class="patient-ticket-item">
                                <div class="patient-ticket-info">
                                    <h4><?php echo htmlspecialchars($ticket['subject']); ?></h4>
                                    <p><?php echo htmlspecialchars(substr($ticket['message'], 0, 100)) . (strlen($ticket['message']) > 100 ? '...' : ''); ?></p>
                                    <p><i class="fas fa-calendar"></i> <?php echo date('M d, Y h:i A', strtotime($ticket['created_at'])); ?></p>
                                </div>
                                <div class="patient-ticket-actions">
                                    <span class="patient-status-badge patient-status-<?php echo strtolower($ticket['status']); ?>">
                                        <?php echo htmlspecialchars(ucfirst($ticket['status'])); ?>
                                    </span>
                                    <a href="../support/my_ticket.php?id=<?php echo $ticket['id']; ?>" class="btn-secondary">
                                        <i class="fas fa-eye"></i>
                                        View Details
                                    </a>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="patient-ticket-item">
                            <div class="patient-ticket-info">
                                <h4>No support tickets found</h4>
                                <p>You haven't created any support tickets yet</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="js/patient_sidebar.js"></script>
</body>
</html>
