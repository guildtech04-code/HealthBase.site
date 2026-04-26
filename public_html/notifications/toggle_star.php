<?php
require_once __DIR__ . '/../includes/auth_guard.php';
ensure_logged_in();
include("../config/db_connect.php");
require_once __DIR__ . '/../includes/security.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["error" => "Invalid method"]);
    exit;
}

require_post_csrf();

$ticket_id = intval($_POST['ticket_id'] ?? 0);
if ($ticket_id > 0) {
    $res = $conn->query("SELECT is_starred FROM support_tickets WHERE id=$ticket_id");
    if ($res && $row = $res->fetch_assoc()) {
        $newVal = $row['is_starred'] ? 0 : 1;
        $stmt = $conn->prepare("UPDATE support_tickets SET is_starred=? WHERE id=?");
        $stmt->bind_param("ii", $newVal, $ticket_id);
        $stmt->execute();
        echo json_encode(["starred" => $newVal]);
        exit;
    }
}
echo json_encode(["error" => "Failed"]);
