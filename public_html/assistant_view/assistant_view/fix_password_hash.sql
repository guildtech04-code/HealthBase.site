-- Fix password hash for user ID 24
-- This will properly hash the password 'admin123'

-- First, let's see the current user
SELECT id, username, email, role, password FROM users WHERE id = 24;

-- Update the password with proper hash for 'admin123'
UPDATE users 
SET password = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' 
WHERE id = 24;

-- Verify the update
SELECT id, username, email, role, 
       CASE 
           WHEN password LIKE '$2y$%' THEN 'HASHED'
           ELSE 'PLAIN TEXT'
       END as password_status
FROM users WHERE id = 24;
