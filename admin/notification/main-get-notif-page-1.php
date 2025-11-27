<?php
// File: admin/notification/main-get-notif-page-1.php
session_name("nobleadmin");
session_start();
include '../../connection/connect.php';

header('Content-Type: application/json');

// Ensure user is logged in
if (!isset($_SESSION['noble_user'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

// Get current user's ID and role
$current_user_id = $_SESSION['noble_id'];
$current_user_role = $_SESSION['noble_lvl'];

// Get notifications for:
// 1. Specific user (target_admin_id matches)
// 2. Specific role (target_role matches)
// 3. All users (both target_admin_id and target_role are NULL)
$query = "SELECT * FROM admin_notifications 
          WHERE (target_admin_id = ? OR target_admin_id IS NULL)
          AND (target_role = ? OR target_role IS NULL)
          ORDER BY created_at DESC 
          LIMIT 20";

$stmt = $conn->prepare($query);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit();
}

$stmt->bind_param("is", $current_user_id, $current_user_role);
$stmt->execute();
$result = $stmt->get_result();

$notifications = [];
$unread_count = 0;

while ($row = $result->fetch_assoc()) {
    $notifications[] = [
        'id' => $row['id'],
        'title' => $row['title'],
        'message' => $row['message'],
        'type' => $row['type'],
        'icon_class' => $row['icon_class'],
        'color_class' => $row['color_class'],
        'created_at' => $row['created_at'],
        'is_read' => $row['is_read']
    ];
    
    if ($row['is_read'] == 0) {
        $unread_count++;
    }
}

$stmt->close();

echo json_encode([
    'success' => true,
    'notifications' => $notifications,
    'unread_count' => $unread_count
]);
?>