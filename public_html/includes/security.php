<?php
// Security helpers: session hardening and CSRF utilities

if (session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    $cookieParams = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $cookieParams['path'] ?? '/',
        'domain' => $cookieParams['domain'] ?? '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Simple idle timeout (30 minutes)
if (!isset($_SESSION['last_activity'])) {
    $_SESSION['last_activity'] = time();
} elseif (time() - $_SESSION['last_activity'] > 1800) {
    session_unset();
    session_destroy();
    session_start();
}
$_SESSION['last_activity'] = time();

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_validate(?string $token): bool {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function require_post_csrf(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_validate($_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        echo 'Invalid request.';
        exit;
    }
}

function sanitize_string(string $s, int $max = 255): string {
    $s = trim($s);
    if (mb_strlen($s) > $max) {
        $s = mb_substr($s, 0, $max);
    }
    return $s;
}

function require_int($value): int {
    if (!isset($value) || !is_numeric($value)) {
        http_response_code(400);
        echo 'Invalid parameter.';
        exit;
    }
    return (int)$value;
}
?>


