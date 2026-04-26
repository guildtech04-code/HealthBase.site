<?php
session_start();
require '../config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit("Unauthorized");
}

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();

echo "success";
?>
