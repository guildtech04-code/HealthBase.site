<?php
$servername = "localhost";   // or 127.0.0.1
$username   = "u654420946_GuildTech";        
$password   = "@Guild4Tech*";            
$dbname     = "u654420946_Healthbase";  

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
