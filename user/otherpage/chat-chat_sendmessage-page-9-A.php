<?php
// chat_sendmessage.php
session_name("nobleuser");
session_start();
include '../../connection/connect.php';
header('Content-Type: application/json');

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$data = json_decode(file_get_contents('php://input'), true);

// Auto-increment fix - simplified
$tables = ['chat_messages'];
foreach ($tables as $table) {
    try {
        $result = $conn->query("SELECT MAX(id) AS max_id FROM $table");
        if ($result) {
            $row = $result->fetch_assoc();
            $max_id = (int)$row['max_id'];
            $next_id = $max_id > 0 ? $max_id + 1 : 1;
            $conn->query("ALTER TABLE $table AUTO_INCREMENT = $next_id");
        }
    } catch (Exception $e) {
        error_log("Auto-increment fix error for $table: " . $e->getMessage());
    }
}

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
    echo json_encode(['status' => 'error', 'error' => 'User not logged in']);
    exit;
}

// Debug: Log all received data
error_log("Received data: " . print_r($data, true));

// Validate JSON data
if (!$data) {
    echo json_encode([
        'status' => 'error', 
        'error' => 'Invalid JSON data',
        'debug' => [
            'raw_input' => file_get_contents('php://input'),
            'json_error' => json_last_error_msg()
        ]
    ]);
    exit;
}

// Validate inputs - check multiple possible parameter names
$message = trim($data['message'] ?? '');

// Try different parameter names that might be sent from frontend
$receiverNobleId = null;
if (isset($data['receiver_noble_id'])) {
    $receiverNobleId = filter_var($data['receiver_noble_id'], FILTER_VALIDATE_INT);
} elseif (isset($data['noble_id'])) {
    $receiverNobleId = filter_var($data['noble_id'], FILTER_VALIDATE_INT);
} elseif (isset($data['recipient_id'])) {
    $receiverNobleId = filter_var($data['recipient_id'], FILTER_VALIDATE_INT);
} elseif (isset($data['sales_id'])) {
    // If sales_id is sent, we need to find the corresponding noble account
    $salesId = filter_var($data['sales_id'], FILTER_VALIDATE_INT);
    if ($salesId) {
        $findNobleSql = "SELECT id FROM nobleaccount WHERE sales_id = ? AND status = 'active'";
        $findStmt = $conn->prepare($findNobleSql);
        $findStmt->bind_param("i", $salesId);
        $findStmt->execute();
        $findResult = $findStmt->get_result();
        if ($findResult->num_rows > 0) {
            $nobleRow = $findResult->fetch_assoc();
            $receiverNobleId = $nobleRow['id'];
        }
        $findStmt->close();
    }
}

// Debug output
$debug_info = [
    'all_data_received' => array_keys($data),
    'receiver_noble_id' => $data['receiver_noble_id'] ?? 'missing',
    'noble_id' => $data['noble_id'] ?? 'missing',
    'recipient_id' => $data['recipient_id'] ?? 'missing',
    'sales_id' => $data['sales_id'] ?? 'missing',
    'filtered_receiver_id' => $receiverNobleId,
    'message_length' => strlen($message)
];

error_log("Debug info: " . print_r($debug_info, true));

if (empty($message)) {
    echo json_encode([
        'status' => 'error', 
        'error' => 'Message is empty',
        'debug' => $debug_info
    ]);
    exit;
}

if (!$receiverNobleId || $receiverNobleId === false) {
    echo json_encode([
        'status' => 'error', 
        'error' => 'Invalid recipient ID',
        'debug' => $debug_info
    ]);
    exit;
}

if (strlen($message) > 1000) {
    echo json_encode([
        'status' => 'error', 
        'error' => 'Message too long (max 1000 characters)',
        'debug' => $debug_info
    ]);
    exit;
}

try {
    $userId = $_SESSION['user_id'];
    
    // Log for debugging
    error_log("User sending message - User ID: $userId, Noble ID: $receiverNobleId, Message length: " . strlen($message));
    
    // Verify receiver exists and get their details
    $checkSql = "SELECT id, sales_id, fullname, lvl FROM nobleaccount WHERE id = ? AND status = 'active'";
    $checkStmt = $conn->prepare($checkSql);
    if (!$checkStmt) {
        throw new Exception("Prepare failed for recipient check: " . $conn->error);
    }
    
    $checkStmt->bind_param("i", $receiverNobleId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        throw new Exception("Recipient not found or inactive");
    }
    
    $nobleData = $checkResult->fetch_assoc();
    $actualNobleId = $nobleData['id'];
    $salesId = $nobleData['sales_id']; // Can be null for non-sales roles
    $checkStmt->close();
    
    // Verify sender exists
    $userCheckSql = "SELECT id, name FROM users WHERE id = ?";
    $userStmt = $conn->prepare($userCheckSql);
    if (!$userStmt) {
        throw new Exception("Prepare failed for user check: " . $conn->error);
    }
    
    $userStmt->bind_param("i", $userId);
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    
    if ($userResult->num_rows === 0) {
        throw new Exception("User not found");
    }
    
    $userData = $userResult->fetch_assoc();
    $userStmt->close();

    // Insert message
    $sql = "INSERT INTO chat_messages 
            (sender_user_id, sender_noble_id, receiver_user_id, receiver_noble_id, sales_id, message, created_at, is_read)
            VALUES (?, NULL, NULL, ?, ?, ?, NOW(), 0)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed for message insert: " . $conn->error);
    }
    
    $stmt->bind_param("iiis", $userId, $actualNobleId, $salesId, $message);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $messageId = $conn->insert_id;
    
    // Log success
    error_log("User message sent successfully - ID: $messageId, From User: $userId, To Noble: $actualNobleId");
    
    echo json_encode([
        'status' => 'success',
        'message_id' => $messageId,
        'timestamp' => date('Y-m-d H:i:s'),
        'debug_info' => [
            'user_id' => $userId,
            'user_name' => $userData['name'],
            'noble_id' => $actualNobleId,
            'noble_name' => $nobleData['fullname'],
            'sales_id' => $salesId,
            'message_length' => strlen($message)
        ]
    ]);
    
    $stmt->close();

} catch (Exception $e) {
    error_log("User send message error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error', 
        'error' => $e->getMessage(),
        'debug_info' => [
            'user_id' => $userId ?? 'not set',
            'receiver_id' => $receiverNobleId ?? 'not set',
            'message_length' => isset($message) ? strlen($message) : 0,
            'all_received_data' => array_keys($data ?? [])
        ]
    ]);
}

$conn->close();
?>