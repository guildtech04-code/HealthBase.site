-- ============================================
-- Verify and Ensure Primary Keys Exist
-- ============================================
-- This script verifies that all critical tables have primary keys
-- and adds them if they are missing

-- 1. Verify patients table has primary key
SET @pk_exists = 0;
SELECT COUNT(*) INTO @pk_exists 
FROM information_schema.TABLE_CONSTRAINTS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'patients' 
  AND CONSTRAINT_TYPE = 'PRIMARY KEY';

SET @sql = IF(@pk_exists = 0,
    'ALTER TABLE `patients` ADD PRIMARY KEY (`id`)',
    'SELECT 1 AS "patients table already has primary key"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Verify consultations table has primary key
SET @pk_exists = 0;
SELECT COUNT(*) INTO @pk_exists 
FROM information_schema.TABLE_CONSTRAINTS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'consultations' 
  AND CONSTRAINT_TYPE = 'PRIMARY KEY';

SET @sql = IF(@pk_exists = 0,
    'ALTER TABLE `consultations` ADD PRIMARY KEY (`id`)',
    'SELECT 1 AS "consultations table already has primary key"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. Verify appointments table has primary key
SET @pk_exists = 0;
SELECT COUNT(*) INTO @pk_exists 
FROM information_schema.TABLE_CONSTRAINTS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'appointments' 
  AND CONSTRAINT_TYPE = 'PRIMARY KEY';

SET @sql = IF(@pk_exists = 0,
    'ALTER TABLE `appointments` ADD PRIMARY KEY (`id`)',
    'SELECT 1 AS "appointments table already has primary key"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4. Verify users table has primary key
SET @pk_exists = 0;
SELECT COUNT(*) INTO @pk_exists 
FROM information_schema.TABLE_CONSTRAINTS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'users' 
  AND CONSTRAINT_TYPE = 'PRIMARY KEY';

SET @sql = IF(@pk_exists = 0,
    'ALTER TABLE `users` ADD PRIMARY KEY (`id`)',
    'SELECT 1 AS "users table already has primary key"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 5. Verify prescriptions table has primary key
SET @pk_exists = 0;
SELECT COUNT(*) INTO @pk_exists 
FROM information_schema.TABLE_CONSTRAINTS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'prescriptions' 
  AND CONSTRAINT_TYPE = 'PRIMARY KEY';

SET @sql = IF(@pk_exists = 0,
    'ALTER TABLE `prescriptions` ADD PRIMARY KEY (`id`)',
    'SELECT 1 AS "prescriptions table already has primary key"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Summary: Display all tables with their primary key status
SELECT 
    TABLE_NAME,
    CASE 
        WHEN CONSTRAINT_TYPE = 'PRIMARY KEY' THEN 'YES'
        ELSE 'NO'
    END AS has_primary_key
FROM information_schema.TABLE_CONSTRAINTS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('patients', 'consultations', 'appointments', 'users', 'prescriptions')
  AND CONSTRAINT_TYPE = 'PRIMARY KEY'
GROUP BY TABLE_NAME;

