<?php
session_name("nobleadmin");
session_start();
require_once '../../connection/connect.php';

// Get user ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    header('HTTP/1.0 404 Not Found');
    exit;
}

// Fetch signature from database
$stmt = $conn->prepare("SELECT e_signature FROM nobleaccount WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->bind_result($signature);
$stmt->fetch();
$stmt->close();

if (empty($signature)) {
    header('HTTP/1.0 404 Not Found');
    exit;
}

// Output image
header('Content-Type: image/png'); // Default to PNG, browser will handle other types
header('Cache-Control: max-age=3600');
echo $signature;
exit;