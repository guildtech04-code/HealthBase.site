<?php
// Include the my tickets page from the correct location
$_SERVER['PHP_SELF'] = '/appointments/my_tickets.php'; // Set the correct path for sidebar detection
include('../support/my_tickets.php');
?>
