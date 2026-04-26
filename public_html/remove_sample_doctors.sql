-- Remove Sample/Test Doctors - Keep Only Real Doctors
-- This removes all sample doctors and schedules, keeping only the 3 real doctors

-- Step 1: Delete all schedules except for the 3 real doctors (IDs: 3, 5, 19)
DELETE FROM `doctor_schedules` 
WHERE doctor_id NOT IN (3, 5, 19);

-- Step 2: Delete all sample doctors (keeping only IDs 3, 5, 19 as doctors)
-- Note: Only deactivate other doctors, don't delete them in case they have appointments
UPDATE `users` 
SET `status` = 'inactive' 
WHERE role = 'doctor' AND id NOT IN (3, 5, 19);

-- Step 3: Ensure our 3 real doctors are active
UPDATE `users` 
SET `status` = 'active' 
WHERE id IN (3, 5, 19) AND role = 'doctor';

-- Step 4: Clean up any orphaned appointment data (optional - be careful!)
-- Only run this if you want to clean up test appointment data
-- DELETE FROM `appointments` WHERE doctor_id NOT IN (3, 5, 19);

-- Verification: Check remaining active doctors
SELECT 
    id,
    first_name,
    last_name,
    specialization,
    status
FROM users 
WHERE role = 'doctor' AND status = 'active'
ORDER BY id;

-- Verification: Check schedules
SELECT 
    u.id,
    u.first_name,
    u.last_name,
    u.specialization,
    COUNT(ds.id) as schedule_count
FROM users u
LEFT JOIN doctor_schedules ds ON u.id = ds.doctor_id
WHERE u.role = 'doctor' AND u.status = 'active'
GROUP BY u.id, u.first_name, u.last_name, u.specialization
ORDER BY u.id;

