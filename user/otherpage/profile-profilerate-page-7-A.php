<?php
session_name("nobleuser");
session_start();
include ROOT_PATH . '/connection/connect.php';

// ✅ Restore session from remember_token (normal account or Google)
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    $stmt = $conn->prepare("SELECT * FROM users WHERE remember_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $user = $res->fetch_assoc();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];

        // Check if the account is Google-based (optional flag or logic)
        if (!empty($user['google_id'])) {
            $_SESSION['google_logged_in'] = true;
            $_SESSION['user_picture'] = $user['profile_picture'] ?? null;
        }
    }
    $stmt->close();
}

// ✅ Final check if logged in (either normal or Google)
if (!isset($_SESSION['user_id'])) {
    // Not logged in, redirect to login/Google callback
    header('Location: ' . BASE_URL . '/login');
    exit;
}

// ✅ Retrieve user info
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'Guest';
$user_email = $_SESSION['user_email'] ?? null;
$user_picture = $_SESSION['user_picture'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = intval($_POST['user_id']); // yung may-ari ng profile
    $author_id = $_SESSION['user_id'];   // yung nagbigay ng feedback
    $rating = intval($_POST['rating']);
    $comment = trim($_POST['comment']);

    if ($rating >= 1 && $rating <= 5) {
        // Check if feedback already exists from this author to this user
        $stmt = $conn->prepare("SELECT id FROM user_feedback WHERE user_id = ? AND author_id = ?");
        $stmt->bind_param("ii", $user_id, $author_id);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows > 0) {
            // Update existing feedback
            $row = $res->fetch_assoc();
            $feedback_id = $row['id'];

            $update = $conn->prepare("UPDATE user_feedback SET rating = ?, comment = ?, created_at = NOW() WHERE id = ?");
            $update->bind_param("isi", $rating, $comment, $feedback_id);
            $update->execute();
            $update->close();
        } else {
            // Insert new feedback
            $insert = $conn->prepare("INSERT INTO user_feedback (user_id, author_id, rating, comment) VALUES (?, ?, ?, ?)");
            $insert->bind_param("iiis", $user_id, $author_id, $rating, $comment);
            $insert->execute();
            $insert->close();
        }

        $stmt->close();
    }
}

header("Location: " . BASE_URL . "/profile?user_id=" . $user_id);
exit;
