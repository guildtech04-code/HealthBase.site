-- ============================================
-- Database Revisions for EHR System Improvements
-- ============================================

-- 1. Add consultation status field (separate from appointment status)
-- Check if column exists first
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'consultations' 
  AND COLUMN_NAME = 'consultation_status';

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `consultations` ADD COLUMN `consultation_status` enum(\'Pending\',\'Cleared\',\'Cancelled\') NOT NULL DEFAULT \'Pending\' AFTER `follow_up_date`',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Add vital signs fields to consultations table
-- Check and add each column individually
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'consultations' AND COLUMN_NAME = 'systolic_bp';
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `consultations` ADD COLUMN `systolic_bp` smallint DEFAULT NULL AFTER `consultation_status`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'consultations' AND COLUMN_NAME = 'diastolic_bp';
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `consultations` ADD COLUMN `diastolic_bp` smallint DEFAULT NULL AFTER `systolic_bp`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'consultations' AND COLUMN_NAME = 'heart_rate';
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `consultations` ADD COLUMN `heart_rate` smallint DEFAULT NULL AFTER `diastolic_bp`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'consultations' AND COLUMN_NAME = 'temperature_c';
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `consultations` ADD COLUMN `temperature_c` decimal(4,1) DEFAULT NULL AFTER `heart_rate`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'consultations' AND COLUMN_NAME = 'respiratory_rate';
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `consultations` ADD COLUMN `respiratory_rate` smallint DEFAULT NULL AFTER `temperature_c`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'consultations' AND COLUMN_NAME = 'oxygen_saturation';
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `consultations` ADD COLUMN `oxygen_saturation` tinyint DEFAULT NULL AFTER `respiratory_rate`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'consultations' AND COLUMN_NAME = 'weight_kg';
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `consultations` ADD COLUMN `weight_kg` decimal(5,2) DEFAULT NULL AFTER `oxygen_saturation`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'consultations' AND COLUMN_NAME = 'height_cm';
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `consultations` ADD COLUMN `height_cm` decimal(5,2) DEFAULT NULL AFTER `weight_kg`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'consultations' AND COLUMN_NAME = 'bmi';
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `consultations` ADD COLUMN `bmi` decimal(5,2) DEFAULT NULL AFTER `height_cm`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3. Add report summary field to consultations
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'consultations' AND COLUMN_NAME = 'report_summary';
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `consultations` ADD COLUMN `report_summary` text DEFAULT NULL AFTER `treatment_plan`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4. Ensure patients table has date_of_birth (already exists, but verify)
-- Note: We'll handle age calculation in application code

-- 5. Add index for consultation status
SET @idx_exists = 0;
SELECT COUNT(*) INTO @idx_exists FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'consultations' AND INDEX_NAME = 'idx_consultation_status';
SET @sql = IF(@idx_exists = 0, 'ALTER TABLE `consultations` ADD INDEX `idx_consultation_status` (`consultation_status`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 6. Add unique constraint to prevent duplicate patient names per user
-- Note: This prevents the same user from creating multiple patient records with identical names
-- Check if constraint already exists before adding
SET @constraint_exists = 0;
SELECT COUNT(*) INTO @constraint_exists 
FROM information_schema.TABLE_CONSTRAINTS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'patients' 
  AND CONSTRAINT_NAME = 'uniq_patient_name_user';

SET @sql = IF(@constraint_exists = 0,
    'ALTER TABLE `patients` ADD UNIQUE KEY `uniq_patient_name_user` (`user_id`, `first_name`, `last_name`)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 7. Ensure consultations table has proper primary key (should already exist)
-- Verify primary key exists
-- ALTER TABLE `consultations` ADD PRIMARY KEY (`id`); -- Only if not exists

