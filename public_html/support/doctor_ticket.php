<?php
session_start();
include("../config/db_connect.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$ticket_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// ✅ Fetch ticket (doctor should be involved)
$stmt = $conn->prepare("
    SELECT id, subject, message, status, created_at
    FROM support_tickets
    WHERE id = ? 
");
$stmt->bind_param("i", $ticket_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("❌ Ticket not found or no access.");
}

$ticket = $result->fetch_assoc();

// ✅ Handle reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_message'])) {
    $reply_message = trim($_POST['reply_message']);
    if ($reply_message !== '') {
        $insert = $conn->prepare("
            INSERT INTO ticket_replies (ticket_id, message, user_id, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $insert->bind_param("isi", $ticket_id, $reply_message, $user_id);
        $insert->execute();

        // Optionally re-open ticket
        $update = $conn->prepare("UPDATE support_tickets SET status='open' WHERE id=?");
        $update->bind_param("i", $ticket_id);
        $update->execute();

        header("Location: doctor_ticket.php?id=" . $ticket_id);
        exit();
    }
}

// ✅ Fetch replies
$replies = $conn->prepare("
    SELECT r.message, r.created_at, u.username AS responder
    FROM ticket_replies r
    JOIN users u ON r.user_id = u.id
    WHERE r.ticket_id = ?
    ORDER BY r.created_at ASC
");
$replies->bind_param("i", $ticket_id);
$replies->execute();
$reply_result = $replies->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Doctor Ticket #<?php echo $ticket['id']; ?></title>
    <style>
        body { font-family: Arial, sans-serif; background:#f4f6f8; margin:0; }
        .container { max-width: 800px; margin:20px auto; background:white; padding:20px; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,0.1); }
        .reply { border-top:1px solid #ddd; padding:10px 0; }
        .reply strong { color:#1565c0; }
        .reply small { color:#777; font-size:0.85em; }
        textarea { width:100%; padding:10px; margin-top:10px; border-radius:6px; border:1px solid #ccc; }
        button { background:#1565c0; color:white; border:none; padding:10px 15px; border-radius:6px; cursor:pointer; }
        button:hover { background:#0d47a1; }
    </style>
</head>
<body>
<div class="container">
    <h2>🎫 Ticket #<?php echo $ticket['id']; ?></h2>
    <p><strong>Subject:</strong> <?php echo htmlspecialchars($ticket['subject']); ?></p>
    <p><strong>Message:</strong><br><?php echo nl2br(htmlspecialchars($ticket['message'])); ?></p>
    <p><strong>Status:</strong> <?php echo ucfirst($ticket['status']); ?></p>
    <p><small>Created: <?php echo $ticket['created_at']; ?></small></p>

    <h3>📩 Replies</h3>
    <?php if ($reply_result->num_rows > 0): ?>
        <?php while ($row = $reply_result->fetch_assoc()): ?>
            <div class="reply">
                <strong><?php echo htmlspecialchars($row['responder']); ?>:</strong>
                <p><?php echo nl2br(htmlspecialchars($row['message'])); ?></p>
                <small><?php echo $row['created_at']; ?></small>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <p>No replies yet.</p>
    <?php endif; ?>

    <h3>✉️ Reply</h3>
    <form method="POST">
        <textarea name="reply_message" rows="4" required placeholder="Type your reply..."></textarea>
        <button type="submit">Send Reply</button>
    </form>
</div>
</body>
</html>
