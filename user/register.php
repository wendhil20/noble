<?php
session_start();
include '../../connection/connect.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    // Basic validation
    if (empty($name) || empty($email) || empty($password) || empty($confirm)) {
        $_SESSION['register_error'] = "Please fill in all fields.";
        header("Location: registerfillup.php");
        exit;
    }

    if ($password !== $confirm) {
        $_SESSION['register_error'] = "Passwords do not match.";
        header("Location: registerfillup.php");
        exit;
    }

    // Check if email is already registered
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $_SESSION['register_error'] = "Email is already in use.";
        header("Location: registerfillup.php");
        exit;
    }

    // Hash password and save user
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $name, $email, $hashed);

    if ($stmt->execute()) {
        $_SESSION['register_success'] = "Registration successful! Please log in.";
        header("Location: index.php");
        exit;
    } else {
        $_SESSION['register_error'] = "Something went wrong. Try again.";
        header("Location: registerfillup.php");
        exit;
    }
}
?>
