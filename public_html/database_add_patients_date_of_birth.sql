-- Idempotent: add date_of_birth to patients when missing (for birthday + age sync)
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'patients'
  AND COLUMN_NAME = 'date_of_birth';

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `patients` ADD COLUMN `date_of_birth` DATE DEFAULT NULL AFTER `last_name`',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
