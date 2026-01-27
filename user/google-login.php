<?php
//google-login.php
session_name("nobleuser");
session_start();
include '../connection/connect.php'; 
require_once '../vendor/autoload.php';

$tables = ['users'];
foreach ($tables as $table) {
    $result = $conn->query("SELECT COUNT(*) as total FROM $table");
    $row = $result->fetch_assoc();
    if ((int)$row['total'] === 0) {
        $conn->query("ALTER TABLE $table AUTO_INCREMENT = 1");
    }
}

// Build dynamic redirect URI
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];

// Detect if localhost or production
$isLocalhost = (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false);

if ($isLocalhost) {
    // Localhost path with 'noble' folder
    $redirectUri = $protocol . $host . '/noble/user/google-callback.php';
} else {
    // Production domain - starts from 'user'
    $redirectUri = $protocol . $host . '/user/google-callback.php';
}

$client = new Google_Client();
$client->setClientId('733754213464-u3t36puhp1o6ra9uqi6j040em5br1bie.apps.googleusercontent.com');
$client->setClientSecret('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');
$client->setRedirectUri($redirectUri);
$client->addScope("email");
$client->addScope("profile");

header('Location: ' . $client->createAuthUrl());
exit;
?>