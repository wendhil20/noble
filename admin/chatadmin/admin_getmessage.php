<?php
// File: admin_getmessage.php
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

// Get the logged-in admin's ID with flexible session handling
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
    error_log("Admin ID not found. Session debug: " . print_r($_SESSION, true));
    echo json_encode(['error' => 'Admin ID not found in session']);
    exit;
}

$userId = filter_var($_GET['user_id'] ?? null, FILTER_VALIDATE_INT);

if (!$userId) {
    echo json_encode(['error' => 'Invalid user ID']);
    exit;
}

try {
    // Log for debugging
    error_log("Admin $adminId requesting messages with User $userId");

    // Get messages ONLY between this specific admin and this specific user
    $sql = "SELECT 
                cm.id,
                cm.message,
                cm.created_at,
                cm.is_read,
                cm.sender_user_id,
                cm.sender_noble_id,
                cm.receiver_user_id,
                cm.receiver_noble_id,
                CASE 
                    WHEN cm.sender_noble_id = ? THEN 1 
                    ELSE 0 
                END as is_admin,
                DATE_FORMAT(cm.created_at, '%M %d, %Y %h:%i %p') as formatted_date,
                DATE_FORMAT(cm.created_at, '%Y-%m-%d %H:%i:%s') as timestamp_sort,
                CASE 
                    WHEN cm.sender_noble_id = ? THEN na.fullname
                    WHEN cm.sender_user_id = ? THEN u.name
                    ELSE 'Unknown'
                END as sender_name
            FROM chat_messages cm
            LEFT JOIN nobleaccount na ON cm.sender_noble_id = na.id
            LEFT JOIN users u ON cm.sender_user_id = u.id
            WHERE 
                -- Messages from user to this specific admin
                (cm.sender_user_id = ? AND cm.receiver_noble_id = ?) 
                OR 
                -- Messages from this specific admin to user
                (cm.sender_noble_id = ? AND cm.receiver_user_id = ?)
            ORDER BY cm.created_at ASC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("iiiiiii", 
        $adminId,        // for CASE statement
        $adminId,        // for sender name
        $userId,         // for sender name
        $userId,         // user to admin messages
        $adminId,        // user to admin messages
        $adminId,        // admin to user messages
        $userId          // admin to user messages
    );
    
    $stmt->execute();
    $result = $stmt->get_result();

    if (!$result) {
        throw new Exception("Query execution failed: " . $stmt->error);
    }

    $messages = [];
    while ($row = $result->fetch_assoc()) {
        // Sanitize message content
        $row['message'] = htmlspecialchars($row['message']);
        $row['sender_name'] = htmlspecialchars($row['sender_name'] ?? 'Unknown');
        $row['is_admin'] = (int)$row['is_admin'];
        $row['is_read'] = (int)$row['is_read'];
        
        $messages[] = $row;
    }

    // Mark messages from user to this admin as read
    $markReadSql = "UPDATE chat_messages 
                    SET is_read = 1 
                    WHERE sender_user_id = ? 
                    AND receiver_noble_id = ? 
                    AND is_read = 0";
    
    $markStmt = $conn->prepare($markReadSql);
    if ($markStmt) {
        $markStmt->bind_param("ii", $userId, $adminId);
        $affected = $markStmt->execute();
        $markStmt->close();
        error_log("Marked messages as read for User $userId to Admin $adminId");
    }

    $stmt->close();
    
    // Log successful retrieval
    error_log("Retrieved " . count($messages) . " messages between Admin $adminId and User $userId");
    
    echo json_encode($messages);

} catch (Exception $e) {
    error_log("Admin get messages error: " . $e->getMessage());
    echo json_encode(['error' => 'Failed to load messages: ' . $e->getMessage()]);
}

$conn->close();
?>