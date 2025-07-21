<?php
session_name("nobleuser");
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
    $login_id = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    // Check if user exists by email or mobile
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? OR mobile = ?");
    $stmt->bind_param("ss", $login_id, $login_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $user = $res->fetch_assoc();

        // ✅ Verify password
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];        // ✅ ADD THIS
            $_SESSION['user_mobile'] = $user['mobile'];      // ✅ AND THIS

            // ✅ Set remember_token if checkbox is checked
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                setcookie("remember_token", $token, time() + (30 * 24 * 60 * 60), "/");

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

    header("Location: index.php");
    exit;
}
?>
