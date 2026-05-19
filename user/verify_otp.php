<?php
// verify_otp.php

include ROOT_PATH . '/connection/connect.php';
header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');
$otp = trim($input['otp'] ?? '');

if (empty($email) || empty($otp)) {
    echo json_encode(['success' => false, 'message' => 'Email and OTP are required']);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Account not found']);
        exit;
    }

    $user = $result->fetch_assoc();

    if (empty($user['otp_code'])) {
        echo json_encode(['success' => false, 'message' => 'No OTP found.']);
        exit;
    }

    if (strtotime($user['otp_expires_at']) < time()) {
        $clear_stmt = $conn->prepare("UPDATE users SET otp_code = NULL, otp_expires_at = NULL WHERE id = ?");
        $clear_stmt->bind_param("i", $user['id']);
        $clear_stmt->execute();
        echo json_encode(['success' => false, 'message' => 'OTP expired.']);
        exit;
    }

    if ($user['otp_code'] !== $otp) {
        echo json_encode(['success' => false, 'message' => 'Incorrect OTP.']);
        exit;
    }

    // OTP is valid — clear and proceed
    $clear_stmt = $conn->prepare("UPDATE users SET otp_code = NULL, otp_expires_at = NULL WHERE id = ?");
    $clear_stmt->bind_param("i", $user['id']);
    $clear_stmt->execute();

    $_SESSION['pending_password_email'] = $user['email'];

    echo json_encode(['success' => true, 'next' => 'password']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error', 'debug_error' => $e->getMessage()]);
}
?>
