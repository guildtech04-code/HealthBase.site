-- Enhanced Doctor Schedules Table
-- Supports: Clinic vs Teleconsultation, AM/PM windows, Appointment Types (By Appointment vs Walk-in)

CREATE TABLE IF NOT EXISTS `doctor_schedules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `doctor_id` int(11) NOT NULL,
  `schedule_type` enum('clinic','teleconsultation') NOT NULL DEFAULT 'clinic',
  `day_of_week` tinyint(1) NOT NULL COMMENT '0=Sunday, 1=Monday, 2=Tuesday, 3=Wednesday, 4=Thursday, 5=Friday, 6=Saturday',
  `time_period` enum('AM','PM','Any') NOT NULL DEFAULT 'Any',
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `appointment_type` enum('By Appointment','Walk-in','First Come First Served') NOT NULL DEFAULT 'By Appointment',
  `is_available` tinyint(1) NOT NULL DEFAULT 1,
  `effective_from` date DEFAULT NULL,
  `effective_to` date DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_doctor_schedule` (`doctor_id`, `schedule_type`, `day_of_week`, `time_period`),
  KEY `idx_schedule_active` (`doctor_id`, `day_of_week`, `is_available`),
  KEY `idx_schedule_daterange` (`effective_from`, `effective_to`),
  CONSTRAINT `fk_schedule_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migrate existing data from doctor_availability (optional)
-- This inserts data from the old table structure
INSERT INTO `doctor_schedules` (`doctor_id`, `schedule_type`, `day_of_week`, `time_period`, `start_time`, `end_time`, `appointment_type`, `is_available`)
SELECT 
  `doctor_id`, 
  'clinic' as schedule_type,
  `day_of_week`,
  'Any' as time_period,
  `start_time`,
  `end_time`,
  'By Appointment' as appointment_type,
  `is_available`
FROM `doctor_availability`;

-- Create index for faster queries
ALTER TABLE `doctor_schedules`
  ADD INDEX `idx_type_day` (`schedule_type`, `day_of_week`);

-- Comments to clarify the structure
ALTER TABLE `doctor_schedules` 
  MODIFY COLUMN `schedule_type` enum('clinic','teleconsultation') NOT NULL DEFAULT 'clinic' COMMENT 'Type of schedule - clinic visit or teleconsultation',
  MODIFY COLUMN `time_period` enum('AM','PM','Any') NOT NULL DEFAULT 'Any' COMMENT 'AM (morning), PM (afternoon), or Any (all day)',
  MODIFY COLUMN `appointment_type` enum('By Appointment','Walk-in','First Come First Served') NOT NULL DEFAULT 'By Appointment' COMMENT 'How appointments are handled';

