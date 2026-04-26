-- Optional manual migration: profile picture path (web path under /uploads/profile_photos/)
-- The app also runs hb_ensure_user_profile_photo_column() on load.

ALTER TABLE `users` ADD COLUMN `profile_photo` VARCHAR(512) DEFAULT NULL;
