<?php
include 'db_connect.php';

if (isset($_GET['id'], $_GET['action'])) {
    $id = $_GET['id'];
    $action = $_GET['action'];

    if ($action === 'activate') {
        $status = 'active';
    } elseif ($action === 'deactivate') {
        $status = 'inactive';
    } else {
        die("Invalid action");
    }

    $stmt = $conn->prepare("UPDATE users SET status=? WHERE id=?");
    $stmt->bind_param("si", $status, $id);
    $stmt->execute();
    $stmt->close();
}

header("Location: manage_users.php");
exit;
?>
