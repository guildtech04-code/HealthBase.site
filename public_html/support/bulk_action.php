<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'assistant') {
    die('Unauthorized');
}

$action = $_POST['action'] ?? '';
$ids = explode(',', $_POST['ids'] ?? '');

if (empty($ids)) {
    echo 'No IDs provided';
    exit;
}

foreach ($ids as $id) {
    $id = intval($id);
    if ($id <= 0) continue;

    switch ($action) {
        case 'mark_read':
            $conn->query("UPDATE support_tickets SET is_read = 1 WHERE id = $id");
            break;
        case 'mark_unread':
            $conn->query("UPDATE support_tickets SET is_read = 0 WHERE id = $id");
            break;
        case 'delete':
            $conn->query("UPDATE support_tickets SET is_deleted = 1 WHERE id = $id");
            break;
    }
}

echo 'success';

