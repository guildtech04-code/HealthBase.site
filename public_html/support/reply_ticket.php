<?php
require_once __DIR__ . '/../includes/auth_guard.php';
ensure_role(['assistant']);
include("../config/db_connect.php");
require_once __DIR__ . '/../includes/security.php';

// role ensured

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['ticket_id'], $_POST['reply_message'])) {
    require_post_csrf();
    $ticket_id = intval($_POST['ticket_id']);
    $assistant_id = $_SESSION['user_id'];
    $reply_message = trim($_POST['reply_message']);

    if ($reply_message !== "") {
        // 1. Store reply
        $stmt = $conn->prepare("
            INSERT INTO ticket_replies (ticket_id, assistant_id, message, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->bind_param("iis", $ticket_id, $assistant_id, $reply_message);
        $stmt->execute();

        // 2. Update ticket status to "answered"
        $update = $conn->prepare("UPDATE support_tickets SET status = 'answered' WHERE id = ?");
        $update->bind_param("i", $ticket_id);
        $update->execute();

        // 3. Fetch the ticket's user (the patient who should receive the notification)
        $getUser = $conn->prepare("SELECT user_id FROM support_tickets WHERE id = ?");
        $getUser->bind_param("i", $ticket_id);
        $getUser->execute();
        $getUser->bind_result($ticket_user_id);
        $getUser->fetch();
        $getUser->close();

        if ($ticket_user_id) {
            // 4. Insert notification for the patient (with ticket_id for clickable link)
            $notif = $conn->prepare("
                INSERT INTO notifications (user_id, message, ticket_id, is_read, created_at)
                VALUES (?, ?, ?, 0, NOW())
            ");
            $notif_message = "Your support ticket #$ticket_id has been replied to.";
            $notif->bind_param("isi", $ticket_user_id, $notif_message, $ticket_id);
            $notif->execute();
        }
    }

    // 5. Redirect back to same view page with success message
    header("Location: view_ticket.php?id=$ticket_id&success=1");
    exit();
} else {
    exit("Invalid request.");
}
