<?php
session_start();
include '../../connection/connect.php'; // your DB connection

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$userId = $_SESSION['user_id'];

$stmt = $conn->prepare("DELETE FROM notifications WHERE user_id = ?");
$stmt->bind_param("i", $userId);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to delete notifications']);
}

$stmt->close();
$conn->close();
