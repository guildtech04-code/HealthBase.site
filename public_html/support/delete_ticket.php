<?php
require_once __DIR__ . '/../includes/auth_guard.php';
ensure_role(['assistant']);
include("../config/db_connect.php");
require_once __DIR__ . '/../includes/security.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    require_post_csrf();
    $ticket_id = intval($_POST['id']);

    $stmt = $conn->prepare("UPDATE support_tickets SET is_deleted = 1 WHERE id = ?");
    $stmt->bind_param("i", $ticket_id);
    if ($stmt->execute()) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["success" => false]);
    }
    exit();
}
echo json_encode(["success" => false]);
?>
