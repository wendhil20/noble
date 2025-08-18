<?php
// File: admin_fetch_users.php
session_name("nobleadmin");
session_start();
require_once '../role/roleaccount.php';
require_role(['productspecialist', 'superadmin']);

header('Content-Type: application/json');
include '../../connection/connect.php';

// Check if admin is logged in
if (!isset($_SESSION['noble_user'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

// Get admin ID with flexible session handling
$adminId = null;

// Try different possible session structures
if (isset($_SESSION['noble_user']['id'])) {
    $adminId = $_SESSION['noble_user']['id'];
} elseif (isset($_SESSION['noble_user']) && is_numeric($_SESSION['noble_user'])) {
    $adminId = $_SESSION['noble_user'];
} elseif (isset($_SESSION['noble_id'])) {
    $adminId = $_SESSION['noble_id'];
} elseif (isset($_SESSION['id'])) {
    $adminId = $_SESSION['id'];
} elseif (isset($_SESSION['admin_id'])) {
    $adminId = $_SESSION['admin_id'];
}

// If still no ID found, try to get from database using email
if (!$adminId && isset($_SESSION['noble_user']) && is_array($_SESSION['noble_user']) && isset($_SESSION['noble_user']['email'])) {
    try {
        $email = $_SESSION['noble_user']['email'];
        $getUserSql = "SELECT id FROM nobleaccount WHERE email = ?";
        $getUserStmt = $conn->prepare($getUserSql);
        $getUserStmt->bind_param("s", $email);
        $getUserStmt->execute();
        $userResult = $getUserStmt->get_result();
        if ($userResult->num_rows > 0) {
            $userData = $userResult->fetch_assoc();
            $adminId = $userData['id'];
        }
        $getUserStmt->close();
    } catch (Exception $e) {
        error_log("Error getting admin ID from email: " . $e->getMessage());
    }
}

if (!$adminId) {
    error_log("Session debug - admin_fetch_users: " . print_r($_SESSION, true));
    echo json_encode(['error' => 'Admin ID not found in session. Session structure: ' . print_r($_SESSION, true)]);
    exit;
}

try {
    // Simplified query to get users with chat history
    $sql = "SELECT DISTINCT
                u.id AS user_id,
                u.name,
                u.email,
                u.mobile,
                u.created_at AS user_joined,
                (
                    SELECT COUNT(*) 
                    FROM chat_messages 
                    WHERE sender_user_id = u.id 
                    AND receiver_noble_id = ? 
                    AND is_read = 0
                ) AS unread_count,
                (
                    SELECT message 
                    FROM chat_messages 
                    WHERE (sender_user_id = u.id AND receiver_noble_id = ?) 
                       OR (receiver_user_id = u.id AND sender_noble_id = ?) 
                    ORDER BY created_at DESC 
                    LIMIT 1
                ) AS last_message,
                (
                    SELECT created_at 
                    FROM chat_messages 
                    WHERE (sender_user_id = u.id AND receiver_noble_id = ?) 
                       OR (receiver_user_id = u.id AND sender_noble_id = ?) 
                    ORDER BY created_at DESC 
                    LIMIT 1
                ) AS last_message_time
            FROM users u
            WHERE EXISTS (
                SELECT 1 
                FROM chat_messages cm 
                WHERE ((cm.sender_user_id = u.id AND cm.receiver_noble_id = ?) 
                       OR (cm.receiver_user_id = u.id AND cm.sender_noble_id = ?))
            )
            ORDER BY last_message_time DESC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("iiiiiii", $adminId, $adminId, $adminId, $adminId, $adminId, $adminId, $adminId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$result) {
        throw new Exception("Database query failed: " . $conn->error);
    }
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
        // Calculate time ago
        $lastMessageTime = $row['last_message_time'];
        $timeAgo = 'No messages';
        
        if ($lastMessageTime) {
            $lastTime = new DateTime($lastMessageTime);
            $now = new DateTime();
            $diff = $now->diff($lastTime);
            
            if ($diff->days >= 7) {
                $timeAgo = $lastTime->format('M j');
            } elseif ($diff->days >= 1) {
                $timeAgo = $diff->days . ' day' . ($diff->days > 1 ? 's' : '') . ' ago';
            } elseif ($diff->h >= 1) {
                $timeAgo = $diff->h . ' hr' . ($diff->h > 1 ? 's' : '') . ' ago';
            } elseif ($diff->i >= 5) {
                $timeAgo = $diff->i . ' min ago';
            } else {
                $timeAgo = 'Just now';
            }
        }
        
        // Determine online status
        $isOnline = 0;
        if ($lastMessageTime) {
            $lastTime = new DateTime($lastMessageTime);
            $now = new DateTime();
            $diff = $now->diff($lastTime);
            if ($diff->i < 10 && $diff->h == 0 && $diff->days == 0) {
                $isOnline = 1;
            }
        }
        
        // Sanitize data
        $row['name'] = htmlspecialchars($row['name'] ?? 'Unknown User');
        $row['email'] = htmlspecialchars($row['email'] ?? '');
        $row['mobile'] = htmlspecialchars($row['mobile'] ?? '');
        $row['last_message'] = htmlspecialchars($row['last_message'] ?? 'No messages yet');
        $row['unread_count'] = (int)$row['unread_count'];
        $row['is_online'] = $isOnline;
        $row['time_ago'] = $timeAgo;
        
        $users[] = $row;
    }
    
    $stmt->close();
    echo json_encode($users);
    
} catch (Exception $e) {
    error_log("Admin fetch conversations error: " . $e->getMessage());
    echo json_encode(['error' => 'Failed to load conversations: ' . $e->getMessage()]);
}

$conn->close();
?>