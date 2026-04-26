<?php
require_once __DIR__ . '/../includes/auth_guard.php';
ensure_role(['user']);
require '../config/db_connect.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/patient_profile_extra.php';
require_once __DIR__ . '/../includes/patient_profile_photo.php';
hb_ensure_patient_profile_extra_table($conn);
hb_ensure_user_profile_photo_column($conn);
$has_user_profile_photo_col = hb_user_profile_photo_column_exists($conn);

$user_id = $_SESSION['user_id'];

function patient_age_from_birthday(string $dob): ?int
{
    try {
        $birth = new DateTime($dob);
        $today = new DateTime('today');
        if ($birth > $today) {
            return null;
        }
        $y = $today->diff($birth)->y;
        return ($y >= 1 && $y <= 120) ? $y : null;
    } catch (Exception $e) {
        return null;
    }
}

$col_dob = $conn->query("
    SELECT COUNT(*) AS c FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'patients' AND COLUMN_NAME = 'date_of_birth'
");
$has_patient_dob = $col_dob && (int) ($col_dob->fetch_assoc()['c'] ?? 0) > 0;

// Fetch current patient data
$user_sql = $has_user_profile_photo_col
    ? 'SELECT id, username, email, profile_photo FROM users WHERE id = ?'
    : 'SELECT id, username, email FROM users WHERE id = ?';
$user_query = $conn->prepare($user_sql);
$user_query->bind_param("i", $user_id);
$user_query->execute();
$user_result = $user_query->get_result();
$user_data = $user_result->fetch_assoc();

if ($has_patient_dob) {
    $patient_query = $conn->prepare("SELECT id, first_name, last_name, age, date_of_birth, gender, health_concern FROM patients WHERE user_id = ?");
} else {
    $patient_query = $conn->prepare("SELECT id, first_name, last_name, age, gender, health_concern FROM patients WHERE user_id = ?");
}
$patient_query->bind_param("i", $user_id);
$patient_query->execute();
$patient_result = $patient_query->get_result();
$patient_data = $patient_result->fetch_assoc();

if (!$patient_data) {
    header("Location: create_patient_record.php");
    exit();
}

$patient_pk = (int) $patient_data['id'];
$extra_table = hb_patient_profile_extra_table_exists($conn);
$ppe = $extra_table ? hb_get_patient_profile_extra($conn, $patient_pk) : hb_patient_profile_extra_defaults();

// Handle form submission
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();

    if ($has_user_profile_photo_col) {
        if (!empty($_POST['remove_profile_photo'])) {
            hb_clear_user_profile_photo($conn, $user_id);
            $user_data['profile_photo'] = null;
        } elseif (!empty($_FILES['profile_photo']['tmp_name'])) {
            $up = hb_save_user_profile_photo_upload($conn, $user_id, $_FILES['profile_photo']);
            if ($up['error'] !== '') {
                $error = $up['error'];
            } elseif ($up['path'] !== null) {
                $user_data['profile_photo'] = $up['path'];
            }
        }
    }

    $first_name = sanitize_string(trim($_POST['first_name'] ?? ''), 100);
    $last_name = sanitize_string(trim($_POST['last_name'] ?? ''), 100);
    $gender = $_POST['gender'] ?? 'Male';
    $health_concern = sanitize_string(trim($_POST['health_concern'] ?? ''), 255);

    $age = null;
    $date_of_birth = null;

    if ($has_patient_dob) {
        $date_of_birth = trim($_POST['date_of_birth'] ?? '');
        if ($date_of_birth === '') {
            $error = 'Birthday is required.';
        } elseif (strtotime($date_of_birth) === false || strtotime($date_of_birth) > strtotime('today')) {
            $error = 'Please enter a valid birthday (not in the future).';
        } else {
            $age = patient_age_from_birthday($date_of_birth);
            if ($age === null) {
                $error = 'Age from birthday must be between 1 and 120.';
            }
        }
    } else {
        $age = (int) ($_POST['age'] ?? 0);
        if ($age < 1 || $age > 120) {
            $error = 'Age must be between 1 and 120.';
        }
    }

    if ($error === '' && !in_array($gender, ['Male', 'Female'], true)) {
        $error = 'Invalid gender selection.';
    }
    if ($error === '' && (empty($first_name) || empty($last_name))) {
        $error = 'Name fields cannot be empty.';
    }

    if ($error === '') {
        if ($has_patient_dob) {
            $update_stmt = $conn->prepare('UPDATE patients SET first_name = ?, last_name = ?, date_of_birth = ?, age = ?, gender = ?, health_concern = ? WHERE user_id = ?');
            $update_stmt->bind_param('sssissi', $first_name, $last_name, $date_of_birth, $age, $gender, $health_concern, $user_id);
        } else {
            $update_stmt = $conn->prepare('UPDATE patients SET first_name = ?, last_name = ?, age = ?, gender = ?, health_concern = ? WHERE user_id = ?');
            $update_stmt->bind_param('ssisss', $first_name, $last_name, $age, $gender, $health_concern, $user_id);
        }

        if ($update_stmt->execute()) {
            if ($extra_table) {
                hb_save_patient_profile_extra($conn, $patient_pk, $_POST, function (string $str, int $max): string {
                    return sanitize_string($str, $max);
                });
                $ppe = hb_get_patient_profile_extra($conn, $patient_pk);
            }
            $success = 'Profile updated successfully!';
            $patient_data['first_name'] = $first_name;
            $patient_data['last_name'] = $last_name;
            $patient_data['age'] = $age;
            $patient_data['gender'] = $gender;
            $patient_data['health_concern'] = $health_concern;
            if ($has_patient_dob) {
                $patient_data['date_of_birth'] = $date_of_birth;
            }
            $_SESSION['patient_display_name'] = trim($first_name . ' ' . $last_name);
        } else {
            $error = 'Failed to update profile: ' . $conn->error;
        }
    }
}

$display_age = null;
if ($has_patient_dob && !empty($patient_data['date_of_birth'])) {
    $display_age = patient_age_from_birthday($patient_data['date_of_birth']);
}
if ($display_age === null && isset($patient_data['age'])) {
    $display_age = (int) $patient_data['age'];
}

$dob_value = '';
if ($has_patient_dob && !empty($patient_data['date_of_birth'])) {
    $dob_value = (string) $patient_data['date_of_birth'];
    if (strlen($dob_value) > 10) {
        $dob_value = substr($dob_value, 0, 10);
    }
}

$sidebar_user_data = [
    'display_name' => trim(($patient_data['first_name'] ?? '') . ' ' . ($patient_data['last_name'] ?? '')),
    'username' => $user_data['username'] ?? '',
    'email' => $user_data['email'] ?? '',
    'role' => 'user',
];

$profile_photo_preview_url = $has_user_profile_photo_col
    ? hb_user_profile_photo_public_url($user_data['profile_photo'] ?? null)
    : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Profile - HealthBase</title>
    <link rel="icon" type="image/x-icon" href="../assets/icons/favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="css/patient_dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        .edit-profile-container {
            max-width: 720px;
            margin: 30px auto;
            padding: 30px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #374151;
            font-weight: 500;
            font-family: 'Inter', sans-serif;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
        }
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            resize: vertical;
            min-height: 80px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #0ea5e9, #0284c7);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-family: 'Inter', sans-serif;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
        }
        .btn-secondary {
            background: #6b7280;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
            margin-top: 10px;
        }
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid;
        }
        .alert-success {
            background: #dcfce7;
            color: #166534;
            border-color: #bbf7d0;
        }
        .alert-error {
            background: #fee2e2;
            color: #dc2626;
            border-color: #fecaca;
        }
        .age-counter-field {
            background: #f3f4f6 !important;
            cursor: not-allowed;
            color: #374151;
        }
        .form-hint {
            display: block;
            margin-top: 6px;
            font-size: 12px;
            color: #64748b;
        }
        .profile-photo-row {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-start;
            gap: 16px;
        }
        .profile-photo-preview {
            width: 96px;
            height: 96px;
            border-radius: 50%;
            background: #f1f5f9;
            border: 2px solid #e2e8f0;
            overflow: hidden;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            font-weight: 600;
            color: #94a3b8;
        }
        .profile-photo-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .profile-photo-actions input[type="file"] {
            max-width: 100%;
        }
        .profile-photo-remove {
            margin-top: 8px;
        }

        /* Tabbed layout — one panel visible at a time */
        .hb-edit-shell {
            margin-top: 8px;
        }
        .hb-edit-tabs {
            display: flex;
            gap: 0;
            border-radius: 12px;
            padding: 4px;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            margin-bottom: 20px;
        }
        .hb-edit-tabs button {
            flex: 1;
            border: none;
            background: transparent;
            padding: 12px 14px;
            font-size: 14px;
            font-weight: 600;
            font-family: 'Inter', 'Poppins', sans-serif;
            color: #64748b;
            border-radius: 10px;
            cursor: pointer;
            transition: background 0.2s, color 0.2s, box-shadow 0.2s;
        }
        .hb-edit-tabs button:hover {
            color: #0f172a;
            background: rgba(255, 255, 255, 0.6);
        }
        .hb-edit-tabs button[aria-selected="true"] {
            background: #fff;
            color: #0284c7;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
        }
        .hb-edit-tabs button:focus-visible {
            outline: 2px solid #0ea5e9;
            outline-offset: 2px;
        }
        .hb-edit-panel {
            display: none;
            animation: hbEditFade 0.22s ease;
        }
        .hb-edit-panel.is-active {
            display: block;
        }
        @keyframes hbEditFade {
            from { opacity: 0.85; }
            to { opacity: 1; }
        }
        .hb-edit-panel-hint {
            font-size: 13px;
            color: #64748b;
            margin: -8px 0 18px 0;
            line-height: 1.45;
        }
        .edit-profile-actions {
            margin-top: 28px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
        }
    </style>
</head>
<body class="patient-dashboard-page">
    <?php include 'includes/patient_sidebar.php'; ?>
    
    <div class="patient-main-content">
        <header class="patient-header">
            <div class="patient-header-left">
                <h1 class="patient-welcome">Edit Patient Profile</h1>
                <p class="patient-subtitle">Update your medical information</p>
            </div>
        </header>

        <div class="edit-profile-container">
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" id="editProfileForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">

                <div class="hb-edit-shell">
                    <div class="hb-edit-tabs" role="tablist" aria-label="Profile sections">
                        <button type="button" role="tab" id="hbTabCore" aria-controls="hbEditPanelCore" aria-selected="true" data-hb-tab="core">
                            <i class="fas fa-user-circle" aria-hidden="true"></i> Core profile
                        </button>
                        <button type="button" role="tab" id="hbTabExtra" aria-controls="hbEditPanelExtra" aria-selected="false" data-hb-tab="extra">
                            <i class="fas fa-layer-group" aria-hidden="true"></i> Additional details
                        </button>
                    </div>

                    <div id="hbEditPanelCore" class="hb-edit-panel is-active" role="tabpanel" aria-labelledby="hbTabCore" data-hb-panel="core" aria-hidden="false">
                        <p class="hb-edit-panel-hint">Photo, legal name, demographics, and primary reason for care. Required fields are marked with *.</p>

                <?php if ($has_user_profile_photo_col): ?>
                <div class="form-group">
                    <label>Profile photo</label>
                    <div class="profile-photo-row">
                        <div class="profile-photo-preview" id="profilePhotoPreview">
                            <?php if ($profile_photo_preview_url): ?>
                                <img src="<?php echo htmlspecialchars($profile_photo_preview_url, ENT_QUOTES, 'UTF-8'); ?>" alt="" width="96" height="96">
                            <?php else: ?>
                                <?php
                                $fn_prev = trim((string) ($patient_data['first_name'] ?? ''));
                                $ln_prev = trim((string) ($patient_data['last_name'] ?? ''));
                                if ($fn_prev !== '' && $ln_prev !== '') {
                                    $prev_ini = strtoupper(substr($fn_prev, 0, 1) . substr($ln_prev, 0, 1));
                                } elseif ($fn_prev !== '') {
                                    $prev_ini = strtoupper(substr($fn_prev, 0, 1));
                                } else {
                                    $prev_ini = '?';
                                }
                                ?>
                                <span aria-hidden="true"><?php echo htmlspecialchars($prev_ini, ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="profile-photo-actions">
                            <input type="file" name="profile_photo" id="profile_photo" accept="image/jpeg,image/png,image/webp,image/gif">
                            <small class="form-hint">JPEG, PNG, WebP, or GIF. Max 2 MB. Shown in the sidebar.</small>
                            <?php if ($profile_photo_preview_url): ?>
                            <div class="profile-photo-remove">
                                <label style="font-weight: 400; cursor: pointer;">
                                    <input type="checkbox" name="remove_profile_photo" value="1"> Remove current photo
                                </label>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label>First Name *</label>
                    <input type="text" name="first_name" value="<?php echo htmlspecialchars($patient_data['first_name'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label>Last Name *</label>
                    <input type="text" name="last_name" value="<?php echo htmlspecialchars($patient_data['last_name'] ?? ''); ?>" required>
                </div>

                <?php if ($has_patient_dob): ?>
                <div class="form-group">
                    <label>Birthday *</label>
                    <input type="date" name="date_of_birth" id="edit_profile_dob" value="<?php echo htmlspecialchars($dob_value); ?>" max="<?php echo date('Y-m-d'); ?>" required>
                    <small class="form-hint"><i class="fas fa-info-circle"></i> Age is calculated automatically from your birthday.</small>
                </div>
                <div class="form-group">
                    <label>Age (years)</label>
                    <input type="text" class="age-counter-field" id="edit_profile_age_counter" value="<?php echo $display_age !== null ? (int) $display_age : ''; ?>" readonly tabindex="-1" aria-live="polite" placeholder="—">
                    <small class="form-hint">This value updates when you change your birthday.</small>
                </div>
                <?php else: ?>
                <div class="form-group">
                    <label>Age *</label>
                    <input type="number" name="age" value="<?php echo htmlspecialchars((string) ($patient_data['age'] ?? 25)); ?>" min="1" max="120" required>
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label>Gender *</label>
                    <select name="gender" required>
                        <option value="Male" <?php echo ($patient_data['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo ($patient_data['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Health Concern *</label>
                    <select name="health_concern" required>
                        <option value="General Consultation" <?php echo (strpos($patient_data['health_concern'] ?? '', 'General') !== false) ? 'selected' : ''; ?>>General Consultation</option>
                        <option value="CONSULTATION : INTERNAL MEDICINE - GASTROENTEROLOGY - Stomach Ulcer" <?php echo (strpos($patient_data['health_concern'] ?? '', 'GASTROENTEROLOGY') !== false) ? 'selected' : ''; ?>>Gastroenterology - Stomach Ulcer</option>
                        <option value="CONSULTATION : DERMATOLOGY - Acne" <?php echo (strpos($patient_data['health_concern'] ?? '', 'DERMATOLOGY') !== false) ? 'selected' : ''; ?>>Dermatology - Acne</option>
                        <option value="CONSULTATION : ORTHOPEDIC SURGERY - Arthritis" <?php echo (strpos($patient_data['health_concern'] ?? '', 'ORTHOPEDIC') !== false) ? 'selected' : ''; ?>>Orthopedic Surgery - Arthritis</option>
                        <option value="CONSULTATION : CARDIOLOGY - Heart Check" <?php echo (strpos($patient_data['health_concern'] ?? '', 'CARDIOLOGY') !== false) ? 'selected' : ''; ?>>Cardiology - Heart Check</option>
                        <option value="CONSULTATION : NEUROLOGY - Headache" <?php echo (strpos($patient_data['health_concern'] ?? '', 'NEUROLOGY') !== false) ? 'selected' : ''; ?>>Neurology - Headache</option>
                    </select>
                </div>
                    </div>

                    <div id="hbEditPanelExtra" class="hb-edit-panel" role="tabpanel" aria-labelledby="hbTabExtra" data-hb-panel="extra" aria-hidden="true">
                        <p class="hb-edit-panel-hint">Optional intake-style fields: emergency contact, physicians, employment, insurance, and consent. All fields remain part of the same save.</p>
                <?php
                $table_exists = $extra_table;
                $ppe_hide_outer_heading = true;
                include __DIR__ . '/partials/patient_extended_profile_fields.php';
                ?>
                    </div>
                </div>

                <div class="edit-profile-actions">
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i> Save Changes
                </button>
                
                <a href="patient_dashboard.php" class="btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                </div>
            </form>
        </div>
    </div>

    <script src="js/patient_sidebar.js"></script>
    <script>
    (function () {
        var tabs = document.querySelectorAll('#editProfileForm [data-hb-tab]');
        var panels = document.querySelectorAll('#editProfileForm [data-hb-panel]');
        if (!tabs.length || !panels.length) return;
        function show(tab) {
            tabs.forEach(function (btn) {
                var on = btn.getAttribute('data-hb-tab') === tab;
                btn.setAttribute('aria-selected', on ? 'true' : 'false');
            });
            panels.forEach(function (panel) {
                var on = panel.getAttribute('data-hb-panel') === tab;
                panel.classList.toggle('is-active', on);
                panel.setAttribute('aria-hidden', on ? 'false' : 'true');
            });
        }
        tabs.forEach(function (btn) {
            btn.addEventListener('click', function () {
                show(btn.getAttribute('data-hb-tab'));
            });
        });
    })();
    </script>
    <?php if ($has_user_profile_photo_col): ?>
    <script>
    (function () {
        var inp = document.getElementById('profile_photo');
        var prev = document.getElementById('profilePhotoPreview');
        var removeCb = document.querySelector('input[name="remove_profile_photo"]');
        if (!inp || !prev) return;
        inp.addEventListener('change', function () {
            var f = this.files && this.files[0];
            if (removeCb) removeCb.checked = false;
            if (!f) return;
            var r = new FileReader();
            r.onload = function () {
                prev.innerHTML = '<img src="' + r.result.replace(/"/g, '&quot;') + '" alt="" width="96" height="96">';
            };
            r.readAsDataURL(f);
        });
    })();
    </script>
    <?php endif; ?>
    <?php if ($has_patient_dob): ?>
    <script>
    (function () {
        function ageFromDob(ymd) {
            if (!ymd) return '';
            var parts = ymd.split('-');
            if (parts.length !== 3) return '';
            var y = parseInt(parts[0], 10), m = parseInt(parts[1], 10) - 1, d = parseInt(parts[2], 10);
            var birth = new Date(y, m, d);
            var today = new Date();
            today.setHours(0, 0, 0, 0);
            birth.setHours(0, 0, 0, 0);
            if (birth > today) return '';
            var age = today.getFullYear() - birth.getFullYear();
            var mo = today.getMonth() - birth.getMonth();
            if (mo < 0 || (mo === 0 && today.getDate() < birth.getDate())) age--;
            if (age >= 1 && age <= 120) return String(age);
            return '';
        }
        var dob = document.getElementById('edit_profile_dob');
        var out = document.getElementById('edit_profile_age_counter');
        function refresh() {
            if (dob && out) out.value = ageFromDob(dob.value);
        }
        if (dob) {
            dob.addEventListener('change', refresh);
            dob.addEventListener('input', refresh);
            refresh();
        }
    })();
    </script>
    <?php endif; ?>
</body>
</html>

