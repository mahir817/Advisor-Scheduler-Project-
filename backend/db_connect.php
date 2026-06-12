<?php
date_default_timezone_set('Asia/Dhaka');
$host = 'localhost';
$user = 'root';
$pass = ''; // Default XAMPP password is empty
$db   = 'advisor_project';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
