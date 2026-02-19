<?php

include '../../connection/connect.php';

include 'auth.php';

// ✅ Final check if logged in (either normal or Google)
if (!isset($_SESSION['user_id'])) {
    // Not logged in, redirect to login/Google callback
    header('Location: google-callback.php');
    exit;
}

// If no users exist, reset auto_increment to 1
$check = $conn->query("SELECT COUNT(*) AS total FROM messages");
$row = $check->fetch_assoc();
if ($row['total'] == 0) {
    $conn->query("ALTER TABLE messages AUTO_INCREMENT = 1");
}

$sender_id = $_SESSION['user_id'];
$receiver_id = 1; // admin

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_POST['message'])) {
    $msg = trim($_POST['message']);

    // Optional: check if both IDs exist in users table
    $check_sender = mysqli_query($conn, "SELECT id FROM users WHERE id = $sender_id");
    $check_receiver = mysqli_query($conn, "SELECT id FROM users WHERE id = $receiver_id");

    if (mysqli_num_rows($check_sender) && mysqli_num_rows($check_receiver)) {
        $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $sender_id, $receiver_id, $msg);
        $stmt->execute();
        header("Location: messenger.php");
    } else {
        echo "Sender or receiver not found.";
    }
}
?>
