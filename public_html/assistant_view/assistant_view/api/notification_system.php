<?php
session_start();
require_once '../../config/db_connect.php';

class SmartNotificationSystem {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    /**
     * Send appointment reminder to patient
     */
    public function sendPatientReminder($appointmentId) {
        try {
            $appointment = $this->getAppointmentDetails($appointmentId);
            
            if (!$appointment) {
                return false;
            }
            
            $message = $this->generatePatientReminderMessage($appointment);
            $this->sendNotification($appointment['patient_id'], 'patient', $message, 'reminder');
            
            // Log the notification
            $this->logNotification($appointmentId, 'patient_reminder', $message);
            
            return true;
        } catch (Exception $e) {
            error_log("Error sending patient reminder: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send appointment alert to doctor
     */
    public function sendDoctorAlert($appointmentId) {
        try {
            $appointment = $this->getAppointmentDetails($appointmentId);
            
            if (!$appointment) {
                return false;
            }
            
            $message = $this->generateDoctorAlertMessage($appointment);
            $this->sendNotification($appointment['doctor_id'], 'doctor', $message, 'alert');
            
            // Log the notification
            $this->logNotification($appointmentId, 'doctor_alert', $message);
            
            return true;
        } catch (Exception $e) {
            error_log("Error sending doctor alert: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send urgent case notification to staff
     */
    public function sendUrgentCaseNotification($appointmentId) {
        try {
            $appointment = $this->getAppointmentDetails($appointmentId);
            
            if (!$appointment || $appointment['priority'] !== 'critical') {
                return false;
            }
            
            $message = $this->generateUrgentCaseMessage($appointment);
            
            // Send to all staff members
            $staffMembers = $this->getStaffMembers();
            foreach ($staffMembers as $staff) {
                $this->sendNotification($staff['id'], 'staff', $message, 'urgent');
            }
            
            // Log the notification
            $this->logNotification($appointmentId, 'urgent_case', $message);
            
            return true;
        } catch (Exception $e) {
            error_log("Error sending urgent case notification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send schedule optimization notification
     */
    public function sendOptimizationNotification($optimizationData) {
        try {
            $message = $this->generateOptimizationMessage($optimizationData);
            
            // Send to assistants and admins
            $assistants = $this->getAssistants();
            foreach ($assistants as $assistant) {
                $this->sendNotification($assistant['id'], 'assistant', $message, 'optimization');
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Error sending optimization notification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get appointment details
     */
    private function getAppointmentDetails($appointmentId) {
        $sql = "SELECT 
                    a.*,
                    p.name as patient_name,
                    p.email as patient_email,
                    p.phone as patient_phone,
                    d.name as doctor_name,
                    d.email as doctor_email
                FROM appointments a
                LEFT JOIN patients p ON a.patient_id = p.id
                LEFT JOIN doctors d ON a.doctor_id = d.id
                WHERE a.id = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$appointmentId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Generate patient reminder message
     */
    private function generatePatientReminderMessage($appointment) {
        $appointmentTime = date('M j, Y \a\t g:i A', strtotime($appointment['appointment_time']));
        
        return "Reminder: You have an appointment with Dr. {$appointment['doctor_name']} on {$appointmentTime}. " .
               "Please arrive 15 minutes early. If you need to reschedule, please contact us immediately.";
    }
    
    /**
     * Generate doctor alert message
     */
    private function generateDoctorAlertMessage($appointment) {
        $appointmentTime = date('M j, Y \a\t g:i A', strtotime($appointment['appointment_time']));
        $priority = strtoupper($appointment['priority']);
        
        return "UPCOMING APPOINTMENT: {$appointment['patient_name']} - {$appointmentTime} " .
               "Priority: {$priority} | Health Risk: {$appointment['health_risk_score']}/10";
    }
    
    /**
     * Generate urgent case message
     */
    private function generateUrgentCaseMessage($appointment) {
        $appointmentTime = date('M j, Y \a\t g:i A', strtotime($appointment['appointment_time']));
        
        return "🚨 URGENT CASE ALERT 🚨\n" .
               "Patient: {$appointment['patient_name']}\n" .
               "Doctor: {$appointment['doctor_name']}\n" .
               "Time: {$appointmentTime}\n" .
               "Health Risk Score: {$appointment['health_risk_score']}/10\n" .
               "Immediate attention required!";
    }
    
    /**
     * Generate optimization message
     */
    private function generateOptimizationMessage($data) {
        return "Schedule optimization completed:\n" .
               "• {$data['rescheduled']} appointments rescheduled\n" .
               "• {$data['waitingTimeReduction']} minutes waiting time reduced\n" .
               "• {$data['priorityAdjustments']} priority adjustments made";
    }
    
    /**
     * Send notification to user
     */
    private function sendNotification($userId, $userType, $message, $type) {
        // Insert into notifications table
        $sql = "INSERT INTO notifications (user_id, user_type, message, type, created_at) 
                VALUES (?, ?, ?, ?, NOW())";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$userId, $userType, $message, $type]);
        
        // Send email notification if user has email
        $this->sendEmailNotification($userId, $userType, $message, $type);
    }
    
    /**
     * Send email notification
     */
    private function sendEmailNotification($userId, $userType, $message, $type) {
        $email = $this->getUserEmail($userId, $userType);
        
        if (!$email) {
            return;
        }
        
        $subject = $this->getEmailSubject($type);
        $htmlMessage = $this->formatEmailMessage($message, $type);
        
        // Use PHPMailer or similar to send email
        $this->sendEmail($email, $subject, $htmlMessage);
    }
    
    /**
     * Get user email
     */
    private function getUserEmail($userId, $userType) {
        $table = $userType === 'patient' ? 'patients' : 
                ($userType === 'doctor' ? 'doctors' : 'users');
        
        $sql = "SELECT email FROM {$table} WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['email'] : null;
    }
    
    /**
     * Get email subject
     */
    private function getEmailSubject($type) {
        $subjects = [
            'reminder' => 'Appointment Reminder - HealthBase',
            'alert' => 'Upcoming Appointment Alert - HealthBase',
            'urgent' => 'URGENT: Critical Case Alert - HealthBase',
            'optimization' => 'Schedule Optimization Complete - HealthBase'
        ];
        
        return $subjects[$type] ?? 'HealthBase Notification';
    }
    
    /**
     * Format email message
     */
    private function formatEmailMessage($message, $type) {
        $html = "<html><body>";
        $html .= "<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>";
        $html .= "<div style='background: #667eea; color: white; padding: 20px; text-align: center;'>";
        $html .= "<h1>HealthBase SMART Scheduling</h1>";
        $html .= "</div>";
        $html .= "<div style='padding: 20px; background: #f7fafc;'>";
        $html .= "<h2>" . $this->getEmailTitle($type) . "</h2>";
        $html .= "<p style='font-size: 16px; line-height: 1.6;'>" . nl2br(htmlspecialchars($message)) . "</p>";
        $html .= "</div>";
        $html .= "<div style='background: #e2e8f0; padding: 15px; text-align: center; font-size: 12px; color: #4a5568;'>";
        $html .= "<p>This is an automated message from HealthBase SMART Scheduling System</p>";
        $html .= "</div>";
        $html .= "</div></body></html>";
        
        return $html;
    }
    
    /**
     * Get email title
     */
    private function getEmailTitle($type) {
        $titles = [
            'reminder' => 'Appointment Reminder',
            'alert' => 'Upcoming Appointment',
            'urgent' => 'URGENT: Critical Case',
            'optimization' => 'Schedule Optimization Update'
        ];
        
        return $titles[$type] ?? 'HealthBase Notification';
    }
    
    /**
     * Send email using PHPMailer
     */
    private function sendEmail($to, $subject, $htmlMessage) {
        // This would integrate with your existing PHPMailer setup
        // For now, just log the email
        error_log("Email sent to {$to}: {$subject}");
    }
    
    /**
     * Log notification
     */
    private function logNotification($appointmentId, $action, $message) {
        $sql = "INSERT INTO notification_logs (appointment_id, action, message, created_at) 
                VALUES (?, ?, ?, NOW())";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$appointmentId, $action, $message]);
    }
    
    /**
     * Get staff members
     */
    private function getStaffMembers() {
        $sql = "SELECT id, name, email FROM users WHERE role IN ('assistant', 'admin')";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get assistants
     */
    private function getAssistants() {
        $sql = "SELECT id, name, email FROM users WHERE role = 'assistant'";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// API endpoints
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $notificationSystem = new SmartNotificationSystem($conn);
    $input = json_decode(file_get_contents('php://input'), true);
    
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'send_patient_reminder':
            $result = $notificationSystem->sendPatientReminder($input['appointment_id']);
            echo json_encode(['success' => $result]);
            break;
            
        case 'send_doctor_alert':
            $result = $notificationSystem->sendDoctorAlert($input['appointment_id']);
            echo json_encode(['success' => $result]);
            break;
            
        case 'send_urgent_notification':
            $result = $notificationSystem->sendUrgentCaseNotification($input['appointment_id']);
            echo json_encode(['success' => $result]);
            break;
            
        case 'send_optimization_notification':
            $result = $notificationSystem->sendOptimizationNotification($input['data']);
            echo json_encode(['success' => $result]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
}
?>
