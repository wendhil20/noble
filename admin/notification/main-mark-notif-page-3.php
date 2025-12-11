<?php
// File: admin/notification/mark_as_read.php
date_default_timezone_set('Asia/Manila');

session_name("nobleadmin");
session_start();
include '../../connection/connect.php';
require_once 'main-handler-notif-page-2.php';

header('Content-Type: application/json');

// Ensure user is logged in
if (!isset($_SESSION['noble_user'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$action = $_POST['action'] ?? null;
$notification_id = $_POST['notification_id'] ?? null;

if (!$action) {
    echo json_encode(['success' => false, 'message' => 'No action specified']);
    exit();
}

// Mark single notification as read
if ($action === 'mark_single' && $notification_id) {
    $result = markAsRead($conn, $notification_id);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Notification marked as read',
            'unread_count' => getUnreadCount($conn)
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to mark as read']);
    }
    exit();
}

// Mark all notifications as read
if ($action === 'mark_all') {
    $query = "UPDATE admin_notifications SET is_read = 1 WHERE is_read = 0";
    $result = $conn->query($query);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'All notifications marked as read',
            'unread_count' => 0
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to mark all as read']);
    }
    exit();
}

// Delete all notifications
if ($action === 'delete_all') {
    $query = "DELETE FROM admin_notifications";
    $result = $conn->query($query);
    
    if ($result) {
        $affected_rows = $conn->affected_rows;
        echo json_encode([
            'success' => true,
            'message' => 'All notifications deleted successfully',
            'unread_count' => 0,
            'affected_rows' => $affected_rows
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to delete all notifications: ' . $conn->error
        ]);
    }
    exit();
}

// Delete notification
if ($action === 'delete' && $notification_id) {
    $notification_id = (int)$notification_id; // Ensure it's an integer
    
    $query = "DELETE FROM admin_notifications WHERE id = ?";
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        // Prepare failed
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $conn->error,
            'error_type' => 'prepare_failed'
        ]);
        exit();
    }
    
    $stmt->bind_param("i", $notification_id);
    $result = $stmt->execute();
    
    if (!$result) {
        // Execute failed
        echo json_encode([
            'success' => false,
            'message' => 'Delete failed: ' . $stmt->error,
            'error_type' => 'execute_failed'
        ]);
        $stmt->close();
        exit();
    }
    
    $affected_rows = $stmt->affected_rows;
    $stmt->close();
    
    // Check if any rows were affected
    if ($affected_rows > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Notification deleted successfully',
            'unread_count' => getUnreadCount($conn),
            'affected_rows' => $affected_rows
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Notification not found or already deleted',
            'error_type' => 'no_rows_affected'
        ]);
    }
    exit();
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>