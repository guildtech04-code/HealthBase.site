-- Update health_concern field in patients table to support longer text for "Others" option
-- This increases the column size from VARCHAR(100) to VARCHAR(255)

USE hb;

-- Increase health_concern column size to accommodate "Others" entries
ALTER TABLE patients MODIFY COLUMN health_concern VARCHAR(255) NOT NULL;

-- Display message
SELECT 'Health concern field updated successfully. Column size increased from VARCHAR(100) to VARCHAR(255).' AS Status;

