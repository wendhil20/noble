<?php
//login.php

include ROOT_PATH . '/connection/connect.php';
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Unknown error'];

try {
    // Reset AUTO_INCREMENT if empty
    $check = $conn->query("SELECT COUNT(*) AS total FROM users");
    $row = $check->fetch_assoc();
    if ($row['total'] == 0) {
        $conn->query("ALTER TABLE users AUTO_INCREMENT = 1");
    }

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        throw new Exception("Invalid request method.");
    }

    $login_id = trim($_POST['login'] ?? $_POST['loginInput'] ?? '');
    $password = $_POST['password'] ?? '';
    $otp = $_POST['otp'] ?? '';
    $remember = isset($_POST['remember']);

    if (empty($login_id)) {
        throw new Exception("Please enter your email or mobile number.");
    }

    // Lookup user
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? OR mobile = ?");
    $stmt->bind_param("ss", $login_id, $login_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("Account not found.");
    }

    $user = $result->fetch_assoc();

    // ✅ Check kung trusted device (OTP already verified before)
    $otp_trust = $_COOKIE['otp_trust_token'] ?? '';
    $is_trusted_device = false;

    if (!empty($otp_trust) && !empty($user['otp_trust_token'])) {
        if (hash_equals($user['otp_trust_token'], $otp_trust)) {
            $is_trusted_device = true;
        }
    }

    // ✅ Login logic
    if (!empty($password)) {
        // Normal / Google login with password
        if (empty($user['password'])) {
            throw new Exception("This account has no password. Please login with Google.");
        }
        if (!password_verify($password, $user['password'])) {
            throw new Exception("Incorrect password.");
        }

    } else if (!empty($otp)) {
        // OTP login
        if (!isset($_SESSION['otp_code']) || !isset($_SESSION['otp_email']) || $_SESSION['otp_email'] !== $user['email']) {
            throw new Exception("OTP session is missing. Please request a new code.");
        }
        if ($otp !== $_SESSION['otp_code']) {
            throw new Exception("Incorrect OTP.");
        }
        unset($_SESSION['otp_code'], $_SESSION['otp_email']);

        // ✅ Mark device as trusted - 30 days
        $otp_token = bin2hex(random_bytes(32));
        setcookie("otp_trust_token", $otp_token, time() + (30 * 24 * 60 * 60), "/", "", false, true);
        $update = $conn->prepare("UPDATE users SET otp_trust_token = ? WHERE id = ?");
        $update->bind_param("si", $otp_token, $user['id']);
        $update->execute();

    } else if ($is_trusted_device) {
        // ✅ Trusted device - skip OTP, pero kailangan pa rin ng password
        if (empty($user['password'])) {
            throw new Exception("Please login with Google.");
        }
        throw new Exception("Trusted device detected. Please enter your password.");

    } else {
        throw new Exception("Password is required.");
    }

    // ✅ Verify email
    if (!$user['is_verified']) {
        throw new Exception("Please verify your email first. Check your inbox.");
    }

    // ✅ Finalize login
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_mobile'] = $user['mobile'];

    if (!empty($user['profile_picture'])) {
        $_SESSION['user_picture'] = $user['profile_picture'];
    }

    // ✅ Assign referral code if user came from a referral link
    require_once ROOT_PATH . '/includes/referral_tracker.php';
    assignReferralToUser($conn, $user['id']);

    // ✅ Remember Me
    if ($remember) {
        $token = bin2hex(random_bytes(32));
        setcookie("otp_trust_token", $otp_token, time() + (7 * 24 * 60 * 60), "/", "", false, true);

        $update = $conn->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
        $update->bind_param("si", $token, $user['id']);
        $update->execute();
    }

    $response['success'] = true;
    $response['redirect'] = BASE_URL . "/home";

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;