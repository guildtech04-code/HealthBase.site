<?php
// healthbase_dashboard.php - Redirect to new patient dashboard
session_start();
include("../config/db_connect.php");

// Fetch logged-in user info
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

// Redirect to the new patient dashboard
header("Location: ../patient/patient_dashboard.php");
exit();
?>