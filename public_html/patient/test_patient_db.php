<?php
// test_patient_db.php - Test database connection and patient data
session_start();
include("../config/db_connect.php");

if (!isset($_SESSION['user_id'])) {
    echo "No user session found. Please login first.";
    exit();
}

$user_id = $_SESSION['user_id'];
echo "<h2>Database Test for User ID: $user_id</h2>";

// Test 1: Check user data
echo "<h3>1. User Data:</h3>";
$user_query = $conn->prepare("SELECT id, username, email, role, first_name, last_name FROM users WHERE id = ?");
$user_query->bind_param("i", $user_id);
$user_query->execute();
$user_result = $user_query->get_result();
$user_data = $user_result->fetch_assoc();

if ($user_data) {
    echo "<pre>";
    print_r($user_data);
    echo "</pre>";
} else {
    echo "No user data found!";
}

// Test 2: Check patient data
echo "<h3>2. Patient Data:</h3>";
$patient_query = $conn->prepare("SELECT id, user_id, first_name, last_name, age, gender, health_concern FROM patients WHERE user_id = ?");
$patient_query->bind_param("i", $user_id);
$patient_query->execute();
$patient_result = $patient_query->get_result();
$patient_data = $patient_result->fetch_assoc();

if ($patient_data) {
    echo "<pre>";
    print_r($patient_data);
    echo "</pre>";
    
    $patient_id = $patient_data['id'];
    
    // Test 3: Check appointments
    echo "<h3>3. Appointments for Patient ID: $patient_id</h3>";
    $appointments_query = $conn->prepare("
        SELECT a.id, a.appointment_date, a.status,
               CONCAT(u.first_name, ' ', u.last_name) AS doctor_name, u.specialization
        FROM appointments a
        JOIN users u ON a.doctor_id = u.id
        WHERE a.patient_id = ?
        ORDER BY a.appointment_date DESC
        LIMIT 5
    ");
    $appointments_query->bind_param("i", $patient_id);
    $appointments_query->execute();
    $appointments_result = $appointments_query->get_result();
    
    if ($appointments_result->num_rows > 0) {
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Date</th><th>Status</th><th>Doctor</th><th>Specialization</th></tr>";
        while ($row = $appointments_result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . $row['appointment_date'] . "</td>";
            echo "<td>" . $row['status'] . "</td>";
            echo "<td>" . $row['doctor_name'] . "</td>";
            echo "<td>" . $row['specialization'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "No appointments found for this patient.";
    }
    
} else {
    echo "No patient record found for this user!";
    echo "<p>This user needs to have a patient record in the patients table.</p>";
}

// Test 4: Show all patients
echo "<h3>4. All Patients in Database:</h3>";
$all_patients_query = $conn->query("SELECT id, user_id, first_name, last_name FROM patients LIMIT 10");
if ($all_patients_query && $all_patients_query->num_rows > 0) {
    echo "<table border='1'>";
    echo "<tr><th>Patient ID</th><th>User ID</th><th>Name</th></tr>";
    while ($row = $all_patients_query->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['user_id'] . "</td>";
        echo "<td>" . $row['first_name'] . " " . $row['last_name'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No patients found in database.";
}

// Test 5: Show all users
echo "<h3>5. All Users in Database:</h3>";
$all_users_query = $conn->query("SELECT id, username, email, role, first_name, last_name FROM users LIMIT 10");
if ($all_users_query && $all_users_query->num_rows > 0) {
    echo "<table border='1'>";
    echo "<tr><th>User ID</th><th>Username</th><th>Email</th><th>Role</th><th>Name</th></tr>";
    while ($row = $all_users_query->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['username'] . "</td>";
        echo "<td>" . $row['email'] . "</td>";
        echo "<td>" . $row['role'] . "</td>";
        echo "<td>" . $row['first_name'] . " " . $row['last_name'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No users found in database.";
}

echo "<hr>";
echo "<p><a href='patient_dashboard.php'>Go to Patient Dashboard</a></p>";
?>

