<?php
// api/doctor_availability.php - API endpoint for real-time doctor availability
session_start();
require_once '../../config/db_connect.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

try {
    $doctor_id = $_GET['doctor_id'] ?? null;
    $date = $_GET['date'] ?? date('Y-m-d');
    
    if (!$doctor_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Doctor ID is required']);
        exit();
    }
    
    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid date format']);
        exit();
    }
    
    // Get doctor information
    $doctor_query = $conn->prepare("
        SELECT id, first_name, last_name, specialization, email
        FROM users 
        WHERE id = ? AND role = 'doctor' AND status = 'active'
    ");
    $doctor_query->bind_param("i", $doctor_id);
    $doctor_query->execute();
    $doctor_result = $doctor_query->get_result();
    
    if ($doctor_result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Doctor not found']);
        exit();
    }
    
    $doctor = $doctor_result->fetch_assoc();
    
    // Get doctor schedules
    $schedules_query = $conn->prepare("
        SELECT 
            schedule_type,
            day_of_week,
            time_period,
            start_time,
            end_time,
            appointment_type
        FROM doctor_schedules
        WHERE doctor_id = ? AND is_available = 1
        ORDER BY schedule_type, day_of_week, time_period
    ");
    $schedules_query->bind_param("i", $doctor_id);
    $schedules_query->execute();
    $schedules_result = $schedules_query->get_result();
    
    $schedules = [
        'clinic' => [],
        'teleconsultation' => []
    ];
    
    while ($row = $schedules_result->fetch_assoc()) {
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
    
    // Get booked appointments for the specified date
    $bookings_query = $conn->prepare("
        SELECT 
            HOUR(appointment_date) as hour,
            MINUTE(appointment_date) as minute,
            status,
            CONCAT(p.first_name, ' ', p.last_name) as patient_name
        FROM appointments a
        LEFT JOIN patients p ON a.patient_id = p.id
        WHERE a.doctor_id = ? 
        AND DATE(a.appointment_date) = ?
        AND a.status IN ('Confirmed', 'Pending')
        ORDER BY appointment_date
    ");
    $bookings_query->bind_param("is", $doctor_id, $date);
    $bookings_query->execute();
    $bookings_result = $bookings_query->get_result();
    
    $booked_times = [];
    $appointments = [];
    
    while ($booking = $bookings_result->fetch_assoc()) {
        $hour = $booking['hour'];
        $booked_times[] = $hour;
        $appointments[] = [
            'hour' => $hour,
            'minute' => $booking['minute'],
            'time' => sprintf('%02d:%02d', $hour, $booking['minute']),
            'status' => $booking['status'],
            'patient_name' => $booking['patient_name']
        ];
    }
    
    // Generate time slots based on doctor's schedule for the selected date
    $dow = date('w', strtotime($date)); // Get day of week for the date
    $all_slots = [];
    $available_hours = [];
    
    // Get available hours from doctor's schedule
    $day_schedules = $conn->prepare("
        SELECT start_time, end_time, schedule_type
        FROM doctor_schedules
        WHERE doctor_id = ? AND day_of_week = ? AND is_available = 1
    ");
    $day_schedules->bind_param("ii", $doctor_id, $dow);
    $day_schedules->execute();
    $day_result = $day_schedules->get_result();
    
    while ($schedule = $day_result->fetch_assoc()) {
        $start_h = (int)date('G', strtotime($schedule['start_time']));
        $end_h = (int)date('G', strtotime($schedule['end_time']));
        for ($h = $start_h; $h < $end_h; $h++) {
            $available_hours[$h] = true;
        }
    }
    
    // Generate slots for available hours
    foreach ($available_hours as $hour => $available) {
        $display_hour = $hour > 12 ? $hour - 12 : $hour;
        $ampm = $hour >= 12 ? 'PM' : 'AM';
        $is_booked = in_array($hour, $booked_times);
        
        $all_slots[] = [
            'hour' => $hour,
            'display_hour' => $display_hour,
            'display_time' => $display_hour . ':00 ' . $ampm,
            'available' => !$is_booked,
            'booked' => $is_booked
        ];
    }
    
    // Calculate statistics
    $total_slots = count($all_slots);
    $booked_slots = count($booked_times);
    $available_slots = $total_slots - $booked_slots;
    
    // Get next available slot
    $next_available = null;
    foreach ($all_slots as $slot) {
        if ($slot['available']) {
            $next_available = $slot;
            break;
        }
    }
    
    // Response data
    $response = [
        'success' => true,
        'doctor' => [
            'id' => $doctor['id'],
            'name' => $doctor['first_name'] . ' ' . $doctor['last_name'],
            'specialization' => $doctor['specialization'],
            'email' => $doctor['email']
        ],
        'date' => $date,
        'statistics' => [
            'total_slots' => $total_slots,
            'available_slots' => $available_slots,
            'booked_slots' => $booked_slots,
            'availability_percentage' => $total_slots > 0 ? round(($available_slots / $total_slots) * 100, 1) : 0
        ],
        'time_slots' => $all_slots,
        'schedules' => $schedules,
        'appointments' => $appointments,
        'next_available' => $next_available,
        'is_available' => $available_slots > 0,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}
?>
