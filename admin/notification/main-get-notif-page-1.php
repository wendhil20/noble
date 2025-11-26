<?php
// File: admin/notifications/get_notifications.php
session_name("nobleadmin");
session_start();
include '../../connection/connect.php';

header('Content-Type: application/json');

// Ensure user is logged in
if (!isset($_SESSION['noble_user'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

// Get all notifications for admins (superadmin, productspecialist, etc.)
$query = "SELECT * FROM admin_notifications 
          ORDER BY created_at DESC 
          LIMIT 20";

$result = mysqli_query($conn, $query);

if (!$result) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit();
}

$notifications = [];
$unread_count = 0;

while ($row = mysqli_fetch_assoc($result)) {
    $notifications[] = [
        'id' => $row['id'],
        'title' => $row['title'],
        'message' => $row['message'],
        'type' => $row['type'], // 'product_upload', 'order', 'shipment', etc.
        'icon_class' => $row['icon_class'],
        'color_class' => $row['color_class'],
        'created_at' => $row['created_at'],
        'is_read' => $row['is_read']
    ];
    
    if ($row['is_read'] == 0) {
        $unread_count++;
    }
}

echo json_encode([
    'success' => true,
    'notifications' => $notifications,
    'unread_count' => $unread_count
]);
?>