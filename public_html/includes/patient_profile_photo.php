<?php
/**
 * Patient profile photo stored in users.profile_photo (web path under /uploads/profile_photos/).
 * Call hb_ensure_user_profile_photo_column() after DB connect.
 */

function hb_user_profile_photo_column_exists(mysqli $conn): bool
{
    $r = $conn->query("SHOW COLUMNS FROM `users` LIKE 'profile_photo'");

    return $r && $r->num_rows > 0;
}

function hb_ensure_user_profile_photo_column(mysqli $conn): bool
{
    if (hb_user_profile_photo_column_exists($conn)) {
        return true;
    }
    if ($conn->query("ALTER TABLE `users` ADD COLUMN `profile_photo` VARCHAR(512) DEFAULT NULL")) {
        return hb_user_profile_photo_column_exists($conn);
    }
    error_log('hb_ensure_user_profile_photo_column: ' . $conn->error);

    return false;
}

function hb_profile_photos_fs_dir(): string
{
    return dirname(__DIR__) . '/uploads/profile_photos';
}

function hb_profile_photos_ensure_dir(): bool
{
    $dir = hb_profile_photos_fs_dir();
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
            return false;
        }
    }
    $idx = $dir . '/index.html';
    if (!is_file($idx)) {
        @file_put_contents($idx, '');
    }

    return is_writable($dir);
}

/**
 * Returns a safe web URL for img src, or null if missing/invalid.
 */
function hb_user_profile_photo_public_url(?string $stored): ?string
{
    $stored = trim((string) $stored);
    if ($stored === '') {
        return null;
    }
    if (strpos($stored, '..') !== false || strpos($stored, "\0") !== false) {
        return null;
    }
    if ($stored[0] !== '/') {
        return null;
    }
    if (strpos($stored, '/uploads/profile_photos/') !== 0) {
        return null;
    }
    $fs = dirname(__DIR__) . $stored;
    $real = realpath($fs);
    $base = realpath(hb_profile_photos_fs_dir());
    if ($real === false || $base === false || strpos($real, $base) !== 0 || !is_file($real)) {
        return null;
    }

    return $stored;
}

function hb_user_profile_photo_delete_file(?string $stored): void
{
    $url = hb_user_profile_photo_public_url($stored);
    if ($url === null) {
        return;
    }
    $fs = dirname(__DIR__) . $url;
    if (is_file($fs)) {
        @unlink($fs);
    }
}

/**
 * @return array{error: string, path: ?string}
 */
function hb_save_user_profile_photo_upload(mysqli $conn, int $user_id, array $file): array
{
    $err = static function (string $m): array {
        return ['error' => $m, 'path' => null];
    };

    if ($user_id < 1) {
        return $err('Invalid session.');
    }
    if (!hb_ensure_user_profile_photo_column($conn)) {
        return $err('Profile photo storage is not available.');
    }
    if (!hb_profile_photos_ensure_dir()) {
        return $err('Upload folder is not writable.');
    }

    $code = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($code === UPLOAD_ERR_NO_FILE) {
        return ['error' => '', 'path' => null];
    }
    if ($code !== UPLOAD_ERR_OK) {
        return $err('Upload failed. Please try a smaller image (max 2 MB).');
    }
    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return $err('Invalid upload.');
    }

    $max = 2 * 1024 * 1024;
    $size = (int) ($file['size'] ?? 0);
    if ($size < 1 || $size > $max) {
        return $err('Image must be at most 2 MB.');
    }

    $info = @getimagesize($file['tmp_name']);
    if ($info === false) {
        return $err('Please upload a valid image (JPEG, PNG, GIF, or WebP).');
    }
    $allowed = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_GIF => 'gif',
        IMAGETYPE_WEBP => 'webp',
    ];
    $type = $info[2] ?? 0;
    if (!isset($allowed[$type])) {
        return $err('Please upload a JPEG, PNG, GIF, or WebP image.');
    }
    $ext = $allowed[$type];
    $name = 'u' . $user_id . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $destFs = hb_profile_photos_fs_dir() . '/' . $name;
    if (!@move_uploaded_file($file['tmp_name'], $destFs)) {
        return $err('Could not save the image.');
    }

    $web = '/uploads/profile_photos/' . $name;

    $prev = $conn->prepare('SELECT profile_photo FROM users WHERE id = ? LIMIT 1');
    $old = null;
    if ($prev) {
        $prev->bind_param('i', $user_id);
        if ($prev->execute()) {
            $row = $prev->get_result()->fetch_assoc();
            $old = $row['profile_photo'] ?? null;
        }
        $prev->close();
    }

    $st = $conn->prepare('UPDATE users SET profile_photo = ? WHERE id = ?');
    if (!$st) {
        @unlink($destFs);

        return $err('Could not update profile.');
    }
    $st->bind_param('si', $web, $user_id);
    if (!$st->execute()) {
        @unlink($destFs);
        $st->close();

        return $err('Could not save profile photo.');
    }
    $st->close();

    hb_user_profile_photo_delete_file($old);

    return ['error' => '', 'path' => $web];
}

function hb_clear_user_profile_photo(mysqli $conn, int $user_id): bool
{
    if ($user_id < 1 || !hb_user_profile_photo_column_exists($conn)) {
        return false;
    }
    $prev = $conn->prepare('SELECT profile_photo FROM users WHERE id = ? LIMIT 1');
    $old = null;
    if ($prev) {
        $prev->bind_param('i', $user_id);
        if ($prev->execute()) {
            $row = $prev->get_result()->fetch_assoc();
            $old = $row['profile_photo'] ?? null;
        }
        $prev->close();
    }
    $st = $conn->prepare('UPDATE users SET profile_photo = NULL WHERE id = ?');
    if (!$st) {
        return false;
    }
    $st->bind_param('i', $user_id);
    $ok = $st->execute();
    $st->close();
    if ($ok) {
        hb_user_profile_photo_delete_file($old);
    }

    return $ok;
}
