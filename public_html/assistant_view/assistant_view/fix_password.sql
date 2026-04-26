-- Fix password for user ID 24
-- This will hash the password 'admin123' properly

UPDATE users 
SET password = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' 
WHERE id = 24;

-- Verify the update
SELECT id, username, email, role, first_name, last_name FROM users WHERE id = 24;
