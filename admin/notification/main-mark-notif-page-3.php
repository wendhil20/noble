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

// Delete notification
if ($action === 'delete' && $notification_id) {
    $query = "DELETE FROM admin_notifications WHERE id = ?";
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param("i", $notification_id);
        $result = $stmt->execute();
        $stmt->close();
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Notification deleted',
                'unread_count' => getUnreadCount($conn)
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete']);
        }
    }
    exit();
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>