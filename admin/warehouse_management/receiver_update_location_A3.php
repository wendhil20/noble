<?php
// update_location.php
session_name("nobleadmin");
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_once 'audit_trail_helper.php'; // ADD THIS LINE
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
error_log("Update Location Raw Input: " . $rawInput);

$input = json_decode($rawInput, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("JSON decode error: " . json_last_error_msg());
    echo json_encode(['success' => false, 'error' => 'Invalid JSON: ' . json_last_error_msg()]);
    exit();
}

if (!isset($input['item_id']) || !isset($input['warehouse_location'])) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit();
}

$itemId = intval($input['item_id']);
$warehouseLocation = trim($input['warehouse_location']);
$itemType = isset($input['item_type']) ? trim($input['item_type']) : 'original';

error_log("Update Location - Item ID: $itemId, Type: '$itemType', Location: $warehouseLocation");

if (empty($warehouseLocation)) {
    echo json_encode(['success' => false, 'error' => 'Location cannot be empty']);
    exit();
}

try {
    // Determine if this is a replacement based on input
    $isReplacement = ($itemType === 'replacement');
    
    error_log("Is Replacement: " . ($isReplacement ? 'YES' : 'NO'));
    
    // Verify the item exists in the correct table
    if ($isReplacement) {
        $checkStmt = $conn->prepare("SELECT id, warehouse_location, order_id FROM replacement_requests WHERE id = ?");
        $tableName = "replacement_requests";
    } else {
        $checkStmt = $conn->prepare("SELECT id, warehouse_location, order_id FROM order_items WHERE id = ?");
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
    
    error_log("Item found in $tableName - Current Location: " . ($itemData['warehouse_location'] ?? 'NULL'));
    
    // Update the appropriate table
    if ($isReplacement) {
        $stmt = $conn->prepare("UPDATE replacement_requests SET warehouse_location = ? WHERE id = ?");
    } else {
        $stmt = $conn->prepare("UPDATE order_items SET warehouse_location = ? WHERE id = ?");
    }
    
    $stmt->bind_param("si", $warehouseLocation, $itemId);
    
    if ($stmt->execute()) {
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        error_log("Update successful in $tableName - Affected rows: $affectedRows");
        
        // LOG AUDIT TRAIL - ADD THIS BLOCK
        $oldLocation = $itemData['warehouse_location'] ?? 'Not Set';
        $order_id = $itemData['order_id'] ?? null;
        
        logAuditTrail(
            $conn,
            'UPDATE_WAREHOUSE_LOCATION',
            $tableName,
            $itemId,
            $order_id,
            $itemId,
            $oldLocation,
            $warehouseLocation,
            "Updated warehouse location from '$oldLocation' to '$warehouseLocation' for " . 
            ($isReplacement ? "replacement item" : "order item")
        );
        
        // Log the action
        $user_info = is_array($_SESSION['noble_user']) ? 
            ($_SESSION['noble_user']['fullname'] ?? $_SESSION['noble_user']['name'] ?? 'Unknown') : 
            'Unknown';
        error_log("Location updated - Item ID: $itemId (Type: $itemType), Table: $tableName, Location: $warehouseLocation, By: $user_info");
        
        echo json_encode([
            'success' => true,
            'message' => 'Location updated successfully',
            'item_type' => $itemType,
            'table' => $tableName,
            'item_id' => $itemId
        ]);
    } else {
        throw new Exception('Failed to execute update query: ' . $stmt->error);
    }
    
} catch (Exception $e) {
    error_log("Error updating location: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>