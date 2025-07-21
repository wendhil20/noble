<?php
session_name("nobleuser");
session_start();
include '../connection/connect.php';

// ✅ Reset AUTO_INCREMENT if needed
$tables = ['users', 'product_ratings'];
foreach ($tables as $table) {
    $result = $conn->query("SELECT MAX(id) AS max_id FROM $table");
    $row = $result->fetch_assoc();
    $max_id = (int)$row['max_id'];
    $next_id = $max_id > 0 ? $max_id + 1 : 1;
    $conn->query("ALTER TABLE $table AUTO_INCREMENT = $next_id");
}

// ✅ Handle Registration
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    // ✅ Basic Validation
    if (empty($name) || empty($email) || empty($mobile) || empty($password) || empty($confirm)) {
        $_SESSION['register_error'] = "Please fill in all fields.";
        header("Location: register.php");
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['register_error'] = "Invalid email format.";
        header("Location: register.php");
        exit;
    }

    if (!preg_match('/^09\d{9}$/', $mobile)) {
        $_SESSION['register_error'] = "Invalid mobile number format. Example: 09123456789";
        header("Location: register.php");
        exit;
    }

    if ($password !== $confirm) {
        $_SESSION['register_error'] = "Passwords do not match.";
        header("Location: register.php");
        exit;
    }

    // ✅ Check for existing email or mobile
    $check = $conn->prepare("SELECT id FROM users WHERE email = ? OR mobile = ?");
    $check->bind_param("ss", $email, $mobile);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $_SESSION['register_error'] = "Email or mobile number already in use.";
        header("Location: register.php");
        exit;
    }

    // ✅ Insert new user
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $insert = $conn->prepare("INSERT INTO users (name, email, mobile, password) VALUES (?, ?, ?, ?)");
    $insert->bind_param("ssss", $name, $email, $mobile, $hashed);

    if ($insert->execute()) {
        $_SESSION['register_success'] = "Registration successful! Please log in.";
        header("Location: index.php");
        exit;
    } else {
        $_SESSION['register_error'] = "Database Error: " . $insert->error;
        header("Location: register.php");
        exit;
    }
}
?>
