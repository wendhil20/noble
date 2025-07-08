<?php
session_start();

// Unset all session variables
session_unset();

// Destroy the session
session_destroy();

// Remove the remember me cookie (if any)
setcookie("remember_token", "", time() - 3600, "/"); // Expire the cookie

// Redirect to Google's OAuth 2.0 logout endpoint
$googleLogoutUrl = 'https://accounts.google.com/Logout';

// Google logout works as expected, but we need a workaround to return to your site after logout.
$redirectUrl = 'http://localhost/noble/user/index.php'; // Change to your actual redirect URL (e.g., 'index.php')

// Set up the location header to redirect after Google logout
header("Location: $googleLogoutUrl");

// Redirect back to your site after Google logout
header("Location: $redirectUrl");
exit;
