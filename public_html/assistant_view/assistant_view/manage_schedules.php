<?php
// manage_schedules.php - Enhanced schedule management for assistants
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_role = $_SESSION['role'] ?? '';
if (!in_array($user_role, ['assistant', 'admin'])) {
    header('Location: ../dashboard/healthbase_dashboard.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$flash_success = '';
$flash_error = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_schedule') {
        $doctor_id = (int)($_POST['doctor_id'] ?? 0);
        $schedule_type = $_POST['schedule_type'] ?? 'clinic';
        $day_of_week = (int)($_POST['day_of_week'] ?? -1);
        $time_period = $_POST['time_period'] ?? 'Any';
        $start_time = $_POST['start_time'] ?? '';
        $end_time = $_POST['end_time'] ?? '';
        $appointment_type = $_POST['appointment_type'] ?? 'By Appointment';
        $is_available = isset($_POST['is_available']) ? 1 : 0;
        $effective_from = !empty($_POST['effective_from']) ? $_POST['effective_from'] : null;
        $effective_to = !empty($_POST['effective_to']) ? $_POST['effective_to'] : null;
        $notes = $_POST['notes'] ?? null;
        
        if ($doctor_id > 0 && $day_of_week >= 0 && $day_of_week <= 6) {
            $stmt = $conn->prepare("
                INSERT INTO doctor_schedules 
                (doctor_id, schedule_type, day_of_week, time_period, start_time, end_time, 
                 appointment_type, is_available, effective_from, effective_to, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("isissssssss", $doctor_id, $schedule_type, $day_of_week, $time_period,
                $start_time, $end_time, $appointment_type, $is_available, $effective_from, $effective_to, $notes);
            
            if ($stmt->execute()) {
                $flash_success = 'Schedule added successfully.';
            } else {
                $flash_error = 'Failed to add schedule: ' . $stmt->error;
            }
        } else {
            $flash_error = 'Invalid schedule inputs.';
        }
    } elseif ($action === 'delete_schedule') {
        $schedule_id = (int)($_POST['schedule_id'] ?? 0);
        if ($schedule_id > 0) {
            $stmt = $conn->prepare("DELETE FROM doctor_schedules WHERE id = ?");
            $stmt->bind_param("i", $schedule_id);
            if ($stmt->execute()) {
                $flash_success = 'Schedule deleted successfully.';
            } else {
                $flash_error = 'Failed to delete schedule.';
            }
        }
    }
}

// Get user info
$user_query = $conn->prepare("SELECT username, email, role FROM users WHERE id = ?");
$user_query->bind_param("i", $user_id);
$user_query->execute();
$user_result = $user_query->get_result();
$user = $user_result->fetch_assoc();

$sidebar_user_data = [
    'username' => htmlspecialchars($user['username'] ?? ''),
    'email' => htmlspecialchars($user['email'] ?? ''),
    'role' => htmlspecialchars($user['role'] ?? '')
];

// Get all doctors
$doctors_query = "SELECT id, first_name, last_name, specialization FROM users WHERE role = 'doctor' AND status = 'active' ORDER BY specialization, first_name";
$doctors_result = $conn->query($doctors_query);

// Get existing schedules with doctor info
$schedules_query = "
    SELECT 
        ds.*,
        u.first_name,
        u.last_name,
        u.specialization
    FROM doctor_schedules ds
    JOIN users u ON ds.doctor_id = u.id
    ORDER BY u.specialization, u.first_name, ds.schedule_type, ds.day_of_week
";
$schedules_result = $conn->query($schedules_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Doctor Schedules - HealthBase</title>
    <link rel="icon" type="image/x-icon" href="/assets/icons/favicon.ico">
    <link rel="stylesheet" href="css/assistant.css">
    <style>
        .schedule-form {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #334155;
            font-size: 13px;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1.5px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
        }
        
        .schedules-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 12px;
            overflow: hidden;
        }
        
        .schedules-table th {
            background: #f8fafc;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #334155;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .schedules-table td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .badge {
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .badge-clinic { background: #dbeafe; color: #1e40af; }
        .badge-tele { background: #dcfce7; color: #166534; }
        .badge-by-appt { background: #fee2e2; color: #991b1b; }
        .badge-walkin { background: #dbeafe; color: #1e40af; }
    </style>
</head>
<body class="assistant-dashboard-page">
    <?php include 'includes/assistant_sidebar.php'; ?>
    
    <div class="assistant-main-content">
        <header class="assistant-header">
            <div>
                <h1 class="assistant-welcome">Manage Doctor Schedules</h1>
                <p class="assistant-subtitle">Configure clinic and teleconsultation schedules</p>
            </div>
        </header>

        <?php if ($flash_success): ?>
            <div class="flash flash-success"><?= htmlspecialchars($flash_success) ?></div>
        <?php endif; ?>
        <?php if ($flash_error): ?>
            <div class="flash flash-error"><?= htmlspecialchars($flash_error) ?></div>
        <?php endif; ?>

        <!-- Add Schedule Form -->
        <div class="schedule-form">
            <h3>Add New Schedule</h3>
            <form method="POST">
                <input type="hidden" name="action" value="add_schedule">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Doctor</label>
                        <select name="doctor_id" required>
                            <option value="">Select Doctor</option>
                            <?php while ($doctor = $doctors_result->fetch_assoc()): ?>
                                <option value="<?= $doctor['id'] ?>">
                                    Dr. <?= htmlspecialchars($doctor['first_name']) ?> <?= htmlspecialchars($doctor['last_name']) ?> — <?= htmlspecialchars($doctor['specialization']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Schedule Type</label>
                        <select name="schedule_type" required>
                            <option value="clinic">Clinic Schedule</option>
                            <option value="teleconsultation">Teleconsultation Schedule</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Day of Week</label>
                        <select name="day_of_week" required>
                            <option value="0">Sunday</option>
                            <option value="1">Monday</option>
                            <option value="2">Tuesday</option>
                            <option value="3">Wednesday</option>
                            <option value="4">Thursday</option>
                            <option value="5">Friday</option>
                            <option value="6">Saturday</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Time Period</label>
                        <select name="time_period">
                            <option value="AM">AM (Morning)</option>
                            <option value="PM">PM (Afternoon)</option>
                            <option value="Any">Any Time</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Start Time</label>
                        <input type="time" name="start_time" required>
                    </div>
                    
                    <div class="form-group">
                        <label>End Time</label>
                        <input type="time" name="end_time" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Appointment Type</label>
                        <select name="appointment_type">
                            <option value="By Appointment">By Appointment (Red icon)</option>
                            <option value="Walk-in">Walk-in (Blue icon)</option>
                            <option value="First Come First Served">First Come First Served</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Notes (optional)</label>
                        <input type="text" name="notes" placeholder="e.g., Special consultation hours">
                    </div>
                </div>
                
                <button type="submit" class="btn-primary">Add Schedule</button>
            </form>
        </div>

        <!-- Existing Schedules -->
        <div class="card">
            <h3>Existing Schedules</h3>
            <table class="schedules-table">
                <thead>
                    <tr>
                        <th>Doctor</th>
                        <th>Type</th>
                        <th>Day</th>
                        <th>Period</th>
                        <th>Time</th>
                        <th>Appointment Type</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($schedules_result->num_rows > 0): ?>
                        <?php while ($schedule = $schedules_result->fetch_assoc()): ?>
                            <?php
                            $day_names = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                            $day_name = $day_names[(int)$schedule['day_of_week']];
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($schedule['first_name'] . ' ' . $schedule['last_name']) ?></td>
                                <td>
                                    <span class="badge <?= $schedule['schedule_type'] === 'clinic' ? 'badge-clinic' : 'badge-tele' ?>">
                                        <?= ucfirst($schedule['schedule_type']) ?>
                                    </span>
                                </td>
                                <td><?= $day_name ?></td>
                                <td><?= $schedule['time_period'] ?></td>
                                <td><?= date('g:i A', strtotime($schedule['start_time'])) ?> - <?= date('g:i A', strtotime($schedule['end_time'])) ?></td>
                                <td>
                                    <span class="badge <?= $schedule['appointment_type'] === 'By Appointment' ? 'badge-by-appt' : 'badge-walkin' ?>">
                                        <?= $schedule['appointment_type'] ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="delete_schedule">
                                        <input type="hidden" name="schedule_id" value="<?= $schedule['id'] ?>">
                                        <button type="submit" style="padding: 5px 10px; background: #dc2626; color: white; border: none; border-radius: 6px; cursor: pointer;">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px; color: #64748b;">
                                No schedules found. Add a schedule using the form above.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="js/assistant_sidebar.js"></script>
</body>
</html>

