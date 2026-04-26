<?php
session_start();
require '../config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
	header("Location: ../auth/login.php");
	exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'user';

// Fetch basic user info
$user_stmt = $conn->prepare("SELECT username, email, role, specialization FROM users WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();
$username = htmlspecialchars($user['username'] ?? 'User');
$email = htmlspecialchars($user['email'] ?? '');
$role = $user['role'] ?? $role;
$specialization = htmlspecialchars($user['specialization'] ?? 'General');

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$subject = trim($_POST['subject'] ?? '');
	$message = trim($_POST['message'] ?? '');

	if ($role === 'assistant') {
		// Assistants can open a ticket on behalf of a recipient by email
		$to_email = trim($_POST['to'] ?? '');
		if ($to_email === '') {
			$error_message = 'Recipient email is required.';
		} elseif ($subject === '' || $message === '') {
			$error_message = 'Subject and message are required.';
		} else {
			// Find recipient user by email
			$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
			$stmt->bind_param("s", $to_email);
			$stmt->execute();
			$recipient = $stmt->get_result()->fetch_assoc();
			if (!$recipient) {
				$error_message = 'Recipient not found.';
			} else {
				$recipient_id = (int)$recipient['id'];
				// Create ticket
				$stmt = $conn->prepare("INSERT INTO support_tickets (user_id, subject, message, status, is_read, is_deleted, is_starred, created_at) VALUES (?, ?, ?, 'open', 0, 0, 0, NOW())");
				$stmt->bind_param("iss", $recipient_id, $subject, $message);
				if ($stmt->execute()) {
					$ticket_id = $stmt->insert_id;
					// Notification for recipient
					$notif_msg = "📩 You received a new support message: " . $subject;
					$notif_link = "view_ticket.php?id=" . $ticket_id;
					$stmt = $conn->prepare("INSERT INTO notifications (user_id, message, link, type, is_read, created_at) VALUES (?, ?, ?, 'support', 0, NOW())");
					$stmt->bind_param("iss", $recipient_id, $notif_msg, $notif_link);
					$stmt->execute();
					$success_message = 'Message sent and ticket created for recipient.';
				} else {
					$error_message = 'Failed to create ticket.';
				}
			}
		}
	} else {
		// Doctors and users can create their own ticket
		if ($subject === '' || $message === '') {
			$error_message = 'Subject and message are required.';
		} else {
			$stmt = $conn->prepare("INSERT INTO support_tickets (user_id, subject, message, status, is_read, is_deleted, is_starred, created_at) VALUES (?, ?, ?, 'open', 0, 0, 0, NOW())");
			$stmt->bind_param("iss", $user_id, $subject, $message);
			if ($stmt->execute()) {
				$success_message = 'Your support ticket has been created.';
			} else {
				$error_message = 'Failed to create your ticket.';
			}
		}
	}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>New Support Ticket - HealthBase</title>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
	<link rel="stylesheet" href="../css/dashboard.css">
	<link rel="stylesheet" href="my_tickets.css">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
	<style>
		.new-ticket-container { max-width: 900px; margin: 0 auto; }
		.form-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; box-shadow: 0 4px 20px rgba(0,0,0,0.06); }
		.form-row { display: flex; gap: 16px; margin-bottom: 16px; }
		.form-row .field { flex: 1; display: flex; flex-direction: column; }
		.field label { font-size: 14px; color: #475569; margin-bottom: 6px; font-weight: 600; }
		.field input, .field textarea { border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px 12px; font-size: 14px; }
		.field textarea { min-height: 160px; resize: vertical; }
		.actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 10px; }
		.btn { border: none; border-radius: 8px; padding: 10px 16px; cursor: pointer; font-weight: 600; }
		.btn-primary { background: #3b82f6; color: white; }
		.btn-secondary { background: #e2e8f0; color: #334155; }
		.alert { padding: 12px 14px; border-radius: 8px; margin-bottom: 16px; }
		.alert-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
		.alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
	</style>
</head>
<body class="dashboard-page">
	<?php 
	// Sidebar per role
	$sidebar_user_data = [
		'username' => $username,
		'email' => $email,
		'role' => $role,
		'specialization' => $specialization
	];
	if ($role === 'doctor') {
		include '../includes/doctor_sidebar.php';
		echo '<div class="doctor-sidebar-backdrop"></div>';
	} else {
		include '../includes/sidebar.php';
	}
	?>

	<header class="main-header">
		<div class="header-left">
			<a href="my_tickets.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
			<div>
				<h1 class="header-title">New Support Ticket</h1>
				<p class="header-subtitle">Reach out to support for assistance</p>
			</div>
		</div>
	</header>

	<div class="main-content">
		<div class="new-ticket-container">
			<div class="form-card">
				<?php if ($success_message): ?>
					<div class="alert alert-success"><?php echo $success_message; ?></div>
				<?php endif; ?>
				<?php if ($error_message): ?>
					<div class="alert alert-error"><?php echo $error_message; ?></div>
				<?php endif; ?>

				<form method="POST" action="">
					<?php if ($role === 'assistant'): ?>
					<div class="form-row">
						<div class="field">
							<label for="to">To (Email)</label>
							<input type="email" id="to" name="to" placeholder="recipient@example.com" required />
						</div>
					</div>
					<?php endif; ?>

					<div class="form-row">
						<div class="field">
							<label for="subject">Subject</label>
							<input type="text" id="subject" name="subject" placeholder="Brief summary" required />
						</div>
					</div>

					<div class="form-row">
						<div class="field">
							<label for="message">Message</label>
							<textarea id="message" name="message" placeholder="Describe your issue" required></textarea>
						</div>
					</div>

					<div class="actions">
						<button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Submit Ticket</button>
					</div>
				</form>
			</div>
		</div>
	</div>

	<?php if ($role === 'doctor'): ?>
	<script src="../js/doctor_sidebar.js"></script>
	<?php endif; ?>
</body>
</html>
