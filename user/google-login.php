<?php
//google-login.php - FIXED: Using .env for OAuth credentials
session_name("nobleuser");
session_start();

// ✅ LOAD ENVIRONMENT VARIABLES
require_once '../.env.php';
require_once '../vendor/autoload.php';
include '../connection/connect.php'; 

// ✅ GET OAUTH CREDENTIALS FROM ENVIRONMENT
$clientId = getenv('GOOGLE_CLIENT_ID');
$clientSecret = getenv('GOOGLE_CLIENT_SECRET');

if (empty($clientId) || empty($clientSecret)) {
    error_log('❌ Google OAuth credentials not configured in .env');
    $_SESSION['login_needed'] = 'OAuth service not configured. Please try again later.';
    header("Location: otherpage/index-page-1-A-B-C-D-E.php");
    exit;
}

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
$client->setClientId($clientId);
$client->setClientSecret($clientSecret);
$client->setRedirectUri($redirectUri);
$client->addScope("email");
$client->addScope("profile");

header('Location: ' . $client->createAuthUrl());
exit;
?>