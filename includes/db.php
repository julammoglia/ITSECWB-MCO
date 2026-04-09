<?php
require_once __DIR__ . '/security.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "pluggedin_itdbadm";

$conn = new mysqli('127.0.0.1', 'root', '', 'pluggedin_itdbadm', 3307);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset('utf8mb4');
?>
