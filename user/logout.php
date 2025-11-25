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

// Redirect to main website homepage
$redirectUrl = $protocol . $host . '/noble/user/otherpage/index-page-1-A-B-C-D-E.php';

// Simply redirect back to your site (logs out of your app)
// Note: User remains logged into Google in their browser
header("Location: $redirectUrl");
exit;
?>