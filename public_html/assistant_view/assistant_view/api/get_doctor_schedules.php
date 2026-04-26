<?php
// api/get_doctor_schedules.php - Fetch doctor schedules with clinic/teleconsultation support
session_start();
require_once '../../config/db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

try {
    $doctor_id = $_GET['doctor_id'] ?? null;
    $schedule_type = $_GET['type'] ?? 'clinic'; // 'clinic' or 'teleconsultation'
    $date = $_GET['date'] ?? null;
    
    if (!$doctor_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Doctor ID is required']);
        exit();
    }
    
    // Validate schedule type
    if (!in_array($schedule_type, ['clinic', 'teleconsultation'])) {
        $schedule_type = 'clinic';
    }
    
    // Get doctor info
    $doctor_query = $conn->prepare("
        SELECT id, first_name, last_name, specialization, email, 
               CONCAT(first_name, ' ', last_name) as full_name
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
    
    // Get schedules for each day
    $schedules_query = "
        SELECT 
            id,
            schedule_type,
            day_of_week,
            time_period,
            start_time,
            end_time,
            appointment_type,
            is_available,
            notes
        FROM doctor_schedules
        WHERE doctor_id = ? AND schedule_type = ? AND is_available = 1
        AND (effective_from IS NULL OR effective_from <= CURDATE())
        AND (effective_to IS NULL OR effective_to >= CURDATE())
        ORDER BY day_of_week, time_period, start_time
    ";
    
    $schedules_stmt = $conn->prepare($schedules_query);
    $schedules_stmt->bind_param("is", $doctor_id, $schedule_type);
    $schedules_stmt->execute();
    $schedules_result = $schedules_stmt->get_result();
    
    // Organize schedules by day
    $schedules_by_day = [
        'sunday' => ['AM' => [], 'PM' => []],
        'monday' => ['AM' => [], 'PM' => []],
        'tuesday' => ['AM' => [], 'PM' => []],
        'wednesday' => ['AM' => [], 'PM' => []],
        'thursday' => ['AM' => [], 'PM' => []],
        'friday' => ['AM' => [], 'PM' => []],
        'saturday' => ['AM' => [], 'PM' => []]
    ];
    
    $day_names = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
    
    while ($schedule = $schedules_result->fetch_assoc()) {
        $day_index = (int)$schedule['day_of_week'];
        $day_name = $day_names[$day_index];
        
        if ($schedule['time_period'] === 'Any' || $schedule['time_period'] === 'AM') {
            if (!empty($schedule['start_time']) && !empty($schedule['end_time'])) {
                $schedules_by_day[$day_name]['AM'][] = [
                    'start_time' => $schedule['start_time'],
                    'end_time' => $schedule['end_time'],
                    'appointment_type' => $schedule['appointment_type'],
                    'notes' => $schedule['notes']
                ];
            }
        }
        
        if ($schedule['time_period'] === 'Any' || $schedule['time_period'] === 'PM') {
            if (!empty($schedule['start_time']) && !empty($schedule['end_time'])) {
                $schedules_by_day[$day_name]['PM'][] = [
                    'start_time' => $schedule['start_time'],
                    'end_time' => $schedule['end_time'],
                    'appointment_type' => $schedule['appointment_type'],
                    'notes' => $schedule['notes']
                ];
            }
        }
    }
    
    // Format response similar to the images
    $schedule_data = [];
    foreach ($day_names as $day) {
        $schedule_data[] = [
            'day' => ucfirst($day),
            'AM' => $schedules_by_day[$day]['AM'],
            'PM' => $schedules_by_day[$day]['PM']
        ];
    }
    
    $response = [
        'success' => true,
        'doctor' => [
            'id' => $doctor['id'],
            'name' => strtoupper($doctor['full_name']),
            'specialization' => $doctor['specialization'],
            'email' => $doctor['email']
        ],
        'schedule_type' => $schedule_type,
        'schedules' => $schedule_data,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}

