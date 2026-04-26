<?php
session_start();
include("db_connect.php");

if (!isset($_SESSION['user_id'])) exit();

$ids = explode(",", $_POST['ids'] ?? "");
if (empty($ids)) exit();

$placeholders = implode(",", array_fill(0, count($ids), "?"));
$types = str_repeat("i", count($ids));

$stmt = $conn->prepare("UPDATE support_tickets SET is_deleted = 1 WHERE id IN ($placeholders)");
$stmt->bind_param($types, ...$ids);
$stmt->execute();
