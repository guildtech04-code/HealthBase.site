<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

$user_role = $_SESSION['role'] ?? '';
if (!in_array($user_role, ['assistant', 'admin'])) {
    header('Location: ../dashboard/healthbase_dashboard.php');
    exit();
}

$user_query = $conn->prepare("SELECT username, email, role FROM users WHERE id = ?");
$user_query->bind_param("i", $_SESSION['user_id']);
$user_query->execute();
$user_result = $user_query->get_result()->fetch_assoc();

$sidebar_user_data = [
    'username' => htmlspecialchars($user_result['username']),
    'email' => htmlspecialchars($user_result['email']),
    'role' => htmlspecialchars($user_result['role'])
];

$allowed_doctor_specs = ['Dermatology', 'Gastroenterology', 'Orthopaedic', 'Orthopaedic Surgery'];

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'create') {
            // Create new user
            $email = trim($_POST['email']);
            $username = trim($_POST['username']);
            $role = $_POST['role'];
            $password = $_POST['password'];
            
            // Check if email already exists
            $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $check->bind_param("s", $email);
            $check->execute();
            if ($check->get_result()->num_rows > 0) {
                $message = 'Email already exists';
                $messageType = 'error';
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $first_name = $username;
                $last_name = $role === 'doctor' ? 'Doctor' : 'User';
                if ($role === 'doctor') {
                    $spec = $_POST['specialization'] ?? '';
                    if (!in_array($spec, $allowed_doctor_specs, true)) {
                        $spec = $allowed_doctor_specs[0];
                    }
                    $insert = $conn->prepare("INSERT INTO users (email, username, password, role, status, first_name, last_name, gender, specialization) VALUES (?, ?, ?, ?, 'active', ?, ?, 'Male', ?)");
                    $insert->bind_param("sssssss", $email, $username, $hashedPassword, $role, $first_name, $last_name, $spec);
                } else {
                    $insert = $conn->prepare("INSERT INTO users (email, username, password, role, status, first_name, last_name, gender, specialization) VALUES (?, ?, ?, ?, 'active', ?, ?, 'Male', NULL)");
                    $insert->bind_param("ssssss", $email, $username, $hashedPassword, $role, $first_name, $last_name);
                }
                if ($insert->execute()) {
                    $message = 'User created successfully';
                    $messageType = 'success';
                } else {
                    $message = 'Error creating user';
                    $messageType = 'error';
                }
            }
        } elseif ($_POST['action'] === 'edit') {
            // Update user
            $user_id = intval($_POST['user_id']);
            $email = trim($_POST['email']);
            $username = trim($_POST['username']);
            $role = $_POST['role'];
            $status = $_POST['status'] ?? 'active';
            if (!in_array($status, ['active', 'inactive'], true)) {
                $status = 'active';
            }

            $doctor_spec = null;
            if ($role === 'doctor') {
                $spec = $_POST['specialization'] ?? '';
                $doctor_spec = in_array($spec, $allowed_doctor_specs, true) ? $spec : $allowed_doctor_specs[0];
            }

            if (!empty($_POST['new_password'])) {
                $hashedPassword = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                if ($role === 'doctor') {
                    $update = $conn->prepare("UPDATE users SET email = ?, username = ?, role = ?, status = ?, specialization = ?, password = ? WHERE id = ?");
                    $update->bind_param("ssssssi", $email, $username, $role, $status, $doctor_spec, $hashedPassword, $user_id);
                } else {
                    $update = $conn->prepare("UPDATE users SET email = ?, username = ?, role = ?, status = ?, specialization = NULL, password = ? WHERE id = ?");
                    $update->bind_param("sssssi", $email, $username, $role, $status, $hashedPassword, $user_id);
                }
            } else {
                if ($role === 'doctor') {
                    $update = $conn->prepare("UPDATE users SET email = ?, username = ?, role = ?, status = ?, specialization = ? WHERE id = ?");
                    $update->bind_param("sssssi", $email, $username, $role, $status, $doctor_spec, $user_id);
                } else {
                    $update = $conn->prepare("UPDATE users SET email = ?, username = ?, role = ?, status = ?, specialization = NULL WHERE id = ?");
                    $update->bind_param("ssssi", $email, $username, $role, $status, $user_id);
                }
            }

            if ($update->execute()) {
                $message = 'User updated successfully';
                $messageType = 'success';
            } else {
                $message = 'Error updating user';
                $messageType = 'error';
            }
        } elseif ($_POST['action'] === 'delete') {
            // Delete user
            $user_id = intval($_POST['user_id']);
            
            // Prevent deleting yourself
            if ($user_id === $_SESSION['user_id']) {
                $message = 'You cannot delete your own account';
                $messageType = 'error';
            } else {
                $delete = $conn->prepare("DELETE FROM users WHERE id = ?");
                $delete->bind_param("i", $user_id);
                
                if ($delete->execute()) {
                    $message = 'User deleted successfully';
                    $messageType = 'success';
                } else {
                    $message = 'Error deleting user. User may have associated records.';
                    $messageType = 'error';
                }
            }
        }
    }
}

// Get all users
$users_result = $conn->query("
    SELECT id, username, email, role, status, specialization, created_at
    FROM users
    ORDER BY created_at DESC
");
$users = [];
while ($row = $users_result->fetch_assoc()) {
    $users[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - HealthBase</title>
    <link rel="icon" type="image/x-icon" href="/assets/icons/favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/assistant.css">
    <style>
        .message-alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .message-alert.success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #10b981;
        }

        .message-alert.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #ef4444;
        }

        .table-wrapper {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: #f8fafc;
        }

        th {
            padding: 15px 20px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            color: #64748b;
            border-bottom: 1px solid #e2e8f0;
        }

        td {
            padding: 15px 20px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            font-size: 14px;
        }

        tr:last-child td {
            border-bottom: none;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            text-transform: capitalize;
        }

        .status-badge.active {
            background: #d1fae5;
            color: #065f46;
        }

        .status-badge.inactive {
            background: #fee2e2;
            color: #991b1b;
        }

        .specialization-badge {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            background: #e0f2fe;
            color: #0369a1;
        }

        .spec-placeholder {
            color: #94a3b8;
            font-size: 13px;
        }

        .role-badge {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .role-admin {
            background: #fef3c7;
            color: #92400e;
        }

        .role-doctor {
            background: #dbeafe;
            color: #1e40af;
        }

        .role-assistant {
            background: #e0e7ff;
            color: #3730a3;
        }

        .role-user {
            background: #f0fdf4;
            color: #166534;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-icon {
            padding: 8px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-icon.edit {
            background: #3b82f6;
            color: white;
        }

        .btn-icon.edit:hover {
            background: #2563eb;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        }

        .btn-icon.delete {
            background: #ef4444;
            color: white;
        }

        .btn-icon.delete:hover {
            background: #dc2626;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
        }

        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            padding: 30px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 20px;
            font-weight: 600;
            color: #1e293b;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #64748b;
            transition: color 0.3s;
        }

        .close-modal:hover {
            color: #1e293b;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #334155;
            font-size: 14px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 1.5px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
            font-family: 'Inter', sans-serif;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-group input:disabled {
            background: #f8fafc;
            cursor: not-allowed;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 25px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            flex: 1;
            transition: all 0.3s;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(59, 130, 246, 0.4);
        }

        .btn-cancel {
            background: white;
            color: #64748b;
            border: 1.5px solid #e2e8f0;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-cancel:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }

        .add-user-btn {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            margin-bottom: 20px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .add-user-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.4);
        }
    </style>
</head>
<body class="assistant-dashboard-page">
<?php include 'includes/assistant_sidebar.php'; ?>

<div class="assistant-main-content">
    <header class="assistant-header">
        <div class="assistant-header-left">
            <h1 class="assistant-welcome">User Management</h1>
            <p class="assistant-subtitle">View and manage all system users</p>
        </div>
        <div class="assistant-header-right">
            <div class="current-time" style="display: flex; align-items: center; gap: 8px; padding: 8px 16px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
                <i class="fas fa-clock" style="color: #3b82f6;"></i>
                <span id="currentDateTime" style="color: #1e293b; font-weight: 600; font-size: 14px;"></span>
            </div>
        </div>
    </header>

    <div class="assistant-dashboard-content">
        <?php if ($message): ?>
            <div class="message-alert <?= $messageType ?>">
                <i class="fas <?= $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
                <?= $message ?>
            </div>
        <?php endif; ?>

        <button class="add-user-btn" onclick="openCreateModal()">
            <i class="fas fa-user-plus"></i> Add New User
        </button>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Specialization</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td>
                            <span class="role-badge role-<?= strtolower($user['role']) ?>">
                                <?php echo $user['role'] === 'user' ? 'Patient' : ucfirst($user['role']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($user['role'] === 'doctor'): ?>
                                <span class="specialization-badge"><?php echo htmlspecialchars($user['specialization'] ?? '—'); ?></span>
                            <?php else: ?>
                                <span class="spec-placeholder">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                        <td>
                            <div class="action-buttons">
                                <button type="button" class="btn-icon edit" onclick="openEditModal(<?= (int) $user['id'] ?>, <?= htmlspecialchars(json_encode($user['username']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($user['email']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($user['role']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($user['status']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($user['specialization'] ?? ''), ENT_QUOTES, 'UTF-8') ?>)">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                <button class="btn-icon delete" onclick="openDeleteModal(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>')">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create User Modal -->
<div class="modal-overlay" id="createModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-user-plus"></i> Create New User</h3>
            <button class="close-modal" onclick="closeModal('createModal')">&times;</button>
        </div>
        <form method="POST" id="createForm">
            <input type="hidden" name="action" value="create">
            <div class="form-group">
                <label for="create_email">Email <span style="color: red;">*</span></label>
                <input type="email" id="create_email" name="email" required>
            </div>
            <div class="form-group">
                <label for="create_username">Username <span style="color: red;">*</span></label>
                <input type="text" id="create_username" name="username" required>
            </div>
            <div class="form-group">
                <label for="create_role">Role <span style="color: red;">*</span></label>
                <select id="create_role" name="role" required onchange="toggleCreateDoctorSpec()">
                    <option value="">Select Role</option>
                    <option value="user">Patient</option>
                    <option value="doctor">Doctor</option>
                    <option value="assistant">Assistant</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <div class="form-group" id="create_specialization_wrap" style="display: none;">
                <label for="create_specialization">Specialization <span style="color: red;">*</span></label>
                <select id="create_specialization" name="specialization">
                    <option value="Dermatology">Dermatology</option>
                    <option value="Gastroenterology">Gastroenterology</option>
                    <option value="Orthopaedic">Orthopaedic</option>
                    <option value="Orthopaedic Surgery">Orthopaedic Surgery</option>
                </select>
            </div>
            <div class="form-group">
                <label for="create_password">Password <span style="color: red;">*</span></label>
                <input type="password" id="create_password" name="password" required minlength="6">
            </div>
            <div class="form-actions">
                <button type="button" class="btn-cancel" onclick="closeModal('createModal')">Cancel</button>
                <button type="submit" class="btn-primary">Create User</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal-overlay" id="editModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Edit User</h3>
            <button class="close-modal" onclick="closeModal('editModal')">&times;</button>
        </div>
        <form method="POST" id="editForm">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" id="edit_user_id" name="user_id">
            <div class="form-group">
                <label for="edit_email">Email <span style="color: red;">*</span></label>
                <input type="email" id="edit_email" name="email" required>
            </div>
            <div class="form-group">
                <label for="edit_username">Username <span style="color: red;">*</span></label>
                <input type="text" id="edit_username" name="username" required>
            </div>
            <div class="form-group">
                <label for="edit_role">Role <span style="color: red;">*</span></label>
                <select id="edit_role" name="role" required onchange="toggleEditDoctorSpec()">
                    <option value="user">Patient</option>
                    <option value="doctor">Doctor</option>
                    <option value="assistant">Assistant</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <input type="hidden" id="edit_status" name="status" value="active">
            <div class="form-group" id="edit_specialization_wrap" style="display: none;">
                <label for="edit_specialization">Specialization <span style="color: red;">*</span></label>
                <select id="edit_specialization" name="specialization">
                    <option value="Dermatology">Dermatology</option>
                    <option value="Gastroenterology">Gastroenterology</option>
                    <option value="Orthopaedic">Orthopaedic</option>
                    <option value="Orthopaedic Surgery">Orthopaedic Surgery</option>
                </select>
            </div>
            <div class="form-group">
                <label for="edit_password">New Password (leave blank to keep current)</label>
                <input type="password" id="edit_password" name="new_password" minlength="6">
                <small style="color: #64748b; font-size: 12px;">Leave blank to keep existing password</small>
            </div>
            <div class="form-actions">
                <button type="button" class="btn-cancel" onclick="closeModal('editModal')">Cancel</button>
                <button type="submit" class="btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete User Modal -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-exclamation-triangle" style="color: #ef4444;"></i> Confirm Deletion</h3>
            <button class="close-modal" onclick="closeModal('deleteModal')">&times;</button>
        </div>
        <form method="POST" id="deleteForm">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" id="delete_user_id" name="user_id">
            <div class="form-group">
                <p style="color: #64748b; font-size: 14px; line-height: 1.6;">
                    Are you sure you want to delete user <strong id="delete_username_display" style="color: #1e293b;"></strong>?
                </p>
                <p style="color: #ef4444; font-size: 13px; margin-top: 10px; padding: 10px; background: #fef2f2; border-radius: 6px; border-left: 3px solid #ef4444;">
                    <i class="fas fa-exclamation-triangle"></i> This action cannot be undone. All user data will be permanently removed.
                </p>
            </div>
            <div class="form-actions">
                <button type="button" class="btn-cancel" onclick="closeModal('deleteModal')" style="flex: 1;">Cancel</button>
                <button type="submit" class="btn-primary" style="flex: 1; background: linear-gradient(135deg, #ef4444, #dc2626);">Delete User</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleCreateDoctorSpec() {
    const role = document.getElementById('create_role').value;
    const wrap = document.getElementById('create_specialization_wrap');
    const sel = document.getElementById('create_specialization');
    if (!wrap) return;
    wrap.style.display = role === 'doctor' ? 'block' : 'none';
    if (sel) sel.required = role === 'doctor';
}

function toggleEditDoctorSpec() {
    const role = document.getElementById('edit_role').value;
    const wrap = document.getElementById('edit_specialization_wrap');
    const sel = document.getElementById('edit_specialization');
    if (!wrap) return;
    wrap.style.display = role === 'doctor' ? 'block' : 'none';
    if (sel) sel.required = role === 'doctor';
}

function openCreateModal() {
    document.getElementById('createModal').style.display = 'flex';
    toggleCreateDoctorSpec();
}

function openEditModal(userId, username, email, role, status, specialization) {
    document.getElementById('edit_user_id').value = userId;
    document.getElementById('edit_username').value = username;
    document.getElementById('edit_email').value = email;
    document.getElementById('edit_role').value = role;
    document.getElementById('edit_status').value = status;
    const specSel = document.getElementById('edit_specialization');
    const allowed = ['Dermatology', 'Gastroenterology', 'Orthopaedic', 'Orthopaedic Surgery'];
    let spec = specialization || '';
    if (allowed.indexOf(spec) === -1) {
        if (/orthop/i.test(spec)) spec = 'Orthopaedic Surgery';
        else if (/gastro/i.test(spec)) spec = 'Gastroenterology';
        else if (/derm/i.test(spec)) spec = 'Dermatology';
        else spec = 'Dermatology';
    }
    if (specSel) specSel.value = spec;
    toggleEditDoctorSpec();
    document.getElementById('editModal').style.display = 'flex';
}

function openDeleteModal(userId, username) {
    document.getElementById('delete_user_id').value = userId;
    document.getElementById('delete_username_display').textContent = username;
    document.getElementById('deleteModal').style.display = 'flex';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
    if (modalId === 'createModal') {
        document.getElementById('createForm').reset();
    } else if (modalId === 'editModal') {
        document.getElementById('editForm').reset();
    } else if (modalId === 'deleteModal') {
        document.getElementById('deleteForm').reset();
    }
}

// Close modal when clicking outside
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.style.display = 'none';
    }
});

// Current Date/Time for Assistant Header
function updateDateTime() {
    const element = document.getElementById('currentDateTime');
    if (!element) return;
    
    const now = new Date();
    const options = { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric',
        hour: '2-digit', 
        minute: '2-digit',
        second: '2-digit'
    };
    element.textContent = now.toLocaleDateString('en-US', options);
}
updateDateTime();
setInterval(updateDateTime, 1000);
</script>

</body>
</html>
