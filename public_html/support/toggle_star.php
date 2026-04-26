<?php
require_once __DIR__ . '/../includes/auth_guard.php';
ensure_role(['assistant']);
require_once '../config/db_connect.php';
require_once __DIR__ . '/../includes/security.php';

require_post_csrf();

$ticket_id = intval($_POST['ticket_id'] ?? 0);

if ($ticket_id <= 0) {
    echo 'Invalid ticket ID';
    exit;
}

// Get current starred status
$result = $conn->query("SELECT is_starred FROM support_tickets WHERE id = $ticket_id");
$row = $result->fetch_assoc();

if ($row) {
    $newStarred = $row['is_starred'] ? 0 : 1;
    $conn->query("UPDATE support_tickets SET is_starred = $newStarred WHERE id = $ticket_id");
}

echo 'success';

