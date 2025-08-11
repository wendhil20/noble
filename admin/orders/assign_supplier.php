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

if (!$input || !isset($input['item_id']) || !isset($input['supplier_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

$item_id = (int)$input['item_id'];
$supplier_id = (int)$input['supplier_id'];

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

    // Verify supplier exists and is linked to this product
    $stmt = $conn->prepare("
        SELECT sl.business_name 
        FROM supp_link_products slp
        JOIN supplier_list sl ON slp.supplier_id = sl.id
        WHERE slp.product_id = ? AND slp.supplier_id = ? AND sl.status = 'active'
    ");
    $stmt->bind_param("ii", $item['product_id'], $supplier_id);
    $stmt->execute();
    $supplier = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$supplier) {
        throw new Exception('Supplier not found or not linked to this product');
    }

    // Update the order item with assigned supplier
    $stmt = $conn->prepare("UPDATE order_items SET supplier_id = ? WHERE id = ?");
    $stmt->bind_param("ii", $supplier_id, $item_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to assign supplier');
    }
    $stmt->close();

    echo json_encode([
        'success' => true,
        'message' => "Supplier '{$supplier['business_name']}' assigned successfully"
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}