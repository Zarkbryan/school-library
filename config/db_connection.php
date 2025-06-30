<?php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "school_library";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset
$conn->set_charset("utf8mb4");  
// Check if the connection is successful


?>