<?php
// warehouse_staff_generate_replacement_po_C1.php
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";
require_role(['warehouse', 'superadmin']);

header('Content-Type: application/json');

if (!isset($_SESSION['noble_user'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['replacement_id'])) {
    echo json_encode(['success' => false, 'error' => 'Missing replacement ID']);
    exit();
}

$replacementId = intval($input['replacement_id']);

try {
    // Get replacement request details
    $stmt = $conn->prepare("
        SELECT rr.id, rr.order_id, rr.po_number, oi.supplier_id, oi.manual_supplier_name
        FROM replacement_requests rr
        LEFT JOIN order_items oi ON rr.order_item_id = oi.id
        WHERE rr.id = ?
    ");
    $stmt->bind_param("i", $replacementId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$result) {
        throw new Exception('Replacement request not found');
    }
    
    // Check if P.O. number already exists
    if (!empty($result['po_number'])) {
        echo json_encode([
            'success' => true,
            'po_number' => $result['po_number'],
            'message' => 'P.O. number already exists'
        ]);
        exit();
    }
    
    // Generate P.O. number: REP-NH[OrderID][Date][Random]
    $orderId = $result['order_id'];
    $date = date('mdY'); // MMDDYYYY format
    $random = rand(100, 999);
    $poNumber = "REP-NH{$orderId}{$date}{$random}";
    
    // Update replacement request with P.O. number
    $updateStmt = $conn->prepare("UPDATE replacement_requests SET po_number = ? WHERE id = ?");
    $updateStmt->bind_param("si", $poNumber, $replacementId);
    
    if ($updateStmt->execute()) {
        $updateStmt->close();
        
        // Log the action
        $user_info = is_array($_SESSION['noble_user']) ? 
            ($_SESSION['noble_user']['fullname'] ?? $_SESSION['noble_user']['name'] ?? 'Unknown') : 
            'Unknown';
        error_log("Replacement P.O. generated - ID: $replacementId, P.O.: $poNumber, By: $user_info");
        
        echo json_encode([
            'success' => true,
            'po_number' => $poNumber,
            'message' => 'P.O. number generated successfully'
        ]);
    } else {
        throw new Exception('Failed to save P.O. number');
    }
    
} catch (Exception $e) {
    error_log("Error generating replacement P.O.: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>