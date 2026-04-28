<?php
// topcheck_clearall.php
session_name("nobleuser");
session_start();
include ROOT_PATH . '/connection/connect.php';

header('Content-Type: application/json');

// Log the request for debugging
error_log("Clear notifications request - Session ID: " . session_id());
error_log("User ID: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'NOT SET'));

if (!isset($_SESSION['user_id'])) {
    error_log("Unauthorized access attempt");
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized - User not logged in']);
    exit();
}

$userId = $_SESSION['user_id'];

try {
    $stmt = $conn->prepare("DELETE FROM notifications WHERE user_id = ?");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        $affectedRows = $stmt->affected_rows;
        error_log("Successfully deleted $affectedRows notifications for user $userId");
        echo json_encode(['success' => true, 'deleted_count' => $affectedRows]);
    } else {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $stmt->close();
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}

$conn->close();
?>