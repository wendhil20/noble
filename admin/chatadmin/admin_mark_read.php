<?php
// File: admin_mark_read.php
session_name("nobleadmin");
session_start();
require_once '../role/roleaccount.php';
require_role(['productspecialist', 'superadmin']);

header('Content-Type: application/json');
include '../../connection/connect.php';

// Check if admin is logged in
if (!isset($_SESSION['noble_user'])) {
    echo json_encode(['status' => 'error', 'error' => 'Not logged in']);
    exit;
}

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(['status' => 'error', 'error' => 'Invalid JSON data']);
    exit;
}

$userId = filter_var($data['user_id'] ?? null, FILTER_VALIDATE_INT);

if (!$userId) {
    echo json_encode(['status' => 'error', 'error' => 'Invalid user ID']);
    exit;
}

try {
    // Mark all unread messages from this user to admin as read
    $sql = "UPDATE chat_messages 
            SET is_read = 1, read_at = NOW()
            WHERE sender_user_id = ? 
            AND receiver_noble_id IS NOT NULL 
            AND is_read = 0";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        $updatedRows = $stmt->affected_rows;
        
        // Log the read status update
        error_log("Messages marked as read - User: $userId, Count: $updatedRows");
        
        echo json_encode([
            'status' => 'success',
            'updated_rows' => $updatedRows,
            'user_id' => $userId
        ]);
    } else {
        throw new Exception("Failed to update read status: " . $stmt->error);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    error_log("Admin mark read error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'error' => 'Failed to mark messages as read']);
}

$conn->close();
?>
