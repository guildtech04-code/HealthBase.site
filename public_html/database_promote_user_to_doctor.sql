-- Promote account to doctor (run in phpMyAdmin / MySQL on your server DB)
-- Email: cruzmarkjabez14@gmail.com — Dr. Mark Jabez Cruz, Orthopaedic Surgery (matches patient booking category "Orthopedic Surgery")
-- Password is unchanged — only role, display name, and specialty are set/updated.

UPDATE `users`
SET
  `role` = 'doctor',
  `first_name` = 'Mark Jabez',
  `last_name` = 'Cruz',
  `specialization` = CASE
    WHEN `specialization` IS NULL OR TRIM(`specialization`) = '' THEN 'Orthopaedic Surgery'
    ELSE `specialization`
  END
WHERE `email` = 'cruzmarkjabez14@gmail.com'
LIMIT 1;

-- If the account is already `doctor` but was given the wrong specialty (e.g. General Medicine), force orthopaedic for booking filters:
-- UPDATE `users` SET `specialization` = 'Orthopaedic Surgery', `first_name` = 'Mark Jabez', `last_name` = 'Cruz' WHERE `email` = 'cruzmarkjabez14@gmail.com' LIMIT 1;

-- Verify (optional):
-- SELECT id, email, username, role, first_name, last_name, specialization FROM users WHERE email = 'cruzmarkjabez14@gmail.com';
