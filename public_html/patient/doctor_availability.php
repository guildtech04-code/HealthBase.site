<?php
// doctor_availability.php - Doctor Availability View for Patients
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require_once '../config/db_connect.php';

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

// Get filter parameters
$specialization_filter = $_GET['specialization'] ?? '';
$date_filter = $_GET['date'] ?? date('Y-m-d');

// Get all doctors with their availability
$doctors_query = "
    SELECT u.id, u.first_name, u.last_name, u.specialization, u.email,
           COUNT(a.id) as total_appointments,
           COUNT(CASE WHEN DATE(a.appointment_date) = ? AND a.status IN ('Confirmed', 'Pending') THEN 1 END) as today_appointments
    FROM users u
    LEFT JOIN appointments a ON u.id = a.doctor_id
    WHERE u.role = 'doctor' AND u.status = 'active'
    GROUP BY u.id, u.first_name, u.last_name, u.specialization, u.email
    ORDER BY u.specialization, u.first_name
";

$doctors_stmt = $conn->prepare($doctors_query);
$doctors_stmt->bind_param("s", $date_filter);
$doctors_stmt->execute();
$doctors_result = $doctors_stmt->get_result();

// Get unique specializations for filter
$specializations_query = "SELECT DISTINCT specialization FROM users WHERE role = 'doctor' AND status = 'active' ORDER BY specialization";
$specializations_result = $conn->query($specializations_query);

// Get today's appointments for each doctor
function getDoctorAvailability($conn, $doctor_id, $date) {
    $availability_query = "
        SELECT 
            HOUR(appointment_date) as hour,
            COUNT(*) as booked_count,
            GROUP_CONCAT(CONCAT(HOUR(appointment_date), ':', MINUTE(appointment_date))) as booked_times
        FROM appointments 
        WHERE doctor_id = ? AND DATE(appointment_date) = ? AND status IN ('Confirmed', 'Pending')
        GROUP BY HOUR(appointment_date)
    ";
    
    $stmt = $conn->prepare($availability_query);
    $stmt->bind_param("is", $doctor_id, $date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $booked_hours = [];
    while ($row = $result->fetch_assoc()) {
        $booked_hours[] = $row['hour'];
    }
    
    return $booked_hours;
}

// Generate time slots (9 AM to 5 PM)
function generateTimeSlots() {
    $slots = [];
    for ($hour = 9; $hour <= 17; $hour++) {
        $display_hour = $hour > 12 ? $hour - 12 : $hour;
        $ampm = $hour >= 12 ? 'PM' : 'AM';
        $slots[] = [
            'hour' => $hour,
            'display' => $display_hour . ':00 ' . $ampm,
            'available' => true
        ];
    }
    return $slots;
}

// Get doctor schedules from doctor_schedules table
function getDoctorSchedulesForPatient($conn, $doctor_id) {
    $schedules_query = "
        SELECT 
            id,
            schedule_type,
            day_of_week,
            time_period,
            start_time,
            end_time,
            appointment_type
        FROM doctor_schedules
        WHERE doctor_id = ? AND is_available = 1
        ORDER BY schedule_type, day_of_week, time_period
    ";
    
    $stmt = $conn->prepare($schedules_query);
    $stmt->bind_param("i", $doctor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $schedules = [
        'clinic' => [],
        'teleconsultation' => []
    ];
    
    while ($row = $result->fetch_assoc()) {
        $day_names = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $day_name = $day_names[$row['day_of_week']];
        
        $time_str = date('g:i A', strtotime($row['start_time'])) . ' - ' . 
                   date('g:i A', strtotime($row['end_time']));
        
        $schedules[$row['schedule_type']][] = [
            'day' => $day_name,
            'day_of_week' => $row['day_of_week'],
            'time_period' => $row['time_period'],
            'time' => $time_str,
            'appointment_type' => $row['appointment_type']
        ];
    }
    
    return $schedules;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Doctor Availability - HealthBase</title>
    <link rel="icon" type="image/x-icon" href="/assets/icons/favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="css/patient_dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .availability-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            padding: 25px;
            margin-bottom: 20px;
        }

        .filter-section {
            background: #f8fafc;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
        }

        .filter-row {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 15px;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-weight: 600;
            color: #334155;
            font-size: 14px;
            margin-bottom: 5px;
        }

        .filter-group select,
        .filter-group input {
            padding: 10px 12px;
            border: 1.5px solid #e2e8f0;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s;
            font-family: 'Inter', sans-serif;
        }

        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .btn-filter {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 14px;
        }

        .btn-filter:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        }

        .doctors-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
        }

        .doctor-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .doctor-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            border-color: #3b82f6;
        }

        .doctor-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .doctor-info h4 {
            margin: 0 0 5px 0;
            color: #1e293b;
            font-size: 18px;
            font-weight: 600;
        }

        .doctor-info p {
            margin: 0;
            color: #64748b;
            font-size: 14px;
        }

        .availability-status {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
            font-weight: 600;
            padding: 4px 8px;
            border-radius: 12px;
        }

        .status-available {
            background: #d1fae5;
            color: #065f46;
        }

        .status-busy {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        .indicator-available {
            background: #10b981;
        }

        .indicator-busy {
            background: #ef4444;
        }

        .time-slots-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(80px, 1fr));
            gap: 8px;
            margin-top: 15px;
        }

        .time-slot {
            padding: 8px 6px;
            text-align: center;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.3s;
            cursor: pointer;
        }

        .time-slot.available {
            background: #f0f9ff;
            border-color: #3b82f6;
            color: #1e40af;
        }

        .time-slot.available:hover {
            background: #3b82f6;
            color: white;
        }

        .time-slot.booked {
            background: #f1f5f9;
            border-color: #cbd5e1;
            color: #94a3b8;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .doctor-stats {
            display: flex;
            justify-content: space-between;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #f1f5f9;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
        }

        .stat-label {
            font-size: 11px;
            color: #64748b;
            margin-top: 2px;
        }

        .book-appointment-btn {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 12px;
            margin-top: 10px;
            width: 100%;
        }

        .book-appointment-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
        }

        .no-doctors {
            text-align: center;
            padding: 40px;
            color: #64748b;
        }

        .no-doctors i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #cbd5e1;
        }

        .refresh-btn {
            background: white;
            color: #64748b;
            border: 1px solid #e2e8f0;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 12px;
        }

        .refresh-btn:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }

        @media (max-width: 768px) {
            .filter-row {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .doctors-grid {
                grid-template-columns: 1fr;
            }
            
            .time-slots-grid {
                grid-template-columns: repeat(auto-fit, minmax(70px, 1fr));
            }
        }
    </style>
</head>
<body class="patient-dashboard-page">
    <?php include 'includes/patient_sidebar.php'; ?>
    
    <div class="patient-main-content">
        <!-- Header -->
        <header class="patient-header">
            <div class="patient-header-left">
                <h1 class="patient-welcome">Doctor Availability</h1>
                <p class="patient-subtitle">View real-time doctor schedules and book appointments</p>
            </div>
            <div class="patient-header-right">
                <button class="refresh-btn" onclick="location.reload()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
        </header>

        <!-- Dashboard Content -->
        <div class="patient-dashboard-content">
            <!-- Filter Section -->
            <div class="filter-section">
                <form method="GET" class="filter-form">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="specialization">Specialization:</label>
                            <select id="specialization" name="specialization">
                                <option value="">All Specializations</option>
                                <?php while ($spec = $specializations_result->fetch_assoc()): ?>
                                    <option value="<?= htmlspecialchars($spec['specialization']) ?>" 
                                            <?= $specialization_filter === $spec['specialization'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($spec['specialization']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="date">Date:</label>
                            <input type="date" id="date" name="date" value="<?= htmlspecialchars($date_filter) ?>" min="<?= date('Y-m-d') ?>">
                        </div>
                        <button type="submit" class="btn-filter">
                            <i class="fas fa-search"></i> Filter
                        </button>
                    </div>
                </form>
            </div>

            <!-- Doctors Availability -->
            <div class="availability-container">
                <h3 style="margin-bottom: 20px; color: #1e293b; font-size: 20px; font-weight: 600;">
                    <i class="fas fa-calendar-alt" style="margin-right: 8px; color: #3b82f6;"></i>
                    Doctor Schedules
                </h3>

                <?php 
                if ($doctors_result->num_rows > 0) {
                    $doctors_array = [];
                    while ($doctor = $doctors_result->fetch_assoc()) {
                        // Apply specialization filter if set
                        if (empty($specialization_filter) || $doctor['specialization'] === $specialization_filter) {
                            $doctors_array[] = $doctor;
                        }
                    }
                    
                    if (count($doctors_array) > 0):
                        foreach ($doctors_array as $doctor): 
                            $schedules = getDoctorSchedulesForPatient($conn, $doctor['id']);
                            $clinic_schedules = $schedules['clinic'];
                            $tele_schedules = $schedules['teleconsultation'];
                            
                            // Organize schedules by day
                            $clinic_by_day = [
                                'Sunday' => ['AM' => [], 'PM' => []],
                                'Monday' => ['AM' => [], 'PM' => []],
                                'Tuesday' => ['AM' => [], 'PM' => []],
                                'Wednesday' => ['AM' => [], 'PM' => []],
                                'Thursday' => ['AM' => [], 'PM' => []],
                                'Friday' => ['AM' => [], 'PM' => []],
                                'Saturday' => ['AM' => [], 'PM' => []]
                            ];
                            
                            foreach ($clinic_schedules as $schedule) {
                                $day = $schedule['day'];
                                $period = $schedule['time_period'];
                                if ($period === 'Any') {
                                    $clinic_by_day[$day]['AM'][] = $schedule;
                                    $clinic_by_day[$day]['PM'][] = $schedule;
                                } else {
                                    $clinic_by_day[$day][$period][] = $schedule;
                                }
                            }
                            
                            $tele_by_day = [
                                'Sunday' => ['AM' => [], 'PM' => []],
                                'Monday' => ['AM' => [], 'PM' => []],
                                'Tuesday' => ['AM' => [], 'PM' => []],
                                'Wednesday' => ['AM' => [], 'PM' => []],
                                'Thursday' => ['AM' => [], 'PM' => []],
                                'Friday' => ['AM' => [], 'PM' => []],
                                'Saturday' => ['AM' => [], 'PM' => []]
                            ];
                            
                            foreach ($tele_schedules as $schedule) {
                                $day = $schedule['day'];
                                $period = $schedule['time_period'];
                                if ($period === 'Any') {
                                    $tele_by_day[$day]['AM'][] = $schedule;
                                    $tele_by_day[$day]['PM'][] = $schedule;
                                } else {
                                    $tele_by_day[$day][$period][] = $schedule;
                                }
                            }
                ?>
                    <div class="doctor-schedule-section" style="margin-bottom: 30px; background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border: 1px solid #e2e8f0;">
                        <div class="doctor-profile" style="display: flex; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #e2e8f0;">
                            <div style="width: 60px; height: 60px; background: linear-gradient(135deg, #3b82f6, #2563eb); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 15px; color: white; font-weight: bold; font-size: 18px;">
                                <?= strtoupper(substr($doctor['first_name'], 0, 1) . substr($doctor['last_name'], 0, 1)) ?>
                            </div>
                            <div>
                                <h3 style="margin: 0; font-size: 18px; color: #1e293b; font-weight: 700;">
                                    Dr. <?= strtoupper(htmlspecialchars($doctor['last_name'] . ', ' . $doctor['first_name'])) ?>
                                </h3>
                                <p style="margin: 5px 0; color: #64748b; font-size: 13px;"><?= htmlspecialchars($doctor['specialization'] ?? 'General Medicine') ?></p>
                            </div>
                        </div>

                        <!-- Clinic Schedule -->
                        <div class="schedule-table-section" style="margin-bottom: 25px;">
                            <h4 style="margin-bottom: 12px; color: #1e293b; font-size: 15px; font-weight: 600;">
                                <i class="fas fa-hospital" style="margin-right: 6px; color: #3b82f6;"></i>
                                Clinic Schedule
                            </h4>
                            <div style="overflow-x: auto;">
                                <table style="width: 100%; border-collapse: collapse; background: white; font-size: 13px;">
                                    <thead>
                                        <tr style="background: #f8fafc;">
                                            <th style="padding: 10px; text-align: left; border: 1px solid #e2e8f0; font-weight: 600; color: #334155;">DAY</th>
                                            <th style="padding: 10px; text-align: left; border: 1px solid #e2e8f0; font-weight: 600; color: #334155;">AM</th>
                                            <th style="padding: 10px; text-align: left; border: 1px solid #e2e8f0; font-weight: 600; color: #334155;">PM</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $day): ?>
                                            <tr>
                                                <td style="padding: 8px; border: 1px solid #e2e8f0; font-weight: 500; color: #334155;"><?= $day ?></td>
                                                <td style="padding: 8px; border: 1px solid #e2e8f0; color: #64748b; font-size: 12px;">
                                                    <?php if (!empty($clinic_by_day[$day]['AM'])): ?>
                                                        <?php foreach ($clinic_by_day[$day]['AM'] as $slot): ?>
                                                            <div style="margin-bottom: 5px;">
                                                                <?= htmlspecialchars($slot['time']) ?>
                                                                <?php if ($slot['appointment_type'] === 'By Appointment'): ?>
                                                                    <i class="fas fa-info-circle" style="color: #dc2626; margin-left: 4px;" title="By Appointment"></i>
                                                                <?php else: ?>
                                                                    <i class="fas fa-info-circle" style="color: #2563eb; margin-left: 4px;" title="Walk-in"></i>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="padding: 8px; border: 1px solid #e2e8f0; color: #64748b; font-size: 12px;">
                                                    <?php if (!empty($clinic_by_day[$day]['PM'])): ?>
                                                        <?php foreach ($clinic_by_day[$day]['PM'] as $slot): ?>
                                                            <div style="margin-bottom: 5px;">
                                                                <?= htmlspecialchars($slot['time']) ?>
                                                                <?php if ($slot['appointment_type'] === 'By Appointment'): ?>
                                                                    <i class="fas fa-info-circle" style="color: #dc2626; margin-left: 4px;" title="By Appointment"></i>
                                                                <?php else: ?>
                                                                    <i class="fas fa-info-circle" style="color: #2563eb; margin-left: 4px;" title="Walk-in"></i>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div style="margin-top: 12px; font-size: 11px; color: #64748b;">
                                <p style="margin: 3px 0;"><i class="fas fa-info-circle" style="color: #dc2626;"></i> By Appointment</p>
                                <p style="margin: 3px 0;"><i class="fas fa-info-circle" style="color: #2563eb;"></i> First Come, First Served</p>
                            </div>
                        </div>

                        <!-- Teleconsultation Schedule -->
                        <div class="schedule-table-section">
                            <h4 style="margin-bottom: 12px; color: #1e293b; font-size: 15px; font-weight: 600;">
                                <i class="fas fa-headset" style="margin-right: 6px; color: #22c55e;"></i>
                                Teleconsultation Schedule
                            </h4>
                            <div style="overflow-x: auto;">
                                <table style="width: 100%; border-collapse: collapse; background: white; font-size: 13px;">
                                    <thead>
                                        <tr style="background: #f8fafc;">
                                            <th style="padding: 10px; text-align: left; border: 1px solid #e2e8f0; font-weight: 600; color: #334155;">DAY</th>
                                            <th style="padding: 10px; text-align: left; border: 1px solid #e2e8f0; font-weight: 600; color: #334155;">AM</th>
                                            <th style="padding: 10px; text-align: left; border: 1px solid #e2e8f0; font-weight: 600; color: #334155;">PM</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $has_tele_schedule = false;
                                        foreach (['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $day): 
                                            if (!empty($tele_by_day[$day]['AM']) || !empty($tele_by_day[$day]['PM'])) {
                                                $has_tele_schedule = true;
                                            }
                                        endforeach;
                                        ?>
                                        
                                        <?php if (!$has_tele_schedule): ?>
                                            <tr>
                                                <td colspan="3" style="padding: 20px; text-align: center; color: #94a3b8;">
                                                    No Schedule
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach (['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $day): ?>
                                                <tr>
                                                    <td style="padding: 8px; border: 1px solid #e2e8f0; font-weight: 500; color: #334155;"><?= $day ?></td>
                                                    <td style="padding: 8px; border: 1px solid #e2e8f0; color: #64748b; font-size: 12px;">
                                                        <?php if (!empty($tele_by_day[$day]['AM'])): ?>
                                                            <?php foreach ($tele_by_day[$day]['AM'] as $slot): ?>
                                                                <div style="margin-bottom: 5px;">
                                                                    <?= htmlspecialchars($slot['time']) ?>
                                                                    <i class="fas fa-headset" style="color: #22c55e; margin-left: 4px;" title="Teleconsultation"></i>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td style="padding: 8px; border: 1px solid #e2e8f0; color: #64748b; font-size: 12px;">
                                                        <?php if (!empty($tele_by_day[$day]['PM'])): ?>
                                                            <?php foreach ($tele_by_day[$day]['PM'] as $slot): ?>
                                                                <div style="margin-bottom: 5px;">
                                                                    <?= htmlspecialchars($slot['time']) ?>
                                                                    <i class="fas fa-headset" style="color: #22c55e; margin-left: 4px;" title="Teleconsultation"></i>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Book Appointment Button -->
                        <div style="text-align: center; margin-top: 20px; padding-top: 15px; border-top: 2px solid #e2e8f0;">
                            <button onclick="window.location.href='/appointments/scheduling.php?doctor_id=<?= $doctor['id'] ?>'" style="background: linear-gradient(135deg, #10b981, #059669); color: white; border: none; padding: 10px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 14px; transition: all 0.3s;">
                                <i class="fas fa-calendar-plus"></i> Book Appointment
                            </button>
                        </div>
                    </div>
                <?php 
                        endforeach;
                    else:
                ?>
                    <div class="no-doctors">
                        <i class="fas fa-user-md"></i>
                        <h3>No doctors found</h3>
                        <p>No doctors match your current filter criteria.</p>
                    </div>
                <?php 
                    endif;
                }
                ?>
            </div>
        </div>
    </div>

    <script src="js/patient_sidebar.js"></script>
    <script src="js/doctor_availability.js"></script>
    <script>
        function bookAppointment(doctorId, date, hour = null) {
            // Redirect to scheduling page with pre-filled data
            let url = '/appointments/scheduling.php?';
            url += 'doctor_id=' + doctorId;
            url += '&date=' + date;
            if (hour) {
                url += '&hour=' + hour;
            }
            window.location.href = url;
        }

        // Auto-refresh every 5 minutes
        setInterval(function() {
            // Only refresh if no filters are active or if it's the current date
            const currentDate = new Date().toISOString().split('T')[0];
            const selectedDate = document.getElementById('date').value;
            const specialization = document.getElementById('specialization').value;
            
            if (selectedDate === currentDate && !specialization) {
                location.reload();
            }
        }, 300000); // 5 minutes
    </script>
</body>
</html>
