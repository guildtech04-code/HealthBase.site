<?php
session_start();
include("db_connect.php");

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['id'])) {
    $ticket_id = intval($_POST['id']);
    $stmt = $conn->prepare("UPDATE support_tickets SET is_read = 1 WHERE id = ?");
    $stmt->bind_param("i", $ticket_id);
    $success = $stmt->execute();
    echo json_encode(["success" => $success]);
}
?>
