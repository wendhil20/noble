<?php
$host = "localhost:3306";
$username = "root"; // Default sa XAMPP
$password = ""; // Default sa XAMPP (walang password)
$database = "noblehomedata"; // Palitan ng tamang database name

$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}



// $host = "localhost";
// $username = "smar_bpasmart"; // Default sa XAMPP
// $password = "DbOKjutmNG1c073D"; // Default sa XAMPP (walang password)
// $database = "smar_land"; // Palitan ng tamang database name

// $conn = new mysqli($host, $username, $password, $database);

// // Check connection
// if ($conn->connect_error) {
//     die("Connection failed: " . $conn->connect_error);
// }

