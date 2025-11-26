<?php
session_name("nobleuser");
session_start();

// Unset all session variables
session_unset();

// Destroy the session
session_destroy();

// Remove the remember me cookie (if any)
setcookie("remember_token", "", time() - 3600, "/");

// Build dynamic redirect URL back to main website
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];

// Detect if localhost or production
$isLocalhost = (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false);

if ($isLocalhost) {
    // Localhost path with 'noble' folder
    $redirectUrl = $protocol . $host . '/noble/index.php';
} else {
    // Production domain - starts from 'user'
    $redirectUrl = $protocol . $host . '/index.php';
}

// Redirect back to your site (logs out of your app)
// Note: User remains logged into Google in their browser
header("Location: $redirectUrl");
exit;
?>