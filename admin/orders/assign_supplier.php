<?php
//assign_supplier.php
session_name("nobleadmin");
session_start();
header('Content-Type: application/json');
require_once '../../connection/connect.php';

if (!isset($_SESSION['noble_user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'User not logged in']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['item_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing item_id parameter']);
    exit;
}

$item_id = (int)$input['item_id'];
$supplier_id = isset($input['supplier_id']) ? (int)$input['supplier_id'] : null;

try {
    // Get the specific order item
    $stmt = $conn->prepare("SELECT * FROM order_items WHERE id = ?");
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $item = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$item) {
        throw new Exception('Order item not found');
    }

    // If supplier_id is null, we're removing the assignment
    if ($supplier_id === null) {
        $stmt = $conn->prepare("UPDATE order_items SET supplier_id = NULL WHERE id = ?");
        $stmt->bind_param("i", $item_id);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to remove supplier assignment');
        }
        $stmt->close();

        echo json_encode([
            'success' => true,
            'message' => 'Supplier assignment removed successfully'
        ]);
        exit;
    }

    // Verify supplier exists and is active
    $stmt = $conn->prepare("SELECT business_name FROM supplier_list WHERE id = ? AND status = 'active'");
    $stmt->bind_param("i", $supplier_id);
    $stmt->execute();
    $supplier = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$supplier) {
        throw new Exception('Supplier not found or inactive');
    }

    // Check if supplier is linked to this product (optional - we allow manual assignment)
    $isLinked = false;
    if ($item['product_id']) {
        $stmt = $conn->prepare("
            SELECT supplier_type 
            FROM supp_link_products 
            WHERE product_id = ? AND supplier_id = ? AND status = 'active'
        ");
        $stmt->bind_param("ii", $item['product_id'], $supplier_id);
        $stmt->execute();
        $linkResult = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        $isLinked = !empty($linkResult);
    }

    // Update the order item with assigned supplier
    $stmt = $conn->prepare("UPDATE order_items SET supplier_id = ? WHERE id = ?");
    $stmt->bind_param("ii", $supplier_id, $item_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to assign supplier');
    }
    $stmt->close();

    $assignmentType = $isLinked ? 'linked' : 'manual';
    
    echo json_encode([
        'success' => true,
        'message' => "Supplier '{$supplier['business_name']}' assigned successfully",
        'assignment_type' => $assignmentType,
        'is_linked' => $isLinked
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>