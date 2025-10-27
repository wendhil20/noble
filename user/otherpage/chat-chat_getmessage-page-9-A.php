<?php
// chat_getmessage.php
session_name("nobleuser");
session_start();
include '../../connection/connect.php';
header('Content-Type: application/json');

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Restore session from remember_token if needed
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    $stmt = $conn->prepare("SELECT * FROM users WHERE remember_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $user = $res->fetch_assoc();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'] ?? '';
        $_SESSION['user_mobile'] = $user['mobile'] ?? '';
        if (!empty($user['google_id'])) {
            $_SESSION['google_logged_in'] = true;
            $_SESSION['user_picture'] = $user['profile_picture'] ?? null;
        }
    }
    $stmt->close();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'User not logged in']);
    exit;
}

$userId = $_SESSION['user_id'];



// Get receiver noble ID from multiple possible parameter names
$receiverNobleId = $_GET['receiver_noble_id'] ?? $_GET['noble_id'] ?? $_GET['recipient_id'] ?? null;

error_log("Receiver Noble ID raw: " . var_export($receiverNobleId, true));

// Handle sales_id parameter - convert to noble account ID
if (!$receiverNobleId && isset($_GET['sales_id'])) {
    $salesId = filter_var($_GET['sales_id'], FILTER_VALIDATE_INT);
    if ($salesId) {
        try {
            $findNobleSql = "SELECT id FROM nobleaccount WHERE sales_id = ? AND status = 'active'";
            $findStmt = $conn->prepare($findNobleSql);
            $findStmt->bind_param("i", $salesId);
            $findStmt->execute();
            $findResult = $findStmt->get_result();
            if ($findResult->num_rows > 0) {
                $nobleRow = $findResult->fetch_assoc();
                $receiverNobleId = $nobleRow['id'];
                error_log("Converted sales_id $salesId to noble_id $receiverNobleId");
            }
            $findStmt->close();
        } catch (Exception $e) {
            error_log("Error converting sales_id to noble_id: " . $e->getMessage());
        }
    }
}

// Convert to integer if it's a string number
if (is_string($receiverNobleId) && is_numeric($receiverNobleId)) {
    $receiverNobleId = (int)$receiverNobleId;
} else {
    $receiverNobleId = filter_var($receiverNobleId, FILTER_VALIDATE_INT);
}

if (!$receiverNobleId || $receiverNobleId <= 0) {
    error_log("Invalid receiver_noble_id: " . var_export($_GET['receiver_noble_id'] ?? null, true));
    echo json_encode([
        'error' => 'Invalid recipient ID',
        'debug' => [
            'raw_param' => $_GET['receiver_noble_id'] ?? 'missing',
            'alternative_params' => [
                'noble_id' => $_GET['noble_id'] ?? 'missing',
                'recipient_id' => $_GET['recipient_id'] ?? 'missing',
                'sales_id' => $_GET['sales_id'] ?? 'missing'
            ],
            'filtered' => $receiverNobleId,
            'all_params' => $_GET
        ]
    ]);
    exit;
}

try {
    // Verify the noble account exists
    $checkSql = "SELECT id, sales_id, fullname, lvl FROM nobleaccount WHERE id = ? AND status = 'active'";
    $checkStmt = $conn->prepare($checkSql);
    if (!$checkStmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $checkStmt->bind_param("i", $receiverNobleId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        echo json_encode([
            'error' => 'Recipient not found',
            'debug' => [
                'noble_id' => $receiverNobleId,
                'user_id' => $userId
            ]
        ]);
        exit;
    }
    
    $nobleData = $checkResult->fetch_assoc();
    $checkStmt->close();

    // Get the user's name
    $userName = $_SESSION['user_name'] ?? 'User';
    $nobleFullname = $nobleData['fullname'] ?? 'Support';

    // Fetch all messages between this user and the noble account
    // Updated: Better condition to catch all message combinations
    $sql = "SELECT 
                cm.id,
                cm.message,
                cm.created_at,
                DATE_FORMAT(cm.created_at, '%M %d, %Y at %h:%i %p') AS formatted_date,
                cm.sender_user_id,
                cm.sender_noble_id,
                cm.receiver_user_id,
                cm.receiver_noble_id,
                cm.is_read,
                cm.sales_id
            FROM chat_messages cm
            WHERE 
                -- User sending to Noble (user -> admin)
                (cm.sender_user_id = ? AND cm.receiver_noble_id = ?) 
                OR 
                -- Noble sending to User (admin -> user)
                (cm.sender_noble_id = ? AND cm.receiver_user_id = ?)
                OR
                -- Additional check for messages linked by sales_id
                (cm.sales_id = ? AND (cm.sender_user_id = ? OR cm.receiver_user_id = ?))
            ORDER BY cm.created_at ASC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("iiiiiii", 
        $userId, $receiverNobleId,           // User -> Noble
        $receiverNobleId, $userId,           // Noble -> User  
        $nobleData['sales_id'], $userId, $userId  // Sales ID linked messages
    );
    $stmt->execute();
    $result = $stmt->get_result();

    if (!$result) {
        throw new Exception("Query execution failed: " . $stmt->error);
    }

    $messages = [];
    while ($row = $result->fetch_assoc()) {
        // Determine message direction and sender name
        $isFromAdmin = false;
        
        // Check if message is from admin/noble to user
        if (!empty($row['sender_noble_id'])) {
            $isFromAdmin = true;
        }
        
        $senderName = $isFromAdmin ? $nobleFullname : $userName;
        
        // Sanitize message content
        $row['message'] = htmlspecialchars($row['message'], ENT_QUOTES, 'UTF-8');
        $row['is_from_admin'] = $isFromAdmin ? 1 : 0;
        $row['is_read'] = (int)$row['is_read'];
        $row['sender_name'] = htmlspecialchars($senderName, ENT_QUOTES, 'UTF-8');
        
        // Add message direction for easier frontend handling
        $row['message_type'] = $isFromAdmin ? 'received' : 'sent';
        
    
        $messages[] = $row;
    }

    // Mark messages from admin to user as read
    $markReadSql = "UPDATE chat_messages 
                    SET is_read = 1 
                    WHERE sender_noble_id = ? 
                    AND receiver_user_id = ? 
                    AND is_read = 0";
    
    $markStmt = $conn->prepare($markReadSql);
    if ($markStmt) {
        $markStmt->bind_param("ii", $receiverNobleId, $userId);
        $markStmt->execute();
        $updatedRows = $markStmt->affected_rows;
        error_log("Marked $updatedRows messages as read for user $userId from noble $receiverNobleId");
        $markStmt->close();
    }

    $stmt->close();
    
    error_log("Retrieved " . count($messages) . " messages between user $userId and noble $receiverNobleId");
    
    echo json_encode([
        'status' => 'success',
        'messages' => $messages,
        'conversation_info' => [
            'user_id' => $userId,
            'user_name' => $userName,
            'noble_id' => $receiverNobleId,
            'noble_name' => $nobleFullname,
            'total_messages' => count($messages)
        ]
    ]);

} catch (Exception $e) {
    error_log("Chat get messages error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'error' => 'Failed to load messages',
        'details' => $e->getMessage(),
        'debug' => [
            'user_id' => $userId,
            'receiver_noble_id' => $receiverNobleId ?? 'not set'
        ]
    ]);
}

$conn->close();
?>