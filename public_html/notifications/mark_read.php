<?php
session_start();
include("db_connect.php");

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit("Unauthorized");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ticket_id'])) {
    $ticketId = intval($_POST['ticket_id']);
    $query = $conn->prepare("UPDATE support_tickets SET is_read = 1 WHERE id = ?");
    $query->bind_param("i", $ticketId);
    $query->execute();
    echo "ok";
} else {
    http_response_code(400);
    echo "Invalid request";
}
