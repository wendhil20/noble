<?php
// reset_qr_code.php
session_name("nobleadmin");
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
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
error_log("Reset QR Raw Input: " . $rawInput);

$input = json_decode($rawInput, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("JSON decode error: " . json_last_error_msg());
    echo json_encode(['success' => false, 'error' => 'Invalid JSON: ' . json_last_error_msg()]);
    exit();
}

if (!isset($input['item_id'])) {
    echo json_encode(['success' => false, 'error' => 'Missing item ID']);
    exit();
}

$itemId = intval($input['item_id']);
$itemType = isset($input['item_type']) ? trim($input['item_type']) : 'original';

// Log incoming request
error_log("Reset QR Request - Item ID: $itemId, Type: '$itemType'");

try {
    // Determine if this is a replacement based on input
    $isReplacement = ($itemType === 'replacement');
    
    error_log("Is Replacement: " . ($isReplacement ? 'YES' : 'NO'));
    
    // Verify the item exists in the correct table
    if ($isReplacement) {
        $checkStmt = $conn->prepare("SELECT id, qr_code, warehouse_location FROM replacement_requests WHERE id = ?");
        $tableName = "replacement_requests";
    } else {
        $checkStmt = $conn->prepare("SELECT id, qr_code, warehouse_location FROM order_items WHERE id = ?");
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
    
    error_log("Item found in $tableName - Current QR: " . ($itemData['qr_code'] ?? 'NULL') . ", Location: " . ($itemData['warehouse_location'] ?? 'NULL'));
    
    // Update the appropriate table
    if ($isReplacement) {
        $stmt = $conn->prepare("UPDATE replacement_requests SET qr_code = NULL, warehouse_location = NULL WHERE id = ?");
    } else {
        $stmt = $conn->prepare("UPDATE order_items SET qr_code = NULL, warehouse_location = NULL WHERE id = ?");
    }
    
    $stmt->bind_param("i", $itemId);
    
    if ($stmt->execute()) {
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        error_log("Reset successful in $tableName - Affected rows: $affectedRows");
        
        // Verify the update
        if ($isReplacement) {
            $verifyStmt = $conn->prepare("SELECT qr_code, warehouse_location FROM replacement_requests WHERE id = ?");
        } else {
            $verifyStmt = $conn->prepare("SELECT qr_code, warehouse_location FROM order_items WHERE id = ?");
        }
        $verifyStmt->bind_param("i", $itemId);
        $verifyStmt->execute();
        $verifyResult = $verifyStmt->get_result();
        $verifiedData = $verifyResult->fetch_assoc();
        $verifyStmt->close();
        
        error_log("After reset - QR: " . ($verifiedData['qr_code'] ?? 'NULL') . ", Location: " . ($verifiedData['warehouse_location'] ?? 'NULL'));
        
        // Log the action
        $user_info = is_array($_SESSION['noble_user']) ? 
            ($_SESSION['noble_user']['fullname'] ?? $_SESSION['noble_user']['name'] ?? 'Unknown') : 
            'Unknown';
        error_log("QR code reset - Item ID: $itemId (Type: $itemType), Table: $tableName, By: $user_info");
        
        echo json_encode([
            'success' => true,
            'message' => 'QR code and location reset successfully',
            'item_type' => $itemType,
            'table' => $tableName,
            'affected_rows' => $affectedRows,
            'item_id' => $itemId
        ]);
    } else {
        throw new Exception('Failed to execute update query: ' . $stmt->error);
    }
    
} catch (Exception $e) {
    error_log("Error resetting QR code: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>