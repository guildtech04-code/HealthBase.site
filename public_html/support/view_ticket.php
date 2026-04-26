<?php
session_start();
include("../config/db_connect.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'assistant') {
    http_response_code(403);
    exit("Unauthorized");
}

$ticketId = intval($_GET['id'] ?? 0);
if ($ticketId <= 0) {
    http_response_code(400);
    exit("Invalid Ticket ID");
}

// Fetch ticket
$query = $conn->prepare("
    SELECT t.id, t.subject, t.message, t.created_at, t.status,
           u.first_name, u.last_name, u.email
    FROM support_tickets t
    JOIN users u ON t.user_id = u.id
    WHERE t.id = ?
");
$query->bind_param("i", $ticketId);
$query->execute();
$ticket = $query->get_result()->fetch_assoc();

if (!$ticket) {
    http_response_code(404);
    exit("Ticket not found");
}

// Mark as read
$conn->query("UPDATE support_tickets SET is_read = 1 WHERE id = $ticketId");

// Fetch replies
$repliesQuery = $conn->prepare("
    SELECT r.id, r.message, r.created_at, r.user_id, r.assistant_id,
           u.first_name AS user_first, u.last_name AS user_last,
           a.first_name AS assistant_first, a.last_name AS assistant_last
    FROM ticket_replies r
    LEFT JOIN users u ON r.user_id = u.id
    LEFT JOIN users a ON r.assistant_id = a.id
    WHERE r.ticket_id = ?
    ORDER BY r.created_at ASC
");
$repliesQuery->bind_param("i", $ticketId);
$repliesQuery->execute();
$replies = $repliesQuery->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Ticket - HealthBase</title>
    <link rel="icon" type="image/x-icon" href="/assets/icons/favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8fafc;
            color: #1e293b;
            padding: 20px;
        }

        .ticket-view-wrapper {
            max-width: 900px;
            margin: 0 auto;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #3b82f6;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 20px;
            transition: all 0.3s;
        }

        .back-button:hover {
            color: #2563eb;
            transform: translateX(-3px);
        }

        .ticket-view-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .ticket-header-section {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            padding: 30px;
        }

        .ticket-header-section h3 {
            margin: 0 0 15px 0;
            font-size: 22px;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
        }

        .ticket-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 25px;
            font-size: 14px;
            opacity: 0.95;
        }

        .ticket-meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .ticket-meta-item i {
            font-size: 14px;
            opacity: 0.9;
        }

        .ticket-body {
            padding: 30px;
        }

        .sender-info {
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            padding: 18px 20px;
            margin-bottom: 25px;
        }

        .sender-name {
            font-weight: 600;
            color: #1e293b;
            font-size: 15px;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .sender-email {
            color: #64748b;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .ticket-message-box {
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            padding: 20px 22px;
            line-height: 1.7;
            color: #334155;
            font-size: 15px;
            white-space: pre-wrap;
            word-wrap: break-word;
            min-height: 100px;
        }

        .replies-section {
            margin-top: 35px;
            padding-top: 30px;
            border-top: 2px solid #e2e8f0;
        }

        .replies-section h4 {
            font-size: 18px;
            font-weight: 600;
            color: #1e293b;
            margin: 0 0 25px 0;
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: 'Poppins', sans-serif;
        }

        .replies-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .reply-card {
            background: white;
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px 22px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .reply-card:hover {
            border-color: #3b82f6;
            box-shadow: 0 4px 16px rgba(59, 130, 246, 0.15);
            transform: translateY(-2px);
        }

        .reply-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .reply-author {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .reply-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 16px;
            flex-shrink: 0;
        }

        .reply-author-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .reply-author-name {
            font-weight: 600;
            color: #1e293b;
            font-size: 15px;
            font-family: 'Inter', sans-serif;
        }

        .reply-author-role-wrapper {
            display: flex;
            gap: 6px;
            align-items: center;
        }

        .reply-author-role {
            font-size: 12px;
            color: #64748b;
        }

        .reply-role-badge {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .reply-role-badge.assistant {
            background: linear-gradient(135deg, #e0e7ff, #c7d2fe);
            color: #3730a3;
        }

        .reply-role-badge.patient {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            color: #065f46;
        }

        .reply-date {
            font-size: 12px;
            color: #94a3b8;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .reply-message {
            color: #334155;
            line-height: 1.8;
            font-size: 15px;
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        .no-replies {
            text-align: center;
            padding: 50px 20px;
            color: #94a3b8;
            font-size: 15px;
        }

        .no-replies i {
            font-size: 56px;
            color: #cbd5e1;
            margin-bottom: 20px;
        }

        .no-replies p {
            font-weight: 500;
            color: #64748b;
        }

        .status-indicator {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-left: 10px;
        }

        .status-open {
            background: #fef3c7;
            color: #92400e;
        }

        .status-read {
            background: #dbeafe;
            color: #1e40af;
        }

        .status-closed {
            background: #d1fae5;
            color: #065f46;
        }
    </style>
</head>
<body>
    <div class="ticket-view-wrapper">
        <a href="support.php" class="back-button">
            <i class="fas fa-arrow-left"></i>
            Back to Support
        </a>

        <div class="ticket-view-container">
            <!-- Ticket Header -->
            <div class="ticket-header-section">
                <h3><?php echo htmlspecialchars($ticket['subject']); ?></h3>
                <div class="ticket-meta">
                    <div class="ticket-meta-item">
                        <i class="fas fa-clock"></i>
                        <span><?php echo date("M d, Y h:i A", strtotime($ticket['created_at'])); ?></span>
                    </div>
                    <div class="ticket-meta-item">
                        <i class="fas fa-info-circle"></i>
                        <span>Status: <span class="status-indicator status-<?php echo strtolower($ticket['status']); ?>"><?php echo ucfirst($ticket['status']); ?></span></span>
                    </div>
                </div>
            </div>

            <!-- Ticket Body -->
            <div class="ticket-body">
                <!-- Sender Information -->
                <div class="sender-info">
                    <div class="sender-name">
                        <i class="fas fa-user"></i>
                        <?php echo htmlspecialchars($ticket['first_name'] . " " . $ticket['last_name']); ?>
                    </div>
                    <div class="sender-email">
                        <i class="fas fa-envelope"></i>
                        <?php echo htmlspecialchars($ticket['email']); ?>
                    </div>
                </div>

                <!-- Ticket Message -->
                <div class="ticket-message-box">
                    <?php echo nl2br(htmlspecialchars($ticket['message'])); ?>
                </div>

                <!-- Replies Section -->
                <div class="replies-section">
                    <h4>
                        <i class="fas fa-comments"></i>
                        Replies (<?php echo $replies->num_rows; ?>)
                    </h4>

                    <?php if ($replies->num_rows === 0): ?>
                        <div class="no-replies">
                            <i class="fas fa-comment-slash"></i>
                            <p>No replies yet. Be the first to respond!</p>
                        </div>
                    <?php else: ?>
                        <div class="replies-list">
                            <?php while ($reply = $replies->fetch_assoc()): ?>
                                <?php
                                if (!empty($reply['assistant_id'])) {
                                    $name = trim($reply['assistant_first'] . ' ' . $reply['assistant_last']);
                                    $role = "Assistant";
                                    $roleClass = "assistant";
                                    $avatar = strtoupper(substr($name, 0, 1));
                                } elseif (!empty($reply['user_id'])) {
                                    $name = trim($reply['user_first'] . ' ' . $reply['user_last']);
                                    $role = "Patient";
                                    $roleClass = "patient";
                                    $avatar = strtoupper(substr($name, 0, 1));
                                } else {
                                    $name = "Unknown";
                                    $role = "User";
                                    $roleClass = "assistant";
                                    $avatar = "?";
                                }
                                ?>
                                <div class="reply-card">
                                    <div class="reply-header">
                                        <div class="reply-author">
                                            <div class="reply-avatar"><?php echo $avatar; ?></div>
                                            <div class="reply-author-info">
                                                <div class="reply-author-name">
                                                    <?php echo htmlspecialchars($name); ?>
                                                </div>
                                                <div class="reply-author-role-wrapper">
                                                    <span class="reply-role-badge <?php echo $roleClass; ?>">
                                                        <?php echo $role; ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="reply-date">
                                            <i class="fas fa-clock"></i>
                                            <?php echo date("M d, Y h:i A", strtotime($reply['created_at'])); ?>
                                        </div>
                                    </div>
                                    <div class="reply-message">
                                        <?php echo nl2br(htmlspecialchars($reply['message'])); ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
