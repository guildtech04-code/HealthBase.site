<?php
require_once __DIR__ . '/../includes/auth_guard.php';
ensure_logged_in();
include("../config/db_connect.php");
require_once __DIR__ . '/../includes/security.php';

if (!isset($_POST['id'])) {
    echo "error";
    exit();
}

require_post_csrf();

$user_id = $_SESSION['user_id'];
$id = intval($_POST['id']);

// Check current status
$stmt = $conn->prepare("SELECT is_read FROM notifications WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if ($row) {
    $newStatus = $row['is_read'] == 1 ? 0 : 1;

    $update = $conn->prepare("UPDATE notifications SET is_read = ? WHERE id = ? AND user_id = ?");
    $update->bind_param("iii", $newStatus, $id, $user_id);
    $update->execute();

    echo "success";
} else {
    echo "error";
}
