<?php
session_name("nobleuser");
session_start();

require_once '../connection/connect.php';

// ✅ DAGDAG - Clear remember_token sa DB
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("UPDATE users SET remember_token = NULL WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $stmt->close();
}

// ✅ Clear cookie - dapat may httponly flag din
setcookie("remember_token", "", time() - 3600, "/", "", false, true);

// Clear session
session_unset();
session_destroy();

// Build dynamic redirect
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$isLocalhost = (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false);

$redirectUrl = $isLocalhost
    ? $protocol . $host . '/noble/index.php'
    : $protocol . $host . '/index.php';

header("Location: $redirectUrl");
exit;
?>