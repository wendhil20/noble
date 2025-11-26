<?php
// index.php - Root redirect file
// Works on both localhost and production domain

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];

// Detect if localhost or production
$isLocalhost = (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false);

if ($isLocalhost) {
    // Localhost path with 'noble' folder
    $redirectUrl = $protocol . $host . '/noble/user/otherpage/index-page-1-A-B-C-D-E.php';
} else {
    // Production domain - starts from 'user'
    $redirectUrl = $protocol . $host . '/user/otherpage/index-page-1-A-B-C-D-E.php';
}

header('Location: ' . $redirectUrl);
exit;
?>