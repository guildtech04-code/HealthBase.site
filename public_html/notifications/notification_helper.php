<?php
// notification_helper.php

function addNotification($conn, $user_id, $type, $ref_id = null) {
    $message = "";
    $link = "";

    switch ($type) {
        case "appointment":
            $message = "📅 You have a new appointment request.";
            $link = "appointments.php?id=" . intval($ref_id);
            break;

        case "support":
            $message = "🛠 Your support ticket has a new reply.";
            $link = "support.php?id=" . intval($ref_id);
            break;

        case "message":
            $message = "📩 You received a new message.";
            $link = "messages.php?id=" . intval($ref_id);
            break;

        default:
            $message = "🔔 You have a new notification.";
            $link = "notifications.php";
    }

    $stmt = $conn->prepare("
        INSERT INTO notifications (user_id, message, link, type) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("isss", $user_id, $message, $link, $type);
    $stmt->execute();
}
