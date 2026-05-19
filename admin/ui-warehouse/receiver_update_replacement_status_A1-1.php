<?php
// update_replacement_status.php
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";
require_role(['warehouse', 'superadmin']);

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
error_log("Update Replacement Status Raw Input: " . $rawInput);

$input = json_decode($rawInput, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("JSON decode error: " . json_last_error_msg());
    echo json_encode(['success' => false, 'error' => 'Invalid JSON: ' . json_last_error_msg()]);
    exit();
}

if (!isset($input['replacement_id']) || !isset($input['status'])) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit();
}

$replacementId = intval($input['replacement_id']);
$newStatus = trim($input['status']);

// Validate status
$validStatuses = ['pending', 'approved', 'processing', 'In Warehouse', 'ready_for_pickup', 'out_for_delivery', 'delivered', 'cancelled'];
if (!in_array($newStatus, $validStatuses)) {
    echo json_encode(['success' => false, 'error' => 'Invalid status value']);
    exit();
}

error_log("Update Replacement Status - Replacement ID: $replacementId, New Status: $newStatus");

try {
    // Verify the replacement exists
    $checkStmt = $conn->prepare("SELECT id, status FROM replacement_requests WHERE id = ?");
    $checkStmt->bind_param("i", $replacementId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows === 0) {
        $checkStmt->close();
        throw new Exception("Replacement request ID $replacementId not found");
    }
    
    $currentData = $result->fetch_assoc();
    $currentStatus = $currentData['status'];
    $checkStmt->close();
    
    error_log("Current Status: $currentStatus");
    
    // Prevent unnecessary updates
    if ($currentStatus === $newStatus) {
        echo json_encode([
            'success' => true,
            'message' => 'Status is already set to ' . $newStatus,
            'no_change' => true
        ]);
        exit();
    }
    
    // If marking as In Warehouse, also update received_status to received
    $isInWarehouse = ($newStatus === 'In Warehouse');

    if ($isInWarehouse) {
        $stmt = $conn->prepare("UPDATE replacement_requests SET status = ?, received_status = 'received', received_by = ?, received_at = NOW() WHERE id = ?");
        $receiverId = $_SESSION['noble_id'] ?? 0;
        $stmt->bind_param("sii", $newStatus, $receiverId, $replacementId);
    } else {
        $stmt = $conn->prepare("UPDATE replacement_requests SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $newStatus, $replacementId);
    }
    
    if ($stmt->execute()) {
        $affectedRows = $stmt->affected_rows;
        $stmt->close();
        
        error_log("Update successful - Affected rows: $affectedRows");
        
        // Log the action
        $user_info = is_array($_SESSION['noble_user']) ? 
            ($_SESSION['noble_user']['fullname'] ?? $_SESSION['noble_user']['name'] ?? 'Unknown') : 
            'Unknown';
        
        error_log("Replacement status updated - ID: $replacementId, Status: $currentStatus → $newStatus, By: $user_info");
        
        echo json_encode([
            'success' => true,
            'message' => 'Replacement status updated successfully',
            'replacement_id' => $replacementId,
            'old_status' => $currentStatus,
            'new_status' => $newStatus,
            'received_status_updated' => $isInWarehouse
        ]);
    } else {
        throw new Exception('Failed to execute update query: ' . $stmt->error);
    }
    
} catch (Exception $e) {
    error_log("Error updating replacement status: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>