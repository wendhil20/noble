<?php
session_name("nobleadmin");
session_start();

// Remove all session variables
session_unset();

// Destroy the session
session_destroy();

// Optional: Delete remember_token cookie if using "Remember Me"
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

// Redirect to login page
header("Location: index.php"); // or use "../loginpage/index.php" if nasa loob ng ibang folder
exit();
?>