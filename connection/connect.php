<?php
// Detect environment (local or production)
$isLocal = in_array($_SERVER['HTTP_HOST'], ['localhost', 'localhost:8000', '127.0.0.1', '127.0.0.1:8000']);

// Load from .env file if exists
if (file_exists(__DIR__ . '../.env')) {
    $env = parse_ini_file(__DIR__ . '../.env');
    foreach ($env as $key => $value) {
        putenv("$key=$value");
    }
}

// Set database credentials based on environment
if ($isLocal) {
    // Local/XAMPP Configuration
    $host = getenv('DB_HOST_LOCAL') ?: 'localhost:3306';
    $username = getenv('DB_USER_LOCAL') ?: 'root';
    $password = getenv('DB_PASSWORD_LOCAL') ?: '';
    $database = getenv('DB_NAME_LOCAL') ?: 'noblehomedata';
} else {
    // Production Configuration
    $host = getenv('DB_HOST_PROD') ?: 'localhost';
    $username = getenv('DB_USER_PROD') ?: 'u318146187_nh';
    $password = getenv('DB_PASSWORD_PROD') ?: 'NobleHomecc@2025';
    $database = getenv('DB_NAME_PROD') ?: 'u318146187_nh';
}

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8
$conn->set_charset("utf8");
