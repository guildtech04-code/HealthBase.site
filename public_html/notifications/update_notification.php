<?php
session_start();
include("db_connect.php");

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success"=>false]);
    exit();
}

$user_id = $_SESSION['user_id'];
$id = intval($_POST['id']);
$action = $_POST['action'];

if ($action === "read") {
    $stmt = $conn->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?");
} else {
    $stmt = $conn->prepare("UPDATE notifications SET is_read=0 WHERE id=? AND user_id=?");
}
$stmt->bind_param("ii", $id, $user_id);
$stmt->execute();
$stmt->close();

$countQuery = $conn->prepare("SELECT COUNT(*) as cnt FROM notifications WHERE user_id=? AND is_read=0");
$countQuery->bind_param("i", $user_id);
$countQuery->execute();
$unreadCount = $countQuery->get_result()->fetch_assoc()['cnt'];

echo json_encode(["success"=>true,"unreadCount"=>$unreadCount]);
