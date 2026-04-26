<?php
session_start();
include("../config/db_connect.php");

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(["error" => "Unauthorized"]);
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch notifications (don't trust DB link, we’ll build it manually)
$query = $conn->prepare("
    SELECT id, message, is_read, created_at, ticket_id, type
    FROM notifications
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 5
");
$query->bind_param("i", $user_id);
$query->execute();
$result = $query->get_result();

$notifications = [];
while ($row = $result->fetch_assoc()) {
    // Force doctor_ticket.php if this is a support ticket
    if ($row['type'] === "support" && !empty($row['ticket_id'])) {
        $row['link'] = "doctor_ticket.php?id=" . $row['ticket_id'];
    } else {
        $row['link'] = "#"; // fallback
    }
    $notifications[] = $row;
}

header('Content-Type: application/json');
echo json_encode($notifications);
