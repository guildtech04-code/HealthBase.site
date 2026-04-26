<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include("../config/db_connect.php");

date_default_timezone_set("Asia/Manila");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'assistant') {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user info for sidebar
$user_query = $conn->prepare("SELECT username, email, role FROM users WHERE id = ?");
$user_query->bind_param("i", $user_id);
$user_query->execute();
$user_result = $user_query->get_result()->fetch_assoc();

$sidebar_user_data = [
    'username' => htmlspecialchars($user_result['username']),
    'email' => htmlspecialchars($user_result['email']),
    'role' => htmlspecialchars($user_result['role'])
];

// Fetch assistant details
$userQuery = $conn->prepare("SELECT first_name, last_name, email FROM users WHERE id = ?");
$userQuery->bind_param("i", $user_id);
$userQuery->execute();
$userResult = $userQuery->get_result()->fetch_assoc();

$firstName = $userResult['first_name'] ?? '';
$lastName  = $userResult['last_name'] ?? '';
$email     = $userResult['email'] ?? '';
$displayName = trim($firstName . ' ' . $lastName);
if ($displayName === '') $displayName = "Support Agent";

// Folder selection
$folder = $_GET['folder'] ?? 'inbox';
$search = trim($_GET['search'] ?? "");

// Tickets/messages
$tickets = [];

if ($folder === 'inbox') {
    $sql = "
        SELECT t.id, u.first_name, u.last_name, t.subject, t.message, t.status,
               t.created_at, t.is_read, t.is_starred
        FROM support_tickets t
        JOIN users u ON t.user_id = u.id
        WHERE t.is_deleted = 0
    ";
    if ($search !== "") {
        $sql .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR t.subject LIKE ? OR t.message LIKE ?)";
    }
    $sql .= " ORDER BY t.created_at DESC";

    $query = $conn->prepare($sql);
    if ($search !== "") {
        $searchTerm = "%" . $search . "%";
        $query->bind_param("ssss", $searchTerm, $searchTerm, $searchTerm, $searchTerm);
    }
    $query->execute();
    $tickets = $query->get_result()->fetch_all(MYSQLI_ASSOC);

} elseif ($folder === 'sent') {
    $sql = "
        SELECT r.id AS reply_id, r.message, r.created_at, t.id AS ticket_id,
               t.subject, u.first_name, u.last_name, t.is_starred
        FROM ticket_replies r
        JOIN support_tickets t ON r.ticket_id = t.id
        JOIN users u ON t.user_id = u.id
        WHERE r.assistant_id = ?
    ";
    if ($search !== "") {
        $sql .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR t.subject LIKE ? OR r.message LIKE ?)";
    }
    $sql .= " ORDER BY r.created_at DESC";

    $query = $conn->prepare($sql);
    if ($search !== "") {
        $searchTerm = "%" . $search . "%";
        $query->bind_param("issss", $user_id, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
    } else {
        $query->bind_param("i", $user_id);
    }
    $query->execute();
    $tickets = $query->get_result()->fetch_all(MYSQLI_ASSOC);

} elseif ($folder === 'trash') {
    $sql = "
        SELECT t.id, u.first_name, u.last_name, t.subject, t.message, t.status,
               t.created_at, t.is_read, t.is_starred
        FROM support_tickets t
        JOIN users u ON t.user_id = u.id
        WHERE t.is_deleted = 1
    ";
    if ($search !== "") {
        $sql .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR t.subject LIKE ? OR t.message LIKE ?)";
    }
    $sql .= " ORDER BY t.created_at DESC";

    $query = $conn->prepare($sql);
    if ($search !== "") {
        $searchTerm = "%" . $search . "%";
        $query->bind_param("ssss", $searchTerm, $searchTerm, $searchTerm, $searchTerm);
    }
    $query->execute();
    $tickets = $query->get_result()->fetch_all(MYSQLI_ASSOC);

} elseif ($folder === 'starred') {
    $sql = "
        SELECT t.id, u.first_name, u.last_name, t.subject, t.message, t.status,
               t.created_at, t.is_read, t.is_starred
        FROM support_tickets t
        JOIN users u ON t.user_id = u.id
        WHERE t.is_starred = 1 AND t.is_deleted = 0
    ";
    if ($search !== "") {
        $sql .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR t.subject LIKE ? OR t.message LIKE ?)";
    }
    $sql .= " ORDER BY t.created_at DESC";

    $query = $conn->prepare($sql);
    if ($search !== "") {
        $searchTerm = "%" . $search . "%";
        $query->bind_param("ssss", $searchTerm, $searchTerm, $searchTerm, $searchTerm);
    }
    $query->execute();
    $tickets = $query->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Unread count
$unreadQuery = $conn->prepare("SELECT COUNT(*) as unread_count FROM support_tickets WHERE is_read = 0 AND is_deleted = 0");
$unreadQuery->execute();
$unreadCount = $unreadQuery->get_result()->fetch_assoc()['unread_count'];

// Users for autocomplete
$usersResult = $conn->query("SELECT email FROM users");
$allUsers = [];
while ($row = $usersResult->fetch_assoc()) {
    $allUsers[] = $row['email'];
}

// Get counts for each folder
$inboxCount = $conn->query("SELECT COUNT(*) as count FROM support_tickets WHERE is_deleted = 0")->fetch_assoc()['count'];
$sentCount = $conn->query("SELECT COUNT(*) as count FROM ticket_replies WHERE assistant_id = $user_id")->fetch_assoc()['count'];
$starredCount = $conn->query("SELECT COUNT(*) as count FROM support_tickets WHERE is_starred = 1 AND is_deleted = 0")->fetch_assoc()['count'];
$trashCount = $conn->query("SELECT COUNT(*) as count FROM support_tickets WHERE is_deleted = 1")->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
    <title>Support Tickets - HealthBase</title>
    <link rel="icon" type="image/x-icon" href="/assets/icons/favicon.ico">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assistant_view/css/assistant.css">
    <script src="../assistant_view/js/assistant_sidebar.js"></script>
<style>
        .support-container {
            padding: 30px;
        }

        .support-header {
            background: white;
            padding: 20px 30px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .support-welcome {
            font-size: 22px;
            font-weight: 600;
            color: #1e293b;
    margin: 0;
        }

        .support-subtitle {
            font-size: 14px;
            color: #64748b;
            margin: 4px 0 0 0;
        }

        .compose-btn {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .compose-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(59, 130, 246, 0.4);
        }

        .folder-sidebar {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            margin-bottom: 20px;
        }

        .folder-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .folder-item {
            padding: 12px 15px;
            border-radius: 8px;
            cursor: pointer;
    display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
            transition: all 0.2s;
            font-weight: 500;
        }

        .folder-item:hover {
            background: #f1f5f9;
        }

        .folder-item.active {
            background: linear-gradient(135deg, #eff6ff, #f0f9ff);
            color: #2563eb;
            font-weight: 600;
        }

        .folder-item i {
            width: 20px;
            text-align: center;
        }

        .folder-count {
            margin-left: auto;
            background: #e2e8f0;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .folder-item.active .folder-count {
            background: #dbeafe;
            color: #1e40af;
        }

        .search-section {
            background: white;
            border-radius: 12px;
    padding: 20px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .search-input-wrapper {
            flex: 1;
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 12px 15px 12px 40px;
            border: 1.5px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .search-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .search-input-wrapper i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
        }

        .bulk-actions {
            display: flex;
            gap: 10px;
        }

        .btn-secondary {
            background: white;
            color: #334155;
            border: 1.5px solid #e2e8f0;
            padding: 10px 16px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-secondary:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }

        .tickets-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }

        .ticket-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
        }

        .ticket-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        }

        .ticket-card.unread {
            border-left: 4px solid #3b82f6;
        }

        .ticket-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .ticket-sender {
            font-weight: 600;
            color: #1e293b;
            font-size: 15px;
        }

        .ticket-date {
            font-size: 12px;
            color: #64748b;
        }

        .ticket-subject {
            font-weight: 600;
            color: #334155;
            margin-bottom: 8px;
            font-size: 16px;
        }

        .ticket-preview {
            color: #64748b;
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 15px;
        }

        .ticket-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .ticket-actions {
            display: flex;
            gap: 10px;
        }

        .action-icon {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }

        .action-icon:hover {
            background: #f1f5f9;
        }

        .star-icon {
            color: #fbbf24;
        }

        .trash-icon {
            color: #ef4444;
        }

        .empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
        }

        .empty-state i {
            font-size: 64px;
            color: #cbd5e1;
            margin-bottom: 20px;
        }

        .empty-state p {
            font-size: 16px;
            color: #64748b;
            font-weight: 500;
        }

        @media (max-width: 768px) {
            .tickets-grid {
                grid-template-columns: 1fr;
            }

            .search-section {
                flex-direction: column;
            }

            .bulk-actions {
                width: 100%;
                flex-wrap: wrap;
            }

            .support-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
}
</style>
</head>
<body class="assistant-dashboard-page">
<?php include '../assistant_view/includes/assistant_sidebar.php'; ?>

<div class="assistant-main-content">
    <header class="support-header">
        <div class="assistant-header-left">
            <h1 class="support-welcome">Support Tickets</h1>
            <p class="support-subtitle">Manage and respond to support inquiries</p>
        </div>
        <div class="assistant-header-right">
            <button class="compose-btn" onclick="openCompose()">
                <i class="fas fa-plus"></i> Compose
            </button>
</div>
    </header>

    <div class="support-container">
        <div style="display: grid; grid-template-columns: 250px 1fr; gap: 20px;">
            <!-- Folder Sidebar -->
            <div class="folder-sidebar">
                <ul class="folder-list">
                    <li class="folder-item <?=($folder==='inbox')?'active':'';?>" onclick="location.href='support.php?folder=inbox'">
                        <i class="fas fa-inbox"></i>
                        <span>Inbox</span>
                        <span class="folder-count"><?= $unreadCount ?></span>
            </li>
                    <li class="folder-item <?=($folder==='sent')?'active':'';?>" onclick="location.href='support.php?folder=sent'">
                        <i class="fas fa-paper-plane"></i>
                        <span>Sent</span>
                        <span class="folder-count"><?= $sentCount ?></span>
            </li>
                    <li class="folder-item <?=($folder==='starred')?'active':'';?>" onclick="location.href='support.php?folder=starred'">
                        <i class="fas fa-star"></i>
                        <span>Starred</span>
                        <span class="folder-count"><?= $starredCount ?></span>
            </li>
                    <li class="folder-item <?=($folder==='trash')?'active':'';?>" onclick="location.href='support.php?folder=trash'">
                        <i class="fas fa-trash"></i>
                        <span>Trash</span>
                        <span class="folder-count"><?= $trashCount ?></span>
            </li>
        </ul>
            </div>

            <!-- Main Content -->
            <div>
                <!-- Search and Actions -->
                <div class="search-section">
                    <div class="search-input-wrapper">
                        <i class="fas fa-search"></i>
                        <input type="text" class="search-input" id="searchInput" placeholder="Search by user or subject..." value="<?= htmlspecialchars($search) ?>">
                    </div>
            <div class="bulk-actions">
                        <button class="btn-secondary" onclick="bulkMarkRead()">
                            <i class="fas fa-check"></i> Mark Read
                        </button>
                        <button class="btn-secondary" onclick="bulkMarkUnread()">
                            <i class="fas fa-times"></i> Mark Unread
                        </button>
                        <button class="btn-secondary" onclick="bulkDelete()">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                </div>

                <!-- Tickets Grid -->
                <div class="tickets-grid">
                    <?php if (count($tickets) === 0): ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>No messages found in <?= htmlspecialchars(ucfirst($folder)) ?>.</p>
                </div>
            <?php else: ?>
                <?php foreach ($tickets as $ticket):
                    $ticketId = $folder==='sent' ? $ticket['ticket_id'] : $ticket['id'];
                    $isRead = ($folder !== 'sent') ? ($ticket['is_read'] ? true : false) : true;
                            $cardClass = $isRead ? 'ticket-card' : 'ticket-card unread';
                    $sender = htmlspecialchars($ticket['first_name'].' '.$ticket['last_name']);
                    $subject = htmlspecialchars($ticket['subject']);
                            $snippet = htmlspecialchars(mb_strimwidth($ticket['message'], 0, 100, '...'));
                    $created = date("M d, Y h:i A", strtotime($ticket['created_at']));
                    $starred = ($ticket['is_starred'] ?? 0) ? true : false;
                ?>
                <div class="<?= $cardClass ?>" onclick="openTicket(<?= $ticketId ?>)" data-id="<?= $ticketId ?>">
                            <div class="ticket-header">
                                <div class="ticket-sender"><?= $sender ?></div>
                                <div class="ticket-date"><?= $created ?></div>
                            </div>
                            <div class="ticket-subject"><?= $subject ?></div>
                            <div class="ticket-preview"><?= $snippet ?></div>
                            <div class="ticket-footer">
                                <div class="ticket-actions">
                                    <div class="action-icon star-icon" onclick="toggleStar(event, this)" data-id="<?= $ticketId ?>">
                                        <i class="<?= $starred ? 'fas fa-star' : 'far fa-star' ?>"></i>
                        </div>
                                <?php if ($folder !== 'trash'): ?>
                                        <div class="action-icon trash-icon" onclick="deleteMessage(event, <?= $ticketId ?>)" title="Move to Trash">
                                            <i class="fas fa-trash"></i>
                                        </div>
                                <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
</div>
    </div>
    </div>
</div>

<!-- Compose window -->
<div class="compose-window" id="composeWindow" style="display:none; position:fixed; right:22px; bottom:22px; width:420px; background:#fff; border-radius:12px; box-shadow:0 18px 60px rgba(0,0,0,0.12); z-index:2500; overflow:hidden;">
    <div class="compose-header" style="background:linear-gradient(90deg,#3b82f6,#2563eb); color:#fff; padding:12px 14px; font-weight:700;">
        New Message
    </div>
    <div class="compose-body" style="padding:14px; display:flex; flex-direction:column; gap:8px;">
        <label style="font-weight:700; font-size:13px;">To:</label>
        <input type="text" id="composeTo" list="userEmails" placeholder="Enter user email">
        <datalist id="userEmails">
            <?php foreach ($allUsers as $userEmail): ?>
                <option value="<?= htmlspecialchars($userEmail) ?>">
            <?php endforeach; ?>
        </datalist>

        <label style="font-weight:700; font-size:13px;">From:</label>
        <input type="text" value="<?= htmlspecialchars($email) ?>" disabled>

        <label style="font-weight:700; font-size:13px;">Subject:</label>
        <input type="text" id="composeSubject" placeholder="Enter subject">

        <label style="font-weight:700; font-size:13px;">Message:</label>
        <textarea id="composeMessage" rows="5" placeholder="Write your message..."></textarea>

        <div style="display:flex; gap:8px; justify-content:flex-end; margin-top:6px;">
            <button class="btn-secondary" onclick="closeCompose()">Close</button>
            <button class="compose-btn" onclick="sendMessage()">Send</button>
        </div>
    </div>
</div>

<script>
let openedMessage = null;

function openTicket(ticketId) {
    window.location.href = 'view_ticket.php?id=' + ticketId;
}

function openCompose() {
    document.getElementById("composeWindow").style.display = "block";
}

function closeCompose() {
    document.getElementById("composeWindow").style.display = "none";
}

function sendMessage() {
    let to = document.getElementById("composeTo").value.trim();
    let sub = document.getElementById("composeSubject").value.trim();
    let msg = document.getElementById("composeMessage").value.trim();
    if (!to || !sub || !msg) return alert("All fields required");
    fetch("new_ticket.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "to=" + encodeURIComponent(to) + "&subject=" + encodeURIComponent(sub) + "&message=" + encodeURIComponent(msg)
    })
    .then(() => {
        alert("Message sent!");
        closeCompose();
        location.reload();
    });
}

function toggleStar(event, el) {
    event.stopPropagation();
    let ticketId = el.getAttribute("data-id");
    fetch("toggle_star.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "ticket_id=" + encodeURIComponent(ticketId)
    })
    .then(() => {
        let icon = el.querySelector('i');
        if (icon.classList.contains("fas")) {
            icon.classList.remove("fas", "fa-star");
            icon.classList.add("far", "fa-star");
        } else {
            icon.classList.remove("far", "fa-star");
            icon.classList.add("fas", "fa-star");
        }
    });
}

function bulkMarkRead() {
    let ids = getSelectedTickets();
    if (!ids.length) return alert("No messages selected");
    fetch("bulk_action.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "action=mark_read&ids=" + ids.join(",")
    })
    .then(() => location.reload());
}

function bulkMarkUnread() {
    let ids = getSelectedTickets();
    if (!ids.length) return alert("No messages selected");
    fetch("bulk_action.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "action=mark_unread&ids=" + ids.join(",")
    })
    .then(() => location.reload());
}

function bulkDelete() {
    let ids = getSelectedTickets();
    if (!ids.length) return alert("No messages selected");
    if (!confirm("Move selected to trash?")) return;
    fetch("bulk_action.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "action=delete&ids=" + ids.join(",")
    })
    .then(() => location.reload());
}

function deleteMessage(e, ticketId) {
    e.stopPropagation();
    if (!confirm("Move to trash?")) return;
    fetch("bulk_action.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "action=delete&ids=" + ticketId
    })
    .then(() => location.reload());
}

function getSelectedTickets() {
    return Array.from(document.querySelectorAll(".msg-checkbox:checked")).map(cb => cb.value);
}

// Live search
document.getElementById("searchInput").addEventListener("keyup", function() {
    let q = this.value.trim();
    if (q.length > 0) {
        window.location.href = 'support.php?folder=<?=$folder?>&search=' + encodeURIComponent(q);
    } else {
        window.location.href = 'support.php?folder=<?=$folder?>';
    }
});
</script>

</body>
</html>
