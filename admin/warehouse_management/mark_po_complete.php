<?php
session_name("nobleadmin");
session_start();

include '../../connection/connect.php';
require_once '../role/roleaccount.php';
require_role(['warehouse']);
require_subrole(['warehouse_receiver']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$po_number = isset($data['po_number']) ? trim($data['po_number']) : '';

if (empty($po_number)) {
    echo json_encode(['success' => false, 'error' => 'Invalid P.O. number']);
    exit();
}

try {
    $conn->begin_transaction();
    
    // Verify all items are actually received
    $verifySql = "SELECT COUNT(*) as total,
                         SUM(CASE WHEN received_status = 'received' THEN 1 ELSE 0 END) as received_count
                  FROM order_items 
                  WHERE po_number = ?";
    $verifyStmt = $conn->prepare($verifySql);
    $verifyStmt->bind_param("s", $po_number);
    $verifyStmt->execute();
    $verifyResult = $verifyStmt->get_result()->fetch_assoc();
    $verifyStmt->close();
    
    if ($verifyResult['total'] != $verifyResult['received_count']) {
        throw new Exception('Not all items have been received yet');
    }
    
    // Get order_id from the PO
    $orderStmt = $conn->prepare("SELECT DISTINCT order_id FROM order_items WHERE po_number = ? LIMIT 1");
    $orderStmt->bind_param("s", $po_number);
    $orderStmt->execute();
    $orderResult = $orderStmt->get_result()->fetch_assoc();
    $order_id = $orderResult['order_id'] ?? null;
    $orderStmt->close();
    
    if (!$order_id) {
        throw new Exception('Could not find order for this P.O.');
    }
    
    // Update po_attachments table
    $updateSql = "UPDATE po_attachments 
                  SET all_items_received = 1,
                      all_items_received_at = NOW()
                  WHERE order_id = ? 
                  AND (original_filename LIKE CONCAT('%', ?, '%') OR supplier_name IN (
                      SELECT DISTINCT CASE 
                          WHEN oi.supplier_id > 0 THEN sl.business_name 
                          ELSE oi.manual_supplier_name 
                      END
                      FROM order_items oi
                      LEFT JOIN supplier_list sl ON oi.supplier_id = sl.id
                      WHERE oi.po_number = ?
                  ))";
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param("iss", $order_id, $po_number, $po_number);
    
    if (!$updateStmt->execute()) {
        throw new Exception('Failed to update P.O. attachment status');
    }
    $updateStmt->close();
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "P.O. $po_number marked as completely received. All {$verifyResult['total']} items have been confirmed."
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>