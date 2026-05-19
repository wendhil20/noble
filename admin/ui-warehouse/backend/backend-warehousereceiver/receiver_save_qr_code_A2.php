<?php
// save_qr_code.php
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";
require_role(['productspecialist', 'superadmin', 'sales', 'warehouse']);

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['noble_user'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

// Get raw input for debugging
$rawInput = file_get_contents('php://input');
error_log("Save QR Raw Input: " . $rawInput);

$input = json_decode($rawInput, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("JSON decode error: " . json_last_error_msg());
    echo json_encode(['success' => false, 'error' => 'Invalid JSON: ' . json_last_error_msg()]);
    exit();
}

if (!isset($input['item_id']) || !isset($input['qr_code'])) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit();
}

$itemId = intval($input['item_id']);
$qrCode = trim($input['qr_code']);
$itemType = isset($input['item_type']) ? trim($input['item_type']) : 'original';

error_log("Save QR - Item ID: $itemId, Type: '$itemType', QR: $qrCode");

if (empty($qrCode)) {
    echo json_encode(['success' => false, 'error' => 'QR code cannot be empty']);
    exit();
}

try {
    // Determine if this is a replacement based on input
    $isReplacement = ($itemType === 'replacement');
    
    error_log("Is Replacement: " . ($isReplacement ? 'YES' : 'NO'));
    
    // Verify the item exists in the correct table
    if ($isReplacement) {
        $checkStmt = $conn->prepare("SELECT id, qr_code FROM replacement_requests WHERE id = ?");
        $tableName = "replacement_requests";
    } else {
        $checkStmt = $conn->prepare("SELECT id, qr_code FROM order_items WHERE id = ?");
        $tableName = "order_items";
    }
    
    $checkStmt->bind_param("i", $itemId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows === 0) {
        $checkStmt->close();
        $errorMsg = "Item ID $itemId not found in table: $tableName";
        error_log($errorMsg);
        throw new Exception($errorMsg);
    }
    
    $itemData = $result->fetch_assoc();
    $checkStmt->close();
    
    error_log("Item found in $tableName - Current QR: " . ($itemData['qr_code'] ?? 'NULL'));
    
    // Update the appropriate table
    if ($isReplacement) {
        $stmt = $conn->prepare("UPDATE replacement_requests SET qr_code = ? WHERE id = ?");
    } else {
        $stmt = $conn->prepare("UPDATE order_items SET qr_code = ? WHERE id = ?");
    }
    
    $stmt->bind_param("si", $qrCode, $itemId);
    
    if ($stmt->execute()) {
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        error_log("Save successful in $tableName - Affected rows: $affectedRows");
        
        if ($affectedRows > 0) {
            // Log the action
            $user_info = is_array($_SESSION['noble_user']) ? 
                ($_SESSION['noble_user']['fullname'] ?? $_SESSION['noble_user']['name'] ?? 'Unknown') : 
                'Unknown';
            error_log("QR code saved - Item ID: $itemId (Type: $itemType), Table: $tableName, QR: $qrCode, By: $user_info");
            
            echo json_encode([
                'success' => true,
                'message' => 'QR code saved successfully',
                'item_type' => $itemType,
                'table' => $tableName,
                'item_id' => $itemId
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'No rows were updated. QR code may already be set to this value.'
            ]);
        }
    } else {
        throw new Exception('Failed to execute update query: ' . $stmt->error);
    }
    
} catch (Exception $e) {
    error_log("Error saving QR code: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>