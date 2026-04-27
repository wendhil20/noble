<?php
session_name("nobleuser");
session_start();
include ROOT_PATH . '/connection/connect.php';

$token = $_GET['token'] ?? '';

if (empty($token)) {
    $_SESSION['register_error'] = "Invalid verification link.";
    header("Location: " . ROOT_PATH . "/");
    exit();
}

// Find user with this token
$stmt = $conn->prepare("SELECT id FROM users WHERE verify_token = ? AND is_verified = 0");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Activate the account
    $update = $conn->prepare("UPDATE users SET is_verified = 1, verify_token = NULL WHERE verify_token = ?");
    $update->bind_param("s", $token);
    $update->execute();

    $_SESSION['register_success'] = "Email verified! You can now login.";
} else {
    $_SESSION['register_error'] = "Invalid or already used verification link.";
}

header("Location: " . ROOT_PATH . "/");
exit();
?>