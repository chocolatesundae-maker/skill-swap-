<?php
// db_connect.php
// Database connection settings
$servername = "localhost";
$username = "root";
$password = "";
$database = "local_skill_swap";

// Only create a connection if it doesn't already exist
if (!isset($conn) || !$conn) {
    $conn = new mysqli($servername, $username, $password, $database);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
}
?>