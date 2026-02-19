<?php
// warehouse_staff_resolve_defect_C2.php
session_name("nobleadmin");
session_start();

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
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
    
    if ($order_item_id <= 0 || $order_id <= 0) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        exit();
    }
    
    // Update all defect reports for this item to resolved
    $stmt = $conn->prepare("
        UPDATE defect_reports 
        SET status = 'resolved' 
        WHERE order_item_id = ? AND order_id = ?
    ");
    $stmt->bind_param("ii", $order_item_id, $order_id);
    
    if ($stmt->execute()) {
        $affected = $stmt->affected_rows;
        $stmt->close();
        
        echo json_encode([
            'success' => true, 
            'message' => "Defect(s) marked as resolved",
            'affected_rows' => $affected
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to resolve defect']);
    }
    exit();
}

echo json_encode(['success' => false, 'error' => 'Invalid request method']);
?>