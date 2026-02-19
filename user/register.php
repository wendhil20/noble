<?php
session_name("nobleuser");
session_start();
include '../connection/connect.php';

// Function definitions...
function validateMobileNumber($mobile) {
    return preg_match('/^09\d{9}$/', $mobile);
}
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}
function emailExists($conn, $email) {
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}
function mobileExists($conn, $mobile) {
    $stmt = $conn->prepare("SELECT id FROM users WHERE mobile = ?");
    $stmt->bind_param("s", $mobile);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

$errors = [];

// If submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validate
    if (empty($name) || strlen($name) < 2) {
        $errors[] = "Full name is required and must be at least 2 characters.";
    }
    if (empty($email) || !validateEmail($email)) {
        $errors[] = "A valid email is required.";
    } elseif (emailExists($conn, $email)) {
        $errors[] = "Email already exists.";
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

    // Save or redirect
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (name, email, mobile, password, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssss", $name, $email, $mobile, $hashed_password);

        if ($stmt->execute()) {
            $_SESSION['register_success'] = "Registration successful! Please log in.";
        } else {
            $_SESSION['register_error'] = "Something went wrong. Try again.";
        }

        header("Location: otherpage/index-page-1-A-B-C-D-E.php");
        exit();
    } else {
        $_SESSION['register_error'] = implode("<br>", $errors);
        header("Location: otherpage/index-page-1-A-B-C-D-E.php");
        exit();
    }
}
?>
