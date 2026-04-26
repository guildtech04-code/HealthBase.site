-- ============================================
-- EHR Module Database Schema
-- Implements Objective 1.2 (EHR Interface)
-- ============================================

-- Table: consultations (visit records)
CREATE TABLE IF NOT EXISTS `consultations` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `appointment_id` int(11) NOT NULL,
    `patient_id` int(11) NOT NULL,
    `doctor_id` int(11) NOT NULL,
    `visit_date` datetime NOT NULL,
    `chief_complaint` text DEFAULT NULL,
    `consultation_notes` text DEFAULT NULL,
    `diagnosis` text DEFAULT NULL,
    `treatment_plan` text DEFAULT NULL,
    `follow_up_date` date DEFAULT NULL,
    `next_visit_risk_score` decimal(5,2) DEFAULT NULL,
    `created_at` timestamp NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_consultation_appt` (`appointment_id`),
    KEY `idx_consultation_patient` (`patient_id`),
    KEY `idx_consultation_doctor` (`doctor_id`),
    KEY `idx_consultation_date` (`visit_date`),
    CONSTRAINT `fk_consultation_appt` FOREIGN KEY (`appointment_id`) REFERENCES `appointments`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_consultation_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_consultation_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: prescriptions
CREATE TABLE IF NOT EXISTS `prescriptions` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `consultation_id` int(11) NOT NULL,
    `appointment_id` int(11) NOT NULL,
    `patient_id` int(11) NOT NULL,
    `doctor_id` int(11) NOT NULL,
    `medication_name` varchar(255) NOT NULL,
    `dosage` varchar(100) DEFAULT NULL,
    `frequency` varchar(100) DEFAULT NULL,
    `duration` varchar(100) DEFAULT NULL,
    `instructions` text DEFAULT NULL,
    `quantity` varchar(50) DEFAULT NULL,
    `created_at` timestamp NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_presc_consult` (`consultation_id`),
    KEY `idx_presc_appt` (`appointment_id`),
    KEY `idx_presc_patient` (`patient_id`),
    KEY `idx_presc_doctor` (`doctor_id`),
    CONSTRAINT `fk_presc_consult` FOREIGN KEY (`consultation_id`) REFERENCES `consultations`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_presc_appt` FOREIGN KEY (`appointment_id`) REFERENCES `appointments`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_presc_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_presc_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: medical_history_entries
CREATE TABLE IF NOT EXISTS `medical_history_entries` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `patient_id` int(11) NOT NULL,
    `entry_type` enum('Diagnosis','Treatment','Lab_Result','Procedure','Note') NOT NULL,
    `entry_text` text NOT NULL,
    `entry_date` datetime NOT NULL,
    `physician_name` varchar(255) DEFAULT NULL,
    `related_consultation_id` int(11) DEFAULT NULL,
    `created_at` timestamp NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_history_patient` (`patient_id`),
    KEY `idx_history_type` (`entry_type`),
    KEY `idx_history_date` (`entry_date`),
    KEY `idx_history_consult` (`related_consultation_id`),
    CONSTRAINT `fk_history_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_history_consult` FOREIGN KEY (`related_consultation_id`) REFERENCES `consultations`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: services (taxonomy for appointments)
CREATE TABLE IF NOT EXISTS `services` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(100) NOT NULL,
    `specialization` varchar(100) DEFAULT NULL,
    `duration_minutes` int(11) NOT NULL DEFAULT 60,
    `price` decimal(10,2) DEFAULT NULL,
    `active` tinyint(1) NOT NULL DEFAULT 1,
    `created_at` timestamp NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_service_name` (`name`),
    KEY `idx_service_spec` (`specialization`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default services
INSERT INTO `services` (`name`, `specialization`, `duration_minutes`, `price`) VALUES
('General Consultation', NULL, 60, 0.00),
('Dermatology Consultation', 'Dermatology', 60, 0.00),
('Gastroenterology Consultation', 'Gastroenterology', 60, 0.00),
('Orthopedic Consultation', 'Orthopedics', 60, 0.00),
('Follow-up Visit', NULL, 30, 0.00)
ON DUPLICATE KEY UPDATE name=name;

-- Add service_id to appointments table if not exists
-- Try to add the column (will fail silently if exists)
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'appointments' 
  AND COLUMN_NAME = 'service_id';

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `appointments` ADD COLUMN `service_id` int(11) DEFAULT NULL AFTER `patient_id`',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add index for service_id (if not exists)
SET @idx_exists = 0;
SELECT COUNT(*) INTO @idx_exists 
FROM information_schema.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'appointments' 
  AND INDEX_NAME = 'idx_appt_service';

SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE `appointments` ADD INDEX `idx_appt_service` (`service_id`)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Table: appointment_reports (for Objective 2.5)
CREATE TABLE IF NOT EXISTS `appointment_reports` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `report_type` enum('Daily','Weekly','Monthly') NOT NULL,
    `period_start` date NOT NULL,
    `period_end` date NOT NULL,
    `doctor_id` int(11) DEFAULT NULL,
    `total_appointments` int(11) NOT NULL DEFAULT 0,
    `confirmed_count` int(11) NOT NULL DEFAULT 0,
    `completed_count` int(11) NOT NULL DEFAULT 0,
    `cancelled_count` int(11) NOT NULL DEFAULT 0,
    `no_show_count` int(11) NOT NULL DEFAULT 0,
    `generated_at` timestamp NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_report_daterange` (`period_start`,`period_end`),
    KEY `idx_report_doctor` (`doctor_id`),
    CONSTRAINT `fk_report_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: risk_predictions (for predictive analytics)
CREATE TABLE IF NOT EXISTS `risk_predictions` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `patient_id` int(11) NOT NULL,
    `prediction_date` datetime NOT NULL,
    `risk_level` enum('Low','Medium','High','Critical') NOT NULL,
    `risk_score` decimal(5,2) NOT NULL,
    `factors` text DEFAULT NULL,
    `predicted_return_days` int(11) DEFAULT NULL,
    `actual_return_date` datetime DEFAULT NULL,
    `accuracy_verified` tinyint(1) DEFAULT 0,
    `created_at` timestamp NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_risk_patient` (`patient_id`),
    KEY `idx_risk_date` (`prediction_date`),
    KEY `idx_risk_level` (`risk_level`),
    CONSTRAINT `fk_risk_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

