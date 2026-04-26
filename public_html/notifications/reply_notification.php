<?php
session_start();
include("db_connect.php");

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success"=>false]);
    exit();
}

$user_id = $_SESSION['user_id'];
$id = intval($_POST['id']);
$reply = trim($_POST['reply']);

if ($reply !== "") {
    // Get notification details
    $stmt = $conn->prepare("SELECT user_id FROM notifications WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
    $stmt->close();

    // Store reply as a new notification (for demonstration)
    $msg = "Doctor replied: " . $reply;
    $notif = $conn->prepare("INSERT INTO notifications (user_id, message, type, link) VALUES (?, ?, 'message', '#')");
    $notif->bind_param("is", $user_id, $msg);
    $notif->execute();
    $notif->close();

    echo json_encode(["success"=>true]);
} else {
    echo json_encode(["success"=>false]);
}
