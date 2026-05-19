<?php
// register.php

include ROOT_PATH . '/connection/connect.php';

// Install PHPMailer first:
// composer require phpmailer/phpmailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require ROOT_PATH . '/vendor/autoload.php';

function validateMobileNumber($mobile)
{
    return preg_match('/^09\d{9}$/', $mobile);
}
function validateEmail($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}
function emailExists($conn, $email)
{
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}
function mobileExists($conn, $mobile)
{
    $stmt = $conn->prepare("SELECT id FROM users WHERE mobile = ?");
    $stmt->bind_param("s", $mobile);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($name) || strlen($name) < 2) {
        $errors[] = "Full name is required and must be at least 2 characters.";
    }
    if (empty($email) || !validateEmail($email)) {
        $errors[] = "A valid email is required.";
    } elseif (emailExists($conn, $email)) {
        // I-check kung google account — kung oo, i-allow mag-set ng password
        $check_stmt = $conn->prepare("SELECT login_method FROM users WHERE email = ?");
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $existing = $check_stmt->get_result()->fetch_assoc();
        if ($existing['login_method'] === 'google') {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $verify_token = bin2hex(random_bytes(32));

            // I-update — is_verified = 0, kailangan pang mag-verify
            $update_stmt = $conn->prepare("
        UPDATE users 
        SET name = ?, mobile = ?, password = ?, login_method = 'normal', 
            is_verified = 0, verify_token = ?
        WHERE email = ?
    ");
            $update_stmt->bind_param("sssss", $name, $mobile, $hashed_password, $verify_token, $email);
            $update_stmt->execute();

            // Send verification email
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'];
            $basePath = dirname($_SERVER['SCRIPT_NAME']);
            $verifyLink = $protocol . '://' . $host . $basePath . '/verifyregistration.php?token=' . $verify_token;

            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'noblehomeconst.ph@gmail.com';
                $mail->Password = 'vlci nqlz hwhq smva';
                $mail->SMTPSecure = 'tls';
                $mail->Port = 587;

                $mail->setFrom('noblehomeconst.ph@gmail.com', 'Noble');
                $mail->addAddress($email, $name);
                $mail->Subject = 'Verify your email - Noble';
                $mail->isHTML(true);
                $mail->Body = "
            <h2>Hi $name!</h2>
            <p>You added a password to your Noble account.</p>
            <p>Click the button below to verify your email address.</p>
            <a href='$verifyLink' 
               style='background:#f97316;color:white;padding:12px 24px;
                      border-radius:8px;text-decoration:none;font-weight:bold;'>
                Verify My Email
            </a>
            <p style='margin-top:16px;color:#888;font-size:12px;'>
                If you did not do this, ignore this email.
            </p>
        ";

                $mail->send();
                $_SESSION['register_success'] = "Password added! Please check your email to verify your account.";

            } catch (Exception $e) {
                $_SESSION['register_error'] = "Account updated but failed to send verification email.";
            }

            header("Location: " . ROOT_PATH . "/");
            exit();

        } else {
            $errors[] = "Email already exists.";
        }
    }
    if (empty($mobile) || !validateMobileNumber($mobile)) {
        $errors[] = "A valid mobile number is required.";
    } elseif (mobileExists($conn, $mobile)) {
        $errors[] = "Mobile number already exists.";
    }
    if (empty($password) || strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters.";
    }
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $verify_token = bin2hex(random_bytes(32)); // unique token

        // Save as UNVERIFIED
        $stmt = $conn->prepare("
            INSERT INTO users (name, email, mobile, password, verify_token, is_verified, created_at) 
            VALUES (?, ?, ?, ?, ?, 0, NOW())
        ");
        $stmt->bind_param("sssss", $name, $email, $mobile, $hashed_password, $verify_token);

        if ($stmt->execute()) {
            // Send verification email
            // Dynamic base URL — works sa local (localhost) at sa live site
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST']; // localhost or yoursite.com
            $basePath = dirname($_SERVER['SCRIPT_NAME']); // kunin yung current folder path

            $verifyLink = $protocol . '://' . $host . $basePath . '/verifyregistration.php?token=' . $verify_token;

            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'noblehomeconst.ph@gmail.com';   // <- palitan
                $mail->Password = 'vlci nqlz hwhq smva';     // <- Gmail App Password
                $mail->SMTPSecure = 'tls';
                $mail->Port = 587;

                $mail->setFrom('noblehomeconst.ph@gmail.com', 'Noble');
                $mail->addAddress($email, $name);
                $mail->Subject = 'Verify your email - Noble';
                $mail->isHTML(true);
                $mail->Body = "
                    <h2>Hi $name!</h2>
                    <p>Click the button below to verify your email address.</p>
                    <a href='$verifyLink' 
                       style='background:#f97316;color:white;padding:12px 24px;
                              border-radius:8px;text-decoration:none;font-weight:bold;'>
                        Verify My Email
                    </a>
                    <p style='margin-top:16px;color:#888;font-size:12px;'>
                        If you did not register, ignore this email.
                    </p>
                ";

                $mail->send();
                $_SESSION['register_success'] = "Registration successful! Please check your email to verify your account.";

            } catch (Exception $e) {
                // Nag-save sa DB pero hindi naka-send ang email
                $_SESSION['register_error'] = "Account created but failed to send verification email. Contact support.";
            }

        } else {
            $_SESSION['register_error'] = "Something went wrong. Try again.";
        }

        header("Location: " . ROOT_PATH . "/");
        exit();

    } else {
        $_SESSION['register_error'] = implode("<br>", $errors);
        header("Location: " . ROOT_PATH . "/");
        exit();
    }
}
?>