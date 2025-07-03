<?php
session_start();
include '../connection/connect.php';

$error = "";

// ✅ Reset auto_increment if no users exist
$check = $conn->query("SELECT COUNT(*) AS total FROM users");
$row = $check->fetch_assoc();
if ($row['total'] == 0) {
    $conn->query("ALTER TABLE users AUTO_INCREMENT = 1");
}

// ✅ Handle login POST
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    // Check if user exists
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $user = $res->fetch_assoc();

        // Verify password
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];

            // ✅ Set remember_token if checked
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                setcookie("remember_token", $token, time() + (30 * 24 * 60 * 60), "/"); // 30 days

                $update = $conn->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
                $update->bind_param("si", $token, $user['id']);
                $update->execute();
            }

            header("Location: index.php");
            exit;
        } else {
            $_SESSION['login_error'] = "Incorrect password.";
        }
    } else {
        $_SESSION['login_error'] = "Account not found.";
    }

    header("Location: index.php"); // go back to form with error
    exit;
}
