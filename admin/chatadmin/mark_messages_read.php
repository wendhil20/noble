<?php
include '../../connection/connect.php';

include '../role/roleaccount.php';
require_role(['admin', 'superadmin']); // allow only admin and superadmin


$user_id = intval($_POST['user_id']);
$admin_id = intval($_POST['admin_id']);

// Mark messages as read
$stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ? AND is_read = 0");
$stmt->bind_param("ii", $user_id, $admin_id);
$stmt->execute();

echo json_encode(['success' => true, 'affected_rows' => $stmt->affected_rows]);
?>