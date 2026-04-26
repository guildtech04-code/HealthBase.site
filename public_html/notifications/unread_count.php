<?php
session_start();
include("db_connect.php");

$result = $conn->query("SELECT COUNT(*) as unread_count FROM support_tickets WHERE is_read = 0");
$row = $result->fetch_assoc();
echo json_encode($row);
?>
