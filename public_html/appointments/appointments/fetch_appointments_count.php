<?php
include("db_connect.php");

$result = $conn->query("SELECT COUNT(*) as total FROM appointments");
$row = $result->fetch_assoc();

echo json_encode(["total" => $row['total']]);
?>
