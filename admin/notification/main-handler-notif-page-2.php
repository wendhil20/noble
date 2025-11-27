<?php
// File: admin/notification/main-handler-notif-page-2.php
// ✅ FIXED: Set timezone to Philippines
date_default_timezone_set('Asia/Manila');

include '../../connection/connect.php';

// ✅ Set MySQL timezone
$conn->query("SET SESSION time_zone = '+08:00'");

/**
 * Log notification action to history table
 */
function logNotificationHistory($conn, $notification_id, $notification_type, $title, $message, $action, $admin_id = null, $admin_name = null, $details = null) {
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $details_json = !empty($details) ? json_encode($details) : null;
    
    // Get admin info from session if not provided
    if (empty($admin_id) && isset($_SESSION['noble_id'])) {
        $admin_id = $_SESSION['noble_id'];
    }
    if (empty($admin_name) && isset($_SESSION['noble_name'])) {
        $admin_name = $_SESSION['noble_name'];
    }
    
    $query = "INSERT INTO admin_notification_history 
              (notification_id, admin_id, admin_name, notification_type, title, message, action, action_details, ip_address, user_agent, created_at) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("History log prepare failed: " . $conn->error);
        return false;
    }
    
    $created_at = date('Y-m-d H:i:s');
    
    // ✅ FIXED: All valid specifiers only (i, s, d, b)
    // 11 parameters = 11 specifiers
    $stmt->bind_param(
        "iisssssssss",
        $notification_id,      // i
        $admin_id,             // i
        $admin_name,           // s
        $notification_type,    // s
        $title,                // s
        $message,              // s
        $action,               // s
        $details_json,         // s
        $ip_address,           // s
        $user_agent,           // s
        $created_at            // s
    );
    
    if ($stmt->execute()) {
        $history_id = $stmt->insert_id;
        $stmt->close();
        error_log("History logged with ID: $history_id");
        
        // Log the action in actions_log table
        logNotificationAction($conn, $history_id, $action, $admin_id, $admin_name);
        return true;
    } else {
        error_log("History log execute failed: " . $stmt->error);
        $stmt->close();
        return false;
    }
}

/**
 * Log notification action to actions_log table
 */
function logNotificationAction($conn, $history_id, $action_type, $admin_id = null, $admin_name = null) {
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    
    if (empty($admin_id) && isset($_SESSION['noble_id'])) {
        $admin_id = $_SESSION['noble_id'];
    }
    if (empty($admin_name) && isset($_SESSION['noble_name'])) {
        $admin_name = $_SESSION['noble_name'];
    }
    
    error_log("logNotificationAction called - History ID: $history_id, Action: $action_type, Admin: $admin_name");
    
    $query = "INSERT INTO admin_notification_actions_log (notification_history_id, action_type, performed_by, performed_by_name, ip_address, created_at) 
              VALUES (?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Action log prepare failed: " . $conn->error);
        return false;
    }
    
    $created_at = date('Y-m-d H:i:s');
    
    // ✅ FIXED: 6 parameters = 6 specifiers (only i, s, d, b allowed)
    // i=history_id, s=action_type, i=admin_id, s=admin_name, s=ip_address, s=created_at
    $stmt->bind_param(
        "isisss",
        $history_id,      // i
        $action_type,     // s
        $admin_id,        // i
        $admin_name,      // s
        $ip_address,      // s
        $created_at       // s
    );
    
    if ($stmt->execute()) {
        error_log("Action logged successfully - History ID: $history_id, Action: $action_type");
        $stmt->close();
        return true;
    } else {
        error_log("Action log execute failed: " . $stmt->error);
        $stmt->close();
        return false;
    }
}

/**
 * Create a notification for all admins (with automatic history logging)
 */
function createNotification($conn, $type, $title, $message, $icon_class, $color_class, $target_admin_id = null, $target_role = null) {
    $created_at = date('Y-m-d H:i:s');
    
    // ✅ KUNIN SA SESSION - SAME AS HISTORY!
    if (empty($target_admin_id) && isset($_SESSION['noble_id'])) {
        $target_admin_id = $_SESSION['noble_id'];
    }
    if (empty($target_role) && isset($_SESSION['noble_lvl'])) {
        $target_role = $_SESSION['noble_lvl'];
    }
    
    error_log("Creating notification - Admin ID: $target_admin_id | Role: $target_role | Type: $type");
    
    $query = "INSERT INTO admin_notifications (type, title, message, icon_class, color_class, target_admin_id, target_role, created_at, is_read) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param(
        "ssssiiss",
        $type,
        $title,
        $message,
        $icon_class,
        $color_class,
        $target_admin_id,
        $target_role,
        $created_at
    );
    
    if ($stmt->execute()) {
        $notification_id = $stmt->insert_id;
        error_log("Notification inserted successfully - ID: " . $notification_id);
        $stmt->close();
        
        // ✅ Automatically log to history
        logNotificationHistory(
            $conn,
            $notification_id,
            $type,
            $title,
            $message,
            'created',
            null,
            null,
            ['source' => 'product_upload']
        );
        
        return true;
    } else {
        error_log("Execute failed: " . $stmt->error);
        $stmt->close();
        return false;
    }
}

/**
 * Notification types and their styles
 */
function getNotificationStyle($type) {
    $styles = [
        'product_upload' => [
            'icon' => 'ri-add-circle-line',
            'color' => 'bg-green-100'
        ],
        'product_update' => [
            'icon' => 'ri-edit-line',
            'color' => 'bg-blue-100'
        ],
        'order_received' => [
            'icon' => 'ri-shopping-cart-line',
            'color' => 'bg-orange-100'
        ],
        'shipment' => [
            'icon' => 'ri-truck-line',
            'color' => 'bg-yellow-100'
        ],
        'inquiry' => [
            'icon' => 'ri-message-3-line',
            'color' => 'bg-purple-100'
        ]
    ];
    
    return $styles[$type] ?? $styles['product_upload'];
}

/**
 * Get all notifications with proper timezone handling
 */
function getAllNotifications($conn, $limit = 20, $admin_id = null, $admin_role = null) {
    $conn->query("SET SESSION time_zone = '+08:00'");
    
    // If admin_id and role not provided, get from session
    if ($admin_id === null && isset($_SESSION['noble_id'])) {
        $admin_id = $_SESSION['noble_id'];
    }
    if ($admin_role === null && isset($_SESSION['noble_lvl'])) {
        $admin_role = $_SESSION['noble_lvl'];
    }
    
    $query = "SELECT id, type, title, message, icon_class, color_class, created_at, is_read 
              FROM admin_notifications 
              WHERE (target_admin_id = ? OR target_admin_id IS NULL)
              AND (target_role = ? OR target_role IS NULL)
              ORDER BY created_at DESC 
              LIMIT ?";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        return [];
    }
    
    // ✅ CORRECT: Bind all 3 parameters
    $stmt->bind_param("isi", $admin_id, $admin_role, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    
    $stmt->close();
    return $notifications;
}

/**
 * Get unread notification count
 */
function getUnreadCount($conn, $admin_id = null, $admin_role = null) {
    // Get from session if not provided
    if ($admin_id === null && isset($_SESSION['noble_id'])) {
        $admin_id = $_SESSION['noble_id'];
    }
    if ($admin_role === null && isset($_SESSION['noble_lvl'])) {
        $admin_role = $_SESSION['noble_lvl'];
    }
    
    $query = "SELECT COUNT(*) as count FROM admin_notifications 
              WHERE is_read = 0 
              AND (target_admin_id = ? OR target_admin_id IS NULL)
              AND (target_role = ? OR target_role IS NULL)";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("is", $admin_id, $admin_role);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['count'] ?? 0;
}

/**
 * Mark notification as read (with history logging)
 */
function markAsRead($conn, $notification_id) {
    $query = "UPDATE admin_notifications SET is_read = 1 WHERE id = ?";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param("i", $notification_id);
    $result = $stmt->execute();
    $stmt->close();
    
    // ✅ Log the read action
    if ($result) {
        $getNotifQuery = "SELECT type, title, message FROM admin_notifications WHERE id = ?";
        $getStmt = $conn->prepare($getNotifQuery);
        $getStmt->bind_param("i", $notification_id);
        $getStmt->execute();
        $notifResult = $getStmt->get_result();
        $notifRow = $notifResult->fetch_assoc();
        $getStmt->close();
        
        if ($notifRow) {
            logNotificationHistory(
                $conn,
                $notification_id,
                $notifRow['type'],
                $notifRow['title'],
                $notifRow['message'],
                'read'
            );
        }
    }
    
    return $result;
}

/**
 * Delete notification (with history logging)
 */
function deleteNotification($conn, $notification_id) {
    // Get notification details before deleting
    $getQuery = "SELECT type, title, message FROM admin_notifications WHERE id = ?";
    $getStmt = $conn->prepare($getQuery);
    $getStmt->bind_param("i", $notification_id);
    $getStmt->execute();
    $notifResult = $getStmt->get_result();
    $notifRow = $notifResult->fetch_assoc();
    $getStmt->close();
    
    if ($notifRow) {
        // Log deletion before deleting
        logNotificationHistory(
            $conn,
            $notification_id,
            $notifRow['type'],
            $notifRow['title'],
            $notifRow['message'],
            'deleted'
        );
    }
    
    // Delete the notification
    $query = "DELETE FROM admin_notifications WHERE id = ?";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Delete prepare failed: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param("i", $notification_id);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Get notification history logs
 */
function getNotificationHistory($conn, $limit = 50, $type = null) {
    $conn->query("SET SESSION time_zone = '+08:00'");
    
    if ($type) {
        $query = "SELECT * FROM admin_notification_history 
                  WHERE notification_type = ? 
                  ORDER BY created_at DESC 
                  LIMIT ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("si", $type, $limit);
    } else {
        $query = "SELECT * FROM admin_notification_history 
                  ORDER BY created_at DESC 
                  LIMIT ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $limit);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $history = [];
    while ($row = $result->fetch_assoc()) {
        $history[] = $row;
    }
    
    $stmt->close();
    return $history;
}
?>