-- Create SMART Assistant Account
-- Run this SQL script in your database to create the assistant account

INSERT INTO users (username, email, password, role, first_name, last_name, gender, status, created_at) 
VALUES (
    'smart_assistant', 
    'assistant@healthbase.com', 
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: SmartAssistant2024
    'assistant', 
    'SMART', 
    'Assistant', 
    'Male', 
    'active', 
    NOW()
);

-- Verify the account was created
SELECT id, username, email, role, first_name, last_name, status FROM users WHERE username = 'smart_assistant';
