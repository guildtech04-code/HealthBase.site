<?php
session_start();
include("db_connect.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'assistant') {
    http_response_code(403);
    exit("Unauthorized");
}

$action = $_POST['action'] ?? '';
$idsRaw = trim($_POST['ids'] ?? '');

if ($action === '' || $idsRaw === '') {
    http_response_code(400);
    exit("Missing action or ids");
}

// Accept comma-separated list (e.g. "1,2,3") or single id like "5"
$idsArray = array_filter(array_map('intval', explode(',', $idsRaw)), function($v){ return $v > 0; });
if (count($idsArray) === 0) {
    http_response_code(400);
    exit("No valid ids");
}

$idList = implode(',', $idsArray);

switch ($action) {
    case 'mark_read':
        $sql = "UPDATE support_tickets SET is_read = 1 WHERE id IN ($idList)";
        $conn->query($sql);
        echo "OK";
        break;

    case 'mark_unread':
        $sql = "UPDATE support_tickets SET is_read = 0 WHERE id IN ($idList)";
        $conn->query($sql);
        echo "OK";
        break;

    case 'delete':
        // soft delete
        $sql = "UPDATE support_tickets SET is_deleted = 1 WHERE id IN ($idList)";
        $conn->query($sql);
        echo "OK";
        break;

    case 'mark_done':
        $sql = "UPDATE support_tickets SET status = 'done' WHERE id IN ($idList)";
        $conn->query($sql);
        echo "OK";
        break;

    case 'mark_undone':
        $sql = "UPDATE support_tickets SET status = 'open' WHERE id IN ($idList)";
        $conn->query($sql);
        echo "OK";
        break;

    default:
        http_response_code(400);
        echo "Unknown action";
        break;
}
