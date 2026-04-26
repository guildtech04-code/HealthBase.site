<?php
session_start();

// Get user ID before clearing session
$user_id = $_SESSION['user_id'] ?? null;

// Clear session
$_SESSION = [];
session_destroy();

// Keep rememberUser cookie so email can be prefilled next login (Remember Me).

// Clear trusted device cookies if user ID is known
if ($user_id) {
    setcookie("trusted_device_" . $user_id, "", time() - 3600, "/");
    setcookie("trusted_until_" . $user_id, "", time() - 3600, "/");
} else {
    // Clear all possible trusted device cookies as fallback
    // This handles cases where session is already destroyed
    if (isset($_COOKIE)) {
        foreach ($_COOKIE as $key => $value) {
            if (strpos($key, 'trusted_device_') === 0 || strpos($key, 'trusted_until_') === 0) {
                setcookie($key, "", time() - 3600, "/");
            }
        }
    }
}

// Redirect to login
header("Location: /auth/login.php");
exit();
?>
