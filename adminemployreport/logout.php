<?php
session_name("nobleemployeereport");
session_start();

// Destroy all session data
session_unset();
session_destroy();

// Recreate session to hold flash message
session_name("nobleemployeereport");
session_start();

// Add flash message for logout success
$_SESSION['flash_message'] = "You have been logged out successfully.";
$_SESSION['flash_type'] = "success";

// Redirect back to login page
header("Location: index.php"); // change 'index.php' if your login file has a different name
exit();
?>
