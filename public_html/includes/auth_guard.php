<?php
require_once __DIR__ . '/security.php';

function ensure_logged_in(): void {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /auth/login.php');
        exit;
    }
}

function ensure_role(array $allowed): void {
    ensure_logged_in();
    $role = $_SESSION['role'] ?? '';
    if (!in_array($role, $allowed, true)) {
        // healthbase_dashboard.php always forwards to the patient app; send each role home instead.
        $homeByRole = [
            'assistant' => '/assistant_view/ml_dashboard.php',
            'admin' => '/admin/manage_users.php',
            'doctor' => '/dashboard/doctor_dashboard.php',
            'user' => '/patient/patient_dashboard.php',
        ];
        $dest = $homeByRole[$role] ?? '/auth/login.php';
        header('Location: ' . $dest);
        exit;
    }
}
?>