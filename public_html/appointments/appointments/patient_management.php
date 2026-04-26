<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include("../config/db_connect.php");

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'doctor'; // Default to doctor if role not set

// Get doctor info
$query = $conn->prepare("SELECT username, email, specialization FROM users WHERE id = ?");
if (!$query) {
    die("Prepare failed: " . $conn->error);
}

$query->bind_param("i", $user_id);
$query->execute();
$result = $query->get_result();
$doctor = $result->fetch_assoc();

if (!$doctor) {
    header("Location: ../auth/login.php?error=invalid_user");
    exit();
}

$username = htmlspecialchars($doctor['username'] ?? 'Doctor');
$email = htmlspecialchars($doctor['email'] ?? 'doctor@healthbase.com');
$specialization = htmlspecialchars($doctor['specialization'] ?? 'General');

// Patients: consultation with this doctor, or a non–declined/cancelled appointment
$appt_not_void = "(a.status IS NULL OR a.status NOT IN ('Declined','Cancelled','declined','cancelled','Canceled','canceled'))";
$patients_query = $conn->prepare("
    SELECT 
        p.id,
        p.first_name,
        p.last_name,
        u.email,
        p.gender,
        p.age,
        p.health_concern,
        (SELECT COUNT(*) FROM appointments a1 WHERE a1.patient_id = p.id AND a1.doctor_id = ?) as total_appointments,
        (SELECT MAX(appointment_date) FROM appointments a2 WHERE a2.patient_id = p.id AND a2.doctor_id = ?) as last_appointment
    FROM patients p
    INNER JOIN users u ON p.user_id = u.id
    WHERE p.id IN (
        SELECT patient_id FROM consultations WHERE doctor_id = ?
        UNION
        SELECT patient_id FROM appointments a WHERE doctor_id = ? AND $appt_not_void
    )
");

if (!$patients_query) {
    die("Patients query prepare failed: " . $conn->error);
}

$d1 = (int) $user_id;
$d2 = (int) $user_id;
$d3 = (int) $user_id;
$d4 = (int) $user_id;
$patients_query->bind_param("iiii", $d1, $d2, $d3, $d4);
if (!$patients_query->execute()) {
    die("Execute failed: " . $patients_query->error);
}

$patients_result = $patients_query->get_result();

// Pass user data to sidebar
$sidebar_user_data = [
    'username' => $username,
    'email' => $email,
    'role' => 'doctor',
    'specialization' => $specialization
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Patient Management - HealthBase</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .patient-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .patient-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid #f1f5f9;
            transition: all 0.3s ease;
        }
        
        .patient-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
        }
        
        .patient-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .patient-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 18px;
            flex-shrink: 0;
        }
        
        .patient-info h3 {
            margin: 0;
            font-size: 18px;
            color: #1e293b;
            font-weight: 600;
        }
        
        .patient-info p {
            margin: 4px 0 0 0;
            color: #64748b;
            font-size: 14px;
        }
        
        .patient-details {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .detail-row {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            color: #64748b;
        }
        
        .detail-row i {
            color: #3b82f6;
            width: 20px;
        }
        
        .detail-row strong {
            color: #1e293b;
            min-width: 80px;
        }
        
        .patient-stats {
            display: flex;
            gap: 15px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #f1f5f9;
        }
        
        .stat-item {
            flex: 1;
            text-align: center;
            padding: 10px;
            background: #f8fafc;
            border-radius: 8px;
        }
        
        .stat-number {
            font-size: 20px;
            font-weight: 700;
            color: #3b82f6;
        }
        
        .stat-label {
            font-size: 12px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
        }
        
        .empty-state i {
            font-size: 64px;
            color: #cbd5e1;
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            margin: 20px 0 10px 0;
            color: #1e293b;
        }
        
        @media (max-width: 768px) {
            .patient-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="dashboard-page">

<?php include '../includes/doctor_sidebar.php'; ?>

<div id="doctorSidebarBackdrop" class="doctor-sidebar-backdrop"></div>

<header class="main-header">
    <div class="header-left">
        <button class="mobile-menu-toggle">
            <i class="fas fa-bars"></i>
        </button>
        <div>
            <h1 class="header-title">Patient Management</h1>
            <p class="header-subtitle">Manage your patients and their information</p>
        </div>
    </div>
    <div class="header-right">
        <!-- Notifications -->
        <div class="notifications" id="notifBell">
            <i class="fas fa-bell"></i>
            <span class="notif-badge" id="notifCount"></span>
        </div>
        <!-- Profile -->
        <div class="profile" id="profileMenu">
            <div class="profile-circle"><?php echo strtoupper(substr($username,0,1)); ?></div>
        </div>
    </div>
</header>

<div class="main-content">
    <?php if ($patients_result->num_rows > 0): ?>
        <div class="patient-grid">
            <?php while ($patient = $patients_result->fetch_assoc()): ?>
                <div class="patient-card">
                    <div class="patient-header">
                        <div class="patient-avatar">
                            <?php echo strtoupper(substr($patient['first_name'], 0, 1)); ?>
                        </div>
                        <div class="patient-info">
                            <h3><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></h3>
                            <p><?php echo htmlspecialchars($patient['email']); ?></p>
                        </div>
                    </div>
                    
                    <div class="patient-details">
                        <div class="detail-row">
                            <i class="fas fa-venus-mars"></i>
                            <strong>Gender:</strong>
                            <span><?php echo htmlspecialchars($patient['gender']); ?></span>
                        </div>
                        <div class="detail-row">
                            <i class="fas fa-birthday-cake"></i>
                            <strong>Age:</strong>
                            <span><?php echo htmlspecialchars($patient['age']); ?> years</span>
                        </div>
                        <div class="detail-row">
                            <i class="fas fa-notes-medical"></i>
                            <strong>Concern:</strong>
                            <span><?php echo htmlspecialchars($patient['health_concern']); ?></span>
                        </div>
                    </div>
                    
                    <div class="patient-stats">
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $patient['total_appointments']; ?></div>
                            <div class="stat-label">Appointments</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number">
                                <?php 
                                if ($patient['last_appointment']) {
                                    echo date('M d, Y', strtotime($patient['last_appointment']));
                                } else {
                                    echo 'N/A';
                                }
                                ?>
                            </div>
                            <div class="stat-label">Last Visit</div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-user-injured"></i>
            <h3>No Patients Yet</h3>
            <p>You don't have any patients at the moment. Patients will appear here after their appointments.</p>
        </div>
    <?php endif; ?>
</div>

<script src="../js/doctor_sidebar.js"></script>
<script src="../js/script.js"></script>
<script>
// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', function() {
    // Mobile menu toggle
    const mobileMenuToggle = document.querySelector('.mobile-menu-toggle');
    if (mobileMenuToggle) {
        mobileMenuToggle.addEventListener('click', function() {
            const sidebar = document.getElementById('doctorSidebar');
            const backdrop = document.getElementById('doctorSidebarBackdrop');
            
            if (sidebar) {
                sidebar.classList.toggle('mobile-open');
                
                if (backdrop) {
                    if (sidebar.classList.contains('mobile-open')) {
                        backdrop.classList.add('active');
                    } else {
                        backdrop.classList.remove('active');
                    }
                }
            }
        });
    }

    // Close sidebar when clicking backdrop
    const backdrop = document.getElementById('doctorSidebarBackdrop');
    if (backdrop) {
        backdrop.addEventListener('click', function() {
            const sidebar = document.getElementById('doctorSidebar');
            if (sidebar) {
                sidebar.classList.remove('mobile-open');
                backdrop.classList.remove('active');
            }
        });
    }
});
</script>
</body>
</html>

