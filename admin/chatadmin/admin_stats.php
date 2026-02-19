<?php
// File: admin_stats.php
session_name("nobleadmin");
session_start();
require_once '../role/roleaccount.php';
require_role(['sales', 'superadmin']);

header('Content-Type: application/json');
include '../../connection/connect.php';

// Check if admin is logged in
if (!isset($_SESSION['noble_user'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

try {
    // Get total messages
    $totalResult = $conn->query("SELECT COUNT(*) as total FROM chat_messages");
    $totalMessages = $totalResult ? $totalResult->fetch_assoc()['total'] : 0;

    // Get unread messages (from users to admin)
    $unreadResult = $conn->query("SELECT COUNT(*) as unread FROM chat_messages WHERE is_read = 0 AND sender_user_id IS NOT NULL AND receiver_noble_id IS NOT NULL");
    $unreadMessages = $unreadResult ? $unreadResult->fetch_assoc()['unread'] : 0;

    // Get active users (users who sent message in last 24 hours)
    $activeResult = $conn->query("
        SELECT COUNT(DISTINCT sender_user_id) as active 
        FROM chat_messages 
        WHERE sender_user_id IS NOT NULL 
        AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $activeUsers = $activeResult ? $activeResult->fetch_assoc()['active'] : 0;

    // Get online sales reps (who logged in within last 15 minutes)
    $salesResult = $conn->query("
        SELECT COUNT(*) as online 
        FROM nobleaccount 
        WHERE lvl IN ('productspecialist', 'superadmin') 
        AND status = 'active' 
        AND last_login >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
    ");
    $onlineSales = $salesResult ? $salesResult->fetch_assoc()['online'] : 0;

    echo json_encode([
        'total_messages' => (int)$totalMessages,
        'unread_messages' => (int)$unreadMessages,
        'active_users' => (int)$activeUsers,
        'online_sales' => (int)$onlineSales
    ]);
    
} catch (Exception $e) {
    error_log("Admin stats error: " . $e->getMessage());
    echo json_encode([
        'total_messages' => 0,
        'unread_messages' => 0,
        'active_users' => 0,
        'online_sales' => 0
    ]);
}

$conn->close();
?>