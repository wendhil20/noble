<?php
// create_replacement_request.php
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";
require_role(['warehouse', 'superadmin']);

header('Content-Type: application/json');

if (!isset($_SESSION['noble_user'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $order_item_id = isset($data['order_item_id']) ? intval($data['order_item_id']) : 0;
    $order_id = isset($data['order_id']) ? intval($data['order_id']) : 0;
    $reason = isset($data['reason']) ? trim($data['reason']) : '';
    $quantity = isset($data['quantity']) ? intval($data['quantity']) : 1;
    $auto_approve = isset($data['auto_approve']) ? (bool)$data['auto_approve'] : false;
    
    if ($order_item_id <= 0 || $order_id <= 0 || empty($reason)) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        exit();
    }
    
    // Check if item exists
    $stmt = $conn->prepare("SELECT quantity FROM order_items WHERE id = ? AND order_id = ? LIMIT 1");
    $stmt->bind_param("ii", $order_item_id, $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $item = $result->fetch_assoc();
    $stmt->close();
    
    if (!$item) {
        echo json_encode(['success' => false, 'error' => 'Item not found']);
        exit();
    }
    
    if ($quantity > $item['quantity']) {
        echo json_encode(['success' => false, 'error' => 'Replacement quantity cannot exceed item quantity']);
        exit();
    }
    
    // Check if replacement already exists
    $checkStmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM replacement_requests 
        WHERE order_item_id = ? 
        AND status IN ('pending', 'approved', 'processing', 'ready_for_pickup', 'out_for_delivery', 'delivered')
    ");
    $checkStmt->bind_param("i", $order_item_id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();
    
    if ($checkResult['count'] > 0) {
        echo json_encode(['success' => false, 'error' => 'Replacement request already exists for this item']);
        exit();
    }
    
    // Create replacement request
    $status = $auto_approve ? 'approved' : 'pending';
    $stmt = $conn->prepare("
        INSERT INTO replacement_requests 
        (order_id, order_item_id, reason, replacement_quantity, status, created_at) 
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param("iisis", $order_id, $order_item_id, $reason, $quantity, $status);
    
    if ($stmt->execute()) {
        $replacement_id = $stmt->insert_id;
        $stmt->close();
        
        // Update defect report status
        $updateDefectStmt = $conn->prepare("
            UPDATE defect_reports 
            SET status = 'replacement_requested' 
            WHERE order_item_id = ? AND status = 'pending'
        ");
        $updateDefectStmt->bind_param("i", $order_item_id);
        $updateDefectStmt->execute();
        $updateDefectStmt->close();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Replacement request created successfully',
            'replacement_id' => $replacement_id,
            'status' => $status
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to create replacement request']);
    }
    exit();
}

echo json_encode(['success' => false, 'error' => 'Invalid request method']);
?>