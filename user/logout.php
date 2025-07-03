<?php
session_start();
session_unset();
session_destroy();
setcookie("remember_token", "", time() - 3600, "/"); // Remove cookie
header("Location: index.php");
exit;
