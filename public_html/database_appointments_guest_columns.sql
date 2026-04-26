-- Run once on production: guest beneficiary when booker has no separate patients row (uniq_patients_user).
-- Idempotent: skip if columns already exist (run in phpMyAdmin or mysql CLI).

SET @db = DATABASE();

SET @sql = (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'appointments' AND COLUMN_NAME = 'guest_first_name') > 0,
    'SELECT 1',
    'ALTER TABLE `appointments` ADD COLUMN `guest_first_name` VARCHAR(100) NULL DEFAULT NULL AFTER `patient_id`, ADD COLUMN `guest_last_name` VARCHAR(100) NULL DEFAULT NULL AFTER `guest_first_name`'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
