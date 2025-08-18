<?php
session_name("nobleuser");
session_start();
header('Content-Type: application/json');
include '../../connection/connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["unread_count" => 0]);
    exit;
}

$userId = $_SESSION['user_id'];

// Count unread messages from admin (or noble account) to this user
$sql = "SELECT COUNT(*) as unread_count 
        FROM chat_messages 
        WHERE receiver_user_id = ? 
          AND sender_noble_id IS NOT NULL
          AND is_read = 0";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

echo json_encode([
    "unread_count" => (int)$row['unread_count']
]);

$stmt->close();
$conn->close();
?>
