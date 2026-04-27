<?php
session_name("nobleuser");
session_start();

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

require_once ROOT_PATH . '/connection/connect.php';

// Clear remember_token in DB
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("UPDATE users SET remember_token = NULL WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $stmt->close();
}

// Clear cookie
setcookie("remember_token", "", time() - 3600, "/", "", false, true);

// Clear session
$_SESSION = [];
session_destroy();

// Redirect
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$isLocalhost = (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false);

$redirectUrl = $isLocalhost
    ? $protocol . $host . '/noble/'
    : $protocol . $host . '/';

header("Location: $redirectUrl");
exit;