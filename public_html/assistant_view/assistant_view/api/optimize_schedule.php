<?php
session_start();
require_once '../../config/db_connect.php';

// Check if user is logged in and has assistant/admin role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['assistant', 'admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $maxWaitingTime = $input['maxWaitingTime'] ?? 30;
    $bufferTime = $input['bufferTime'] ?? 10;
    $autoPriority = $input['autoPriority'] ?? true;
    $emergencyOverride = $input['emergencyOverride'] ?? true;
    
    $optimizations = [
        'rescheduled' => 0,
        'waitingTimeReduction' => 0,
        'priorityAdjustments' => 0,
        'changes' => []
    ];
    
    // Get today's appointments
    $sql = "SELECT 
                a.*,
                p.name as patient_name,
                d.name as doctor_name,
                d.availability_status
            FROM appointments a
            LEFT JOIN patients p ON a.patient_id = p.id
            LEFT JOIN doctors d ON a.doctor_id = d.id
            WHERE DATE(a.appointment_time) = CURDATE()
            AND a.status IN ('scheduled', 'confirmed')
            ORDER BY a.priority DESC, a.appointment_time ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate current waiting times and identify conflicts
    $conflicts = identifySchedulingConflicts($appointments, $maxWaitingTime);
    
    // Apply optimizations
    foreach ($conflicts as $conflict) {
        $optimization = optimizeAppointment($conflict, $appointments, $maxWaitingTime, $bufferTime);
        
        if ($optimization) {
            $optimizations['rescheduled']++;
            $optimizations['waitingTimeReduction'] += $optimization['waitingTimeReduction'];
            $optimizations['changes'][] = $optimization;
            
            // Update appointment in database
            updateAppointmentSchedule($optimization);
        }
    }
    
    // Auto-adjust priorities based on health risk
    if ($autoPriority) {
        $priorityAdjustments = adjustPriorities($appointments);
        $optimizations['priorityAdjustments'] = $priorityAdjustments;
    }
    
    // Send notifications for changes
    sendOptimizationNotifications($optimizations['changes']);
    
    echo json_encode([
        'success' => true,
        'optimizations' => $optimizations
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Optimization failed: ' . $e->getMessage()
    ]);
}

function identifySchedulingConflicts($appointments, $maxWaitingTime) {
    $conflicts = [];
    $doctorSchedules = [];
    
    // Group appointments by doctor
    foreach ($appointments as $appointment) {
        $doctorId = $appointment['doctor_id'];
        if (!isset($doctorSchedules[$doctorId])) {
            $doctorSchedules[$doctorId] = [];
        }
        $doctorSchedules[$doctorId][] = $appointment;
    }
    
    // Check for conflicts in each doctor's schedule
    foreach ($doctorSchedules as $doctorId => $schedule) {
        usort($schedule, function($a, $b) {
            return strtotime($a['appointment_time']) - strtotime($b['appointment_time']);
        });
        
        for ($i = 0; $i < count($schedule) - 1; $i++) {
            $current = $schedule[$i];
            $next = $schedule[$i + 1];
            
            $currentEnd = strtotime($current['appointment_time']) + (30 * 60); // 30 min appointment
            $nextStart = strtotime($next['appointment_time']);
            $gap = ($nextStart - $currentEnd) / 60; // gap in minutes
            
            if ($gap < 10) { // Less than 10 minutes gap
                $conflicts[] = [
                    'type' => 'overlap',
                    'appointments' => [$current, $next],
                    'gap' => $gap
                ];
            }
        }
    }
    
    return $conflicts;
}

function optimizeAppointment($conflict, $allAppointments, $maxWaitingTime, $bufferTime) {
    $appointments = $conflict['appointments'];
    
    // Find alternative time slots
    $alternativeSlots = findAlternativeSlots($appointments[0], $allAppointments, $bufferTime);
    
    if (empty($alternativeSlots)) {
        return null;
    }
    
    // Select best alternative based on priority and health risk
    $bestSlot = selectBestSlot($alternativeSlots, $appointments[0]);
    
    if ($bestSlot) {
        return [
            'appointment_id' => $appointments[0]['id'],
            'old_time' => $appointments[0]['appointment_time'],
            'new_time' => $bestSlot['time'],
            'waitingTimeReduction' => $conflict['gap'],
            'reason' => 'Schedule conflict resolution'
        ];
    }
    
    return null;
}

function findAlternativeSlots($appointment, $allAppointments, $bufferTime) {
    $doctorId = $appointment['doctor_id'];
    $preferredDate = date('Y-m-d', strtotime($appointment['appointment_time']));
    
    // Get doctor's availability
    $availableSlots = getDoctorAvailability($doctorId, $preferredDate);
    
    // Filter out conflicting times
    $conflictingTimes = [];
    foreach ($allAppointments as $apt) {
        if ($apt['doctor_id'] == $doctorId && $apt['id'] != $appointment['id']) {
            $conflictingTimes[] = strtotime($apt['appointment_time']);
        }
    }
    
    $validSlots = [];
    foreach ($availableSlots as $slot) {
        $slotTime = strtotime($slot);
        $hasConflict = false;
        
        foreach ($conflictingTimes as $conflictTime) {
            if (abs($slotTime - $conflictTime) < ($bufferTime * 60)) {
                $hasConflict = true;
                break;
            }
        }
        
        if (!$hasConflict) {
            $validSlots[] = [
                'time' => $slot,
                'score' => calculateSlotScore($slot, $appointment)
            ];
        }
    }
    
    return $validSlots;
}

function getDoctorAvailability($doctorId, $date) {
    // This would typically query a doctor availability table
    // For now, generate standard business hours
    $slots = [];
    $startHour = 9;
    $endHour = 17;
    
    for ($hour = $startHour; $hour < $endHour; $hour++) {
        for ($minute = 0; $minute < 60; $minute += 30) {
            $time = sprintf('%02d:%02d:00', $hour, $minute);
            $slots[] = $date . ' ' . $time;
        }
    }
    
    return $slots;
}

function calculateSlotScore($slot, $appointment) {
    $score = 0;
    
    // Prefer morning slots for urgent cases
    $hour = date('H', strtotime($slot));
    if ($appointment['priority'] == 'critical' && $hour < 12) {
        $score += 10;
    }
    
    // Prefer slots closer to original time
    $timeDiff = abs(strtotime($slot) - strtotime($appointment['appointment_time']));
    $score += max(0, 10 - ($timeDiff / 3600)); // Decrease score for larger time differences
    
    return $score;
}

function selectBestSlot($slots, $appointment) {
    if (empty($slots)) {
        return null;
    }
    
    // Sort by score (highest first)
    usort($slots, function($a, $b) {
        return $b['score'] - $a['score'];
    });
    
    return $slots[0];
}

function updateAppointmentSchedule($optimization) {
    global $conn;
    
    $sql = "UPDATE appointments 
            SET appointment_time = ?, 
                status = 'rescheduled',
                updated_at = NOW()
            WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$optimization['new_time'], $optimization['appointment_id']]);
}

function adjustPriorities($appointments) {
    $adjustments = 0;
    
    foreach ($appointments as $appointment) {
        $currentPriority = $appointment['priority'];
        $suggestedPriority = suggestPriority($appointment);
        
        if ($currentPriority != $suggestedPriority) {
            updateAppointmentPriority($appointment['id'], $suggestedPriority);
            $adjustments++;
        }
    }
    
    return $adjustments;
}

function suggestPriority($appointment) {
    $healthRisk = $appointment['health_risk_score'] ?? 5;
    
    if ($healthRisk >= 8) {
        return 'critical';
    } elseif ($healthRisk >= 6) {
        return 'high';
    } elseif ($healthRisk >= 4) {
        return 'medium';
    } else {
        return 'low';
    }
}

function updateAppointmentPriority($appointmentId, $priority) {
    global $conn;
    
    $sql = "UPDATE appointments 
            SET priority = ?, 
                updated_at = NOW()
            WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$priority, $appointmentId]);
}

function sendOptimizationNotifications($changes) {
    foreach ($changes as $change) {
        // Send notification to patient
        sendPatientNotification($change);
        
        // Send notification to doctor
        sendDoctorNotification($change);
    }
}

function sendPatientNotification($change) {
    // This would integrate with your notification system
    // For now, just log the notification
    error_log("Patient notification: Appointment rescheduled to " . $change['new_time']);
}

function sendDoctorNotification($change) {
    // This would integrate with your notification system
    // For now, just log the notification
    error_log("Doctor notification: Appointment rescheduled to " . $change['new_time']);
}
?>
