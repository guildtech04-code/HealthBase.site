<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'assistant') {
    die('Unauthorized');
}

$ticket_id = intval($_POST['ticket_id'] ?? 0);

if ($ticket_id <= 0) {
    echo 'Invalid ticket ID';
    exit;
}

$conn->query("UPDATE support_tickets SET is_read = 1 WHERE id = $ticket_id");
echo 'success';

