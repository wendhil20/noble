<?php
session_name("nobleuser");
session_start();
include '../../connection/connect.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

$userId = $_SESSION['user_id'];

// Fetch latest 10 notifications
$query = "SELECT id, message, created_at, is_read
          FROM notifications
          WHERE user_id = ?
          ORDER BY created_at DESC
          LIMIT 10";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

$notifications = [];
$unread_count = 0;

while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
    if ($row['is_read'] == 0) {
        $unread_count++;
    }
}

echo json_encode([
    "notifications" => $notifications,
    "unread_count" => $unread_count
]);

$stmt->close();
$conn->close();
?>
