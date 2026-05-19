<?php
//warehouse_staff_assign_supplier_A1&B1.php
include ROOT_PATH . "/connection/connect.php";
include ROOT_PATH . "/admin/authentication/index-admin-role.php";
header('Content-Type: application/json');


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
$manual_supplier_name = isset($input['manual_supplier_name']) ? trim($input['manual_supplier_name']) : null;
$type = isset($input['type']) ? $input['type'] : 'linked';

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

    // Handle different types of operations
    switch ($type) {
        case 'linked':
            // Assigning a linked supplier
            if ($supplier_id === null || $supplier_id === 0) {
                throw new Exception('Invalid supplier ID for linked assignment');
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

            // Update the order item with assigned supplier (clear manual supplier)
            $stmt = $conn->prepare("UPDATE order_items SET supplier_id = ?, manual_supplier_name = NULL WHERE id = ?");
            $stmt->bind_param("ii", $supplier_id, $item_id);
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to assign linked supplier');
            }
            $stmt->close();

            echo json_encode([
                'success' => true,
                'message' => "Linked supplier '{$supplier['business_name']}' assigned successfully",
                'assignment_type' => 'linked'
            ]);
            break;

        case 'manual':
            // Assigning a manual supplier
            if (empty($manual_supplier_name)) {
                throw new Exception('Manual supplier name is required');
            }

            // Update the order item with manual supplier (supplier_id = 0 indicates manual)
            $stmt = $conn->prepare("UPDATE order_items SET supplier_id = 0, manual_supplier_name = ? WHERE id = ?");
            $stmt->bind_param("si", $manual_supplier_name, $item_id);
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to assign manual supplier');
            }
            $stmt->close();

            echo json_encode([
                'success' => true,
                'message' => "Manual supplier '{$manual_supplier_name}' assigned successfully",
                'assignment_type' => 'manual'
            ]);
            break;

        case 'unassign':
            // Removing linked supplier assignment
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
            break;

        case 'unassign_manual':
            // Removing manual supplier assignment
            $stmt = $conn->prepare("UPDATE order_items SET supplier_id = NULL, manual_supplier_name = NULL WHERE id = ?");
            $stmt->bind_param("i", $item_id);
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to remove manual supplier assignment');
            }
            $stmt->close();

            echo json_encode([
                'success' => true,
                'message' => 'Manual supplier assignment removed successfully'
            ]);
            break;

        default:
            throw new Exception('Invalid operation type');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>