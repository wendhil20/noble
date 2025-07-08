<?php
include '../../connection/connect.php';

include '../role/roleaccount.php';
require_role(['admin', 'superadmin']); // allow only admin and superadmin


$message = trim($_POST['message']);
$receiver_id = intval($_POST['receiver_id']);
$admin_id = intval($_POST['admin_id']);

if (empty($message) || !$receiver_id || !$admin_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit;
}

// Insert message
$stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message, created_at) VALUES (?, ?, ?, NOW())");
$stmt->bind_param("iis", $admin_id, $receiver_id, $message);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message_id' => $stmt->insert_id]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to send message']);
}
?>