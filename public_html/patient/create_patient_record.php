<?php
// create_patient_record.php - Create patient record for users who don't have one
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/auth_guard.php';
ensure_role(['user']);
include("../config/db_connect.php");
require_once __DIR__ . '/../includes/security.php';

// user ensured by ensure_role()

$user_id = $_SESSION['user_id'];

// Check if user already has a patient record
$check_query = $conn->prepare("SELECT id FROM patients WHERE user_id = ?");
$check_query->bind_param("i", $user_id);
$check_query->execute();
$check_result = $check_query->get_result();

if ($check_result->num_rows > 0) {
    // User already has a patient record, redirect to dashboard
    header("Location: patient_dashboard.php");
    exit();
}

// Check if date_of_birth and phone columns exist in users table
$check_cols = $conn->query("
    SELECT COLUMN_NAME 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'users' 
    AND COLUMN_NAME IN ('date_of_birth', 'phone')
");
$existing_user_cols = [];
while ($row = $check_cols->fetch_assoc()) {
    $existing_user_cols[] = $row['COLUMN_NAME'];
}

// Build query with only existing columns
$base_columns = "username, email, first_name, last_name, gender";
$new_columns = "";
if (in_array('date_of_birth', $existing_user_cols)) {
    $new_columns .= ", date_of_birth";
}
if (in_array('phone', $existing_user_cols)) {
    $new_columns .= ", phone";
}

// Get user data including date_of_birth and phone from registration (if columns exist)
$user_query = $conn->prepare("SELECT $base_columns $new_columns FROM users WHERE id = ?");
$user_query->bind_param("i", $user_id);
$user_query->execute();
$user_result = $user_query->get_result();
$user_data = $user_result->fetch_assoc();

if (!$user_data) {
    die("User not found!");
}

// Calculate age from date_of_birth if available
$calculated_age = null;
if (isset($user_data['date_of_birth']) && !empty($user_data['date_of_birth'])) {
    try {
        $birthDate = new DateTime($user_data['date_of_birth']);
        $today = new DateTime();
        $calculated_age = $today->diff($birthDate)->y;
    } catch (Exception $e) {
        $calculated_age = null;
    }
}

// Ensure we have default values if data is missing
if (empty($user_data['first_name'])) $user_data['first_name'] = '';
if (empty($user_data['last_name'])) $user_data['last_name'] = '';
if (empty($user_data['gender'])) $user_data['gender'] = 'Male';
if (!isset($user_data['date_of_birth'])) $user_data['date_of_birth'] = '';
if (!isset($user_data['phone'])) $user_data['phone'] = '';

// Handle form submission
if ($_POST) {
    require_post_csrf();
    $first_name = sanitize_string($_POST['first_name'] ?? $user_data['first_name'], 100);
    $last_name = sanitize_string($_POST['last_name'] ?? $user_data['last_name'], 100);
    $date_of_birth = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : ($user_data['date_of_birth'] ?? null);
    $phone = !empty($_POST['phone']) ? sanitize_string($_POST['phone'], 20) : ($user_data['phone'] ?? null);
    
    // Calculate age from date_of_birth
    $age = null;
    if ($date_of_birth) {
        $birthDate = new DateTime($date_of_birth);
        $today = new DateTime();
        $age = $today->diff($birthDate)->y;
        if ($age < 1 || $age > 120) { 
            $age = null; 
        }
    }
    
    // Fallback to manual age input if date_of_birth not provided or invalid
    if ($age === null && !empty($_POST['age'])) {
        $age = (int)$_POST['age'];
        if ($age < 1 || $age > 120) { $age = 25; }
    } elseif ($age === null) {
        $age = 25; // Default fallback
    }
    
    $gender = $_POST['gender'] ?? $user_data['gender'];
    if (!in_array($gender, ['Male','Female'], true)) { $gender = $user_data['gender']; }
    $health_concern = sanitize_string($_POST['health_concern'] ?? 'General Consultation', 255);
    
    // Check for duplicate patient name (same user_id + first_name + last_name combination)
    $duplicate_check = $conn->prepare("SELECT id FROM patients WHERE user_id = ? AND first_name = ? AND last_name = ?");
    $duplicate_check->bind_param("iss", $user_id, $first_name, $last_name);
    $duplicate_check->execute();
    $duplicate_result = $duplicate_check->get_result();
    
    if ($duplicate_result->num_rows > 0) {
        $error = "A patient record with the name '$first_name $last_name' already exists. Please use a different name or contact support if you need to update your existing record.";
    } else {
        // Check if date_of_birth and phone columns exist in patients table
    $check_cols = $conn->query("
        SELECT COLUMN_NAME 
        FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'patients' 
        AND COLUMN_NAME IN ('date_of_birth', 'phone')
    ");
    $existing_cols = [];
    while ($row = $check_cols->fetch_assoc()) {
        $existing_cols[] = $row['COLUMN_NAME'];
    }
    
    $has_dob = in_array('date_of_birth', $existing_cols);
    $has_phone = in_array('phone', $existing_cols);
    
    // Create patient record with available columns
    if ($has_dob && $has_phone) {
        $insert_query = $conn->prepare("
            INSERT INTO patients (user_id, first_name, last_name, date_of_birth, age, gender, phone, health_concern) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $insert_query->bind_param("isssisss", $user_id, $first_name, $last_name, $date_of_birth, $age, $gender, $phone, $health_concern);
    } elseif ($has_dob) {
        $insert_query = $conn->prepare("
            INSERT INTO patients (user_id, first_name, last_name, date_of_birth, age, gender, health_concern) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $insert_query->bind_param("isssiss", $user_id, $first_name, $last_name, $date_of_birth, $age, $gender, $health_concern);
    } elseif ($has_phone) {
        $insert_query = $conn->prepare("
            INSERT INTO patients (user_id, first_name, last_name, age, gender, phone, health_concern) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $insert_query->bind_param("ississs", $user_id, $first_name, $last_name, $age, $gender, $phone, $health_concern);
    } else {
        $insert_query = $conn->prepare("
            INSERT INTO patients (user_id, first_name, last_name, age, gender, health_concern) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $insert_query->bind_param("ississ", $user_id, $first_name, $last_name, $age, $gender, $health_concern);
        }
        
        if ($insert_query->execute()) {
            header("Location: patient_dashboard.php");
            exit();
        } else {
            // Check if error is due to duplicate name constraint
            $error_code = $conn->errno;
            if ($error_code == 1062) { // MySQL duplicate entry error code
                $error = "A patient record with the name '$first_name $last_name' already exists. Please use a different name or contact support if you need to update your existing record.";
            } else {
                $error = "Failed to create patient record: " . htmlspecialchars($conn->error);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Patient Record - HealthBase</title>
    <link rel="icon" type="image/x-icon" href="/assets/icons/favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../patient/css/patient_dashboard.css">
    <style>
        body.patient-dashboard-page {
            background: #f4f6f8;
            min-height: 100vh;
            padding: 20px;
            margin: 0;
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="patient-dashboard-page" style="background: #f4f6f8; min-height: 100vh; padding: 20px; margin: 0;">
    <div style="max-width: 600px; margin: 50px auto; padding: 30px; background: white; border-radius: 16px; box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);">
        <div style="text-align: center; margin-bottom: 30px;">
            <i class="fas fa-user-check" style="font-size: 48px; color: #0ea5e9; margin-bottom: 20px;"></i>
            <h1 style="color: #1e293b; font-family: 'Inter', sans-serif; margin: 0;">Verify Your Information</h1>
            <p style="color: #64748b; font-family: 'Inter', sans-serif;">Please review and confirm your registration information</p>
            <div style="background: #dbeafe; border-left: 4px solid #3b82f6; padding: 12px 15px; border-radius: 8px; margin-top: 15px; text-align: left; display: inline-block; max-width: 100%;">
                <i class="fas fa-info-circle" style="color: #3b82f6; margin-right: 8px;"></i>
                <span style="color: #1e40af; font-size: 14px;">Your information has been pre-filled from your registration. Please verify and make any necessary corrections.</span>
            </div>
            
            <!-- Display loaded registration data for verification -->
            <div style="background: #f0fdf4; border: 1px solid #86efac; padding: 15px; border-radius: 8px; margin-top: 15px; font-size: 13px; color: #166534;">
                <strong><i class="fas fa-check-circle" style="color: #16a34a;"></i> Registration Data Loaded from Your Account:</strong>
                <div style="margin-top: 8px; display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px;">
                    <div><strong>Name:</strong> <?php echo htmlspecialchars(trim(($user_data['first_name'] ?? '') . ' ' . ($user_data['last_name'] ?? ''))); ?></div>
                    <div><strong>Email:</strong> <?php echo htmlspecialchars($user_data['email'] ?? ''); ?></div>
                    <div><strong>Gender:</strong> <?php echo htmlspecialchars($user_data['gender'] ?? 'Not set'); ?></div>
                    <div><strong>Date of Birth:</strong> <?php echo !empty($user_data['date_of_birth']) ? htmlspecialchars($user_data['date_of_birth']) : '<span style="color: #dc2626;">Not provided during registration</span>'; ?></div>
                    <div><strong>Phone:</strong> <?php echo !empty($user_data['phone']) ? htmlspecialchars($user_data['phone']) : '<span style="color: #dc2626;">Not provided during registration</span>'; ?></div>
                </div>
                <?php if (empty($user_data['date_of_birth']) || empty($user_data['phone'])): ?>
                <div style="margin-top: 10px; padding: 10px; background: #fef3c7; border-radius: 6px; color: #92400e; font-size: 12px;">
                    <i class="fas fa-exclamation-triangle"></i> <strong>Note:</strong> Some information is missing. Please fill in the required fields below.
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if (isset($error)): ?>
            <div style="background: #fee2e2; color: #dc2626; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #fecaca;">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" style="display: flex; flex-direction: column; gap: 20px;">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
            <div>
                <label style="display: block; margin-bottom: 8px; color: #374151; font-weight: 500; font-family: 'Inter', sans-serif;">
                    <i class="fas fa-user" style="color: #3b82f6; margin-right: 5px;"></i> First Name
                </label>
                <input type="text" name="first_name" value="<?php echo isset($user_data['first_name']) && !empty($user_data['first_name']) ? htmlspecialchars($user_data['first_name']) : ''; ?>" required
                       style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 8px; font-family: 'Inter', sans-serif; font-size: 14px; background: #f9fafb;">
            </div>
            
            <div>
                <label style="display: block; margin-bottom: 8px; color: #374151; font-weight: 500; font-family: 'Inter', sans-serif;">
                    <i class="fas fa-user" style="color: #3b82f6; margin-right: 5px;"></i> Last Name
                </label>
                <input type="text" name="last_name" value="<?php echo isset($user_data['last_name']) && !empty($user_data['last_name']) ? htmlspecialchars($user_data['last_name']) : ''; ?>" required
                       style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 8px; font-family: 'Inter', sans-serif; font-size: 14px; background: #f9fafb;">
            </div>
            
            <div>
                <label style="display: block; margin-bottom: 8px; color: #374151; font-weight: 500; font-family: 'Inter', sans-serif;">
                    <i class="fas fa-calendar-alt" style="color: #3b82f6; margin-right: 5px;"></i> Date of Birth
                </label>
                <input type="date" name="date_of_birth" id="date_of_birth" value="<?php echo isset($user_data['date_of_birth']) && !empty($user_data['date_of_birth']) ? htmlspecialchars($user_data['date_of_birth']) : ''; ?>" max="<?= date('Y-m-d') ?>" required
                       style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 8px; font-family: 'Inter', sans-serif; font-size: 14px; background: #f9fafb;">
                <small style="color: #64748b; font-size: 12px; margin-top: 5px; display: block;">
                    <i class="fas fa-info-circle"></i> Age will be automatically calculated from your date of birth.
                    <?php if (!empty($user_data['date_of_birth'])): ?>
                    <span style="display:block;margin-top:4px;">Using the birthday from your registration — change here if it was entered incorrectly.</span>
                    <?php endif; ?>
                </small>
            </div>
            
            <div>
                <label style="display: block; margin-bottom: 8px; color: #374151; font-weight: 500; font-family: 'Inter', sans-serif;">
                    <i class="fas fa-birthday-cake" style="color: #3b82f6; margin-right: 5px;"></i> Age (Auto-calculated)
                </label>
                <input type="number" name="age" id="calculated_age" value="<?php echo $calculated_age ?? ''; ?>" readonly
                       style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 8px; font-family: 'Inter', sans-serif; font-size: 14px; background: #e5e7eb; cursor: not-allowed; color: #6b7280;" min="1" max="120">
            </div>
            
            <div>
                <label style="display: block; margin-bottom: 8px; color: #374151; font-weight: 500; font-family: 'Inter', sans-serif;">
                    <i class="fas fa-phone" style="color: #3b82f6; margin-right: 5px;"></i> Phone Number
                </label>
                <input type="tel" name="phone" value="<?php echo isset($user_data['phone']) && !empty($user_data['phone']) ? htmlspecialchars($user_data['phone']) : ''; ?>" required
                       style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 8px; font-family: 'Inter', sans-serif; font-size: 14px; background: #f9fafb;">
            </div>
            
            <div>
                <label style="display: block; margin-bottom: 8px; color: #374151; font-weight: 500; font-family: 'Inter', sans-serif;">
                    <i class="fas fa-venus-mars" style="color: #3b82f6; margin-right: 5px;"></i> Gender
                </label>
                <select name="gender" required style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 8px; font-family: 'Inter', sans-serif; font-size: 14px; background: #f9fafb;">
                    <option value="Male" <?php echo ($user_data['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                    <option value="Female" <?php echo ($user_data['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                </select>
            </div>
            
            <div>
                <label style="display: block; margin-bottom: 8px; color: #374151; font-weight: 500; font-family: 'Inter', sans-serif;">Health Concern</label>
                <select name="health_concern" required style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 8px; font-family: 'Inter', sans-serif; font-size: 14px;">
                    <option value="General Consultation">General Consultation</option>
                    <option value="CONSULTATION : INTERNAL MEDICINE - GASTROENTEROLOGY - Stomach Ulcer">Gastroenterology - Stomach Ulcer</option>
                    <option value="CONSULTATION : DERMATOLOGY - Acne">Dermatology - Acne</option>
                    <option value="CONSULTATION : ORTHOPEDIC SURGERY - Arthritis">Orthopedic Surgery - Arthritis</option>
                    <option value="CONSULTATION : CARDIOLOGY - Heart Check">Cardiology - Heart Check</option>
                    <option value="CONSULTATION : NEUROLOGY - Headache">Neurology - Headache</option>
                </select>
            </div>
            
            <button type="submit" style="background: linear-gradient(135deg, #0ea5e9, #0284c7); color: white; padding: 15px; border: none; border-radius: 8px; font-family: 'Inter', sans-serif; font-size: 16px; font-weight: 600; cursor: pointer; transition: transform 0.2s ease; margin-top: 10px;">
                <i class="fas fa-check-circle"></i> Confirm & Create Patient Record
            </button>
        </form>
        
        <div style="text-align: center; margin-top: 20px;">
            <a href="../auth/logout.php" style="color: #64748b; text-decoration: none; font-family: 'Inter', sans-serif;">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>
    
    <script>
        // Age calculation from date of birth
        function calculateAge() {
            const dobInput = document.getElementById('date_of_birth');
            const ageInput = document.getElementById('calculated_age');
            
            if (dobInput && ageInput && dobInput.value) {
                const birthDate = new Date(dobInput.value);
                const today = new Date();
                let age = today.getFullYear() - birthDate.getFullYear();
                const monthDiff = today.getMonth() - birthDate.getMonth();
                
                if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                    age--;
                }
                
                if (age >= 1 && age <= 120) {
                    ageInput.value = age;
                } else {
                    ageInput.value = '';
                }
            }
        }
        
        // Initialize age calculation on page load
        window.addEventListener('DOMContentLoaded', function() {
            const dobInput = document.getElementById('date_of_birth');
            if (dobInput) {
                // Calculate age on page load if date_of_birth is already set
                if (dobInput.value) {
                    calculateAge();
                }
                dobInput.addEventListener('change', calculateAge);
                dobInput.addEventListener('input', calculateAge);
            }
        });
    </script>
    <script src="js/patient_sidebar.js"></script>
</body>
</html>
