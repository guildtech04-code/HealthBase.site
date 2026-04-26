<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include("../config/db_connect.php");

// Ensure logged-in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$ticket_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch ticket (only if it belongs to this user)
$stmt = $conn->prepare("
    SELECT id, subject, message, status, created_at
    FROM support_tickets
    WHERE id = ? AND user_id = ?
");
$stmt->bind_param("ii", $ticket_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("❌ Ticket not found or you don't have access.");
}

$ticket = $result->fetch_assoc();

// Handle reply submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_message'])) {
    $reply_message = trim($_POST['reply_message']);
    if ($reply_message !== '') {
        $insert = $conn->prepare("INSERT INTO ticket_replies (ticket_id, message, user_id, created_at) VALUES (?, ?, ?, NOW())");
        $insert->bind_param("isi", $ticket_id, $reply_message, $user_id);
        $insert->execute();
        // Optionally update ticket status to 'open'
        $update = $conn->prepare("UPDATE support_tickets SET status = 'open' WHERE id = ?");
        $update->bind_param("i", $ticket_id);
        $update->execute();
        header("Location: my_ticket.php?id=" . $ticket_id);
        exit();
    }
}

// Fetch replies
$replies = $conn->prepare("
    SELECT r.message, r.created_at, u.username AS responder
    FROM ticket_replies r
    LEFT JOIN users u ON r.user_id = u.id OR r.assistant_id = u.id
    WHERE r.ticket_id = ?
    ORDER BY r.created_at ASC
");
$replies->bind_param("i", $ticket_id);
$replies->execute();
$reply_result = $replies->get_result();

// Fetch logged-in user info for header/profile
$query = $conn->prepare("SELECT username, email, role FROM users WHERE id = ?");
$query->bind_param("i", $user_id);
$query->execute();
$user_data = $query->get_result()->fetch_assoc();
$username = htmlspecialchars($user_data['username']);
$email = htmlspecialchars($user_data['email']);
$role = $user_data['role'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Ticket #<?php echo $ticket['id']; ?></title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; background-color: #f4f6f8; }
        header { background-color: #00695c; color: white; padding: 20px 40px; font-size: 1.5rem; font-weight: bold; display: flex; justify-content: space-between; align-items: center; }
        .header-right { display: flex; align-items: center; gap: 20px; }
        .notification { position: relative; font-size: 1.5rem; cursor: pointer; }
        #notifDropdown { display: none; position: absolute; top: 50px; right: 60px; background: white; color: black; padding: 10px; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.2); min-width: 350px; z-index: 1000; }
        #notifDropdown strong { display: block; margin-bottom: 10px; }
        #notifList { list-style: none; padding: 0; margin: 0; max-height: 200px; overflow-y: auto; }
        #notifList li { padding: 8px; border-bottom: 1px solid #ddd; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; }
        #notifList li:last-child { border-bottom: none; }
        #notifList small { margin-left: auto; font-size: 0.75rem; color: #888; }
        #toggleView { display: block; text-align: center; padding: 6px; font-size: 0.85rem; color: #00695c; cursor: pointer; border-top: 1px solid #ddd; background: #f9f9f9; }
        #toggleView:hover { background: #eee; }
        .profile { position: relative; font-size: 1.5rem; cursor: pointer; }
        .profile-info { position: absolute; top: 40px; right: 0; background: white; color: black; padding: 10px 15px; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.2); display: none; white-space: nowrap; font-size: 0.9rem; }
        .profile:hover .profile-info { display: block; }
        .container { display: flex; }
        nav { width: 80px; background-color: #004d40; min-height: 100vh; display: flex; flex-direction: column; align-items: center; padding-top: 20px; }
        nav a { color: white; text-decoration: none; margin: 20px 0; font-size: 1.2rem; position: relative; }
        nav a::after { content: attr(data-tooltip); position: absolute; left: 100%; margin-left: 10px; background: #333; color: #fff; padding: 5px 10px; border-radius: 5px; opacity: 0; white-space: nowrap; transition: opacity 0.2s; }
        nav a:hover::after { opacity: 1; }
        main { flex: 1; padding: 30px; }
        .ticket-container { background: white; padding: 20px; border-radius: 10px; max-width: 800px; margin: auto; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        h2 { margin-top: 0; }
        .ticket-info { margin-bottom: 20px; }
        .reply { border-top: 1px solid #ddd; padding: 10px 0; }
        .reply strong { color: #00695c; }
        .reply small { color: #777; font-size: 0.85em; }
        .back-btn { display: inline-block; margin-top: 20px; padding: 10px 15px; background: #00695c; color: white; text-decoration: none; border-radius: 6px; }
        .back-btn:hover { background: #004d40; }
        .reply-form textarea { width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #ccc; margin-bottom: 10px; }
        .reply-form button { padding: 10px 15px; background: #00695c; color: white; border: none; border-radius: 6px; cursor: pointer; }
        .reply-form button:hover { background: #004d40; }
    </style>
</head>
<body>
<header>
    <div>Dr. Leelin Medical Clinic - HealthBase Dashboard</div>
    <div class="header-right">
        <div class="notification" id="notifIcon">🔔</div>
        <div id="notifDropdown">
            <strong>Notifications</strong>
            <ul id="notifList"><li>Loading...</li></ul>
            <span id="toggleView">See All</span>
        </div>
        <div class="profile">👤
            <div class="profile-info">
                <strong><?php echo $username; ?></strong><br>
                <?php echo $email; ?>
            </div>
        </div>
    </div>
</header>
<div class="container">
    <nav>
        <a href="healthbase_dashboard.php" data-tooltip="Dashboard">🏠</a>
        <a href="scheduling.php" data-tooltip="Scheduling">📅</a>
        <a href="#" data-tooltip="Return Visit Risk">📊</a>
        <a href="#" data-tooltip="Reports">📁</a>
        <a href="contact_support.php" data-tooltip="Contact Support">🎧</a>
        <a href="logout.php" id="logoutBtn" data-tooltip="Logout">🚪</a>
    </nav>
    <main>
        <div class="ticket-container">
            <h2>🎫 Ticket #<?php echo $ticket['id']; ?></h2>
            <div class="ticket-info">
                <p><strong>Subject:</strong> <?php echo htmlspecialchars($ticket['subject']); ?></p>
                <p><strong>Message:</strong> <?php echo nl2br(htmlspecialchars($ticket['message'])); ?></p>
                <p><strong>Status:</strong> <?php echo ucfirst($ticket['status']); ?></p>
                <p><small>Created at: <?php echo $ticket['created_at']; ?></small></p>
            </div>

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
            <form method="POST" class="reply-form">
                <textarea name="reply_message" rows="4" placeholder="Type your reply here..." required></textarea>
                <button type="submit">Send Reply</button>
            </form>
        </div>
    </main>
</div>

<script>
    const notifIcon = document.getElementById("notifIcon");
    const notifDropdown = document.getElementById("notifDropdown");
    const notifList = document.getElementById("notifList");
    const toggleView = document.getElementById("toggleView");

    notifIcon.addEventListener("click", () => {
        notifDropdown.style.display = notifDropdown.style.display === "block" ? "none" : "block";
    });

    let expanded = false;
    toggleView.addEventListener("click", () => {
        expanded = !expanded;
        notifList.style.maxHeight = expanded ? "500px" : "200px";
        toggleView.textContent = expanded ? "See Less" : "See All";
    });

    let unreadCount = 0;
    function fetchNotifications() {
        fetch("fetch_notifications.php")
            .then(res => res.json())
            .then(data => {
                notifList.innerHTML = "";
                unreadCount = 0;
                if (data.length === 0) {
                    notifList.innerHTML = "<li>No new notifications</li>";
                } else {
                    data.forEach(notif => {
                        let li = document.createElement("li");
                        let messageHtml = notif.ticket_id
                            ? `<a href="my_ticket.php?id=${notif.ticket_id}">${notif.message}</a>`
                            : notif.message;
                        li.innerHTML = `
                            <input type="checkbox" class="markRead" data-id="${notif.id}" ${notif.is_read == 1 ? "checked" : ""}>
                            <span style="${notif.is_read == 0 ? 'font-weight:bold;' : 'color:gray;'}">${messageHtml}</span>
                            <small>${new Date(notif.created_at).toLocaleString()}</small>
                        `;
                        notifList.appendChild(li);
                        if (notif.is_read == 0) unreadCount++;
                    });
                }
                notifIcon.innerHTML = `🔔 ${unreadCount > 0 ? "<span style='color:red;font-size:0.8rem;'>(" + unreadCount + ")</span>" : ""}`;
                document.querySelectorAll(".markRead").forEach(checkbox => {
                    checkbox.addEventListener("change", function () {
                        fetch("mark_notification.php", {
                            method: "POST",
                            headers: { "Content-Type": "application/x-www-form-urlencoded" },
                            body: "id=" + this.dataset.id
                        }).then(() => fetchNotifications());
                    });
                });
            });
    }
    setInterval(fetchNotifications, 5000);
    fetchNotifications();

    document.getElementById("logoutBtn").addEventListener("click", function (e) {
        e.preventDefault();
        if (confirm("Are you sure you want to log out?")) window.location.href = "logout.php";
    });
</script>
</body>
</html>
