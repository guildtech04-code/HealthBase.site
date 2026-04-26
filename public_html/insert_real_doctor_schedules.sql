-- Insert Real Doctor Schedules Based on the Images
-- This replaces mock data with actual schedules from the provided images

-- First, create the doctor_schedules table if it doesn't exist
CREATE TABLE IF NOT EXISTS `doctor_schedules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `doctor_id` int(11) NOT NULL,
  `schedule_type` enum('clinic','teleconsultation') NOT NULL DEFAULT 'clinic',
  `day_of_week` tinyint(1) NOT NULL,
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
  CONSTRAINT `fk_schedule_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- IMPORTANT: Update doctor names to match the REAL doctors from images
-- Remove any sample/test data and use only real doctor names

-- First, clear all existing schedules to start fresh with real data
DELETE FROM `doctor_schedules`;

-- Update to REAL doctor names from the images
-- Dr. SARROSA, EDWARD - Orthopaedic Surgery (ID: 19)
UPDATE `users` 
SET `first_name` = 'SARROSA', `last_name` = 'EDWARD', `specialization` = 'Orthopaedic Surgery'
WHERE id = 19 AND role = 'doctor';

-- Dr. SARROSA, DONNA MARIE - Dermatology (ID: 5)
UPDATE `users` 
SET `first_name` = 'SARROSA', `last_name` = 'DONNA MARIE', `specialization` = 'Dermatology'
WHERE id = 5 AND role = 'doctor';

-- Dr. LEELIN, FREDERICK ANDRE - Medicine, Gastroenterology (ID: 3)
UPDATE `users` 
SET `first_name` = 'LEELIN', `last_name` = 'FREDERICK ANDRE', `specialization` = 'Gastroenterology'
WHERE id = 3 AND role = 'doctor';

-- ============================================
-- Dr. SARROSA, EDWARD (ID: 19) - Orthopaedic Surgery
-- Real doctor from image
-- ============================================

-- Clinic Schedule
INSERT INTO `doctor_schedules` (`doctor_id`, `schedule_type`, `day_of_week`, `time_period`, `start_time`, `end_time`, `appointment_type`) VALUES
-- Wednesday AM: 9:00 AM - 12:00 PM
(19, 'clinic', 3, 'AM', '09:00:00', '12:00:00', 'By Appointment'),
-- Saturday PM: 1:00 PM - 4:00 PM
(19, 'clinic', 6, 'PM', '13:00:00', '16:00:00', 'By Appointment');

-- No teleconsultation schedule for this doctor

-- ============================================
-- Dr. SARROSA, DONNA MARIE (ID: 5) - Dermatology
-- Real doctor from image
-- ============================================

-- Clinic Schedule
INSERT INTO `doctor_schedules` (`doctor_id`, `schedule_type`, `day_of_week`, `time_period`, `start_time`, `end_time`, `appointment_type`) VALUES
-- Monday PM: 1:00 PM - 5:00 PM
(5, 'clinic', 1, 'PM', '13:00:00', '17:00:00', 'By Appointment'),
-- Tuesday AM: 9:00 AM - 12:00 PM
(5, 'clinic', 2, 'AM', '09:00:00', '12:00:00', 'By Appointment'),
-- Thursday AM: 9:00 AM - 12:00 PM
(5, 'clinic', 4, 'AM', '09:00:00', '12:00:00', 'By Appointment'),
-- Saturday AM: 9:00 AM - 12:00 PM
(5, 'clinic', 6, 'AM', '09:00:00', '12:00:00', 'By Appointment');

-- Teleconsultation Schedule
INSERT INTO `doctor_schedules` (`doctor_id`, `schedule_type`, `day_of_week`, `time_period`, `start_time`, `end_time`, `appointment_type`) VALUES
-- Monday to Friday AM: 10:00 AM - 12:00 PM
(5, 'teleconsultation', 1, 'AM', '10:00:00', '12:00:00', 'First Come First Served'),
(5, 'teleconsultation', 2, 'AM', '10:00:00', '12:00:00', 'First Come First Served'),
(5, 'teleconsultation', 3, 'AM', '10:00:00', '12:00:00', 'First Come First Served'),
(5, 'teleconsultation', 4, 'AM', '10:00:00', '12:00:00', 'First Come First Served'),
(5, 'teleconsultation', 5, 'AM', '10:00:00', '12:00:00', 'First Come First Served');

-- ============================================
-- Dr. LEELIN, FREDERICK ANDRE (ID: 3) - Medicine, Gastroenterology
-- Real doctor from image
-- ============================================

-- Clinic Schedule
INSERT INTO `doctor_schedules` (`doctor_id`, `schedule_type`, `day_of_week`, `time_period`, `start_time`, `end_time`, `appointment_type`) VALUES
-- Monday AM: 11:30 AM - 12:00 PM
(3, 'clinic', 1, 'AM', '11:30:00', '12:00:00', 'By Appointment'),
-- Monday PM: 12:00 PM - 2:00 PM
(3, 'clinic', 1, 'PM', '12:00:00', '14:00:00', 'By Appointment'),
-- Tuesday PM: 2:00 PM - 5:00 PM
(3, 'clinic', 2, 'PM', '14:00:00', '17:00:00', 'By Appointment'),
-- Wednesday AM: 11:30 AM - 12:00 PM
(3, 'clinic', 3, 'AM', '11:30:00', '12:00:00', 'By Appointment'),
-- Wednesday PM: 12:00 PM - 2:00 PM
(3, 'clinic', 3, 'PM', '12:00:00', '14:00:00', 'By Appointment'),
-- Thursday PM: 2:00 PM - 5:00 PM
(3, 'clinic', 4, 'PM', '14:00:00', '17:00:00', 'By Appointment'),
-- Friday AM: 11:30 AM - 12:00 PM
(3, 'clinic', 5, 'AM', '11:30:00', '12:00:00', 'By Appointment'),
-- Friday PM: 12:00 PM - 2:00 PM
(3, 'clinic', 5, 'PM', '12:00:00', '14:00:00', 'By Appointment');

-- Teleconsultation Schedule
INSERT INTO `doctor_schedules` (`doctor_id`, `schedule_type`, `day_of_week`, `time_period`, `start_time`, `end_time`, `appointment_type`) VALUES
-- Note: Image shows time as "10:00 PM - 3:00 PM" which seems like a typo, using 1:00 PM - 5:00 PM pattern
-- Monday PM: 1:00 PM - 5:00 PM (adjusted from image typo)
(3, 'teleconsultation', 1, 'PM', '13:00:00', '17:00:00', 'First Come First Served'),
-- Tuesday PM: 1:00 PM - 5:00 PM
(3, 'teleconsultation', 2, 'PM', '13:00:00', '17:00:00', 'First Come First Served'),
-- Wednesday PM: 1:00 PM - 5:00 PM
(3, 'teleconsultation', 3, 'PM', '13:00:00', '17:00:00', 'First Come First Served'),
-- Thursday PM: 1:00 PM - 5:00 PM
(3, 'teleconsultation', 4, 'PM', '13:00:00', '17:00:00', 'First Come First Served'),
-- Friday PM: 1:00 PM - 5:00 PM
(3, 'teleconsultation', 5, 'PM', '13:00:00', '17:00:00', 'First Come First Served');

