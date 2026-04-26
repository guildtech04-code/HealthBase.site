<?php
// Include the appointments page from the correct location
$_SERVER['PHP_SELF'] = '/dashboard/appointments.php'; // Set the correct path for sidebar detection
include('../appointments/appointments.php');
?>
