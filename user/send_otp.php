<?php
// send_otp.php


use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require ROOT_PATH . '/vendor/autoload.php';
include ROOT_PATH . '/connection/connect.php';

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $email = '';
    $input = json_decode(file_get_contents('php://input'), true);
    if ($input && isset($input['email'])) {
        $email = trim($input['email']);
    } else {
        $email = trim($_POST['email'] ?? '');
    }

    if (empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Email is required']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email address']);
        exit;
    }

    $rate_limit_key = "otp_" . md5($email);
    if (isset($_SESSION[$rate_limit_key]) && $_SESSION[$rate_limit_key] > time() - 20) {
        echo json_encode(['success' => false, 'message' => 'Please wait 20 sec before requesting another OTP']);
        exit;
    }
    $_SESSION[$rate_limit_key] = time();

    $otp = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
    $expires = date('Y-m-d H:i:s', time() + 300); // 5 minutes

    $stmt = $conn->prepare("SELECT id, name, login_method FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 0) {
        $name = ucfirst(explode("@", $email)[0]);
        $default_pass = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);

        $insert_stmt = $conn->prepare("INSERT INTO users (name, email, password, login_method, created_at) VALUES (?, ?, ?, 'otp', NOW())");
        $insert_stmt->bind_param("sss", $name, $email, $default_pass);
        if (!$insert_stmt->execute()) {
            throw new Exception('Failed to create user account');
        }

        $user_id = $insert_stmt->insert_id;
        $user_name = $name;
        $is_new_user = true;

    } else {
        $user = $res->fetch_assoc();
        $user_id = $user['id'];
        $user_name = $user['name'];
        $current_login_method = $user['login_method'];
        $is_new_user = false;

        $valid_methods = ['normal', 'otp', 'google'];
        if (!in_array($current_login_method, $valid_methods)) {
            // Fix corrupted login_method
            $fix_stmt = $conn->prepare("UPDATE users SET login_method = 'otp' WHERE id = ?");
            $fix_stmt->bind_param("i", $user_id);
            $fix_stmt->execute();
        }
        // Do not overwrite 'normal' or 'google' unless needed
        // We assume 'otp' will be accepted in corrupted/fallback only
    }

    $otp_stmt = $conn->prepare("UPDATE users SET otp_code = ?, otp_expires_at = ? WHERE id = ?");
    $otp_stmt->bind_param("ssi", $otp, $expires, $user_id);
    if (!$otp_stmt->execute()) {
        throw new Exception('Failed to save OTP');
    }

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'noblehomeconst.ph@gmail.com';
    $mail->Password = 'vlci nqlz hwhq smva';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    $mail->setFrom('noblehomeconst.ph@gmail.com', 'NobleHome');
    $mail->addAddress($email, $user_name);
    $mail->isHTML(true);
    $mail->Subject = 'Your NobleHome Login Code';

    $welcome_message = $is_new_user ?
        "<p>Welcome to NobleHome! We've created your account.</p>" :
        "<p>Welcome back, {$user_name}!</p>";

    $mail->Body = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
        <div style='background: linear-gradient(135deg, #f97316, #ea580c); padding: 20px; text-align: center;'>
            <h1 style='color: white; margin: 0;'>NobleHome</h1>
        </div>
        <div style='padding: 30px; background: #f9f9f9;'>
            {$welcome_message}
            <p>Your login verification code is:</p>
            <div style='background: white; border: 2px solid #f97316; border-radius: 8px; padding: 20px; text-align: center; margin: 20px 0;'>
                <h2 style='color: #f97316; font-size: 32px; margin: 0; letter-spacing: 8px;'>{$otp}</h2>
            </div>
            <p><strong>Important:</strong> This code will expire in 5 minutes.</p>
            <p>If you didn't request this code, please ignore this email.</p>
        </div>
        <div style='background: #e5e5e5; padding: 15px; text-align: center; font-size: 12px; color: #666;'>
            <p>This is an automated message from NobleHome. Please do not reply to this email.</p>
        </div>
    </div>";

    $mail->AltBody = "Welcome to NobleHome!\n\nYour login verification code is: {$otp}\n\nThis code will expire in 5 minutes.\n\nIf you didn't request this code, please ignore this email.";

    if (!$mail->send()) {
        throw new Exception('Failed to send email: ' . $mail->ErrorInfo);
    }

    echo json_encode([
        'success' => true,
        'message' => $is_new_user ? 'Account created! OTP sent to your email.' : 'OTP sent successfully!',
        'is_new_user' => $is_new_user,
        'debug_info' => [
            'user_id' => $user_id,
            'email' => $email,
            'otp_expires' => $expires
        ]
    ]);

} catch (Exception $e) {
    error_log("Send OTP Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to send OTP. Please try again later.',
        'debug_error' => $e->getMessage()
    ]);
}
?>
