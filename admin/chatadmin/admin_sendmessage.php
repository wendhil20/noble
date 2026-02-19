<?php
// File: admin_sendmessage.php
session_name("nobleadmin");
session_start();
require_once '../role/roleaccount.php';
require_role(['sales', 'superadmin']);

header('Content-Type: application/json');
include '../../connection/connect.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

// Validate input
$message = trim($data['message'] ?? '');
$receiverUserId = filter_var($data['receiver_user_id'] ?? null, FILTER_VALIDATE_INT);

if (empty($message)) {
    echo json_encode(['status' => 'error', 'error' => 'Message cannot be empty']);
    exit;
}

if (!$receiverUserId) {
    echo json_encode(['status' => 'error', 'error' => 'Invalid recipient']);
    exit;
}

if (strlen($message) > 1000) {
    echo json_encode(['status' => 'error', 'error' => 'Message too long (max 1000 characters)']);
    exit;
}

try {
    // Multiple ways to get admin ID - try different session structures
    $senderNobleId = null;
    
    // Try different possible session structures
    if (isset($_SESSION['noble_user']['id'])) {
        $senderNobleId = $_SESSION['noble_user']['id'];
    } elseif (isset($_SESSION['noble_user']) && is_numeric($_SESSION['noble_user'])) {
        $senderNobleId = $_SESSION['noble_user'];
    } elseif (isset($_SESSION['noble_id'])) {
        $senderNobleId = $_SESSION['noble_id'];
    } elseif (isset($_SESSION['id'])) {
        $senderNobleId = $_SESSION['id'];
    } elseif (isset($_SESSION['admin_id'])) {
        $senderNobleId = $_SESSION['admin_id'];
    }
    
    // Log session structure for debugging
    error_log("Full session data: " . print_r($_SESSION, true));
    error_log("Extracted admin ID: " . ($senderNobleId ?? 'NULL'));
    
    if (!$senderNobleId) {
        // Try to get from database based on session email if available
        if (isset($_SESSION['noble_user']) && is_array($_SESSION['noble_user']) && isset($_SESSION['noble_user']['email'])) {
            $email = $_SESSION['noble_user']['email'];
            $getUserSql = "SELECT id FROM nobleaccount WHERE email = ?";
            $getUserStmt = $conn->prepare($getUserSql);
            $getUserStmt->bind_param("s", $email);
            $getUserStmt->execute();
            $userResult = $getUserStmt->get_result();
            if ($userResult->num_rows > 0) {
                $userData = $userResult->fetch_assoc();
                $senderNobleId = $userData['id'];
            }
            $getUserStmt->close();
        }
        
        if (!$senderNobleId) {
            throw new Exception("Admin ID not found in session. Session structure: " . print_r($_SESSION, true));
        }
    }
    
    error_log("Final Admin ID: " . $senderNobleId);
    error_log("Receiver User ID: " . $receiverUserId);
    error_log("Message: " . $message);
    
    // Verify that the admin exists in nobleaccount table
    $checkAdminSql = "SELECT id, email, fullname FROM nobleaccount WHERE id = ?";
    $checkAdminStmt = $conn->prepare($checkAdminSql);
    if (!$checkAdminStmt) {
        throw new Exception("Prepare failed for admin check: " . $conn->error);
    }
    
    $checkAdminStmt->bind_param("i", $senderNobleId);
    $checkAdminStmt->execute();
    $adminResult = $checkAdminStmt->get_result();
    
    if ($adminResult->num_rows === 0) {
        throw new Exception("Admin ID $senderNobleId not found in nobleaccount table");
    }
    
    $adminData = $adminResult->fetch_assoc();
    error_log("Admin found: " . print_r($adminData, true));
    $checkAdminStmt->close();
    
    // Verify that the recipient user exists
    $checkUserSql = "SELECT id, name FROM users WHERE id = ?";
    $checkStmt = $conn->prepare($checkUserSql);
    if (!$checkStmt) {
        throw new Exception("Prepare failed for user check: " . $conn->error);
    }
    
    $checkStmt->bind_param("i", $receiverUserId);
    $checkStmt->execute();
    $userResult = $checkStmt->get_result();
    
    if ($userResult->num_rows === 0) {
        throw new Exception("Recipient user not found");
    }
    
    $userData = $userResult->fetch_assoc();
    error_log("User found: " . print_r($userData, true));
    $checkStmt->close();

    // Insert the message
    $sql = "INSERT INTO chat_messages 
            (sender_user_id, sender_noble_id, receiver_user_id, receiver_noble_id, message, created_at, is_read)
            VALUES (NULL, ?, ?, NULL, ?, NOW(), 0)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("iis", $senderNobleId, $receiverUserId, $message);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $messageId = $conn->insert_id;
    
    // Log the sent message
    error_log("Admin message sent successfully - ID: $messageId, From Noble: $senderNobleId, To User: $receiverUserId");
    
    echo json_encode([
        'status' => 'success',
        'message_id' => $messageId,
        'timestamp' => date('Y-m-d H:i:s'),
        'admin_info' => [
            'id' => $senderNobleId,
            'name' => $adminData['fullname'],
            'email' => $adminData['email']
        ],
        'user_info' => [
            'id' => $receiverUserId,
            'name' => $userData['name']
        ],
        'debug_info' => [
            'admin_id' => $senderNobleId,
            'user_id' => $receiverUserId,
            'message_length' => strlen($message)
        ]
    ]);
    
    $stmt->close();
    
} catch (Exception $e) {
    error_log("Admin send message error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error', 
        'error' => $e->getMessage(),
        'debug_info' => [
            'session_structure' => print_r($_SESSION, true),
            'receiver_id' => $receiverUserId,
            'message_length' => strlen($message)
        ]
    ]);
}

$conn->close();
?>