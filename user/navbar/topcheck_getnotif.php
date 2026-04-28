<?php
//topcheck_getnotif.php
session_name("nobleuser");
session_start();
include ROOT_PATH . '/connection/connect.php';

// Kung walang session, wag mag error – return empty data lang
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        "notifications" => [],
        "unread_count" => 0
    ]);
    exit;
}

$userId = $_SESSION['user_id'];

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
